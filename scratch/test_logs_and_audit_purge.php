<?php
/**
 * Test de Integración E2E: Monitoreo de Logs y Depuración de Auditoría
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Services/LogMonitorService.php';
require_once __DIR__ . '/../app/Services/AuditPurgeService.php';

use App\Database;
use App\Services\LogMonitorService;
use App\Services\AuditPurgeService;

echo "=========================================================================\n";
echo "PRUEBAS DE VERIFICACIÓN: MONITOREO DE LOGS Y DEPURACIÓN SEMESTRAL\n";
echo "=========================================================================\n\n";

$testsTotal = 0;
$testsPassed = 0;
$testsFailed = 0;

function runTest($name, $callback) {
    global $testsTotal, $testsPassed, $testsFailed;
    $testsTotal++;
    try {
        $result = $callback();
        if ($result === true) {
            echo "  [OK] {$name} -> ✅\n";
            $testsPassed++;
        } else {
            echo "  [FALLÓ] {$name}: {$result} -> ❌\n";
            $testsFailed++;
        }
    } catch (\Throwable $e) {
        echo "  [ERROR] {$name}: " . $e->getMessage() . " -> ❌\n";
        $testsFailed++;
    }
}

// ---------------------------------------------------------
// 1. MONITOREO DE LOGS (LogMonitorService)
// ---------------------------------------------------------
echo "--- [MÓDULO: Monitoreo de Logs y Diagnóstico de Red ZKTeco] ---\n";

runTest("LogMonitorService: Overview de archivos de log existentes", function() {
    $overview = LogMonitorService::getLogsOverview();
    if (!isset($overview['sync']) || !isset($overview['php'])) {
        return "Faltan claves sync o php en el overview";
    }
    if ($overview['sync']['exists'] !== true) {
        return "sync_current.log reportado como inexistente";
    }
    return true;
});

runTest("LogMonitorService: Lectura eficiente tail() sin agotar memoria", function() {
    $path = LogMonitorService::getLogPath('sync');
    $lines = LogMonitorService::tail($path, 25);
    if (!is_array($lines) || count($lines) === 0) {
        return "tail() no retornó líneas válidas";
    }
    if (count($lines) > 25) {
        return "tail() retornó más líneas de las solicitadas";
    }
    return true;
});

runTest("LogMonitorService: Diagnóstico de salud de red ZKTeco (getZkNetworkHealth)", function() {
    $health = LogMonitorService::getZkNetworkHealth();
    if (!isset($health['status']) || !isset($health['status_label']) || !isset($health['badge_class'])) {
        return "Estructura de salud incompleta";
    }
    if (!in_array($health['status'], ['HEALTHY', 'WARNING', 'CRITICAL', 'IDLE'])) {
        return "Estado no reconocido: " . $health['status'];
    }
    return true;
});

runTest("LogMonitorService: Diagnóstico de salud PHP y Base de Datos (getPhpErrorHealth)", function() {
    $health = LogMonitorService::getPhpErrorHealth();
    if (!isset($health['status']) || !isset($health['fatal_count'])) {
        return "Estructura de salud PHP incompleta";
    }
    return true;
});

// ---------------------------------------------------------
// 2. DEPURACIÓN DE AUDITORÍA (AuditPurgeService)
// ---------------------------------------------------------
echo "\n--- [MÓDULO: Depuración y Archivado Semestral de Auditoría] ---\n";

runTest("AuditPurgeService: Estadísticas y métricas de tablas (getAuditStats)", function() {
    $stats = AuditPurgeService::getAuditStats(6);
    if (!isset($stats['eventos']['total']) || !isset($stats['cola']['total']) || !isset($stats['historico'])) {
        return "Estructura de estadísticas incompleta";
    }
    if ($stats['eventos']['total'] < 1) {
        return "Total de eventos menor a 1";
    }
    return true;
});

runTest("AuditPurgeService: Ciclo de Archivado y Depuración con registros de prueba", function() {
    $testDate = date('Y-m-d H:i:s', strtotime('-8 months'));
    $testAggregateId = 'TEST-PURGE-' . time();

    Database::execute("
        INSERT INTO eventos_asistencia 
            (aggregate_type, aggregate_id, event_type, event_data, version, created_by, ip_address, created_at)
        VALUES 
            ('MARCACION', :agg, 'TEST_PURGE_EVENT', '{\"test\": true}', 1, 'TEST_SUITE', '127.0.0.1', :fec)
    ", [':agg' => $testAggregateId, ':fec' => $testDate]);

    $insertedEvent = Database::queryOne("SELECT id FROM eventos_asistencia WHERE aggregate_id = ?", [$testAggregateId]);
    if (!$insertedEvent) {
        return "No se pudo insertar el evento de prueba";
    }
    $eventId = $insertedEvent['id'];

    $testIdempotency = 'TEST-IDEM-' . time();
    $devId = Database::queryOne("SELECT id FROM dispositivos LIMIT 1")['id'] ?? 1;

    Database::execute("
        INSERT INTO cola_eventos_asistencia
            (id_dispositivo, user_id, timestamp, idempotency_key, estado, creado_en, actualizado_en)
        VALUES
            (:dev, '9999', :f1, :idem, 'PROCESSED', :f2, :f3)
    ", [':dev' => $devId, ':f1' => $testDate, ':idem' => $testIdempotency, ':f2' => $testDate, ':f3' => $testDate]);

    $insertedQueue = Database::queryOne("SELECT id FROM cola_eventos_asistencia WHERE idempotency_key = ?", [$testIdempotency]);
    if (!$insertedQueue) {
        return "No se pudo insertar el elemento de cola de prueba";
    }
    $queueId = $insertedQueue['id'];

    $res = AuditPurgeService::purgeAndArchive(6, true, 'TEST_AUDITOR', '127.0.0.1', 'Prueba unitaria de depuración');
    if (!$res['success']) {
        return "Fallo purgeAndArchive: " . ($res['error'] ?? '');
    }

    $inActive = Database::queryOne("SELECT id FROM eventos_asistencia WHERE id = ?", [$eventId]);
    if ($inActive) {
        return "El evento aún permanece en la tabla activa eventos_asistencia";
    }

    $inHist = Database::queryOne("SELECT id FROM eventos_asistencia_historico WHERE id = ?", [$eventId]);
    if (!$inHist) {
        return "El evento NO fue archivado en eventos_asistencia_historico";
    }

    $inActiveQueue = Database::queryOne("SELECT id FROM cola_eventos_asistencia WHERE id = ?", [$queueId]);
    if ($inActiveQueue) {
        return "El elemento de cola aún permanece en cola_eventos_asistencia activa";
    }

    $inHistQueue = Database::queryOne("SELECT id FROM cola_eventos_asistencia_historico WHERE id = ?", [$queueId]);
    if (!$inHistQueue) {
        return "El elemento de cola NO fue archivado en cola_eventos_asistencia_historico";
    }

    Database::execute("DELETE FROM eventos_asistencia_historico WHERE id = ?", [$eventId]);
    Database::execute("DELETE FROM cola_eventos_asistencia_historico WHERE id = ?", [$queueId]);
    Database::execute("DELETE FROM mantenimiento_auditoria_logs WHERE usuario = 'TEST_AUDITOR'");

    return true;
});

// ---------------------------------------------------------
// 3. VERIFICACIÓN HTTP VÍA CURL / WEB REQUESTS
// ---------------------------------------------------------
echo "\n--- [MÓDULO: Verificación de Endpoints HTTP y AJAX] ---\n";

class TestHttpClientStandalone {
    private string $baseUrl;
    private string $cookieFile;
    public ?string $lastCsrfToken = null;
    public int $lastStatusCode = 0;
    public ?string $lastRedirectUrl = null;
    public string $lastBody = '';

    public function __construct(string $baseUrl) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->cookieFile = sys_get_temp_dir() . '/cookie_audit_' . uniqid() . '.txt';
    }

    public function __destruct() {
        if (file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    public function get(string $path, array $queryParams = [], bool $isAjax = false): string {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        if (!empty($queryParams)) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($queryParams);
        }
        return $this->request('GET', $url, [], $isAjax);
    }

    public function post(string $path, array $data = [], bool $isAjax = false): string {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        if ($this->lastCsrfToken !== null && !isset($data['_csrf'])) {
            $data['_csrf'] = $this->lastCsrfToken;
        }
        return $this->request('POST', $url, $data, $isAjax);
    }

    private function request(string $method, string $url, array $data = [], bool $isAjax = false): string {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $headers = ['User-Agent: Mozilla/5.0 AuditTester/1.0'];
        if ($isAjax) {
            $headers[] = 'X-Requested-With: XMLHttpRequest';
            $headers[] = 'Accept: application/json';
        }
        if ($this->lastCsrfToken) {
            $headers[] = 'X-CSRF-TOKEN: ' . $this->lastCsrfToken;
        }

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $rawResponse = curl_exec($ch);
        $this->lastStatusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $rawHeaders = substr($rawResponse, 0, $headerSize);
        $this->lastBody = substr($rawResponse, $headerSize);
        $this->lastRedirectUrl = null;

        foreach (explode("\r\n", $rawHeaders) as $line) {
            if (str_contains($line, ':')) {
                list($key, $val) = explode(':', $line, 2);
                if (strtolower(trim($key)) === 'location') {
                    $this->lastRedirectUrl = trim($val);
                }
            }
        }

        if (preg_match('/name=["\']_csrf["\']\s+value=["\']([a-f0-9]{64})["\']/i', $this->lastBody, $m)) {
            $this->lastCsrfToken = $m[1];
        } elseif (preg_match('/csrf_token["\']?\s*:\s*["\']([a-f0-9]{64})["\']/i', $this->lastBody, $m)) {
            $this->lastCsrfToken = $m[1];
        }

        return $this->lastBody;
    }
}

$client = new TestHttpClientStandalone('http://localhost:8080/control_personal');

// Obtener login page y token CSRF
$client->get('index.php?route=login');
$loginOk = false;
if ($client->lastCsrfToken) {
    $client->post('index.php?route=login', [
        'usuario' => 'admin',
        'password' => 'admin123',
        '_csrf' => $client->lastCsrfToken
    ]);
    if ($client->lastStatusCode === 302 && !str_contains($client->lastRedirectUrl ?? '', 'route=login')) {
        $loginOk = true;
    }
}

runTest("Autenticación HTTP de Administrador", function() use ($loginOk) {
    return $loginOk ? true : "No se pudo iniciar sesión como admin";
});

runTest("HTTP Endpoint: ?route=dispositivos&action=logs_diagnostico (JSON)", function() use ($client) {
    $body = $client->get('index.php?route=dispositivos&action=logs_diagnostico', [], true);
    if ($client->lastStatusCode !== 200) {
        return "HTTP Code no es 200, recibido: " . $client->lastStatusCode;
    }
    $json = json_decode($body, true);
    if (!isset($json['success']) || !$json['success'] || !isset($json['zk_health'])) {
        return "Respuesta JSON no contiene zk_health válido";
    }
    return true;
});

runTest("HTTP Endpoint: ?route=dispositivos&action=logs_stream&type=sync (JSON Tail)", function() use ($client) {
    $body = $client->get('index.php?route=dispositivos&action=logs_stream', ['type' => 'sync', 'lines' => 15], true);
    if ($client->lastStatusCode !== 200) {
        return "HTTP Code: " . $client->lastStatusCode;
    }
    $json = json_decode($body, true);
    if (!isset($json['success']) || !$json['success'] || !is_array($json['lines'])) {
        return "Respuesta no es array de líneas";
    }
    return true;
});

runTest("HTTP Endpoint: ?route=dispositivos&action=audit_stats (JSON)", function() use ($client) {
    $body = $client->get('index.php?route=dispositivos&action=audit_stats', ['months' => 6], true);
    if ($client->lastStatusCode !== 200) {
        return "HTTP Code: " . $client->lastStatusCode;
    }
    $json = json_decode($body, true);
    if (!isset($json['success']) || !$json['success'] || !isset($json['stats']['eventos'])) {
        return "Respuesta no contiene stats de auditoría";
    }
    return true;
});

runTest("HTTP Endpoint: ?route=dispositivos (Vista incluye botones y modales)", function() use ($client) {
    $body = $client->get('index.php?route=dispositivos');
    if ($client->lastStatusCode !== 200) {
        return "HTTP Code: " . $client->lastStatusCode;
    }
    if (strpos($body, 'modalLogsMonitor') === false) {
        return "El modal modalLogsMonitor no se encuentra en el HTML renderizado";
    }
    if (strpos($body, 'modalAuditPurge') === false) {
        return "El modal modalAuditPurge no se encuentra en el HTML renderizado";
    }
    if (strpos($body, 'Monitoreo de Logs') === false) {
        return "Botón Monitoreo de Logs no presente en la barra de herramientas";
    }
    return true;
});

echo "\n=========================================================================\n";
echo "RESUMEN DE PRUEBAS:\n";
echo "Total ejecutadas: {$testsTotal}\n";
echo "Aprobadas:        {$testsPassed} ✅\n";
echo "Fallidas:         {$testsFailed} ❌\n";
echo "=========================================================================\n";

if ($testsFailed === 0) {
    echo "¡TODAS LAS PRUEBAS DE MONITOREO Y AUDITORÍA PASARON EXITOSAMENTE!\n";
    exit(0);
} else {
    exit(1);
}
