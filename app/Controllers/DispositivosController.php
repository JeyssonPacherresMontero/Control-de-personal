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

    public function sincronizar(): void {
        AuthController::checkAuth();

        $id = (int)($_GET['id'] ?? 0);
        $pythonScript = APP_ROOT . '/sync/sync_zkteco.py';
        
        $cmd = "python \"$pythonScript\"";
        if ($id > 0) {
            $cmd .= " --device $id";
        }

        $output = [];
        $returnCode = 0;
        exec("$cmd 2>&1", $output, $returnCode);

        $_SESSION['flash_sync_output'] = implode("\n", $output);
        $_SESSION['flash_sync_status'] = ($returnCode === 0) ? 'success' : 'warning';

        header('Location: ?route=dispositivos&msg=sincronizado');
        exit;
    }

    public function testConexion(): void {
        AuthController::checkAuth();

        $id = (int)($_GET['id'] ?? 0);
        $device = Database::queryOne("SELECT * FROM dispositivos WHERE id = ?", [$id]);

        if (!$device) {
            echo json_encode(['success' => false, 'error' => 'Dispositivo no encontrado.']);
            exit;
        }

        $pythonScript = APP_ROOT . '/sync/test_device.py';
        $ip = $device['ip'];
        $port = $device['puerto'] ?? 4370;
        $comkey = $device['clave_comunicacion'] ?? 0;
        $udp = ($device['protocolo'] === 'UDP') ? '1' : '0';

        $cmd = "python \"$pythonScript\" $ip $port $comkey $udp";
        $output = [];
        $returnCode = 0;
        exec("$cmd 2>&1", $output, $returnCode);

        $outputText = implode("\n", $output);
        $isOnline = str_contains($outputText, 'CONEXIÓN EXITOSA') || str_contains($outputText, '[✔]');

        // Actualizar estado en base de datos
        Database::execute("
            UPDATE dispositivos 
            SET estado_conexion = ?, 
                ultimo_sync = IF(? = 'ONLINE', NOW(), ultimo_sync),
                ultimo_error = IF(? = 'ONLINE', NULL, ?) 
            WHERE id = ?
        ", [$isOnline ? 'ONLINE' : 'OFFLINE', $isOnline ? 'ONLINE' : 'OFFLINE', $isOnline ? 'ONLINE' : 'OFFLINE', $isOnline ? null : $outputText, $id]);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => $isOnline,
            'output' => $outputText
        ]);
        exit;
    }
}
