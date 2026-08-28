<?php
namespace App\Controllers;

use App\Database;
use App\Services\AttendanceCalculator;

class AsistenciaController {
    public function index(): void {
        AuthController::checkAuth();

        $fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
        $fechaFin = $_GET['fecha_fin'] ?? date('Y-m-d');
        $deptoId = !empty($_GET['departamento_id']) ? (int)$_GET['departamento_id'] : null;
        $estado = !empty($_GET['estado']) ? trim($_GET['estado']) : null;
        $search = !empty($_GET['search']) ? trim($_GET['search']) : null;

        // Construir consulta dinámica
        $sql = "
            SELECT a.*, 
                   e.nombres, e.apellidos, e.dni, e.codigo_reloj,
                   d.nombre as departamento_nombre,
                   t.nombre as turno_nombre
            FROM asistencia_diaria a
            JOIN empleados e ON a.id_empleado = e.id
            LEFT JOIN departamentos d ON e.departamento_id = d.id
            LEFT JOIN turnos t ON a.id_turno = t.id
            WHERE a.fecha BETWEEN :fecha_inicio AND :fecha_fin
        ";
        $params = [
            ':fecha_inicio' => $fechaInicio,
            ':fecha_fin'    => $fechaFin
        ];

        if ($deptoId) {
            $sql .= " AND e.departamento_id = :depto_id";
            $params[':depto_id'] = $deptoId;
        }

        if ($estado) {
            $sql .= " AND a.estado = :estado";
            $params[':estado'] = $estado;
        }

        if ($search) {
            $sql .= " AND (e.nombres LIKE :search1 OR e.apellidos LIKE :search2 OR e.dni LIKE :search3 OR e.codigo_reloj LIKE :search4)";
            $params[':search1'] = "%$search%";
            $params[':search2'] = "%$search%";
            $params[':search3'] = "%$search%";
            $params[':search4'] = "%$search%";
        }

        $sql .= " ORDER BY a.fecha DESC, e.apellidos ASC, e.nombres ASC";

        $asistencias = Database::query($sql, $params);
        $departamentos = Database::query("SELECT * FROM departamentos WHERE activo = 1 ORDER BY nombre ASC");

        // Si se solicita exportar a CSV
        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            $this->exportCSV($asistencias, $fechaInicio, $fechaFin);
            return;
        }

        require_once APP_ROOT . '/views/asistencia/index.php';
    }

    public function recalcular(): void {
        AuthController::checkAuth();

        $fechaInicio = $_POST['fecha_inicio'] ?? date('Y-m-d');
        $fechaFin = $_POST['fecha_fin'] ?? $fechaInicio;

        $calculator = new AttendanceCalculator(ATTENDANCE_DEBOUNCE_MINUTES);
        $results = $calculator->processDateRange($fechaInicio, $fechaFin);

        header("Location: ?route=asistencia&fecha_inicio=$fechaInicio&fecha_fin=$fechaFin&msg=recalculado");
        exit;
    }

    public function editar(): void {
        AuthController::checkAuth();

        $id = (int)($_POST['id'] ?? 0);
        $estado = $_POST['estado'] ?? 'PRESENTE';
        $minutosTardanza = (int)($_POST['minutos_tardanza'] ?? 0);
        $minutosExtra = (int)($_POST['minutos_extra'] ?? 0);
        $observaciones = trim($_POST['observaciones'] ?? '');

        if ($id > 0) {
            Database::execute("
                UPDATE asistencia_diaria 
                SET estado = :estado,
                    minutos_tardanza = :tardanza,
                    minutos_extra = :extra,
                    observaciones = :obs,
                    manual = 1
                WHERE id = :id
            ", [
                ':estado' => $estado,
                ':tardanza' => $minutosTardanza,
                ':extra' => $minutosExtra,
                ':obs' => $observaciones,
                ':id' => $id
            ]);
        }

        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?route=asistencia'));
        exit;
    }

    private function exportCSV(array $data, string $start, string $end): void {
        $filename = "reporte_asistencia_{$start}_al_{$end}.csv";
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"$filename\"");

        $output = fopen('php://output', 'w');
        // BOM UTF-8 para que Excel abra sin problemas de acentos y caracteres especiales
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Cabeceras
        fputcsv($output, [
            'Fecha',
            'Código Reloj',
            'DNI',
            'Apellidos y Nombres',
            'Departamento',
            'Turno',
            'Entrada Programada',
            'Salida Programada',
            'Entrada Real',
            'Salida Real',
            'Tardanza (Min)',
            'Horas Trabajadas (Min)',
            'Horas Extras (Min)',
            'Estado',
            'Observaciones'
        ]);

        foreach ($data as $r) {
            fputcsv($output, [
                $r['fecha'],
                $r['codigo_reloj'],
                $r['dni'],
                $r['apellidos'] . ' ' . $r['nombres'],
                $r['departamento_nombre'] ?? 'Sin Área',
                $r['turno_nombre'] ?? 'Sin Turno',
                $r['hora_entrada_programada'] ?? '-',
                $r['hora_salida_programada'] ?? '-',
                $r['hora_entrada_real'] ? substr($r['hora_entrada_real'], 11, 8) : '-',
                $r['hora_salida_real'] ? substr($r['hora_salida_real'], 11, 8) : '-',
                $r['minutos_tardanza'],
                $r['minutos_trabajados'],
                $r['minutos_extra'],
                $r['estado'],
                $r['observaciones'] ?? ''
            ]);
        }

        fclose($output);
        exit;
    }
}
