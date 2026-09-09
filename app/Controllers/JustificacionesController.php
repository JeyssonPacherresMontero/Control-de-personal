<?php
namespace App\Controllers;

use App\Database;
use App\Services\AttendanceCalculator;

class JustificacionesController {
    public function index(): void {
        AuthController::checkAuth();

        $fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
        $fechaFin = $_GET['fecha_fin'] ?? date('Y-m-d');
        $estado = $_GET['estado'] ?? 'TODOS';

        $sql = "
            SELECT j.*, e.nombres, e.apellidos, e.dni, e.codigo_reloj, d.nombre as depto_nombre
            FROM justificaciones j
            JOIN empleados e ON j.id_empleado = e.id
            LEFT JOIN departamentos d ON e.departamento_id = d.id
            WHERE (j.fecha_inicio <= :f2 AND j.fecha_fin >= :f1)
        ";
        $params = [
            ':f1' => $fechaInicio,
            ':f2' => $fechaFin
        ];

        if ($estado !== 'TODOS') {
            $sql .= " AND j.estado = :estado";
            $params[':estado'] = $estado;
        }

        $sql .= " ORDER BY j.creado_en DESC";

        $justificaciones = Database::query($sql, $params);
        $empleados = Database::query("SELECT id, codigo_reloj, dni, nombres, apellidos FROM empleados WHERE activo = 1 ORDER BY apellidos ASC");

        require_once APP_ROOT . '/views/justificaciones/index.php';
    }

    public function guardar(): void {
        AuthController::checkAuth();
        AuthController::requireRole(['ADMIN', 'RRHH', 'SUPERVISOR'], 'justificaciones');
        \App\Csrf::validateRequest();

        $idEmpleado = (int)($_POST['id_empleado'] ?? 0);
        $tipo = $_POST['tipo'] ?? 'TARDANZA';
        $fechaInicio = $_POST['fecha_inicio'] ?? date('Y-m-d');
        $fechaFin = $_POST['fecha_fin'] ?? $fechaInicio;
        $motivo = trim($_POST['motivo'] ?? '');

        if ($idEmpleado <= 0 || empty($motivo)) {
            header('Location: ?route=justificaciones&error=campos_requeridos');
            exit;
        }

        try {
            Database::execute("
                INSERT INTO justificaciones 
                (id_empleado, tipo, fecha_inicio, fecha_fin, motivo, estado, creado_en)
                VALUES (?, ?, ?, ?, ?, 'APROBADO', NOW())
            ", [$idEmpleado, $tipo, $fechaInicio, $fechaFin, $motivo]);

            // Recalcular asistencia para el rango afectado
            $calculator = new AttendanceCalculator(ATTENDANCE_DEBOUNCE_MINUTES);
            $calculator->processDateRange($fechaInicio, $fechaFin);

            header('Location: ?route=justificaciones&msg=guardado');
            exit;
        } catch (\PDOException $e) {
            header('Location: ?route=justificaciones&error=db_error');
            exit;
        }
    }

    public function resolver(): void {
        AuthController::checkAuth();
        AuthController::requireRole(['ADMIN', 'RRHH', 'SUPERVISOR'], 'justificaciones');
        \App\Csrf::validateRequest();

        $id = (int)($_POST['id'] ?? 0);
        $nuevoEstado = $_POST['estado'] ?? 'APROBADO';
        $usuario = AuthController::user()['nombre'] ?? 'Administrador';

        if ($id > 0) {
            try {
                $just = Database::queryOne("SELECT * FROM justificaciones WHERE id = ?", [$id]);
                if ($just) {
                    Database::execute("
                        UPDATE justificaciones 
                        SET estado = :estado, aprobado_por = :user, fecha_resolucion = NOW()
                        WHERE id = :id
                    ", [
                        ':estado' => $nuevoEstado,
                        ':user'   => $usuario,
                        ':id'     => $id
                    ]);

                    // Recalcular asistencia en el rango de fechas de la justificación
                    $calculator = new AttendanceCalculator(ATTENDANCE_DEBOUNCE_MINUTES);
                    $calculator->processDateRange($just['fecha_inicio'], $just['fecha_fin']);
                }
                header('Location: ?route=justificaciones&msg=actualizado');
                exit;
            } catch (\PDOException $e) {
                header('Location: ?route=justificaciones&error=db_error');
                exit;
            }
        }

        header('Location: ?route=justificaciones');
        exit;
    }
}
