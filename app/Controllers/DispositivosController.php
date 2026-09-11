<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Services\PythonRunner;

class DispositivosController {
    public function index(): void {
        AuthController::checkAuth();

        $dispositivos = Database::query("SELECT * FROM dispositivos ORDER BY id ASC");
        
        // Logs de sincronización recientes
        $logs = Database::query("
            SELECT l.*, d.nombre as dispositivo_nombre, d.ip as dispositivo_ip
            FROM log_sincronizacion l
            LEFT JOIN dispositivos d ON l.id_dispositivo = d.id
            ORDER BY l.fecha_hora DESC
            LIMIT 50
        ");

        require_once APP_ROOT . '/views/dispositivos/index.php';
    }

    public function guardar(): void {
        AuthController::checkAuth();
        AuthController::requireRole('ADMIN', 'dispositivos');
        \App\Security\Csrf::validate();

        $id = (int)($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $ip = trim($_POST['ip'] ?? '');
        $puerto = (int)($_POST['puerto'] ?? 4370);
        $protocolo = $_POST['protocolo'] ?? 'TCP';
        $clave = (int)($_POST['clave_comunicacion'] ?? 0);
        $ubicacion = trim($_POST['ubicacion'] ?? '');
        $modelo = trim($_POST['modelo'] ?? '');
        $activo = isset($_POST['activo']) ? 1 : 0;

        if (empty($nombre) || empty($ip)) {
            header('Location: ?route=dispositivos&msg=campos_requeridos');
            exit;
        }

        $params = [
            ':nom'   => $nombre,
            ':ip'    => $ip,
            ':port'  => $puerto,
            ':proto' => $protocolo,
            ':clave' => $clave,
            ':ubi'   => $ubicacion,
            ':mod'   => $modelo,
            ':act'   => $activo
        ];

        if ($id > 0) {
            $params[':id'] = $id;
            $result = Database::executeSafe("
                UPDATE dispositivos 
                SET nombre = :nom, ip = :ip, puerto = :port, protocolo = :proto, 
                    clave_comunicacion = :clave, ubicacion = :ubi, modelo = :mod, activo = :act
                WHERE id = :id
            ", $params);
        } else {
            $result = Database::executeSafe("
                INSERT INTO dispositivos 
                (nombre, ip, puerto, protocolo, clave_comunicacion, ubicacion, modelo, activo)
                VALUES (:nom, :ip, :port, :proto, :clave, :ubi, :mod, :act)
            ", $params);
        }

        if (!$result['success']) {
            header('Location: ?route=dispositivos&msg=' . ($result['error'] === 'duplicado' ? 'duplicado' : 'error_interno'));
            exit;
        }

        header('Location: ?route=dispositivos&msg=guardado');
        exit;
    }

    public function eliminar(): void {
        AuthController::requireRole('ADMIN', 'dispositivos');
        \App\Security\Csrf::validate();

        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            // Soft delete: nunca borres físicamente un dispositivo con historial de marcaciones
            Database::execute("UPDATE dispositivos SET activo = 0 WHERE id = ?", [$id]);
        }
        header('Location: ?route=dispositivos&msg=desactivado');
        exit;
    }

    /**
     * Sincronización de marcaciones desde terminales biométricos.
     * Exige método POST y validación de token CSRF.
     */
    public function sincronizar(): void {
        AuthController::checkAuth();
        AuthController::requireRole(['ADMIN', 'RRHH'], 'dashboard');

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
                  || isset($_GET['ajax']) 
                  || isset($_POST['ajax'])
                  || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            if ($isAjax) {
                http_response_code(405);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => 'Método HTTP no permitido. Se requiere POST con token CSRF.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            header('Location: ?route=dispositivos&msg=metodo_no_permitido');
            exit;
        }

        // Validar CSRF
        \App\Security\Csrf::validate();


        $id = (int)($_POST['id'] ?? ($_GET['id'] ?? 0));
        $mode = $_POST['mode'] ?? ($_GET['mode'] ?? 'incremental'); // 'incremental', 'today' o 'full'
        $pythonScript = APP_ROOT . '/sync/sync_zkteco.py';
        $pythonBin = defined('PYTHON_BIN') ? PYTHON_BIN : 'python';

        // Validación temprana: verificar script
        if (!file_exists($pythonScript)) {
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Script de sincronización no encontrado.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            header('Location: ?route=dispositivos&msg=error_interno');
            exit;
        }

        $lockFile = APP_ROOT . '/storage/sync.lock';
        $logFile = APP_ROOT . '/storage/logs/sync_current.log';
        $storageDir = APP_ROOT . '/storage/logs';

        if (!file_exists($storageDir)) {
            @mkdir($storageDir, 0777, true);
        }

        $lastLogId = (int)(Database::queryOne("SELECT MAX(id) as max_id FROM log_sincronizacion")['max_id'] ?? 0);

        // Lock atómico real
        $isAlreadyRunning = false;
        if (file_exists($lockFile)) {
            $lockContent = @file_get_contents($lockFile);
            $lockData = json_decode((string)$lockContent, true);
            $lockTime = (int)($lockData['timestamp'] ?? filemtime($lockFile));
            if (time() - $lockTime < 90) {
                $isAlreadyRunning = true;
            } else {
                @unlink($lockFile);
            }
        }

        if (!$isAlreadyRunning) {
            $fp = @fopen($lockFile, 'x');
            if ($fp === false) {
                $isAlreadyRunning = true;
            } else {
                fwrite($fp, json_encode([
                    'device_id' => $id,
                    'mode' => $mode,
                    'started_at' => date('Y-m-d H:i:s'),
                    'start_log_id' => $lastLogId,
                    'timestamp' => time()
                ]));
                fclose($fp);

                $modeText = ($mode === 'today') ? 'SOLO HOY (RÁPIDO)' : (($mode === 'full') ? 'HISTÓRICO COMPLETO' : 'INCREMENTAL (PENDIENTES)');
                @file_put_contents($logFile, "=== Iniciando sincronización [$modeText - " . date('Y-m-d H:i:s') . "] ===\n");

                $cmd = "\"$pythonBin\" -E \"$pythonScript\"";
                if ($mode === 'today') {
                    $cmd .= " --today-only";
                }
                if ($id > 0) {
                    $cmd .= " --device $id";
                }

                // Usar entorno limpio con PythonRunner
                $env = PythonRunner::buildCleanEnv();

                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    $bgCmd = "cmd /c start /B \"\" $cmd >> \"$logFile\" 2>&1";
                    $descriptors = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
                    $proc = @proc_open($bgCmd, $descriptors, $pipes, APP_ROOT, $env);
                    if (is_resource($proc)) {
                        fclose($pipes[0]); fclose($pipes[1]); fclose($pipes[2]);
                        proc_close($proc);
                    }
                } else {
                    $proc = @proc_open($cmd . " >> \"$logFile\" 2>&1 &", 
                        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, APP_ROOT, $env);
                    if (is_resource($proc)) {
                        fclose($pipes[0]); fclose($pipes[1]); fclose($pipes[2]);
                        proc_close($proc);
                    }
                }
            }
        }

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            $msg = $isAlreadyRunning ? 'Ya hay una sincronización en curso. Monitoreando...' : 
                   (($mode === 'today') ? 'Sincronización rápida (Solo Hoy) iniciada.' : 
                   (($mode === 'full') ? 'Sincronización histórica completa iniciada.' : 'Sincronización inteligente (marcaciones pendientes) iniciada.'));

            echo json_encode([
                'success' => true,
                'status' => 'started',
                'mode' => $mode,
                'already_running' => $isAlreadyRunning,
                'message' => $msg
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            exit;
        }

        header('Location: ?route=dispositivos&msg=sincronizando');
        exit;
    }

    public function syncStatus(): void {
        AuthController::checkAuth();
        session_write_close(); // Liberar bloqueo de sesión para permitir navegación concurrente fluida
        header('Content-Type: application/json; charset=utf-8');

        $lockFile = APP_ROOT . '/storage/sync.lock';
        $logFile = APP_ROOT . '/storage/logs/sync_current.log';

        $isRunning = false;
        $startLogId = 0;
        $startTimestamp = 0;

        if (file_exists($lockFile)) {
            $lockContent = @file_get_contents($lockFile);
            $lockData = json_decode($lockContent, true);
            $startLogId = (int)($lockData['start_log_id'] ?? 0);
            $startTimestamp = (int)($lockData['timestamp'] ?? filemtime($lockFile));

            // Si el lock tiene más de 90 segundos, asumir timeout y limpiar
            if (time() - $startTimestamp < 90) {
                $isRunning = true;
            } else {
                @unlink($lockFile);
                $isRunning = false;
            }
        }

        // Leer tail del log de archivo
        $logTail = '';
        $logFinished = false;
        if (file_exists($logFile)) {
            $rawLog = @file_get_contents($logFile);
            if ($rawLog) {
                if (str_contains($rawLog, 'Ciclo de sincronización finalizado') 
                    || str_contains($rawLog, 'finalizado exitosamente')
                    || str_contains($rawLog, 'Traceback (most recent call last)')) {
                    $logFinished = true;
                }
                $lines = explode("\n", trim($rawLog));
                $logTail = implode("\n", array_slice($lines, -8));
            }
        }

        // Obtener el último log registrado en la base de datos
        $ultimoLog = Database::queryOne("
            SELECT l.*, d.nombre as dispositivo_nombre 
            FROM log_sincronizacion l
            LEFT JOIN dispositivos d ON l.id_dispositivo = d.id
            ORDER BY l.id DESC LIMIT 1
        ");

        if ($isRunning) {
            $currentMaxLogId = (int)($ultimoLog['id'] ?? 0);
            if ($startLogId > 0 && $currentMaxLogId > $startLogId) {
                $isRunning = false;
                @unlink($lockFile);
            } elseif ($logFinished && (time() - $startTimestamp >= 2)) {
                $isRunning = false;
                @unlink($lockFile);
            }
        }

        $logTail = PythonRunner::cleanEncoding($logTail);
        $dispositivos = Database::query("SELECT id, nombre, ip, puerto, estado_conexion, ultimo_sync, ultimo_error FROM dispositivos ORDER BY id ASC");

        echo json_encode([
            'running' => $isRunning,
            'success' => ($ultimoLog && $ultimoLog['estado'] === 'EXITO'),
            'output' => $ultimoLog['mensaje'] ?? 'Sincronización completada.',
            'log_tail' => $logTail,
            'dispositivos' => $dispositivos,
            'ultimo_log' => $ultimoLog
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    public function testConexion(): void {
        AuthController::checkAuth();
        AuthController::requireRole(['ADMIN', 'RRHH']);
        \App\Security\Csrf::validate();
        @set_time_limit(35);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(405);
            echo json_encode(['success' => false, 'output' => 'Método HTTP no permitido. Se requiere POST con token de seguridad.']);
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $device = Database::queryOne("SELECT * FROM dispositivos WHERE id = ?", [$id]);

        if (!$device) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'output' => 'Dispositivo no encontrado en la base de datos.']);
            exit;
        }

        $pythonScript = APP_ROOT . '/sync/test_device.py';
        $ip = $device['ip'];
        $port = (int)($device['puerto'] ?? 4370);
        $comkey = (int)($device['clave_comunicacion'] ?? 0);
        $udp = ($device['protocolo'] === 'UDP') ? '1' : '0';

        $res = PythonRunner::run($pythonScript, [$ip, $port, $comkey, $udp], 25);
        $outputText = $res['output'];

        $isOnline = str_contains($outputText, 'CONEXIÓN EXITOSA') || 
                     str_contains($outputText, 'EXITOSA') || 
                     str_contains($outputText, '[OK]') || 
                     str_contains($outputText, '[✔]');

        // Actualizar estado en base de datos
        if ($isOnline) {
            Database::execute("
                UPDATE dispositivos 
                SET estado_conexion = 'ONLINE', 
                    ultimo_sync = NOW(),
                    ultimo_error = NULL 
                WHERE id = ?
            ", [$id]);
        } else {
            Database::execute("
                UPDATE dispositivos 
                SET estado_conexion = 'OFFLINE', 
                    ultimo_error = ? 
                WHERE id = ?
            ", [$outputText, $id]);
        }

        $updatedDevice = Database::queryOne("SELECT id, estado_conexion, ultimo_sync, ultimo_error FROM dispositivos WHERE id = ?", [$id]);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $isOnline,
            'output' => $outputText,
            'estado_conexion' => $updatedDevice['estado_conexion'] ?? ($isOnline ? 'ONLINE' : 'OFFLINE'),
            'ultimo_sync' => $updatedDevice['ultimo_sync'] ? substr($updatedDevice['ultimo_sync'], 0, 16) : 'Nunca',
            'ultimo_error' => $updatedDevice['ultimo_error'] ?? null
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    /**
     * AJAX: Registra o actualiza un usuario directamente en el reloj biométrico ZKTeco
     */
    public function enviarUsuarioReloj(): void {
        AuthController::checkAuth();
        AuthController::requireRole(['ADMIN', 'RRHH']);
        \App\Csrf::validateRequest();

        $deviceId = (int)($_POST['device_id'] ?? 1);
        $userId = trim($_POST['user_id'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $privilege = max(0, min(2, (int)($_POST['privilege'] ?? 0)));
        $password = trim($_POST['password'] ?? '');

        if (empty($userId) || empty($name)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Se requiere el código/ID de reloj y el nombre del usuario.']);
            exit;
        }

        $pythonScript = APP_ROOT . '/sync/biometric_admin.py';
        $args = ['set-user', '--device', $deviceId, '--user-id', $userId, '--name', $name, '--privilege', $privilege];
        if (!empty($password)) {
            $args[] = '--password';
            $args[] = $password;
        }

        $res = PythonRunner::run($pythonScript, $args, 30);
        $output = $res['output'];

        $jsonStart = strpos($output, '{');
        if ($jsonStart !== false) {
            $jsonStr = substr($output, $jsonStart);
            $parsed = json_decode($jsonStr, true);
            if (is_array($parsed)) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($parsed, JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => $res['success'], 'message' => $output ?: 'Comando enviado al biométrico.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * AJAX: Activa el modo de captura/enrolamiento de huella dactilar en el reloj biométrico
     */
    public function enrolarHuella(): void {
        AuthController::checkAuth();
        AuthController::requireRole(['ADMIN', 'RRHH']);
        \App\Csrf::validateRequest();

        $deviceId = (int)($_POST['device_id'] ?? 1);
        $userId = trim($_POST['user_id'] ?? '');
        $rawTempId = (int)($_POST['temp_id'] ?? 2);
        // Si viene en rango 1-10 (1=Pulgar D, 2=Índice D, ..., 10=Meñique I), mapear a 0-9 para el SDK ZKTeco
        $tempId = ($rawTempId >= 1 && $rawTempId <= 10) ? ($rawTempId - 1) : max(0, min(9, $rawTempId));

        if (empty($userId)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Se requiere el ID de usuario en el reloj.']);
            exit;
        }

        $pythonScript = APP_ROOT . '/sync/biometric_admin.py';
        $args = ['enroll', '--device', $deviceId, '--user-id', $userId, '--temp-id', $tempId];

        $res = PythonRunner::run($pythonScript, $args, 35);
        $output = $res['output'];

        $jsonStart = strpos($output, '{');
        if ($jsonStart !== false) {
            $jsonStr = substr($output, $jsonStart);
            $parsed = json_decode($jsonStr, true);
            if (is_array($parsed)) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($parsed, JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => $res['success'], 'message' => $output ?: 'Modo de captura activado en reloj.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * AJAX: Descarga las huellas/rostros del reloj y las guarda en la tabla plantillas_biometricas
     */
    public function sincronizarBiometria(): void {
        AuthController::checkAuth();
        AuthController::requireRole(['ADMIN', 'RRHH']);
        \App\Csrf::validateRequest();

        $deviceId = (int)($_POST['device_id'] ?? 1);
        $userId = trim($_POST['user_id'] ?? '');

        $pythonScript = APP_ROOT . '/sync/biometric_admin.py';
        $args = ['download-templates', '--device', $deviceId];
        if (!empty($userId)) {
            $args[] = '--user-id';
            $args[] = $userId;
        } else {
            $args[] = '--user-id';
            $args[] = '';
        }

        $res = PythonRunner::run($pythonScript, $args, 45);
        $output = $res['output'];

        $jsonStart = strpos($output, '{');
        if ($jsonStart !== false) {
            $jsonStr = substr($output, $jsonStart);
            $parsed = json_decode($jsonStr, true);
            if (is_array($parsed)) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($parsed, JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => $res['success'], 'message' => $output ?: 'Plantillas biométricas respaldadas en base de datos.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * AJAX: Consulta el estado biométrico del usuario en MySQL
     */
    public function obtenerBiometriaUsuario(): void {
        AuthController::checkAuth();
        AuthController::requireRole(['ADMIN', 'RRHH']);
        session_write_close(); // Liberar bloqueo de sesión para lecturas concurrentes

        $userId = trim($_GET['user_id'] ?? '');
        if (empty($userId)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Código de reloj no proporcionado.']);
            exit;
        }

        $plantillas = Database::query("
            SELECT pb.id, pb.codigo_reloj, pb.tipo, pb.dedo_indice, pb.tamano, pb.actualizado_en, d.nombre as dispositivo_nombre
            FROM plantillas_biometricas pb
            LEFT JOIN dispositivos d ON pb.id_dispositivo_origen = d.id
            WHERE pb.codigo_reloj = ?
        ", [$userId]);

        $fingerCount = 0;
        $faceCount = 0;
        foreach ($plantillas as $p) {
            if ($p['tipo'] === 'HUELLA') $fingerCount++;
            if ($p['tipo'] === 'FACIAL') $faceCount++;
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'user_id' => $userId,
            'huellas_count' => $fingerCount,
            'facial_count' => $faceCount,
            'plantillas' => $plantillas
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * AJAX/POST: Respalda y libera el búfer de marcaciones de la memoria del reloj ZKTeco
     */
    public function limpiarMemoria(): void {
        AuthController::checkAuth();
        AuthController::requireRole('ADMIN');
        \App\Csrf::validateRequest();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(405);
            echo json_encode(['success' => false, 'output' => 'Método HTTP no permitido. Se requiere POST.']);
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $device = Database::queryOne("SELECT * FROM dispositivos WHERE id = ?", [$id]);

        if (!$device) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'output' => 'Dispositivo biométrico no encontrado.']);
            exit;
        }

        $pythonScript = APP_ROOT . '/sync/sync_zkteco.py';
        $res = PythonRunner::run($pythonScript, ['--device', $id, '--clear'], 45);
        $output = $res['output'];

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
                  || isset($_GET['ajax']) 
                  || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        $success = !str_contains(strtolower($output), 'error crítico') && !str_contains(strtolower($output), 'traceback') && $res['success'];

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => $success,
                'output' => $output ?: ($success ? 'Memoria del reloj biométrico respaldada y liberada con éxito.' : 'No se pudo comunicar con el dispositivo.')
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            exit;
        }

        header('Location: ?route=dispositivos&msg=' . ($success ? 'memoria_liberada' : 'error_limpiar'));
        exit;
    }
}
