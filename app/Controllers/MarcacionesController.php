<?php
namespace App\Controllers;

use App\Database;
use App\Services\AttendanceCalculator;
use App\Services\EventStore;

require_once __DIR__ . '/../Services/EventStore.php';

class MarcacionesController {
    public function index(): void {
        AuthController::checkAuth();

        $fechaInicio = $_GET['fecha_inicio'] ?? ($_GET['fecha'] ?? date('Y-m-01'));
        $fechaFin = $_GET['fecha_fin'] ?? ($_GET['fecha'] ?? date('Y-m-d'));
        $dispositivoId = !empty($_GET['dispositivo_id']) ? (int)$_GET['dispositivo_id'] : null;
        $tipo = !empty($_GET['tipo']) ? trim($_GET['tipo']) : null;
        $search = !empty($_GET['search']) ? trim($_GET['search']) : null;

        $sql = "
            SELECT m.*, 
                   e.nombres, e.apellidos, e.dni,
                   d.nombre as dispositivo_nombre, d.ip as dispositivo_ip
            FROM marcaciones m
            LEFT JOIN empleados e ON m.id_empleado = e.id
            LEFT JOIN dispositivos d ON m.id_dispositivo = d.id
            WHERE DATE(m.fecha_hora) BETWEEN :fecha_inicio AND :fecha_fin
        ";
        $params = [
            ':fecha_inicio' => $fechaInicio,
            ':fecha_fin'    => $fechaFin
        ];

        if ($dispositivoId) {
            $sql .= " AND m.id_dispositivo = :disp_id";
            $params[':disp_id'] = $dispositivoId;
        }

        if ($tipo) {
            $sql .= " AND LOWER(m.tipo) = :tipo";
            $params[':tipo'] = strtolower($tipo);
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
            $dispositivoNombre = null;
            if ($dispositivoId) {
                $dRow = Database::queryOne("SELECT nombre, ip FROM dispositivos WHERE id = ?", [$dispositivoId]);
                if ($dRow) $dispositivoNombre = $dRow['nombre'] . ' (' . $dRow['ip'] . ')';
            }

            if ($exportType === 'excel' || $exportType === 'xls') {
                $this->exportExcel($marcaciones, $fechaInicio, $fechaFin, $dispositivoNombre, $tipo, $search);
                return;
            } elseif ($exportType === 'csv') {
                $this->exportCSV($marcaciones, $fechaInicio, $fechaFin, $dispositivoNombre, $tipo);
                return;
            }
        }

        require_once APP_ROOT . '/views/marcaciones/index.php';
    }

