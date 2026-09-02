<?php
namespace App\Controllers;

use App\Database;

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

        $id = (int)($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $ip = trim($_POST['ip'] ?? '');
        $puerto = (int)($_POST['puerto'] ?? 4370);
        $protocolo = $_POST['protocolo'] ?? 'TCP';
        $clave = (int)($_POST['clave_comunicacion'] ?? 0);
        $ubicacion = trim($_POST['ubicacion'] ?? '');
        $modelo = trim($_POST['modelo'] ?? '');
        $activo = isset($_POST['activo']) ? 1 : 0;

        if ($id > 0) {
            Database::execute("
                UPDATE dispositivos 
                SET nombre = :nom, ip = :ip, puerto = :port, protocolo = :proto, 
                    clave_comunicacion = :clave, ubicacion = :ubi, modelo = :mod, activo = :act
                WHERE id = :id
            ", [
                ':nom'   => $nombre,
                ':ip'    => $ip,
                ':port'  => $puerto,
                ':proto' => $protocolo,
                ':clave' => $clave,
                ':ubi'   => $ubicacion,
                ':mod'   => $modelo,
                ':act'   => $activo,
                ':id'    => $id
            ]);
        } else {
            Database::execute("
                INSERT INTO dispositivos 
                (nombre, ip, puerto, protocolo, clave_comunicacion, ubicacion, modelo, activo)
                VALUES (:nom, :ip, :port, :proto, :clave, :ubi, :mod, :act)
            ", [
                ':nom'   => $nombre,
                ':ip'    => $ip,
                ':port'  => $puerto,
                ':proto' => $protocolo,
                ':clave' => $clave,
                ':ubi'   => $ubicacion,
                ':mod'   => $modelo,
                ':act'   => $activo
            ]);
        }

        header('Location: ?route=dispositivos&msg=guardado');
        exit;
    }

    public function eliminar(): void {
        AuthController::checkAuth();

        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            Database::execute("DELETE FROM dispositivos WHERE id = ?", [$id]);
        }
        header('Location: ?route=dispositivos&msg=eliminado');
        exit;
    }

    /**
     * Construye un array de entorno limpio eliminando PYTHONHOME/PYTHONPATH
     * que ZKBioTime inyecta globalmente y que corrompen cualquier Python distinto.
     */
    private function buildCleanPythonEnv(): array {
        $env = [];
        
        // Variables esenciales de Windows
        $systemRoot = getenv('SystemRoot') ?: (getenv('windir') ?: 'C:\\Windows');
        $env['SystemRoot'] = $systemRoot;
        $env['windir'] = $systemRoot;
        $env['COMSPEC'] = getenv('COMSPEC') ?: 'C:\\Windows\\system32\\cmd.exe';
        $env['PATHEXT'] = getenv('PATHEXT') ?: '.COM;.EXE;.BAT;.CMD;.VBS;.VBE;.JS;.JSE;.WSF;.WSH;.MSC';

        // Copiar todas las variables de entorno actuales
        foreach ($_SERVER as $k => $v) {
            if (is_string($v) && !str_starts_with($k, 'HTTP_')) {
                $env[$k] = $v;
            }
        }
        // Merge con getenv() para cubrir variables que $_SERVER no tenga
        foreach (getenv() as $k => $v) {
            if (!isset($env[$k]) && is_string($v)) {
                $env[$k] = $v;
            }
        }
        
        // Eliminar las variables tóxicas de ZKBioTime
        unset($env['PYTHONHOME'], $env['PYTHONPATH']);
        
        // Limpiar PATH: remover entradas de ZKBioTime y asegurar rutas del sistema
        $pathKey = isset($env['Path']) ? 'Path' : (isset($env['PATH']) ? 'PATH' : 'Path');
        $rawPath = $env[$pathKey] ?? getenv('Path') ?: getenv('PATH') ?: '';
        
        $paths = explode(';', $rawPath);
        $cleanPaths = array_filter($paths, function($p) {
            return trim($p) !== '' && stripos($p, 'ZKBioTime') === false;
        });

        // Asegurar que System32 esté en el PATH para sockets y red
        $sys32 = $systemRoot . '\\system32';
        if (!in_array($sys32, $cleanPaths, true) && !in_array(strtolower($sys32), array_map('strtolower', $cleanPaths), true)) {
            array_unshift($cleanPaths, $sys32);
        }

        $env[$pathKey] = implode(';', $cleanPaths);
        $env['Path'] = $env[$pathKey];
        $env['PATH'] = $env[$pathKey];
        
        // Forzar UTF-8 para evitar errores de codificación en Windows
        $env['PYTHONIOENCODING'] = 'utf-8';
        $env['PYTHONUTF8'] = '1';
        
        return $env;
    }

    public function sincronizar(): void {
        AuthController::checkAuth();

        $id = (int)($_GET['id'] ?? 0);
        $mode = $_GET['mode'] ?? 'today'; // 'today' (ultra rápido) o 'full' (histórico)
        $pythonScript = APP_ROOT . '/sync/sync_zkteco.py';
        $pythonBin = defined('PYTHON_BIN') ? PYTHON_BIN : 'python';
        
        $lockFile = APP_ROOT . '/storage/sync.lock';
        $logFile = APP_ROOT . '/storage/logs/sync_current.log';
        $storageDir = APP_ROOT . '/storage/logs';

        if (!file_exists($storageDir)) {
            @mkdir($storageDir, 0777, true);
        }

        // Obtener el ID del último log existente en BD antes de iniciar
        $lastLogId = (int)(Database::queryOne("SELECT MAX(id) as max_id FROM log_sincronizacion")['max_id'] ?? 0);

        // Si ya hay una sincronización activa hace menos de 90 segundos
        $isAlreadyRunning = false;
        if (file_exists($lockFile)) {
            $lockContent = @file_get_contents($lockFile);
            $lockData = json_decode($lockContent, true);
            $lockTime = (int)($lockData['timestamp'] ?? filemtime($lockFile));
            if (time() - $lockTime < 90) {
                $isAlreadyRunning = true;
            } else {
                @unlink($lockFile);
            }
        }

        if (!$isAlreadyRunning) {
            @file_put_contents($lockFile, json_encode([
                'device_id' => $id,
                'mode' => $mode,
                'started_at' => date('Y-m-d H:i:s'),
                'start_log_id' => $lastLogId,
                'timestamp' => time()
            ]));

            $modeText = ($mode === 'today') ? 'SOLO HOY (RÁPIDO)' : 'HISTÓRICO COMPLETO';
            @file_put_contents($logFile, "=== Iniciando sincronización [$modeText - " . date('Y-m-d H:i:s') . "] ===\n");

            // Comando en segundo plano en Windows
            $cmd = "\"$pythonBin\" -E \"$pythonScript\"";
            if ($mode === 'today') {
                $cmd .= " --today-only";
            }
            if ($id > 0) {
                $cmd .= " --device $id";
            }

            // Iniciar proceso desacoplado en background sin bloquear PHP
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $bgCmd = "cmd /c start /B \"\" " . $cmd . " >> \"" . $logFile . "\" 2>&1";
                pclose(popen($bgCmd, "r"));
            } else {
                exec($cmd . " >> \"" . $logFile . "\" 2>&1 &");
            }
        }

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
                  || isset($_GET['ajax']) 
                  || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'status' => 'started',
                'mode' => $mode,
                'already_running' => $isAlreadyRunning,
                'message' => $isAlreadyRunning ? 'Ya hay una sincronización en curso. Monitoreando...' : ($mode === 'today' ? 'Sincronización rápida (Solo Hoy) iniciada.' : 'Sincronización histórica iniciada.')
            ]);
            exit;
        }

        header('Location: ?route=dispositivos&msg=sincronizando');
        exit;
    }

    public function limpiarMemoria(): void {
        AuthController::checkAuth();
        $user = AuthController::user();

        // Solo administradores pueden purgar memoria del reloj
        if (($user['rol'] ?? '') !== 'ADMIN') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'output' => 'Acceso denegado: Solo administradores pueden liberar memoria.']);
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'output' => 'ID de dispositivo no válido.']);
            exit;
        }

        $device = Database::queryOne("SELECT * FROM dispositivos WHERE id = ?", [$id]);
        if (!$device) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'output' => 'Dispositivo no encontrado.']);
            exit;
        }

        $pythonScript = APP_ROOT . '/sync/sync_zkteco.py';
        $pythonBin = defined('PYTHON_BIN') ? PYTHON_BIN : 'python';

        $cmd = "\"$pythonBin\" -E \"$pythonScript\" --device $id --clear";
        $env = $this->buildCleanPythonEnv();
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];
        $process = proc_open($cmd, $descriptors, $pipes, APP_ROOT, $env);
        
        $outputText = '';
        if (is_resource($process)) {
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            $outputText = trim($stdout . "\n" . $stderr);
        }

        if (function_exists('mb_convert_encoding')) {
            $outputText = mb_convert_encoding($outputText, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        }

        $success = str_contains($outputText, 'EXITO') || str_contains($outputText, 'limpiada');

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $success,
            'output' => $outputText ?: 'Operación completada en el dispositivo.'
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    public function syncStatus(): void {
        AuthController::checkAuth();
        header('Content-Type: application/json');

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

        // Obtener el último log registrado en la base de datos
        $ultimoLog = Database::queryOne("
            SELECT l.*, d.nombre as dispositivo_nombre 
            FROM log_sincronizacion l
            LEFT JOIN dispositivos d ON l.id_dispositivo = d.id
            ORDER BY l.id DESC LIMIT 1
        ");

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

        // Si ya se insertó un nuevo registro en log_sincronizacion posterior al inicio de esta sincronización,
        // o si el log en disco ya finalizó, la sincronización YA TERMINÓ.
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

        $dispositivos = Database::query("SELECT id, nombre, ip, puerto, estado_conexion, ultimo_sync, ultimo_error FROM dispositivos ORDER BY id ASC");

        echo json_encode([
            'running' => $isRunning,
            'success' => ($ultimoLog && $ultimoLog['estado'] === 'EXITO'),
            'output' => $ultimoLog['mensaje'] ?? 'Sincronización completada.',
            'log_tail' => $logTail,
            'dispositivos' => $dispositivos,
            'ultimo_log' => $ultimoLog
        ]);
        exit;
    }

    public function testConexion(): void {
        AuthController::checkAuth();
        @set_time_limit(30);

        $id = (int)($_GET['id'] ?? 0);
        $device = Database::queryOne("SELECT * FROM dispositivos WHERE id = ?", [$id]);

        if (!$device) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'output' => 'Dispositivo no encontrado en la base de datos.']);
            exit;
        }

        $pythonScript = APP_ROOT . '/sync/test_device.py';
        $pythonBin = defined('PYTHON_BIN') ? PYTHON_BIN : 'python';
        $ip = escapeshellarg($device['ip']);
        $port = (int)($device['puerto'] ?? 4370);
        $comkey = (int)($device['clave_comunicacion'] ?? 0);
        $udp = ($device['protocolo'] === 'UDP') ? '1' : '0';

        $cmd = "\"$pythonBin\" -E \"$pythonScript\" $ip $port $comkey $udp";
        
        // Ejecutar con entorno limpio (sin PYTHONHOME de ZKBioTime)
        $env = $this->buildCleanPythonEnv();
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];
        $process = proc_open($cmd, $descriptors, $pipes, APP_ROOT, $env);
        
        $outputText = '';
        $returnCode = 1;
        if (is_resource($process)) {
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $returnCode = proc_close($process);
            $outputText = trim($stdout . "\n" . $stderr);
        }
        
        // Garantizar codificación UTF-8 válida para json_encode
        if (function_exists('mb_convert_encoding')) {
            $outputText = mb_convert_encoding($outputText, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        }

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
}
