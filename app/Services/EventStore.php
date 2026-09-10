<?php
namespace App\Services;

use App\Database;
use DateTime;
use Exception;

require_once __DIR__ . '/../Database.php';

/**
 * Servicio EventStore para Gestión de Event Sourcing
 * Provee almacenamiento inmutable y reconstrucción de estado para Marcaciones y Asistencias.
 */
class EventStore {
    /**
     * Registra un nuevo evento inmutable en el Event Store (Append-Only)
     */
    public static function recordEvent(
        string $aggregateType,
        string $aggregateId,
        string $eventType,
        array $eventData,
        string $createdBy = 'SYSTEM',
        ?string $ipAddress = null
    ): int {
        if ($ipAddress === null) {
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        }

        $jsonPayload = json_encode($eventData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $maxRetries = 3;
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                // Obtener la última versión del stream para versionado secuencial
                $lastEvent = Database::queryOne(
                    "SELECT version FROM eventos_asistencia 
                     WHERE aggregate_type = ? AND aggregate_id = ? 
                     ORDER BY version DESC LIMIT 1",
                    [$aggregateType, $aggregateId]
                );
                $nextVersion = $lastEvent ? ((int)$lastEvent['version'] + 1) : 1;

                return Database::execute(
                    "INSERT INTO eventos_asistencia 
                     (aggregate_type, aggregate_id, event_type, event_data, version, created_by, ip_address) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [
                        $aggregateType,
                        $aggregateId,
                        $eventType,
                        $jsonPayload,
                        $nextVersion,
                        $createdBy,
                        $ipAddress
                    ]
                );
            } catch (\PDOException $e) {
                $errorCode = (string)$e->getCode();
                $errorInfo = $e->errorInfo[1] ?? 0;
                // Código SQLSTATE 23000 o MySQL error 1062 es violación de clave única
                if (($errorCode === '23000' || $errorInfo === 1062) && $attempt < $maxRetries) {
                    usleep(random_int(10000, 50000)); // 10ms - 50ms backoff
                    continue;
                }
                throw $e;
            }
        }
        return 0;
    }

    /**
     * Obtiene el stream completo de eventos para un agregado específico
     */
    public static function getStream(string $aggregateType, string $aggregateId): array {
        $rows = Database::query(
            "SELECT * FROM eventos_asistencia 
             WHERE aggregate_type = ? AND aggregate_id = ? 
             ORDER BY version ASC, id ASC",
            [$aggregateType, $aggregateId]
        );

        foreach ($rows as &$row) {
            $row['event_data'] = json_decode($row['event_data'], true) ?: [];
        }

        return $rows;
    }

    /**
     * Obtiene la línea de tiempo completa de eventos para un empleado en una fecha específica.
     * Consolida eventos tanto del aggregate de asistencia como de las marcaciones ocurridas ese día.
     */
    public static function getTimelineForEmployeeDate(int $employeeId, string $date): array {
        $attendanceAggregateId = "emp_{$employeeId}_{$date}";
        
        $sql = "
            SELECT e.* 
            FROM eventos_asistencia e
            WHERE (e.aggregate_type = 'ASISTENCIA_DIARIA' AND e.aggregate_id = :att_id)
               OR (e.aggregate_type = 'MARCACION' AND JSON_UNQUOTE(JSON_EXTRACT(e.event_data, '$.id_empleado')) = :emp_id 
                   AND DATE(JSON_UNQUOTE(JSON_EXTRACT(e.event_data, '$.fecha_hora'))) = :fecha)
            ORDER BY e.created_at ASC, e.id ASC
        ";

        $rows = Database::query($sql, [
            ':att_id' => $attendanceAggregateId,
            ':emp_id' => (string)$employeeId,
            ':fecha'  => $date
        ]);

        $timeline = [];
        foreach ($rows as $row) {
            $data = json_decode($row['event_data'], true) ?: [];
            $row['event_data'] = $data;

            // Formatear descripción amigable según el tipo de evento
            $formatted = self::formatEventForDisplay($row['event_type'], $data, $row['created_by'], $row['created_at']);
            $row['display'] = $formatted;

            $timeline[] = $row;
        }

        return $timeline;
    }

    /**
     * Formatea un evento para presentación visual amigable en la interfaz de usuario
     */
    public static function formatEventForDisplay(string $eventType, array $data, string $createdBy, string $createdAt): array {
        $badgeClass = 'badge-secondary';
        $icon = 'fa-info-circle';
        $title = $eventType;
        $description = '';
        $details = [];

        switch ($eventType) {
            case 'MARCACION_CAPTURADA_DISPOSITIVO':
                $badgeClass = 'badge-info';
                $icon = 'fa-fingerprint';
                $title = 'Marcación Capturada en Biométrico';
                $tipo = ucfirst($data['tipo'] ?? 'Marcación');
                $hora = !empty($data['fecha_hora']) ? substr($data['fecha_hora'], 11, 8) : '--:--';
                $dispNom = $data['dispositivo_nombre'] ?? 'Reloj Biométrico';
                $dispIp = $data['dispositivo_ip'] ?? '';
                $description = "{$tipo} registrada a las {$hora} en dispositivo {$dispNom}" . ($dispIp ? " ({$dispIp})" : "");
                $details = [
                    'Método de Verificación' => $data['tipo_verificacion'] ?? 'Huella',
                    'ID en Reloj' => $data['codigo_reloj'] ?? '',
                    'UID Reloj' => $data['uid_dispositivo'] ?? 'N/A'
                ];
                break;

            case 'MARCACION_SINCRONIZADA_SERVIDOR':
                $badgeClass = 'badge-primary';
                $icon = 'fa-cloud-arrow-down';
                $title = 'Marcación Sincronizada con Servidor';
                $horaSync = !empty($data['fecha_hora']) ? $data['fecha_hora'] : $createdAt;
                $description = "El servidor central persistió la marcación ({$horaSync}) con éxito";
                $details = [
                    'Dispositivo Origen' => $data['dispositivo_nombre'] ?? 'Reloj Biométrico',
                    'Timestamp Servidor' => $createdAt
                ];
                break;

            case 'MARCACION_MANUAL_REGISTRADA':
                $badgeClass = 'badge-warning';
                $icon = 'fa-keyboard';
                $title = 'Marcación Manual Ingresada por RRHH';
                $tipo = ucfirst($data['tipo'] ?? 'Marcación');
                $hora = !empty($data['fecha_hora']) ? $data['fecha_hora'] : '';
                $description = "Se registró manualmente una {$tipo} ({$hora}) por el usuario {$createdBy}";
                $details = [
                    'Usuario Responsable' => $createdBy,
                    'Motivo/Observación' => $data['motivo'] ?? 'Registro administrativo manual'
                ];
                break;

            case 'ASISTENCIA_CALCULADA':
                $est = $data['estado'] ?? '';
                $badgeClass = ($est === 'PRESENTE') ? 'badge-success' : (($est === 'TARDANZA') ? 'badge-warning' : 'badge-danger');
                $icon = 'fa-calculator';
                $title = 'Asistencia Evaluada por Motor de Reglas';
                $estado = $data['estado'] ?? 'CALCULADO';
                $tardanza = (int)($data['minutos_tardanza'] ?? 0);
                $tol = (int)($data['tolerancia_aplicada'] ?? 0);
                $description = "Resultado del cómputo: Estado [{$estado}]. ";
                if ($tardanza > 0) {
                    $description .= "Tardanza de {$tardanza} min (Tolerancia permitida: {$tol} min excedida).";
                } else {
                    $description .= "Ingreso puntual o dentro de los {$tol} min de tolerancia permitida.";
                }
                $details = [
                    'Hora Programada' => ($data['hora_entrada_programada'] ?? '--:--') . ' a ' . ($data['hora_salida_programada'] ?? '--:--'),
                    'Hora Real Marcada' => (!empty($data['hora_entrada_real']) ? substr($data['hora_entrada_real'], 11, 5) : '--:--') . ' a ' . (!empty($data['hora_salida_real']) ? substr($data['hora_salida_real'], 11, 5) : '--:--'),
                    'Minutos Trabajados' => ($data['minutos_trabajados'] ?? 0) . ' min',
                    'Horas Extras' => ($data['minutos_extra'] ?? 0) . ' min'
                ];
                break;

            case 'ASISTENCIA_MODIFICADA_MANUAL':
            case 'ASISTENCIA_MODIFICADA_ADMIN':
                $badgeClass = 'badge-primary';
                $icon = 'fa-user-pen';
                $title = 'Corrección Administrativa de Horario y Asistencia';
                $entNva = $data['hora_entrada_nueva'] ?? '';
                $salNva = $data['hora_salida_nueva'] ?? '';
                $description = "El Administrador {$createdBy} corrigió el registro de asistencia: Entrada [{$entNva}] / Salida [{$salNva}] con estado [{$data['estado_nuevo']}].";
                $details = [
                    'Hora Entrada Oficial' => $entNva ?: 'Sin marcar',
                    'Hora Entrada Anterior' => $data['hora_entrada_anterior'] ?? 'N/A',
                    'Hora Salida Oficial' => $salNva ?: 'Sin marcar',
                    'Hora Salida Anterior' => $data['hora_salida_anterior'] ?? 'N/A',
                    'Estado Oficial' => $data['estado_nuevo'] ?? 'N/A',
                    'Tardanza Computada' => ($data['tardanza_nueva'] ?? 0) . ' min',
                    'Tiempo Trabajado' => ($data['minutos_trabajados'] ?? 0) . ' min',
                    'Motivo de Corrección' => $data['motivo'] ?? 'Ajuste administrativo oficial'
                ];
                break;

            case 'JUSTIFICACION_APLICADA':
            case 'JUSTIFICACION_ADMIN_REGISTRADA':
                $badgeClass = 'badge-info';
                $icon = 'fa-file-signature';
                $title = 'Justificación Oficial Registrada por Administración';
                $tipoJust = $data['tipo_justificacion'] ?? 'JUSTIFICACIÓN';
                $description = "Se aplicó {$tipoJust} aprobada oficialmente por {$createdBy}. Estado: [{$data['estado_aplicado']}].";
                $details = [
                    'Tipo de Justificación' => $tipoJust,
                    'Estado Aplicado' => $data['estado_aplicado'] ?? 'JUSTIFICADO',
                    'Motivo/Sustento' => $data['motivo'] ?? '',
                    'Aprobado por' => $data['aprobado_por'] ?? $createdBy
                ];
                break;

            default:
                $description = json_encode($data, JSON_UNESCAPED_UNICODE);
                break;
        }

        return [
            'badgeClass'  => $badgeClass,
            'icon'        => $icon,
            'title'       => $title,
            'description' => $description,
            'details'     => $details
        ];
    }
}