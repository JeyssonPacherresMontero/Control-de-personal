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

        if (isset($_GET['export'])) {
            $exportType = strtolower(trim($_GET['export']));
            if ($exportType === 'excel' || $exportType === 'xls') {
                $this->exportExcel($marcaciones, $fecha);
                return;
            } elseif ($exportType === 'csv') {
                $this->exportCSV($marcaciones, $fecha);
                return;
            }
        }

        require_once APP_ROOT . '/views/marcaciones/index.php';
    }

    private function exportExcel(array $data, string $fecha): void {
        $filename = "Reporte_Marcaciones_{$fecha}.xls";
        header("Content-Type: application/vnd.ms-excel; charset=utf-8");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Pragma: no-cache");
        header("Expires: 0");

        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        echo '<head>';
        echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
        echo '<style>
            body { font-family: "Segoe UI", Tahoma, Arial, sans-serif; font-size: 11pt; color: #1e293b; }
            .title { font-size: 15pt; font-weight: bold; color: #0f172a; text-align: center; }
            .subtitle { font-size: 10pt; color: #475569; text-align: center; margin-bottom: 15px; }
            .report-table { border-collapse: collapse; width: 100%; }
            .report-table th { background-color: #0f766e; color: #ffffff; font-weight: bold; border: 1px solid #115e59; padding: 8px; font-size: 10pt; text-align: center; }
            .report-table td { border: 1px solid #cbd5e1; padding: 6px 8px; font-size: 9.5pt; vertical-align: middle; }
            .report-table tr:nth-child(even) { background-color: #f8fafc; }
            .text-center { text-align: center; }
            .text-left { text-align: left; }
            .font-bold { font-weight: bold; }
        </style>';
        echo '</head>';
        echo '<body>';

        echo '<table style="width:100%; margin-bottom: 12px;">';
        echo '<tr><td colspan="8" class="title">' . htmlspecialchars(APP_NAME) . ' - REGISTRO DE MARCACIONES DE RELOJES BIOMÉTRICOS</td></tr>';
        echo '<tr><td colspan="8" class="subtitle">Fecha: <b>' . htmlspecialchars($fecha) . '</b> | Total registros: <b>' . count($data) . '</b> | Generado: ' . date('d/m/Y H:i:s') . '</td></tr>';
        echo '</table>';

        echo '<table class="report-table">';
        echo '<thead><tr>';
        echo '<th>#</th><th>Fecha y Hora</th><th>ID en Reloj</th><th>DNI</th><th>Empleado</th><th>Reloj Biométrico</th><th>Tipo de Marcación</th><th>Método de Verificación</th>';
        echo '</tr></thead><tbody>';

        $i = 1;
        foreach ($data as $m) {
            $tipoStr = match(strtolower($m['tipo'] ?? '')) {
                'entrada' => 'Entrada',
                'salida' => 'Salida',
                'refrigerio_salida' => 'Salida a Refrigerio',
                'refrigerio_entrada' => 'Regreso de Refrigerio',
                default => 'Marcación'
            };

            $verif = strtolower($m['tipo_verificacion'] ?? '');
            if (str_contains($verif, 'huella') || $verif === 'fingerprint') $verifStr = 'Huella Dactilar';
            elseif (str_contains($verif, 'facial') || str_contains($verif, 'face')) $verifStr = 'Reconocimiento Facial';
            elseif (str_contains($verif, 'tarjeta') || str_contains($verif, 'card') || str_contains($verif, 'rfid')) $verifStr = 'Tarjeta RFID';
            elseif (str_contains($verif, 'manual')) $verifStr = 'Registro Manual RRHH';
            elseif (str_contains($verif, 'clave') || str_contains($verif, 'pin')) $verifStr = 'Contraseña / PIN';
            else $verifStr = $m['tipo_verificacion'] ?: 'Biométrico';

            echo '<tr>';
            echo '<td class="text-center">' . $i++ . '</td>';
            echo '<td class="text-center font-bold">' . htmlspecialchars($m['fecha_hora']) . '</td>';
            echo '<td class="text-center" style="mso-number-format:\'@\';">' . htmlspecialchars($m['codigo_reloj']) . '</td>';
            echo '<td class="text-center" style="mso-number-format:\'@\';">' . htmlspecialchars($m['dni'] ?? '-') . '</td>';
            echo '<td class="text-left font-bold">' . htmlspecialchars(!empty($m['nombres']) ? $m['apellidos'] . ' ' . $m['nombres'] : 'Sin vincular') . '</td>';
            echo '<td class="text-left">' . htmlspecialchars($m['dispositivo_nombre'] ?? 'Desconocido') . '</td>';
            echo '<td class="text-center">' . htmlspecialchars($tipoStr) . '</td>';
            echo '<td class="text-center">' . htmlspecialchars($verifStr) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</body></html>';
        exit;
    }

    private function exportCSV(array $data, string $fecha): void {
        $filename = "reporte_marcaciones_{$fecha}.csv";
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Pragma: no-cache");
        header("Expires: 0");

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        $delimiter = ';';

        fputcsv($output, ['N°', 'Fecha y Hora', 'ID en Reloj', 'DNI', 'Empleado', 'Reloj Biométrico', 'Tipo de Marcación', 'Método de Verificación', 'Estado de Procesamiento'], $delimiter);

        foreach ($data as $m) {
            $tipoStr = match(strtolower($m['tipo'] ?? '')) {
                'entrada' => 'Entrada',
                'salida' => 'Salida',
                'refrigerio_salida' => 'Salida a Refrigerio',
                'refrigerio_entrada' => 'Regreso de Refrigerio',
                default => 'Marcación'
            };

            $verif = strtolower($m['tipo_verificacion'] ?? '');
            if (str_contains($verif, 'huella') || $verif === 'fingerprint') $verifStr = 'Huella Dactilar';
            elseif (str_contains($verif, 'facial') || str_contains($verif, 'face')) $verifStr = 'Reconocimiento Facial';
            elseif (str_contains($verif, 'tarjeta') || str_contains($verif, 'card') || str_contains($verif, 'rfid')) $verifStr = 'Tarjeta RFID';
            elseif (str_contains($verif, 'manual')) $verifStr = 'Registro Manual RRHH';
            elseif (str_contains($verif, 'clave') || str_contains($verif, 'pin')) $verifStr = 'Contraseña / PIN';
            else $verifStr = $m['tipo_verificacion'] ?: 'Biométrico';

            fputcsv($output, [
                $m['id'],
                $m['fecha_hora'],
                $m['codigo_reloj'],
                $m['dni'] ?? '',
                !empty($m['nombres']) ? $m['apellidos'] . ' ' . $m['nombres'] : 'Sin vincular',
                $m['dispositivo_nombre'] ?? 'Desconocido',
                $tipoStr,
                $verifStr,
                $m['procesado'] ? 'Procesado' : 'Pendiente'
            ], $delimiter);
        }

        fclose($output);
        exit;
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
