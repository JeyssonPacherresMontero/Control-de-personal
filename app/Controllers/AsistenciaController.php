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

        if (!empty($estado) && strtolower($estado) !== 'todos') {
            $estadoUpper = strtoupper($estado);
            if ($estadoUpper === 'FALTA') {
                $sql .= " AND (a.estado = 'FALTA' OR a.estado = 'FALTA_INJUSTIFICADA')";
            } elseif ($estadoUpper === 'JUSTIFICADO') {
                $sql .= " AND (a.estado = 'JUSTIFICADO' OR a.estado = 'PERMISO' OR a.estado = 'VACACIONES' OR a.estado = 'LICENCIA')";
            } elseif ($estadoUpper === 'INCIDENCIAS') {
                $sql .= " AND (a.estado = 'TARDANZA' OR a.estado = 'FALTA' OR a.estado = 'FALTA_INJUSTIFICADA' OR a.estado = 'SALIDA_SIN_MARCAR')";
            } else {
                $sql .= " AND a.estado = :estado";
                $params[':estado'] = $estadoUpper;
            }
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
                $this->exportExcel($asistencias, $fechaInicio, $fechaFin, $deptoNombre, $estado, $search);
                return;
            } elseif ($exportType === 'csv') {
                $this->exportCSV($asistencias, $fechaInicio, $fechaFin, $deptoNombre, $estado);
                return;
            }
        }

        require_once APP_ROOT . '/views/asistencia/index.php';
    }

    public function recalcular(): void {
        AuthController::checkAuth();
        \App\Csrf::validateRequest();

        $fechaInicio = $_POST['fecha_inicio'] ?? date('Y-m-d');
        $fechaFin = $_POST['fecha_fin'] ?? $fechaInicio;

        $calculator = new AttendanceCalculator(ATTENDANCE_DEBOUNCE_MINUTES);
        $results = $calculator->processDateRange($fechaInicio, $fechaFin);

        header("Location: ?route=asistencia&fecha_inicio=$fechaInicio&fecha_fin=$fechaFin&msg=recalculado");
        exit;
    }

    public function editar(): void {
        AuthController::checkAuth();
        \App\Csrf::validateRequest();

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
     * Exporta el reporte en formato Excel (.xls) estructurado en MHTML multipart con logo_icon.png incrustado nativamente.
     */
    private function exportExcel(array $data, string $start, string $end, ?string $deptoNombre = null, ?string $estado = null, ?string $search = null): void {
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
        $totalSinSalida = 0;
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
            } elseif ($est === 'SALIDA_SIN_MARCAR') {
                $totalSinSalida++;
            }
            $sumMinTrabajados += (int)$r['minutos_trabajados'];
            $sumMinExtra += (int)$r['minutos_extra'];
        }

        $horasTrabajadasTotales = sprintf('%dh %02dm', floor($sumMinTrabajados / 60), $sumMinTrabajados % 60);
        $horasExtrasTotales = sprintf('%dh %02dm', floor($sumMinExtra / 60), $sumMinExtra % 60);
        $horasTardanzaTotales = sprintf('%dh %02dm', floor($sumMinTardanza / 60), $sumMinTardanza % 60);
        $pctPuntualidad = $totalRegistros > 0 ? round(($totalPresentes / $totalRegistros) * 100, 1) : 0;

        $currentUser = AuthController::user();
        $generadoPor = ($currentUser['nombre'] ?? 'Administrador') . ' (' . ($currentUser['rol'] ?? 'RRHH') . ')';

        $logoPath = APP_ROOT . '/public/img/logo_icon.png';
        if (!file_exists($logoPath)) {
            $logoPath = APP_ROOT . '/img/logo_icon.png';
        }
        $hasLogo = file_exists($logoPath);
        $logoData = $hasLogo ? base64_encode(file_get_contents($logoPath)) : '';
        $logoDataChunked = chunk_split($logoData, 76, "\r\n");

        $boundary = "----=_NextPart_JUSHSAL_" . md5(uniqid());

        // Cabecera MHTML Multipart
        echo "MIME-Version: 1.0\r\n";
        echo "X-Document-Type: Worksheet\r\n";
        echo "Content-Type: multipart/related; boundary=\"{$boundary}\"; type=\"text/html\"\r\n\r\n";

        // Parte 1: Documento HTML
        echo "--{$boundary}\r\n";
        echo "Content-Type: text/html; charset=\"utf-8\"\r\n";
        echo "Content-Transfer-Encoding: 8bit\r\n";
        echo "Content-Location: report.htm\r\n\r\n";

        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        echo '<head>';
        echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
        echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Control de Asistencia</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
        echo '<style>
            body { font-family: "Segoe UI", Tahoma, Arial, sans-serif; font-size: 10pt; color: #1e293b; }
            .inst-name { font-size: 14pt; font-weight: bold; color: #1e3a8a; text-transform: uppercase; }
            .inst-sub { font-size: 9.5pt; color: #475569; font-weight: bold; }
            .report-title { font-size: 13pt; font-weight: bold; color: #0f172a; background-color: #f1f5f9; padding: 6px; text-align: center; border: 1px solid #cbd5e1; }
            
            .meta-table { border-collapse: collapse; margin-bottom: 12px; width: 100%; }
            .meta-table td { border: 1px solid #e2e8f0; padding: 5px 8px; font-size: 9pt; }
            .meta-label { background-color: #f8fafc; font-weight: bold; color: #334155; width: 15%; }
            .meta-val { font-weight: 500; color: #0f172a; }

            .kpi-table { border-collapse: collapse; margin-bottom: 16px; width: 100%; }
            .kpi-table th { background-color: #0f172a; color: #ffffff; font-weight: bold; border: 1px solid #0f172a; padding: 6px 8px; font-size: 8.5pt; text-align: center; }
            .kpi-table td { border: 1px solid #cbd5e1; padding: 6px 8px; font-size: 10pt; font-weight: bold; text-align: center; }
            
            .report-table { border-collapse: collapse; width: 100%; margin-top: 10px; }
            .report-table th { background-color: #1e3a8a; color: #ffffff; font-weight: bold; border: 1px solid #172554; padding: 8px 6px; font-size: 9pt; text-align: center; }
            .report-table td { border: 1px solid #cbd5e1; padding: 5px 6px; font-size: 8.5pt; vertical-align: middle; }
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
            
            .tfoot-total { background-color: #e2e8f0; font-weight: bold; border-top: 2px solid #1e3a8a; }
            .signatures-table { margin-top: 35px; width: 100%; border-collapse: collapse; }
            .signatures-table td { border: none; text-align: center; font-size: 9pt; padding-top: 40px; }
        </style>';
        echo '</head>';
        echo '<body>';

        // 1. ENCABEZADO INSTITUCIONAL CON LOGO
        echo '<table style="width:100%; margin-bottom: 12px; border-collapse: collapse;">';
        echo '<tr>';
        if ($hasLogo) {
            echo '<td rowspan="2" style="width: 80px; text-align: center; vertical-align: middle; padding: 6px;">';
            echo '<img src="logo_icon.png" alt="JUSHSAL" height="55" width="55" style="max-height:55px; max-width:55px; width:auto; object-fit:contain;" />';
            echo '</td>';
            echo '<td colspan="15" class="inst-name">JUNTA DE USUARIOS DEL SECTOR HIDRÁULICO MENOR SAN LORENZO (JUSHSAL)</td>';
        } else {
            echo '<td colspan="16" class="inst-name">JUNTA DE USUARIOS DEL SECTOR HIDRÁULICO MENOR SAN LORENZO (JUSHSAL)</td>';
        }
        echo '</tr>';
        echo '<tr>';
        echo '<td colspan="' . ($hasLogo ? '15' : '16') . '" class="inst-sub">SISTEMA INTEGRADO DE CONTROL DE PERSONAL Y ASISTENCIA LABORAL</td>';
        echo '</tr>';
        echo '<tr><td colspan="16" style="height: 6px;"></td></tr>';
        echo '<tr><td colspan="16" class="report-title">REPORTE OFICIAL DETALLADO DE ASISTENCIA LABORAL</td></tr>';
        echo '</table>';

        // 2. PARÁMETROS DEL REPORTE Y METADATOS
        echo '<table class="meta-table">';
        echo '<tr>';
        echo '<td class="meta-label">Período Consultado:</td><td class="meta-val"><b>' . date('d/m/Y', strtotime($start)) . '</b> al <b>' . date('d/m/Y', strtotime($end)) . '</b></td>';
        echo '<td class="meta-label">Departamento / Área:</td><td class="meta-val">' . htmlspecialchars($deptoNombre ?: 'Todos los Departamentos') . '</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<td class="meta-label">Filtro de Estado:</td><td class="meta-val">' . htmlspecialchars($estado ?: 'Todos los Estados') . ($search ? ' | Búsqueda: "' . htmlspecialchars($search) . '"' : '') . '</td>';
        echo '<td class="meta-label">Emitido Por:</td><td class="meta-val">' . htmlspecialchars($generadoPor) . ' &nbsp;|&nbsp; <b>Fecha:</b> ' . date('d/m/Y H:i:s') . '</td>';
        echo '</tr>';
        echo '</table>';

        // 3. RESUMEN EJECUTIVO Y KPIS
        echo '<table class="kpi-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>TOTAL REGISTROS</th>';
        echo '<th>ASISTENCIAS PUNTUALES</th>';
        echo '<th>% PUNTUALIDAD</th>';
        echo '<th>TARDANZAS</th>';
        echo '<th>TIEMPO TARDANZA</th>';
        echo '<th>FALTAS</th>';
        echo '<th>JUSTIFICADOS / PERMISOS</th>';
        echo '<th>SIN SALIDA</th>';
        echo '<th>HORAS TRABAJADAS</th>';
        echo '<th>HORAS EXTRAS</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        echo '<tr>';
        echo '<td style="background-color:#f8fafc;">' . $totalRegistros . '</td>';
        echo '<td style="color:#166534; background-color:#f0fdf4;">' . $totalPresentes . '</td>';
        echo '<td style="color:#0284c7; background-color:#f0f9ff;">' . $pctPuntualidad . '%</td>';
        echo '<td style="color:#92400e; background-color:#fffbeb;">' . $totalTardanzas . '</td>';
        echo '<td style="color:#92400e; background-color:#fffbeb;">' . $horasTardanzaTotales . '</td>';
        echo '<td style="color:#991b1b; background-color:#fef2f2;">' . $totalFaltas . '</td>';
        echo '<td style="color:#075985; background-color:#f0f9ff;">' . $totalJustificados . '</td>';
        echo '<td style="color:#475569; background-color:#f8fafc;">' . $totalSinSalida . '</td>';
        echo '<td style="color:#0f172a; background-color:#f8fafc;">' . $horasTrabajadasTotales . '</td>';
        echo '<td style="color:#0369a1; background-color:#f0f9ff;">' . $horasExtrasTotales . '</td>';
        echo '</tr>';
        echo '</tbody>';
        echo '</table>';

        // 4. TABLA PRINCIPAL DE DATOS
        echo '<table class="report-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th style="width: 35px;">#</th>';
        echo '<th style="width: 80px;">Fecha</th>';
        echo '<th style="width: 75px;">DNI</th>';
        echo '<th style="width: 70px;">Cód. Reloj</th>';
        echo '<th style="width: 220px;">Apellidos y Nombres</th>';
        echo '<th style="width: 130px;">Departamento / Área</th>';
        echo '<th style="width: 100px;">Turno Asignado</th>';
        echo '<th style="width: 70px;">Prog. Ent.</th>';
        echo '<th style="width: 70px;">Prog. Sal.</th>';
        echo '<th style="width: 70px;">Real Ent.</th>';
        echo '<th style="width: 70px;">Real Sal.</th>';
        echo '<th style="width: 80px;">Tardanza</th>';
        echo '<th style="width: 90px;">T. Trabajado</th>';
        echo '<th style="width: 80px;">H. Extra</th>';
        echo '<th style="width: 110px;">Estado</th>';
        echo '<th style="width: 180px;">Observaciones / Sustento</th>';
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
            echo '<td class="text-center font-bold">' . date('d/m/Y', strtotime($r['fecha'])) . '</td>';
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

        // 5. BLOQUE DE FIRMAS Y CONFORMIDAD INSTITUCIONAL
        echo '<table class="signatures-table">';
        echo '<tr>';
        echo '<td style="width: 50%;">';
        echo '<div style="display:inline-block; border-top: 1.5px solid #334155; padding-top: 6px; width: 280px;">';
        echo '<b>RESPONSABLE DE RECURSOS HUMANOS</b><br>';
        echo '<span style="color:#64748b; font-size:8.5pt;">Control de Personal y Asistencia - JUSHSAL</span>';
        echo '</div>';
        echo '</td>';
        echo '<td style="width: 50%;">';
        echo '<div style="display:inline-block; border-top: 1.5px solid #334155; padding-top: 6px; width: 280px;">';
        echo '<b>V°B° ADMINISTRACIÓN GENERAL</b><br>';
        echo '<span style="color:#64748b; font-size:8.5pt;">Junta de Usuarios San Lorenzo</span>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';

        echo '</body>';
        echo '</html>';

        // Parte 2: Adjunto de la imagen incrustada
        if ($hasLogo) {
            echo "\r\n--{$boundary}\r\n";
            echo "Content-Type: image/png; name=\"logo_icon.png\"\r\n";
            echo "Content-Transfer-Encoding: base64\r\n";
            echo "Content-ID: <logo_icon.png>\r\n";
            echo "Content-Location: logo_icon.png\r\n\r\n";
            echo $logoDataChunked . "\r\n";
        }

        echo "--{$boundary}--\r\n";
        exit;
    }

    /**
     * Exporta el reporte a CSV altamente estructurado con membrete institucional, KPIs y formato compatible.
     */
    private function exportCSV(array $data, string $start, string $end, ?string $deptoNombre = null, ?string $estado = null): void {
        $filename = "reporte_asistencia_{$start}_al_{$end}.csv";
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Pragma: no-cache");
        header("Expires: 0");

        $output = fopen('php://output', 'w');
        // BOM UTF-8 para compatibilidad nativa con Excel en Windows (reconoce tildes y caracteres especiales)
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        $delimiter = ';';

        // Cálculo de KPIs
        $totalRegistros = count($data);
        $totalPresentes = 0;
        $totalTardanzas = 0;
        $totalFaltas = 0;
        $totalJustificados = 0;
        $totalSinSalida = 0;
        $sumMinTardanza = 0;
        $sumMinTrabajados = 0;
        $sumMinExtra = 0;

        foreach ($data as $r) {
            $est = $r['estado'];
            if ($est === 'PRESENTE') $totalPresentes++;
            elseif ($est === 'TARDANZA') {
                $totalTardanzas++;
                $sumMinTardanza += (int)$r['minutos_tardanza'];
            } elseif ($est === 'FALTA' || $est === 'FALTA_INJUSTIFICADA') $totalFaltas++;
            elseif (in_array($est, ['JUSTIFICADO', 'PERMISO', 'VACACIONES'], true)) $totalJustificados++;
            elseif ($est === 'SALIDA_SIN_MARCAR') $totalSinSalida++;

            $sumMinTrabajados += (int)$r['minutos_trabajados'];
            $sumMinExtra += (int)$r['minutos_extra'];
        }

        $horasTrabajadasTotales = sprintf('%02dh %02dm', floor($sumMinTrabajados / 60), $sumMinTrabajados % 60);
        $horasExtrasTotales = sprintf('%02dh %02dm', floor($sumMinExtra / 60), $sumMinExtra % 60);
        $horasTardanzaTotales = sprintf('%02dh %02dm', floor($sumMinTardanza / 60), $sumMinTardanza % 60);
        $pctPuntualidad = $totalRegistros > 0 ? round(($totalPresentes / $totalRegistros) * 100, 1) : 0;

        $currentUser = AuthController::user();
        $generadoPor = ($currentUser['nombre'] ?? 'Administrador') . ' (' . ($currentUser['rol'] ?? 'RRHH') . ')';

        // 1. MEMBRETE Y METADATOS INSTITUCIONALES EN CSV
        fputcsv($output, ['JUNTA DE USUARIOS DEL SECTOR HIDRAULICO MENOR SAN LORENZO (JUSHSAL)'], $delimiter);
        fputcsv($output, ['SISTEMA INTEGRADO DE CONTROL DE PERSONAL Y ASISTENCIA LABORAL'], $delimiter);
        fputcsv($output, ['REPORTE OFICIAL CONSOLIDADO DE ASISTENCIA DIARIA'], $delimiter);
        fputcsv($output, ['Periodo:', date('d/m/Y', strtotime($start)) . ' al ' . date('d/m/Y', strtotime($end)), 'Area / Departamento:', $deptoNombre ?: 'Todos los Departamentos', 'Filtro Estado:', $estado ?: 'Todos', 'Emitido por:', $generadoPor, 'Fecha Emision:', date('d/m/Y H:i:s')], $delimiter);
        fputcsv($output, [], $delimiter); // Línea en blanco

        // 2. RESUMEN EJECUTIVO Y KPIS EN CSV
        fputcsv($output, ['=== RESUMEN EJECUTIVO DE ASISTENCIA ==='], $delimiter);
        fputcsv($output, ['Total Registros', 'Asistencias Puntuales', '% Puntualidad', 'Tardanzas (Casos)', 'Tiempo Tardanzas', 'Faltas', 'Justificados / Licencias', 'Sin Salida', 'Total Horas Laboradas', 'Total Horas Extras'], $delimiter);
        fputcsv($output, [
            $totalRegistros,
            $totalPresentes,
            $pctPuntualidad . '%',
            $totalTardanzas,
            $horasTardanzaTotales,
            $totalFaltas,
            $totalJustificados,
            $totalSinSalida,
            $horasTrabajadasTotales,
            $horasExtrasTotales
        ], $delimiter);
        fputcsv($output, [], $delimiter); // Línea en blanco

        // 3. ENCABEZADO DE TABLA PRINCIPAL
        fputcsv($output, ['=== DETALLE INDIVIDUAL DE ASISTENCIAS ==='], $delimiter);
        fputcsv($output, [
            'N°',
            'Fecha',
            'DNI',
            'Codigo Reloj',
            'Apellidos y Nombres',
            'Departamento / Area',
            'Turno Asignado',
            'Entrada Programada',
            'Salida Programada',
            'Entrada Real',
            'Salida Real',
            'Minutos Tardanza',
            'Tiempo Tardanza',
            'Tiempo Trabajado',
            'Minutos Trabajados',
            'Minutos Horas Extras',
            'Horas Extras',
            'Estado Asistencia',
            'Observaciones / Sustento'
        ], $delimiter);

        $i = 1;
        foreach ($data as $r) {
            $minTrab = (int)$r['minutos_trabajados'];
            $strTrabajado = sprintf('%02dh %02dm', floor($minTrab / 60), $minTrab % 60);

            $minTard = (int)$r['minutos_tardanza'];
            $strTardanza = sprintf('%02dh %02dm', floor($minTard / 60), $minTard % 60);

            $minExt = (int)$r['minutos_extra'];
            $strExtra = sprintf('%02dh %02dm', floor($minExt / 60), $minExt % 60);

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
                $i++,
                date('d/m/Y', strtotime($r['fecha'])),
                '="' . ($r['dni'] ?? '') . '"', // Formato para preservar ceros a la izquierda en Excel
                '="' . ($r['codigo_reloj'] ?? '') . '"',
                $r['apellidos'] . ' ' . $r['nombres'],
                $r['departamento_nombre'] ?? 'Sin Area',
                $r['turno_nombre'] ?? 'Sin Turno',
                $r['hora_entrada_programada'] ? substr($r['hora_entrada_programada'], 0, 5) : '--:--',
                $r['hora_salida_programada'] ? substr($r['hora_salida_programada'], 0, 5) : '--:--',
                $r['hora_entrada_real'] ? substr($r['hora_entrada_real'], 11, 5) : '--:--',
                $r['hora_salida_real'] ? substr($r['hora_salida_real'], 11, 5) : '--:--',
                $minTard,
                $strTardanza,
                $strTrabajado,
                $minTrab,
                $minExt,
                $strExtra,
                $estLabel,
                $r['observaciones'] ?? ''
            ], $delimiter);
        }

        // Fila de totales
        fputcsv($output, [], $delimiter);
        fputcsv($output, ['TOTALES GENERALES:', '', '', '', '', '', '', '', '', '', '', $sumMinTardanza, $horasTardanzaTotales, $horasTrabajadasTotales, $sumMinTrabajados, $sumMinExtra, $horasExtrasTotales, $totalRegistros . ' registros', ''], $delimiter);

        fclose($output);
        exit;
    }
}