    private function exportExcel(array $data, string $start, string $end, ?string $dispositivoNombre = null, ?string $tipo = null, ?string $search = null): void {
        $filename = "Reporte_Marcaciones_{$start}_al_{$end}.xls";
        header("Content-Type: application/vnd.ms-excel; charset=utf-8");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Pragma: no-cache");
        header("Expires: 0");

        $totalRegistros = count($data);
        $totalEntradas = 0;
        $totalSalidas = 0;
        $totalRefrigerios = 0;
        $totalOtros = 0;
        $totalProcesados = 0;

        foreach ($data as $m) {
            $t = strtolower($m['tipo'] ?? '');
            if ($t === 'entrada') $totalEntradas++;
            elseif ($t === 'salida') $totalSalidas++;
            elseif (str_contains($t, 'refrigerio')) $totalRefrigerios++;
            else $totalOtros++;

            if (!empty($m['procesado'])) $totalProcesados++;
        }

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
        echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Marcaciones</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
        echo '<style>
            body { font-family: "Segoe UI", Tahoma, Arial, sans-serif; font-size: 10pt; color: #1e293b; }
            .inst-name { font-size: 14pt; font-weight: bold; color: #0f766e; text-transform: uppercase; }
            .inst-sub { font-size: 9.5pt; color: #475569; font-weight: bold; }
            .report-title { font-size: 13pt; font-weight: bold; color: #0f172a; background-color: #f1f5f9; padding: 6px; text-align: center; border: 1px solid #cbd5e1; }
            
            .meta-table { border-collapse: collapse; margin-bottom: 12px; width: 100%; }
            .meta-table td { border: 1px solid #e2e8f0; padding: 5px 8px; font-size: 9pt; }
            .meta-label { background-color: #f8fafc; font-weight: bold; color: #334155; width: 18%; }
            .meta-val { font-weight: 500; color: #0f172a; }

            .kpi-table { border-collapse: collapse; margin-bottom: 16px; width: 100%; }
            .kpi-table th { background-color: #0f766e; color: #ffffff; font-weight: bold; border: 1px solid #0f766e; padding: 6px 8px; font-size: 8.5pt; text-align: center; }
            .kpi-table td { border: 1px solid #cbd5e1; padding: 6px 8px; font-size: 10pt; font-weight: bold; text-align: center; }

            .report-table { border-collapse: collapse; width: 100%; }
            .report-table th { background-color: #0f766e; color: #ffffff; font-weight: bold; border: 1px solid #115e59; padding: 8px 6px; font-size: 9pt; text-align: center; }
            .report-table td { border: 1px solid #cbd5e1; padding: 5px 6px; font-size: 8.5pt; vertical-align: middle; }
            .report-table tr:nth-child(even) { background-color: #f8fafc; }
            
            .text-center { text-align: center; }
            .text-left { text-align: left; }
            .font-bold { font-weight: bold; }
            
            .badge-entrada { background-color: #dcfce7; color: #166534; font-weight: bold; text-align: center; }
            .badge-salida { background-color: #e0e7ff; color: #3730a3; font-weight: bold; text-align: center; }
            .badge-refrig { background-color: #e0f2fe; color: #075985; font-weight: bold; text-align: center; }
            .badge-otro { background-color: #f1f5f9; color: #475569; font-weight: bold; text-align: center; }

            .tfoot-total { background-color: #e2e8f0; font-weight: bold; border-top: 2px solid #0f766e; }
            .signatures-table { margin-top: 35px; width: 100%; border-collapse: collapse; }
            .signatures-table td { border: none; text-align: center; font-size: 9pt; padding-top: 40px; }
        </style>';
        echo '</head>';
        echo '<body>';

        // 1. ENCABEZADO CON LOGO
        echo '<table style="width:100%; margin-bottom: 12px; border-collapse: collapse;">';
        echo '<tr>';
        if ($hasLogo) {
            echo '<td rowspan="2" style="width: 80px; text-align: center; vertical-align: middle; padding: 6px;">';
            echo '<img src="logo_icon.png" alt="JUSHSAL" height="55" width="55" style="max-height:55px; max-width:55px; width:auto; object-fit:contain;" />';
            echo '</td>';
            echo '<td colspan="8" class="inst-name">JUNTA DE USUARIOS DEL SECTOR HIDRÁULICO MENOR SAN LORENZO (JUSHSAL)</td>';
        } else {
            echo '<td colspan="9" class="inst-name">JUNTA DE USUARIOS DEL SECTOR HIDRÁULICO MENOR SAN LORENZO (JUSHSAL)</td>';
        }
        echo '</tr>';
        echo '<tr>';
        echo '<td colspan="' . ($hasLogo ? '8' : '9') . '" class="inst-sub">SISTEMA INTEGRADO DE CONTROL DE PERSONAL Y ASISTENCIA LABORAL</td>';
        echo '</tr>';
        echo '<tr><td colspan="9" style="height: 6px;"></td></tr>';
        echo '<tr><td colspan="9" class="report-title">REGISTRO OFICIAL DE MARCACIONES DE RELOJES BIOMÉTRICOS</td></tr>';
        echo '</table>';

        // 2. PARÁMETROS DEL REPORTE
        echo '<table class="meta-table">';
        echo '<tr>';
        echo '<td class="meta-label">Período Consultado:</td><td class="meta-val"><b>' . date('d/m/Y', strtotime($start)) . '</b> al <b>' . date('d/m/Y', strtotime($end)) . '</b></td>';
        echo '<td class="meta-label">Reloj Biométrico:</td><td class="meta-val">' . htmlspecialchars($dispositivoNombre ?: 'Todos los Dispositivos') . '</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<td class="meta-label">Filtros Aplicados:</td><td class="meta-val">' . htmlspecialchars($tipo ? 'Tipo: ' . strtoupper($tipo) : 'Todos los Tipos') . ($search ? ' | Búsqueda: "' . htmlspecialchars($search) . '"' : '') . '</td>';
        echo '<td class="meta-label">Emitido Por:</td><td class="meta-val">' . htmlspecialchars($generadoPor) . ' &nbsp;|&nbsp; <b>Fecha:</b> ' . date('d/m/Y H:i:s') . '</td>';
        echo '</tr>';
        echo '</table>';

        // 3. RESUMEN DE MARCACIONES
        echo '<table class="kpi-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>TOTAL MARCACIONES</th>';
        echo '<th>ENTRADAS</th>';
        echo '<th>SALIDAS</th>';
        echo '<th>REFRIGERIOS</th>';
        echo '<th>OTRAS MARCACIONES</th>';
        echo '<th>PROCESADAS EN ASISTENCIA</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        echo '<tr>';
        echo '<td style="background-color:#f8fafc;">' . $totalRegistros . '</td>';
        echo '<td style="color:#166534; background-color:#f0fdf4;">' . $totalEntradas . '</td>';
        echo '<td style="color:#3730a3; background-color:#eef2ff;">' . $totalSalidas . '</td>';
        echo '<td style="color:#075985; background-color:#f0f9ff;">' . $totalRefrigerios . '</td>';
        echo '<td style="color:#475569; background-color:#f8fafc;">' . $totalOtros . '</td>';
        echo '<td style="color:#15803d; background-color:#f0fdf4;">' . $totalProcesados . ' (' . ($totalRegistros > 0 ? round(($totalProcesados / $totalRegistros) * 100, 1) : 0) . '%)</td>';
        echo '</tr>';
        echo '</tbody>';
        echo '</table>';

        // 4. TABLA PRINCIPAL DE DATOS
        echo '<table class="report-table">';
        echo '<thead><tr>';
        echo '<th style="width: 35px;">#</th>';
        echo '<th style="width: 125px;">Fecha y Hora</th>';
        echo '<th style="width: 75px;">ID en Reloj</th>';
        echo '<th style="width: 75px;">DNI</th>';
        echo '<th style="width: 220px;">Empleado Identificado</th>';
        echo '<th style="width: 160px;">Reloj Biométrico</th>';
        echo '<th style="width: 110px;">Tipo de Marcación</th>';
        echo '<th style="width: 140px;">Método de Verificación</th>';
        echo '<th style="width: 90px;">Estado</th>';
        echo '</tr></thead><tbody>';

        $i = 1;
        foreach ($data as $m) {
            $t = strtolower($m['tipo'] ?? '');
            $tipoStr = match($t) {
                'entrada' => 'Entrada',
                'salida' => 'Salida',
                'refrigerio_salida' => 'Salida a Refrigerio',
                'refrigerio_entrada' => 'Regreso de Refrigerio',
                default => 'Marcación'
            };

            $badgeClass = match($t) {
                'entrada' => 'badge-entrada',
                'salida' => 'badge-salida',
                'refrigerio_salida', 'refrigerio_entrada' => 'badge-refrig',
                default => 'badge-otro'
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
            echo '<td class="' . $badgeClass . '">' . htmlspecialchars($tipoStr) . '</td>';
            echo '<td class="text-center">' . htmlspecialchars($verifStr) . '</td>';
            echo '<td class="text-center" style="' . ($m['procesado'] ? 'color:#15803d; font-weight:bold;' : 'color:#b45309;') . '">' . ($m['procesado'] ? 'Procesado' : 'Pendiente') . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '<tfoot>';
        echo '<tr class="tfoot-total">';
        echo '<td colspan="8" class="text-right font-bold" style="padding: 8px;">TOTAL DE MARCACIONES REGISTRADAS:</td>';
        echo '<td class="text-center font-bold">' . $totalRegistros . '</td>';
        echo '</tr>';
        echo '</tfoot>';
        echo '</table>';

        // 5. BLOQUE DE FIRMAS
        echo '<table class="signatures-table">';
        echo '<tr>';
        echo '<td style="width: 50%;">';
        echo '<div style="display:inline-block; border-top: 1.5px solid #334155; padding-top: 6px; width: 280px;">';
        echo '<b>RESPONSABLE DE CONTROL DE ASISTENCIA</b><br>';
        echo '<span style="color:#64748b; font-size:8.5pt;">Recursos Humanos - JUSHSAL</span>';
        echo '</div>';
        echo '</td>';
        echo '<td style="width: 50%;">';
        echo '<div style="display:inline-block; border-top: 1.5px solid #334155; padding-top: 6px; width: 280px;">';
        echo '<b>RESPONSABLE DE TI & SISTEMAS</b><br>';
        echo '<span style="color:#64748b; font-size:8.5pt;">Auditoría de Dispositivos Biométricos</span>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';

        echo '</body></html>';

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

    private function exportCSV(array $data, string $start, string $end, ?string $dispositivoNombre = null, ?string $tipo = null): void {
        $filename = "reporte_marcaciones_{$start}_al_{$end}.csv";
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Pragma: no-cache");
        header("Expires: 0");

        $output = fopen('php://output', 'w');
        // BOM UTF-8 para compatibilidad nativa con Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        $delimiter = ';';

        $totalRegistros = count($data);
        $totalEntradas = 0;
        $totalSalidas = 0;
        $totalRefrigerios = 0;
        $totalOtros = 0;
        $totalProcesados = 0;

        foreach ($data as $m) {
            $t = strtolower($m['tipo'] ?? '');
            if ($t === 'entrada') $totalEntradas++;
            elseif ($t === 'salida') $totalSalidas++;
            elseif (str_contains($t, 'refrigerio')) $totalRefrigerios++;
            else $totalOtros++;

            if (!empty($m['procesado'])) $totalProcesados++;
        }

        $currentUser = AuthController::user();
        $generadoPor = ($currentUser['nombre'] ?? 'Administrador') . ' (' . ($currentUser['rol'] ?? 'RRHH') . ')';

        // 1. MEMBRETE INSTITUCIONAL
        fputcsv($output, ['JUNTA DE USUARIOS DEL SECTOR HIDRAULICO MENOR SAN LORENZO (JUSHSAL)'], $delimiter);
        fputcsv($output, ['SISTEMA INTEGRADO DE CONTROL DE PERSONAL Y ASISTENCIA LABORAL'], $delimiter);
        fputcsv($output, ['REGISTRO OFICIAL DE MARCACIONES DE RELOJES BIOMETRICOS'], $delimiter);
        fputcsv($output, ['Periodo:', date('d/m/Y', strtotime($start)) . ' al ' . date('d/m/Y', strtotime($end)), 'Reloj Biometrico:', $dispositivoNombre ?: 'Todos los Relojes', 'Tipo Marcacion:', $tipo ? strtoupper($tipo) : 'Todos', 'Emitido por:', $generadoPor, 'Fecha Emision:', date('d/m/Y H:i:s')], $delimiter);
        fputcsv($output, [], $delimiter);

        // 2. RESUMEN DE MARCACIONES
        fputcsv($output, ['=== RESUMEN DE MARCACIONES BIOMETRICAS ==='], $delimiter);
        fputcsv($output, ['Total Marcaciones', 'Entradas', 'Salidas', 'Refrigerios', 'Otras', 'Procesadas en Asistencia', '% Procesadas'], $delimiter);
        fputcsv($output, [
            $totalRegistros,
            $totalEntradas,
            $totalSalidas,
            $totalRefrigerios,
            $totalOtros,
            $totalProcesados,
            ($totalRegistros > 0 ? round(($totalProcesados / $totalRegistros) * 100, 1) : 0) . '%'
        ], $delimiter);
        fputcsv($output, [], $delimiter);

        // 3. TABLA PRINCIPAL
        fputcsv($output, ['=== DETALLE DE MARCACIONES ==='], $delimiter);
        fputcsv($output, ['N°', 'Fecha', 'Hora', 'ID en Reloj', 'DNI', 'Apellidos y Nombres', 'Reloj Biometrico', 'Tipo de Marcacion', 'Metodo de Verificacion', 'Estado de Procesamiento'], $delimiter);

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

            $fechaPart = substr($m['fecha_hora'], 0, 10);
            $horaPart = substr($m['fecha_hora'], 11, 8);

            fputcsv($output, [
                $i++,
                date('d/m/Y', strtotime($fechaPart)),
                $horaPart,
                '="' . $m['codigo_reloj'] . '"',
                '="' . ($m['dni'] ?? '') . '"',
                !empty($m['nombres']) ? $m['apellidos'] . ' ' . $m['nombres'] : 'Sin vincular',
                $m['dispositivo_nombre'] ?? 'Desconocido',
                $tipoStr,
                $verifStr,
                $m['procesado'] ? 'Procesado' : 'Pendiente'
            ], $delimiter);
        }

        // Fila de totales
        fputcsv($output, [], $delimiter);
        fputcsv($output, ['TOTAL GENERAL DE MARCACIONES:', $totalRegistros . ' registros'], $delimiter);

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

            // Inserción de marcación manual (Proyección Read Model)
            Database::execute("
                INSERT INTO marcaciones 
                (id_empleado, codigo_reloj, id_dispositivo, fecha_hora, tipo, tipo_verificacion, procesado)
                VALUES (?, ?, ?, ?, ?, 'MANUAL_RRHH', 0)
                ON DUPLICATE KEY UPDATE tipo = VALUES(tipo)
            ", [$idEmpleado, $codigoReloj, $idDispositivo, $fechaHora, $tipo]);

            // Obtener dispositivo
            $disp = Database::queryOne("SELECT nombre, ip FROM dispositivos WHERE id = ?", [$idDispositivo]);
            $currentUser = AuthController::user();
            $usuario = $currentUser['usuario'] ?? 'RRHH';

            // Event Sourcing: Registrar evento inmutable
            try {
                EventStore::recordEvent(
                    'MARCACION',
                    "emp_{$idEmpleado}_" . str_replace([' ', ':'], ['_', '-'], $fechaHora),
                    'MARCACION_MANUAL_REGISTRADA',
                    [
                        'id_empleado'         => $idEmpleado,
                        'codigo_reloj'        => $codigoReloj,
                        'id_dispositivo'      => $idDispositivo,
                        'dispositivo_nombre'  => $disp['nombre'] ?? 'Manual',
                        'dispositivo_ip'      => $disp['ip'] ?? '127.0.0.1',
                        'fecha_hora'          => $fechaHora,
                        'tipo'                => $tipo,
                        'tipo_verificacion'   => 'MANUAL_RRHH',
                        'motivo'              => 'Marcación manual registrada en panel web por ' . $usuario
                    ],
                    $usuario
                );
            } catch (\Exception $e) {
                // Silencioso
            }

            // Recalcular asistencia para esa fecha
            $dateOnly = substr($fechaHora, 0, 10);
            $calculator = new AttendanceCalculator(ATTENDANCE_DEBOUNCE_MINUTES);
            $calculator->processDate($dateOnly);
        }

        header('Location: ?route=marcaciones&fecha=' . substr($fechaHora, 0, 10) . '&msg=guardado');
        exit;
    }
}
