<?php
namespace App\Controllers;

use App\Database;
use App\Services\AttendanceCalculator;

class MarcacionesController {
    public function index(): void {
        AuthController::checkAuth();

        $fecha = $_GET['fecha'] ?? date('Y-m-d');
        $dispositivoId = !empty($_GET['dispositivo_id']) ? (int)$_GET['dispositivo_id'] : null;
        $search = !empty($_GET['search']) ? trim($_GET['search']) : null;

        $sql = "
            SELECT m.*, 
                   e.nombres, e.apellidos, e.dni,
                   d.nombre as dispositivo_nombre, d.ip as dispositivo_ip
            FROM marcaciones m
            LEFT JOIN empleados e ON m.id_empleado = e.id
            LEFT JOIN dispositivos d ON m.id_dispositivo = d.id
            WHERE DATE(m.fecha_hora) = :fecha
        ";
        $params = [':fecha' => $fecha];

        if ($dispositivoId) {
            $sql .= " AND m.id_dispositivo = :disp_id";
            $params[':disp_id'] = $dispositivoId;
        }

        if ($search) {
            $sql .= " AND (e.nombres LIKE :s1 OR e.apellidos LIKE :s2 OR e.dni LIKE :s3 OR m.codigo_reloj LIKE :s4)";
            $params[':s1'] = "%$search%";
            $params[':s2'] = "%$search%";
            $params[':s3'] = "%$search%";
            $params[':s4'] = "%$search%";
        }

        $sql .= " ORDER BY m.fecha_hora DESC";

        $marcaciones = Database::query($sql, $params);
        $dispositivos = Database::query("SELECT * FROM dispositivos ORDER BY nombre ASC");
        $empleados = Database::query("SELECT id, codigo_reloj, dni, nombres, apellidos FROM empleados WHERE activo = 1 ORDER BY apellidos ASC");

        require_once APP_ROOT . '/views/marcaciones/index.php';
    }

    public function guardarManual(): void {
        AuthController::checkAuth();

        $idEmpleado = (int)($_POST['id_empleado'] ?? 0);
        $fechaHora = trim($_POST['fecha_hora'] ?? '');
        $tipo = $_POST['tipo'] ?? 'entrada';
        $idDispositivo = (int)($_POST['id_dispositivo'] ?? 1);

        if ($idEmpleado > 0 && !empty($fechaHora)) {
            $emp = Database::queryOne("SELECT codigo_reloj FROM empleados WHERE id = ?", [$idEmpleado]);
            $codigoReloj = $emp ? $emp['codigo_reloj'] : (string)$idEmpleado;

            // Inserción de marcación manual
            Database::execute("
                INSERT INTO marcaciones 
                (id_empleado, codigo_reloj, id_dispositivo, fecha_hora, tipo, tipo_verificacion, procesado)
                VALUES (?, ?, ?, ?, ?, 'MANUAL_RRHH', 0)
                ON DUPLICATE KEY UPDATE tipo = VALUES(tipo)
            ", [$idEmpleado, $codigoReloj, $idDispositivo, $fechaHora, $tipo]);

            // Recalcular asistencia para esa fecha
            $dateOnly = substr($fechaHora, 0, 10);
            $calculator = new AttendanceCalculator(ATTENDANCE_DEBOUNCE_MINUTES);
            $calculator->processDate($dateOnly);
        }

        header('Location: ?route=marcaciones&fecha=' . substr($fechaHora, 0, 10) . '&msg=guardado');
        exit;
    }
}
