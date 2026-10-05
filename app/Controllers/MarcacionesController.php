<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Services\AttendanceCalculator;
use App\Services\EventStore;


require_once __DIR__ . '/../Services/EventStore.php';

class MarcacionesController {
    public function index(): void {
        AuthController::checkAuth();

        $userRole = AuthController::role();
        $currentUser = AuthController::user();
        $supervisorDeptoId = (int)($currentUser['departamento_id'] ?? 0);

        $fechaInicio = !empty($_GET['fecha_inicio']) ? trim($_GET['fecha_inicio']) : (!empty($_GET['fecha']) ? trim($_GET['fecha']) : date('Y-m-01'));
        $fechaFin = !empty($_GET['fecha_fin']) ? trim($_GET['fecha_fin']) : (!empty($_GET['fecha']) ? trim($_GET['fecha']) : date('Y-m-d'));
        if ($fechaInicio > $fechaFin) {
            $tmp = $fechaInicio;
            $fechaInicio = $fechaFin;
            $fechaFin = $tmp;
        }

        $departamentoId = !empty($_GET['departamento_id']) ? (int)$_GET['departamento_id'] : null;
        $dispositivoId = !empty($_GET['dispositivo_id']) ? (int)$_GET['dispositivo_id'] : null;
        $tipo = !empty($_GET['tipo']) ? trim($_GET['tipo']) : null;
        $search = !empty($_GET['search']) ? trim($_GET['search']) : null;

        // Auto-vincular marcaciones huérfanas en segundo plano si existen empleados coincidentes
        try {
            Database::execute("
                UPDATE marcaciones m 
                JOIN empleados e ON (m.codigo_reloj = e.codigo_reloj OR TRIM(LEADING '0' FROM m.codigo_reloj) = TRIM(LEADING '0' FROM e.codigo_reloj))
                SET m.id_empleado = e.id 
                WHERE m.id_empleado IS NULL
            ");
        } catch (\Throwable $e) {}

        // Paginación
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(10, min(1000, (int)($_GET['per_page'] ?? 250)));
        $offset = ($page - 1) * $perPage;

        // Construcción de cláusula WHERE con rango indexable (Sargable Query Optimization)
        $dtInicio = $fechaInicio . ' 00:00:00';
        $dtFin = $fechaFin . ' 23:59:59';
        $where = " WHERE m.fecha_hora >= :dt_inicio AND m.fecha_hora <= :dt_fin";
        $params = [
            ':dt_inicio' => $dtInicio,
            ':dt_fin'    => $dtFin
        ];

        if ($userRole === 'SUPERVISOR' && $supervisorDeptoId > 0) {
            $where .= " AND e.departamento_id = :sup_depto";
            $params[':sup_depto'] = $supervisorDeptoId;
        } elseif ($departamentoId) {
            $where .= " AND e.departamento_id = :depto_id";
            $params[':depto_id'] = $departamentoId;
        }

        if ($dispositivoId) {
            $where .= " AND m.id_dispositivo = :disp_id";
            $params[':disp_id'] = $dispositivoId;
        }

        if (!empty($tipo) && strtolower($tipo) !== 'todos') {
            $tipoLower = strtolower($tipo);
            if ($tipoLower === 'refrigerio' || $tipoLower === 'refrigerios') {
                $where .= " AND (LOWER(m.tipo) LIKE '%refrigerio%' OR LOWER(m.tipo) LIKE '%break%')";
            } elseif ($tipoLower === 'refrigerio_salida') {
                $where .= " AND (LOWER(m.tipo) = 'refrigerio_salida' OR (LOWER(m.tipo) LIKE '%refrigerio%' AND TIME(m.fecha_hora) <= '13:30:00'))";
            } elseif ($tipoLower === 'refrigerio_entrada') {
                $where .= " AND (LOWER(m.tipo) = 'refrigerio_entrada' OR (LOWER(m.tipo) LIKE '%refrigerio%' AND TIME(m.fecha_hora) > '13:30:00'))";
            } elseif ($tipoLower === 'comision_servicio' || $tipoLower === 'comision') {
                $where .= " AND (LOWER(m.tipo) = 'comision_servicio' OR UPPER(m.tipo_verificacion) = 'COMISION_SERVICIO')";
            } elseif ($tipoLower === 'vacaciones') {
                $where .= " AND (LOWER(m.tipo) = 'vacaciones' OR UPPER(m.tipo_verificacion) = 'VACACIONES')";
            } elseif ($tipoLower === 'sin_vincular') {
                $where .= " AND m.id_empleado IS NULL";
            } elseif ($tipoLower === 'otros' || $tipoLower === 'desconocido') {
                $where .= " AND (LOWER(m.tipo) NOT IN ('entrada', 'salida', 'comision_servicio', 'vacaciones') AND LOWER(m.tipo) NOT LIKE '%refrigerio%' AND LOWER(m.tipo) NOT LIKE '%break%')";
            } else {
                $where .= " AND LOWER(m.tipo) = :tipo";
                $params[':tipo'] = $tipoLower;
            }
        }

        if ($search) {
            $where .= " AND (e.nombres LIKE :s1 OR e.apellidos LIKE :s2 OR e.dni LIKE :s3 OR m.codigo_reloj LIKE :s4 OR CONCAT(e.apellidos, ' ', e.nombres) LIKE :s5 OR CONCAT(e.nombres, ' ', e.apellidos) LIKE :s6)";
            $params[':s1'] = "%$search%";
            $params[':s2'] = "%$search%";
            $params[':s3'] = "%$search%";
            $params[':s4'] = "%$search%";
            $params[':s5'] = "%$search%";
            $params[':s6'] = "%$search%";
        }

        // 1. Agregación de KPIs directamente en SQL para máxima velocidad
        $kpiSql = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN LOWER(m.tipo) = 'entrada' THEN 1 ELSE 0 END) as entradas,
                SUM(CASE WHEN LOWER(m.tipo) = 'salida' THEN 1 ELSE 0 END) as salidas,
                SUM(CASE WHEN LOWER(m.tipo) LIKE '%refrigerio%' OR LOWER(m.tipo) LIKE '%break%' THEN 1 ELSE 0 END) as refrigerios,
                SUM(CASE WHEN LOWER(m.tipo) = 'refrigerio_salida' OR (LOWER(m.tipo) LIKE '%refrigerio%' AND TIME(m.fecha_hora) <= '13:30:00') THEN 1 ELSE 0 END) as ref_salida,
                SUM(CASE WHEN LOWER(m.tipo) = 'refrigerio_entrada' OR (LOWER(m.tipo) LIKE '%refrigerio%' AND TIME(m.fecha_hora) > '13:30:00') THEN 1 ELSE 0 END) as ref_entrada,
                SUM(CASE WHEN UPPER(m.tipo_verificacion) = 'COMISION_SERVICIO' OR LOWER(m.tipo) = 'comision_servicio' THEN 1 ELSE 0 END) as comisiones,
                SUM(CASE WHEN UPPER(m.tipo_verificacion) = 'VACACIONES' OR LOWER(m.tipo) = 'vacaciones' THEN 1 ELSE 0 END) as vacaciones,
                SUM(CASE WHEN m.id_empleado IS NULL THEN 1 ELSE 0 END) as sin_vincular,
                SUM(CASE WHEN m.procesado = 1 THEN 1 ELSE 0 END) as procesados
            FROM marcaciones m
            LEFT JOIN empleados e ON m.id_empleado = e.id
            $where
        ";
        $kpis = Database::queryOne($kpiSql, $params) ?: [
            'total' => 0, 'entradas' => 0, 'salidas' => 0, 'refrigerios' => 0, 'ref_salida' => 0, 'ref_entrada' => 0,
            'comisiones' => 0, 'vacaciones' => 0, 'sin_vincular' => 0, 'procesados' => 0
        ];
        $totalRecords = (int)($kpis['total'] ?? 0);
        $totalPages = $totalRecords > 0 ? (int)ceil($totalRecords / $perPage) : 1;

        // 2. Exportación (si aplica, procesa el conjunto completo sin paginación)
        if (isset($_GET['export'])) {
            $exportSql = "
                SELECT m.*, 
                       e.nombres, e.apellidos, e.dni,
                       dep.nombre as departamento_nombre,
                       d.nombre as dispositivo_nombre, d.ip as dispositivo_ip
                FROM marcaciones m
                LEFT JOIN empleados e ON m.id_empleado = e.id
                LEFT JOIN departamentos dep ON e.departamento_id = dep.id
                LEFT JOIN dispositivos d ON m.id_dispositivo = d.id
                $where
                ORDER BY m.fecha_hora DESC
            ";
            $exportData = Database::query($exportSql, $params);

            $exportType = strtolower(trim($_GET['export']));
            $dispositivoNombre = null;
            if ($dispositivoId) {
                $dRow = Database::queryOne("SELECT nombre, ip FROM dispositivos WHERE id = ?", [$dispositivoId]);
                if ($dRow) $dispositivoNombre = $dRow['nombre'] . ' (' . $dRow['ip'] . ')';
            }

            session_write_close(); // Liberar bloqueo de sesión durante la descarga de reportes pesados

            if ($exportType === 'excel' || $exportType === 'xls') {
                $this->exportExcel($exportData, $fechaInicio, $fechaFin, $dispositivoNombre, $tipo, $search);
                return;
            }
        }

        // 3. Consulta de registros paginados
        $sql = "
            SELECT m.*, 
                   e.nombres, e.apellidos, e.dni,
                   dep.nombre as departamento_nombre,
                   d.nombre as dispositivo_nombre, d.ip as dispositivo_ip
            FROM marcaciones m
            LEFT JOIN empleados e ON m.id_empleado = e.id
            LEFT JOIN departamentos dep ON e.departamento_id = dep.id
            LEFT JOIN dispositivos d ON m.id_dispositivo = d.id
            $where
            ORDER BY m.fecha_hora DESC
            LIMIT $perPage OFFSET $offset
        ";

        $marcaciones = Database::query($sql, $params);
        $dispositivos = Database::query("SELECT * FROM dispositivos ORDER BY nombre ASC");
        $departamentos = Database::query("SELECT * FROM departamentos ORDER BY nombre ASC");
        $empleados = Database::query("SELECT e.id, e.codigo_reloj, e.dni, e.nombres, e.apellidos, d.nombre as departamento_nombre FROM empleados e LEFT JOIN departamentos d ON e.departamento_id = d.id WHERE e.activo = 1 ORDER BY e.apellidos ASC");

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

        $pctProcesado = $totalRegistros > 0 ? round(($totalProcesados / $totalRegistros) * 100, 1) : 0;
        $generadoEl = date('d/m/Y H:i:s');
        $usuario = AuthController::user()['nombre'] ?? 'Administrador';

        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Calibri, Arial, sans-serif; font-size: 11pt; }
                table { border-collapse: collapse; width: 100%; }
                th, td { border: 1px solid #B0C4DE; padding: 6px 8px; text-align: left; }
                .title-main { font-size: 14pt; font-weight: bold; color: #1E3A8A; text-align: center; }
                .title-sub { font-size: 11pt; color: #475569; text-align: center; font-style: italic; }
                .meta-header { background-color: #F1F5F9; font-weight: bold; color: #334155; }
                .th-col { background-color: #1E3A8A; color: #FFFFFF; font-weight: bold; text-align: center; }
                .text-center { text-align: center; }
                .bg-entrada { background-color: #ECFDF5; color: #065F46; font-weight: bold; }
                .bg-salida { background-color: #EFF6FF; color: #1E40AF; font-weight: bold; }
                .bg-refrigerio { background-color: #FFFBEB; color: #92400E; }
                .bg-otro { background-color: #F8FAFC; color: #475569; }
                .badge-proc { color: #065F46; font-weight: bold; }
                .badge-pend { color: #D97706; font-weight: bold; }
                .summary-table td { border: 1px solid #CBD5E1; padding: 5px 10px; }
            </style>
        </head>
        <body>
            <table>
                <tr>
                    <td colspan="10" class="title-main">JUNTA DE USUARIOS DEL SECTOR HIDRÁULICO MENOR SAN LORENZO (JUSHSAL)</td>
                </tr>
                <tr>
                    <td colspan="10" class="title-sub">SISTEMA DE CONTROL DE PERSONAL Y ASISTENCIA - REPORTE DE MARCACIONES CRUDAS</td>
                </tr>
                <tr><td colspan="10"></td></tr>
                <tr>
                    <td colspan="2" class="meta-header">Período de Consulta:</td>
                    <td colspan="3"><?= date('d/m/Y', strtotime($start)) ?> al <?= date('d/m/Y', strtotime($end)) ?></td>
                    <td colspan="2" class="meta-header">Generado por:</td>
                    <td colspan="3"><?= htmlspecialchars($usuario) ?></td>
                </tr>
                <tr>
                    <td colspan="2" class="meta-header">Terminal / Filtro:</td>
                    <td colspan="3"><?= htmlspecialchars($dispositivoNombre ?: 'Todos los Relojes') ?></td>
                    <td colspan="2" class="meta-header">Fecha de Emisión:</td>
                    <td colspan="3"><?= $generadoEl ?></td>
                </tr>
                <tr><td colspan="10"></td></tr>
            </table>

            <!-- TABLA DE RESUMEN EJECUTIVO -->
            <table class="summary-table" style="width: 70%; margin-bottom: 15px;">
                <tr style="background-color: #E2E8F0; font-weight: bold;">
                    <td colspan="6" class="text-center">RESUMEN CONSOLIDADO DE EVENTOS</td>
                </tr>
                <tr class="text-center">
                    <td style="background-color: #F8FAFC;">Total Marcaciones</td>
                    <td style="background-color: #ECFDF5;">Entradas</td>
                    <td style="background-color: #EFF6FF;">Salidas</td>
                    <td style="background-color: #FFFBEB;">Refrigerios</td>
                    <td style="background-color: #F1F5F9;">Otros</td>
                    <td style="background-color: #ECFDF5;">% Procesado</td>
                </tr>
                <tr class="text-center" style="font-weight: bold; font-size: 12pt;">
                    <td><?= $totalRegistros ?></td>
                    <td style="color: #065F46;"><?= $totalEntradas ?></td>
                    <td style="color: #1E40AF;"><?= $totalSalidas ?></td>
                    <td style="color: #92400E;"><?= $totalRefrigerios ?></td>
                    <td style="color: #475569;"><?= $totalOtros ?></td>
                    <td style="color: #065F46;"><?= $pctProcesado ?>%</td>
                </tr>
            </table>

            <!-- DETALLE DE MARCACIONES -->
            <table>
                <thead>
                    <tr>
                        <th class="th-col" style="width: 40px;">#</th>
                        <th class="th-col" style="width: 90px;">Fecha</th>
                        <th class="th-col" style="width: 80px;">Hora</th>
                        <th class="th-col" style="width: 90px;">Cód. Reloj</th>
                        <th class="th-col" style="width: 90px;">DNI</th>
                        <th class="th-col" style="width: 200px;">Apellidos y Nombres</th>
                        <th class="th-col" style="width: 140px;">Área / Departamento</th>
                        <th class="th-col" style="width: 140px;">Terminal Biométrico</th>
                        <th class="th-col" style="width: 120px;">Tipo Evento</th>
                        <th class="th-col" style="width: 120px;">Método Verif.</th>
                        <th class="th-col" style="width: 90px;">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $i = 1;
                    foreach ($data as $m): 
                        $t = strtolower($m['tipo'] ?? '');
                        $verifRaw = strtoupper($m['tipo_verificacion'] ?? 'HUELLA');
                        $classBg = match(true) {
                            $t === 'entrada' => 'bg-entrada',
                            $t === 'salida' => 'bg-salida',
                            $t === 'refrigerio_salida' => 'bg-refrigerio',
                            $t === 'refrigerio_entrada' => 'bg-entrada',
                            str_contains($t, 'refrigerio') => 'bg-refrigerio',
                            $t === 'comision_servicio' || $verifRaw === 'COMISION_SERVICIO' => 'bg-salida',
                            $t === 'vacaciones' || $verifRaw === 'VACACIONES' => 'bg-entrada',
                            default => 'bg-otro'
                        };
                        $tipoLabel = match(true) {
                            $t === 'entrada' => 'ENTRADA',
                            $t === 'salida' => 'SALIDA',
                            $t === 'refrigerio_salida' => 'SALIDA A REFRIGERIO',
                            $t === 'refrigerio_entrada' => 'RETORNO DE REFRIGERIO',
                            str_contains($t, 'refrigerio') => 'REFRIGERIO',
                            $t === 'comision_servicio' || $verifRaw === 'COMISION_SERVICIO' => 'COMISIÓN DE SERVICIO',
                            $t === 'vacaciones' || $verifRaw === 'VACACIONES' => 'VACACIONES',
                            default => strtoupper($m['tipo'] ?? 'MARCACIÓN')
                        };
                        $fechaPart = substr($m['fecha_hora'], 0, 10);
                        $horaPart = substr($m['fecha_hora'], 11, 8);
                        $verifLabel = match(true) {
                            $verifRaw === 'COMISION_SERVICIO' => 'COMISIÓN OFICIAL',
                            $verifRaw === 'VACACIONES' => 'VACACIONES OFICIAL',
                            $verifRaw === 'ADMIN_OFICIAL' => 'EDICIÓN ADMIN',
                            $verifRaw === 'MANUAL_RRHH' => 'MANUAL RRHH',
                            $verifRaw === 'MANUAL_ADMIN' => 'MANUAL ADMIN',
                            default => $verifRaw
                        };
                    ?>
                    <tr>
                        <td class="text-center"><?= $i++ ?></td>
                        <td class="text-center"><?= date('d/m/Y', strtotime($fechaPart)) ?></td>
                        <td class="text-center" style="font-weight: bold;"><?= $horaPart ?></td>
                        <td class="text-center" style="mso-number-format:'\@';"><?= htmlspecialchars($m['codigo_reloj']) ?></td>
                        <td class="text-center" style="mso-number-format:'\@';"><?= htmlspecialchars($m['dni'] ?? '-') ?></td>
                        <td><?= htmlspecialchars(!empty($m['nombres']) ? $m['apellidos'] . ' ' . $m['nombres'] : 'Sin vincular') ?></td>
                        <td><?= htmlspecialchars($m['departamento_nombre'] ?? 'Sin Área') ?></td>
                        <td><?= htmlspecialchars($m['dispositivo_nombre'] ?? 'Desconocido') ?></td>
                        <td class="text-center <?= $classBg ?>"><?= $tipoLabel ?></td>
                        <td class="text-center"><?= htmlspecialchars($verifLabel) ?></td>
                        <td class="text-center <?= $m['procesado'] ? 'badge-proc' : 'badge-pend' ?>">
                            <?= $m['procesado'] ? 'PROCESADO' : 'PENDIENTE' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </body>
        </html>
        <?php
        exit;
    }

    public function guardarManual(): void {
        AuthController::checkAuth();
        AuthController::requireRole(['ADMIN', 'RRHH'], 'marcaciones');
        \App\Csrf::validateRequest();

        $idEmpleado = (int)($_POST['id_empleado'] ?? 0);
        $fechaHora = trim($_POST['fecha_hora'] ?? '');
        $tipo = $_POST['tipo'] ?? 'entrada';
        $idDispositivo = (int)($_POST['id_dispositivo'] ?? 1);
        $motivo = trim($_POST['motivo'] ?? 'Marcación registrada manualmente');

        if ($idEmpleado <= 0 || empty($fechaHora)) {
            header('Location: ?route=marcaciones&error=campos_requeridos');
            exit;
        }

        try {
            $emp = Database::queryOne("SELECT codigo_reloj FROM empleados WHERE id = ?", [$idEmpleado]);
            $codigoReloj = $emp ? $emp['codigo_reloj'] : (string)$idEmpleado;

            $userRole = AuthController::role();
            $verifTipo = ($userRole === 'ADMIN') ? 'MANUAL_ADMIN' : 'MANUAL_RRHH';

            // Obtener dispositivo y serie
            $disp = Database::queryOne("SELECT nombre, ip, numero_serie FROM dispositivos WHERE id = ?", [$idDispositivo]);
            $deviceSerial = !empty($disp['numero_serie']) ? strtoupper(trim($disp['numero_serie'])) : "DEV_{$idDispositivo}";

            // Normalización idéntica para idempotency_key
            $normUser = ltrim(trim((string)$codigoReloj), '0');
            if ($normUser === '') $normUser = '0';
            $normTime = substr(trim($fechaHora), 0, 19);
            $normPunch = strtolower(trim($tipo));
            $normVerify = strtolower(trim($verifTipo));
            $idempotencyKey = hash('sha256', "{$deviceSerial}|{$normUser}|{$normTime}|{$normPunch}|{$normVerify}");

            // Inserción de marcación manual con clave de idempotencia y origen
            Database::execute("
                INSERT INTO marcaciones 
                (id_empleado, codigo_reloj, id_dispositivo, fecha_hora, tipo, tipo_verificacion, idempotency_key, origen, procesado)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'MANUAL', 0)
                ON DUPLICATE KEY UPDATE 
                    tipo = VALUES(tipo), 
                    tipo_verificacion = VALUES(tipo_verificacion),
                    idempotency_key = VALUES(idempotency_key),
                    origen = 'MANUAL'
            ", [$idEmpleado, $codigoReloj, $idDispositivo, $fechaHora, $tipo, $verifTipo, $idempotencyKey]);

            $currentUser = AuthController::user();
            $usuario = $currentUser['nombre'] ?? ($currentUser['usuario'] ?? 'RRHH');

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
                        'tipo_verificacion'   => $verifTipo,
                        'motivo'              => $motivo,
                        'registrado_por'      => $usuario,
                        'rol'                 => $userRole
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

            header('Location: ?route=marcaciones&fecha=' . substr($fechaHora, 0, 10) . '&msg=guardado');
            exit;
        } catch (\PDOException $e) {
            header('Location: ?route=marcaciones&error=db_error');
            exit;
        }
    }
}
