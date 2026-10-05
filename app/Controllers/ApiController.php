<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Services\AttendanceCalculator;
use App\Services\EventStore;

/**
 * ==========================================================
 * API RESTful de Sincronización e Ingesta de Marcaciones ZKTeco
 * Recibe eventos normalizados de Push Listener o integraciones externas
 * ==========================================================
 */
class ApiController {

    private function jsonResponse(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    private function authenticateRequest(): ?array {
        $headers = getallheaders() ?: [];
        $apiKey = $headers['X-API-KEY'] ?? ($headers['x-api-key'] ?? ($_SERVER['HTTP_X_API_KEY'] ?? null));

        if (!$apiKey && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
            if (preg_match('/Bearer\s+(\S+)/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
                $apiKey = $matches[1];
            }
        }

        if (empty($apiKey)) {
            $this->jsonResponse([
                'success' => false,
                'status'  => 'unauthorized',
                'error'   => 'Falta cabecera de autenticación X-API-KEY requerida.'
            ], 401);
        }

        // 1. Validar contra API_SECRET_KEY del sistema
        if (defined('API_SECRET_KEY') && hash_equals(API_SECRET_KEY, $apiKey)) {
            return ['type' => 'master', 'key' => $apiKey];
        }

        // 2. Validar contra api_token individual de dispositivos
        $device = Database::queryOne("SELECT id, nombre, numero_serie, modo, activo FROM dispositivos WHERE api_token = ? AND activo = 1", [$apiKey]);
        if ($device) {
            return ['type' => 'device', 'device' => $device];
        }

        $this->jsonResponse([
            'success' => false,
            'status'  => 'unauthorized',
            'error'   => 'Credencial X-API-KEY no autorizada o inválida.'
        ], 401);
        return null;
    }

    /**
     * POST /api/attendance/sync
     * Ingesta idempotente en tiempo real de marcaciones biométricas
     */
    public function syncAttendance(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse([
                'success' => false,
                'status'  => 'method_not_allowed',
                'error'   => 'Método no permitido. Se requiere POST.'
            ], 405);
        }

        $auth = $this->authenticateRequest();

        $rawBody = file_get_contents('php://input');
        $payload = json_decode($rawBody, true);

        if (!is_array($payload)) {
            $this->jsonResponse([
                'success' => false,
                'status'  => 'bad_request',
                'error'   => 'Cuerpo JSON inválido o malformado.'
            ], 400);
        }

        $deviceSerial = trim((string)($payload['device_serial'] ?? ''));
        $userId = trim((string)($payload['user_id'] ?? ''));
        $timestamp = trim((string)($payload['timestamp'] ?? ''));
        $punchType = strtolower(trim((string)($payload['punch_type'] ?? 'entrada')));
        $verifyType = strtolower(trim((string)($payload['verify_type'] ?? 'huella')));
        $source = strtoupper(trim((string)($payload['source'] ?? 'PUSH')));
        $uidDispositivo = isset($payload['uid_dispositivo']) ? (int)$payload['uid_dispositivo'] : null;
        $idempotencyKey = trim((string)($payload['idempotency_key'] ?? ''));

        if (empty($userId) || empty($timestamp)) {
            $this->jsonResponse([
                'success' => false,
                'status'  => 'validation_error',
                'error'   => 'Los campos user_id y timestamp son obligatorios.'
            ], 422);
        }

        // Validar dispositivo registrado y activo
        $device = null;
        if (!empty($deviceSerial)) {
            $device = Database::queryOne("SELECT id, nombre, numero_serie, modo, activo FROM dispositivos WHERE numero_serie = ? AND activo = 1", [$deviceSerial]);
        }

        if (!$device && $auth['type'] === 'device') {
            $device = $auth['device'];
            $deviceSerial = $device['numero_serie'];
        }

        if (!$device) {
            // Intentar por ID por defecto si solo hay uno
            $device = Database::queryOne("SELECT id, nombre, numero_serie, modo, activo FROM dispositivos WHERE activo = 1 ORDER BY id ASC LIMIT 1");
            if (!$device) {
                $this->jsonResponse([
                    'success' => false,
                    'status'  => 'device_not_found',
                    'error'   => 'No se encontró un terminal biométrico autorizado para este evento.'
                ], 403);
            }
            $deviceSerial = $device['numero_serie'] ?: "DEV_{$device['id']}";
        }

        $deviceId = (int)$device['id'];

        // Si no vino idempotency_key, computarla deterministamente
        $normUser = ltrim($userId, '0');
        if ($normUser === '') $normUser = '0';
        $normTime = substr($timestamp, 0, 19);

        if (empty($idempotencyKey)) {
            $idempotencyKey = hash('sha256', strtoupper($deviceSerial) . "|{$normUser}|{$normTime}|{$punchType}|{$verifyType}");
        }

        // 1. CHEQUEO DE IDEMPOTENCIA PREVENTIVO
        $existing = Database::queryOne("SELECT id FROM marcaciones WHERE idempotency_key = ?", [$idempotencyKey]);
        if ($existing) {
            $this->jsonResponse([
                'success'         => true,
                'status'          => 'already_processed',
                'message'         => 'Marcación previamente procesada y confirmada.',
                'idempotency_key' => $idempotencyKey,
                'id'              => $existing['id']
            ], 200);
        }

        // 2. Mapear empleado activo
        $emp = Database::queryOne("
            SELECT id FROM empleados 
            WHERE (codigo_reloj = ? OR LTRIM(REPLACE(codigo_reloj, '0', ' ')) = LTRIM(REPLACE(?, '0', ' '))) 
              AND activo = 1 
            LIMIT 1
        ", [$userId, $userId]);
        $empId = $emp ? (int)$emp['id'] : null;

        // 3. Inserción idempotente en marcaciones
        $res = Database::executeSafe("
            INSERT INTO marcaciones 
            (id_empleado, codigo_reloj, id_dispositivo, fecha_hora, tipo, tipo_verificacion, uid_dispositivo, idempotency_key, origen, procesado)
            VALUES (:emp, :cod, :dev, :fec, :tip, :ver, :uid, :idem, :orig, 0)
            ON DUPLICATE KEY UPDATE 
                idempotency_key = COALESCE(idempotency_key, VALUES(idempotency_key))
        ", [
            ':emp'  => $empId,
            ':cod'  => $userId,
            ':dev'  => $deviceId,
            ':fec'  => $normTime,
            ':tip'  => $punchType,
            ':ver'  => $verifyType,
            ':uid'  => $uidDispositivo,
            ':idem' => $idempotencyKey,
            ':orig' => $source
        ]);

        if (!$res['success']) {
            // Si hubo colisión de clave única, responder gracefully como already_processed
            if ($res['error'] === 'duplicado') {
                $this->jsonResponse([
                    'success'         => true,
                    'status'          => 'already_processed',
                    'idempotency_key' => $idempotencyKey
                ], 200);
            }

            $this->jsonResponse([
                'success' => false,
                'status'  => 'error',
                'error'   => 'Error en base de datos al registrar marcación: ' . ($res['message'] ?? '')
            ], 500);
        }

        // 4. Actualizar/Confirmar en cola_eventos_asistencia
        Database::execute("
            INSERT INTO cola_eventos_asistencia
            (id_dispositivo, device_serial, user_id, timestamp, punch_type, verify_type, uid_dispositivo, origen, idempotency_key, estado, intentos)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'PROCESSED', 1)
            ON DUPLICATE KEY UPDATE estado = 'PROCESSED', ultimo_error = NULL
        ", [$deviceId, $deviceSerial, $userId, $normTime, $punchType, $verifyType, $uidDispositivo, $source, $idempotencyKey]);

        // 5. Auditoría inmutable en Event Store
        try {
            EventStore::recordEvent(
                'MARCACION',
                $empId ? "emp_{$empId}_" . str_replace([' ', ':'], ['_', '-'], $normTime) : "zk_{$userId}_" . str_replace([' ', ':'], ['_', '-'], $normTime),
                'MARCACION_PUSH_RECIBIDA',
                [
                    'id_empleado'        => $empId,
                    'codigo_reloj'       => $userId,
                    'id_dispositivo'     => $deviceId,
                    'dispositivo_nombre' => $device['nombre'],
                    'dispositivo_serial' => $deviceSerial,
                    'fecha_hora'         => $normTime,
                    'tipo'               => $punchType,
                    'tipo_verificacion'  => $verifyType,
                    'idempotency_key'    => $idempotencyKey,
                    'origen'             => $source
                ],
                "PUSH_LISTENER_{$deviceSerial}"
            );
        } catch (\Exception $e) {
            // Silencioso
        }

        // 6. Actualizar estado de conexión del dispositivo
        Database::execute("
            UPDATE dispositivos 
            SET estado_conexion = 'ONLINE', 
                ultimo_sync = NOW(), 
                ultimo_error = NULL 
            WHERE id = ?
        ", [$deviceId]);

        // 7. Disparar recálculo de asistencia para la fecha involucrada
        try {
            $dateOnly = substr($normTime, 0, 10);
            $calculator = new AttendanceCalculator(ATTENDANCE_DEBOUNCE_MINUTES);
            $calculator->processDate($dateOnly);
        } catch (\Exception $e) {
            // No interrumpir la confirmación HTTP al reloj
        }

        $this->jsonResponse([
            'success'         => true,
            'status'          => 'processed',
            'message'         => 'Marcación recibida, validada e insertada exitosamente.',
            'idempotency_key' => $idempotencyKey,
            'device'          => $device['nombre']
        ], 200);
    }
}
