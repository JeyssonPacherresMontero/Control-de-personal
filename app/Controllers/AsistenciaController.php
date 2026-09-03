<?php
namespace App\Controllers;

use App\Database;
use App\Services\AttendanceCalculator;
use App\Services\EventStore;

require_once __DIR__ . '/../Services/EventStore.php';

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

        // Si se solicita exportar
        if (isset($_GET['export'])) {
            $exportType = strtolower(trim($_GET['export']));
            $deptoNombre = null;
            if ($deptoId) {
                $dRow = Database::queryOne("SELECT nombre FROM departamentos WHERE id = ?", [$deptoId]);
                if ($dRow) $deptoNombre = $dRow['nombre'];
            }

            if ($exportType === 'excel' || $exportType === 'xls') {
                $this->exportExcel($asistencias, $fechaInicio, $fechaFin, $deptoNombre);
                return;
            } elseif ($exportType === 'csv') {
                $this->exportCSV($asistencias, $fechaInicio, $fechaFin);
                return;
            }
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
            // 1. Obtener estado previo para auditoría y trazabilidad
            $prevRecord = Database::queryOne("SELECT * FROM asistencia_diaria WHERE id = ?", [$id]);

            // 2. Actualizar proyección Read Model
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

            // 3. Event Sourcing: Registrar evento inmutable de modificación manual
            if ($prevRecord) {
                $currentUser = AuthController::user();
                $usuario = $currentUser['usuario'] ?? 'ADMIN/RRHH';
                $empId = (int)$prevRecord['id_empleado'];
                $fecha = $prevRecord['fecha'];

                try {
                    EventStore::recordEvent(
                        'ASISTENCIA_DIARIA',
                        "emp_{$empId}_{$fecha}",
                        'ASISTENCIA_MODIFICADA_MANUAL',
                        [
                            'id_asistencia'          => $id,
                            'id_empleado'            => $empId,
                            'fecha'                  => $fecha,
                            'estado_anterior'        => $prevRecord['estado'],
                            'nuevo_estado'           => $estado,
                            'minutos_tardanza_ant'   => $prevRecord['minutos_tardanza'],
                            'nuevos_minutos_tardanza'=> $minutosTardanza,
                            'minutos_extra_ant'      => $prevRecord['minutos_extra'],
                            'nuevos_minutos_extra'   => $minutosExtra,
                            'observaciones_ant'      => $prevRecord['observaciones'],
                            'motivo'                 => $observaciones ?: 'Ajuste manual sin motivo especificado',
                            'modificado_por'         => $usuario
                        ],
                        $usuario
                    );
                } catch (\Exception $e) {
                    // Silencioso
                }
            }
        }

        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?route=asistencia&msg=ajustado'));
        exit;
    }

    /**
     * Endpoint API para consultar la línea de tiempo completa de eventos de un empleado en una fecha
     */
    public function historialEventos(): void {
        AuthController::checkAuth();
        header('Content-Type: application/json');

        $empId = (int)($_GET['id_empleado'] ?? 0);
        $fecha = $_GET['fecha'] ?? date('Y-m-d');

        if ($empId <= 0) {
            echo json_encode(['success' => false, 'error' => 'ID de empleado inválido']);
            exit;
        }

        $empleado = Database::queryOne("SELECT id, nombres, apellidos, dni, codigo_reloj FROM empleados WHERE id = ?", [$empId]);
        $asistencia = Database::queryOne("SELECT * FROM asistencia_diaria WHERE id_empleado = ? AND fecha = ?", [$empId, $fecha]);
        $timeline = EventStore::getTimelineForEmployeeDate($empId, $fecha);

        echo json_encode([
            'success'    => true,
            'empleado'   => $empleado,
            'asistencia' => $asistencia,
            'fecha'      => $fecha,
            'total'      => count($timeline),
            'events'     => $timeline
        ]);
        exit;
    }

    /**
     * Exporta el reporte en formato Excel (.xls) estructurado en tablas HTML con estilos y colores.
     */
    private function exportExcel(array $data, string $start, string $end, ?string $deptoNombre = null): void {
        $filename = "Reporte_Asistencia_{$start}_al_{$end}.xls";

        header("Content-Type: application/vnd.ms-excel; charset=utf-8");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Pragma: no-cache");
        header("Expires: 0");

        // Cálculo de KPIs y Totales
        $totalRegistros = count($data);
        $totalPresentes = 0;
        $totalTardanzas = 0;
        $totalFaltas = 0;
        $totalJustificados = 0;
        $sumMinTardanza = 0;
        $sumMinTrabajados = 0;
        $sumMinExtra = 0;

        foreach ($data as $r) {
            $est = $r['estado'];
            if ($est === 'PRESENTE') {
                $totalPresentes++;
            } elseif ($est === 'TARDANZA') {
                $totalTardanzas++;
                $sumMinTardanza += (int)$r['minutos_tardanza'];
            } elseif ($est === 'FALTA' || $est === 'FALTA_INJUSTIFICADA') {
                $totalFaltas++;
            } elseif (in_array($est, ['JUSTIFICADO', 'PERMISO', 'VACACIONES'], true)) {
                $totalJustificados++;
            }
            $sumMinTrabajados += (int)$r['minutos_trabajados'];
            $sumMinExtra += (int)$r['minutos_extra'];
        }

        $horasTrabajadasTotales = sprintf('%dh %02dm', floor($sumMinTrabajados / 60), $sumMinTrabajados % 60);
        $horasExtrasTotales = sprintf('%dh %02dm', floor($sumMinExtra / 60), $sumMinExtra % 60);
        $horasTardanzaTotales = sprintf('%dh %02dm', floor($sumMinTardanza / 60), $sumMinTardanza % 60);

        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        echo '<head>';
        echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
        echo '<style>
            body { font-family: "Segoe UI", Tahoma, Arial, sans-serif; font-size: 11pt; color: #1e293b; }
            .title { font-size: 16pt; font-weight: bold; color: #0f172a; text-align: center; }
            .subtitle { font-size: 10.5pt; color: #475569; text-align: center; }
            
            .kpi-table { border-collapse: collapse; margin-bottom: 20px; }
            .kpi-table td { border: 1px solid #cbd5e1; padding: 7px 12px; font-size: 10pt; }
            .kpi-header { background-color: #f1f5f9; font-weight: bold; color: #334155; }
            .kpi-val { font-weight: bold; text-align: center; }
            
            .report-table { border-collapse: collapse; width: 100%; }
            .report-table th { background-color: #1e3a8a; color: #ffffff; font-weight: bold; border: 1px solid #172554; padding: 9px 8px; font-size: 10pt; text-align: center; }
            .report-table td { border: 1px solid #cbd5e1; padding: 6px 8px; font-size: 9.5pt; vertical-align: middle; }
            .report-table tr:nth-child(even) { background-color: #f8fafc; }
            
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .text-left { text-align: left; }
            .font-bold { font-weight: bold; }
            
            .badge-presente { background-color: #dcfce7; color: #166534; font-weight: bold; text-align: center; }
            .badge-tardanza { background-color: #fef3c7; color: #92400e; font-weight: bold; text-align: center; }
            .badge-falta { background-color: #fee2e2; color: #991b1b; font-weight: bold; text-align: center; }
            .badge-justificado { background-color: #e0f2fe; color: #075985; font-weight: bold; text-align: center; }
            .badge-sin-salida { background-color: #f1f5f9; color: #475569; font-weight: bold; text-align: center; }
            
            .tfoot-total { background-color: #e2e8f0; font-weight: bold; border-top: 2px solid #475569; }
        </style>';
        echo '</head>';
        echo '<body>';

        // Título del reporte
        echo '<table style="width:100%; margin-bottom: 12px;">';
        echo '<tr><td colspan="16" class="title">JUNTA DE USUARIOS DEL SECTOR HIDRÁULICO MENOR SAN LORENZO (JUSHSAL)<br><span style="font-size:11pt; font-weight:600; color:#475569;">REPORTE OFICIAL DE CONTROL DE ASISTENCIA</span></td></tr>';
        echo '<tr><td colspan="16" class="subtitle">Período: <b>' . htmlspecialchars($start) . '</b> al <b>' . htmlspecialchars($end) . '</b>' . ($deptoNombre ? ' &nbsp;|&nbsp; Departamento: <b>' . htmlspecialchars($deptoNombre) . '</b>' : '') . ' &nbsp;|&nbsp; Generado: ' . date('d/m/Y H:i:s') . '</td></tr>';
        echo '</table>';

        // Resumen / KPIs
        echo '<table class="kpi-table" style="margin-bottom: 16px;">';
        echo '<tr>';
        echo '<td class="kpi-header">Total Registros</td><td class="kpi-val">' . $totalRegistros . '</td>';
        echo '<td class="kpi-header">Presentes</td><td class="kpi-val" style="color:#166534;">' . $totalPresentes . '</td>';
        echo '<td class="kpi-header">Tardanzas</td><td class="kpi-val" style="color:#92400e;">' . $totalTardanzas . ' (' . $horasTardanzaTotales . ')</td>';
        echo '<td class="kpi-header">Faltas</td><td class="kpi-val" style="color:#991b1b;">' . $totalFaltas . '</td>';
        echo '<td class="kpi-header">Justificados</td><td class="kpi-val" style="color:#075985;">' . $totalJustificados . '</td>';
        echo '<td class="kpi-header">Total Trabajado</td><td class="kpi-val">' . $horasTrabajadasTotales . '</td>';
        echo '<td class="kpi-header">Total H. Extras</td><td class="kpi-val" style="color:#0369a1;">' . $horasExtrasTotales . '</td>';
        echo '</tr>';
        echo '</table>';

        // Tabla Principal
        echo '<table class="report-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th style="width: 35px;">#</th>';
        echo '<th style="width: 85px;">Fecha</th>';
        echo '<th style="width: 80px;">DNI</th>';
        echo '<th style="width: 75px;">Cód. Reloj</th>';
        echo '<th style="width: 220px;">Apellidos y Nombres</th>';
        echo '<th style="width: 140px;">Departamento</th>';
        echo '<th style="width: 110px;">Turno</th>';
        echo '<th style="width: 75px;">Prog. Ent.</th>';
        echo '<th style="width: 75px;">Prog. Sal.</th>';
        echo '<th style="width: 75px;">Real Ent.</th>';
        echo '<th style="width: 75px;">Real Sal.</th>';
        echo '<th style="width: 85px;">Tardanza</th>';
        echo '<th style="width: 95px;">T. Trabajado</th>';
        echo '<th style="width: 85px;">H. Extra</th>';
        echo '<th style="width: 115px;">Estado</th>';
        echo '<th style="width: 180px;">Observaciones</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        $i = 1;
        foreach ($data as $r) {
            $est = $r['estado'];
            $badgeClass = match($est) {
                'PRESENTE' => 'badge-presente',
                'TARDANZA' => 'badge-tardanza',
                'FALTA', 'FALTA_INJUSTIFICADA' => 'badge-falta',
                'JUSTIFICADO', 'PERMISO', 'VACACIONES' => 'badge-justificado',
                'SALIDA_SIN_MARCAR' => 'badge-sin-salida',
                default => ''
            };

            $horaEntReal = $r['hora_entrada_real'] ? substr($r['hora_entrada_real'], 11, 5) : '--:--';
            $horaSalReal = $r['hora_salida_real'] ? substr($r['hora_salida_real'], 11, 5) : '--:--';
            
            $minTrab = (int)$r['minutos_trabajados'];
            $strTrabajado = sprintf('%dh %02dm', floor($minTrab / 60), $minTrab % 60);

            $minTard = (int)$r['minutos_tardanza'];
            $strTardanza = $minTard > 0 ? "+{$minTard} min" : "-";

            $minExt = (int)$r['minutos_extra'];
            $strExtra = $minExt > 0 ? "+{$minExt} min" : "-";

            $estLabel = match($est) {
                'PRESENTE' => 'PRESENTE',
                'TARDANZA' => 'TARDANZA',
                'FALTA' => 'FALTA',
                'FALTA_INJUSTIFICADA' => 'FALTA INJUSTIFICADA',
                'JUSTIFICADO' => 'JUSTIFICADO',
                'PERMISO' => 'PERMISO',
                'VACACIONES' => 'VACACIONES',
                'DESCANSO' => 'DESCANSO',
                'SALIDA_SIN_MARCAR' => 'SIN SALIDA',
                default => $est
            };

            echo '<tr>';
            echo '<td class="text-center">' . $i++ . '</td>';
            echo '<td class="text-center font-bold">' . htmlspecialchars($r['fecha']) . '</td>';
            echo '<td class="text-center" style="mso-number-format:\'@\';">' . htmlspecialchars($r['dni']) . '</td>';
            echo '<td class="text-center" style="mso-number-format:\'@\';">' . htmlspecialchars($r['codigo_reloj']) . '</td>';
            echo '<td class="text-left font-bold">' . htmlspecialchars($r['apellidos'] . ' ' . $r['nombres']) . '</td>';
            echo '<td class="text-left">' . htmlspecialchars($r['departamento_nombre'] ?? 'Sin Área') . '</td>';
            echo '<td class="text-center">' . htmlspecialchars($r['turno_nombre'] ?? 'Sin Turno') . '</td>';
            echo '<td class="text-center">' . ($r['hora_entrada_programada'] ? substr($r['hora_entrada_programada'], 0, 5) : '--:--') . '</td>';
            echo '<td class="text-center">' . ($r['hora_salida_programada'] ? substr($r['hora_salida_programada'], 0, 5) : '--:--') . '</td>';
            echo '<td class="text-center font-bold" style="' . ($minTard > 0 ? 'color:#b91c1c;' : 'color:#15803d;') . '">' . $horaEntReal . '</td>';
            echo '<td class="text-center font-bold">' . $horaSalReal . '</td>';
            echo '<td class="text-center font-bold" style="' . ($minTard > 0 ? 'color:#c2410c;' : 'color:#64748b;') . '">' . $strTardanza . '</td>';
            echo '<td class="text-center">' . $strTrabajado . '</td>';
            echo '<td class="text-center font-bold" style="' . ($minExt > 0 ? 'color:#0284c7;' : 'color:#64748b;') . '">' . $strExtra . '</td>';
            echo '<td class="' . $badgeClass . '">' . htmlspecialchars($estLabel) . '</td>';
            echo '<td class="text-left">' . htmlspecialchars($r['observaciones'] ?? '') . '</td>';
            echo '</tr>';
        }

        // Fila de totales al pie de la tabla
        echo '</tbody>';
        echo '<tfoot>';
        echo '<tr class="tfoot-total">';
        echo '<td colspan="11" class="text-right font-bold" style="padding: 8px;">TOTALES GENERALES:</td>';
        echo '<td class="text-center font-bold" style="color:#c2410c;">' . ($sumMinTardanza > 0 ? "+{$sumMinTardanza} min" : "0 min") . '</td>';
        echo '<td class="text-center font-bold">' . $horasTrabajadasTotales . '</td>';
        echo '<td class="text-center font-bold" style="color:#0284c7;">' . ($sumMinExtra > 0 ? "+{$sumMinExtra} min" : "0 min") . '</td>';
        echo '<td colspan="2" class="text-center font-bold">' . $totalRegistros . ' registros</td>';
        echo '</tr>';
        echo '</tfoot>';
        echo '</table>';

        echo '</body>';
        echo '</html>';
        exit;
    }

    /**
     * Exporta el reporte a CSV con separador punto y coma (;) compatible con Excel en español y BOM UTF-8.
     */
    private function exportCSV(array $data, string $start, string $end): void {
        $filename = "reporte_asistencia_{$start}_al_{$end}.csv";
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Pragma: no-cache");
        header("Expires: 0");

        $output = fopen('php://output', 'w');
        // BOM UTF-8 para que Excel en Windows reconozca tildes y caracteres especiales
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Delimitador punto y coma (;) para compatibilidad nativa con Excel en español
        $delimiter = ';';

        // Cabeceras
        fputcsv($output, [
            'Fecha',
            'DNI',
            'Código Reloj',
            'Apellidos y Nombres',
            'Departamento / Área',
            'Turno Asignado',
            'Entrada Programada',
            'Salida Programada',
            'Entrada Real',
            'Salida Real',
            'Tardanza (Minutos)',
            'Tiempo Trabajado (Horas:Min)',
            'Minutos Trabajados',
            'Horas Extras (Minutos)',
            'Estado',
            'Observaciones'
        ], $delimiter);

        foreach ($data as $r) {
            $minTrab = (int)$r['minutos_trabajados'];
            $strTrabajado = sprintf('%02dh %02dm', floor($minTrab / 60), $minTrab % 60);

            $estLabel = match($r['estado']) {
                'PRESENTE' => 'PRESENTE',
                'TARDANZA' => 'TARDANZA',
                'FALTA' => 'FALTA',
                'FALTA_INJUSTIFICADA' => 'FALTA INJUSTIFICADA',
                'JUSTIFICADO' => 'JUSTIFICADO',
                'PERMISO' => 'PERMISO',
                'VACACIONES' => 'VACACIONES',
                'DESCANSO' => 'DESCANSO',
                'SALIDA_SIN_MARCAR' => 'SIN SALIDA',
                default => $r['estado']
            };

            fputcsv($output, [
                $r['fecha'],
                $r['dni'],
                $r['codigo_reloj'],
                $r['apellidos'] . ' ' . $r['nombres'],
                $r['departamento_nombre'] ?? 'Sin Área',
                $r['turno_nombre'] ?? 'Sin Turno',
                $r['hora_entrada_programada'] ?? '--:--',
                $r['hora_salida_programada'] ?? '--:--',
                $r['hora_entrada_real'] ? substr($r['hora_entrada_real'], 11, 5) : '--:--',
                $r['hora_salida_real'] ? substr($r['hora_salida_real'], 11, 5) : '--:--',
                (int)$r['minutos_tardanza'],
                $strTrabajado,
                $minTrab,
                (int)$r['minutos_extra'],
                $estLabel,
                $r['observaciones'] ?? ''
            ], $delimiter);
        }

        fclose($output);
        exit;
    }
}
