<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Services\AttendanceCalculator;
use App\Services\EventStore;


require_once __DIR__ . '/../Services/EventStore.php';

class AsistenciaController {
    public function index(): void {
        AuthController::checkAuth();

        $userRole = AuthController::role();
        $currentUser = AuthController::user();
        $supervisorDeptoId = (int)($currentUser['departamento_id'] ?? 0);

        $empleadoId = !empty($_GET['empleado_id']) ? (int)$_GET['empleado_id'] : (!empty($_GET['id_empleado']) ? (int)$_GET['id_empleado'] : null);
        $defaultInicio = $empleadoId ? date('Y-m-01') : date('Y-m-d');
        $fechaInicio = !empty($_GET['fecha_inicio']) ? trim($_GET['fecha_inicio']) : $defaultInicio;
        $fechaFin = !empty($_GET['fecha_fin']) ? trim($_GET['fecha_fin']) : date('Y-m-d');
        if ($fechaInicio > $fechaFin) {
            $tmp = $fechaInicio;
            $fechaInicio = $fechaFin;
            $fechaFin = $tmp;
        }

        // Auto-calcular días faltantes o con marcaciones pendientes en asistencia_diaria para el rango solicitado (hasta 31 días)
        try {
            $db = Database::getInstance()->getConnection();
            $dInicio = new \DateTime($fechaInicio);
            $dFin = new \DateTime($fechaFin);
            $diffDias = $dInicio->diff($dFin)->days;

            if ($diffDias <= 31) {
                $stmtCheck = $db->prepare("SELECT DISTINCT fecha FROM asistencia_diaria WHERE fecha BETWEEN :f1 AND :f2");
                $stmtCheck->execute([':f1' => $fechaInicio, ':f2' => $fechaFin]);
                $existingDates = $stmtCheck->fetchAll(\PDO::FETCH_COLUMN);
                $existingSet = array_flip($existingDates);

                $calc = null;
                $period = new \DatePeriod($dInicio, new \DateInterval('P1D'), (clone $dFin)->modify('+1 day'));
                foreach ($period as $dt) {
                    $currDate = $dt->format('Y-m-d');
                    $isDateToday = ($currDate === date('Y-m-d'));

                    // Verificar si existen marcaciones sin procesar para este día
                    $stmtUnproc = $db->prepare("SELECT 1 FROM marcaciones WHERE fecha_hora >= :d1 AND fecha_hora <= :d2 AND procesado = 0 LIMIT 1");
                    $stmtUnproc->execute([':d1' => "$currDate 00:00:00", ':d2' => "$currDate 23:59:59"]);
                    $hasUnprocessed = (bool)$stmtUnproc->fetchColumn();

                    // Recalcular si no existe en asistencia_diaria, si tiene marcaciones pendientes de cálculo, o si es hoy
                    if (!isset($existingSet[$currDate]) || $hasUnprocessed || $isDateToday) {
                        $stmtMarc = $db->prepare("SELECT 1 FROM marcaciones WHERE fecha_hora >= :d1 AND fecha_hora <= :d2 LIMIT 1");
                        $stmtMarc->execute([':d1' => "$currDate 00:00:00", ':d2' => "$currDate 23:59:59"]);
                        if ($stmtMarc->fetchColumn() || $diffDias === 0 || !isset($existingSet[$currDate])) {
                            if (!$calc) {
                                $calc = new AttendanceCalculator(ATTENDANCE_DEBOUNCE_MINUTES);
                            }
                            $calc->processDate($currDate);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log("Error auto-calculating missing attendance: " . $e->getMessage());
        }

        $deptoId = !empty($_GET['departamento_id']) ? (int)$_GET['departamento_id'] : null;
        if ($userRole === 'SUPERVISOR' && $supervisorDeptoId > 0) {
            $deptoId = $supervisorDeptoId;
        }

        // Estado por defecto:
        // Si no se envió filtro de estado:
        // - Si no hay empleado específico seleccionado: mostrar SOLO los que asistieron / marcaron presencia
        // - Si hay empleado seleccionado: mostrar todos sus registros del período (incluyendo descansos y faltas)
        if (isset($_GET['estado'])) {
            $estado = trim($_GET['estado']);
        } else {
            $estado = $empleadoId ? 'TODOS' : 'ASISTIERON';
        }

        $search = !empty($_GET['search']) ? trim($_GET['search']) : null;

        // Paginación
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(10, min(1000, (int)($_GET['per_page'] ?? 200)));
        $offset = ($page - 1) * $perPage;

        // Construir cláusula WHERE dinámica
        $where = " WHERE a.fecha BETWEEN :fecha_inicio AND :fecha_fin";
        $params = [
            ':fecha_inicio' => $fechaInicio,
            ':fecha_fin'    => $fechaFin
        ];

        if ($empleadoId) {
            $where .= " AND a.id_empleado = :emp_id";
            $params[':emp_id'] = $empleadoId;
        }

        if ($deptoId) {
            $where .= " AND e.departamento_id = :depto_id";
            $params[':depto_id'] = $deptoId;
        }

        if (!empty($estado) && strtolower($estado) !== 'todos') {
            $estadoUpper = strtoupper($estado);
            if ($estadoUpper === 'ASISTIERON') {
                $where .= " AND (a.hora_entrada_real IS NOT NULL OR a.hora_salida_real IS NOT NULL OR a.estado IN ('PRESENTE', 'TARDANZA', 'SALIDA_SIN_MARCAR', 'ENTRADA_SIN_MARCAR', 'COMISION_SERVICIO'))";
            } elseif ($estadoUpper === 'EN_JORNADA') {
                $where .= " AND a.hora_entrada_real IS NOT NULL AND a.hora_salida_real IS NULL AND (a.observaciones LIKE '%Jornada en curso%' OR a.estado IN ('PRESENTE', 'TARDANZA'))";
            } elseif ($estadoUpper === 'PRESENTE') {
                $where .= " AND a.estado = 'PRESENTE' AND a.hora_entrada_real IS NOT NULL";
            } elseif ($estadoUpper === 'PENDIENTE') {
                $where .= " AND (a.estado = 'PENDIENTE' OR a.hora_entrada_real IS NULL) AND a.estado NOT IN ('FALTA', 'FALTA_INJUSTIFICADA', 'JUSTIFICADO', 'PERMISO', 'VACACIONES', 'DESCANSO', 'COMISION_SERVICIO')";
            } elseif ($estadoUpper === 'FALTA') {
                $where .= " AND (a.estado = 'FALTA' OR a.estado = 'FALTA_INJUSTIFICADA')";
            } elseif ($estadoUpper === 'JUSTIFICADO') {
                $where .= " AND (a.estado = 'JUSTIFICADO' OR a.estado = 'PERMISO' OR a.estado = 'VACACIONES' OR a.estado = 'LICENCIA' OR a.estado = 'COMISION_SERVICIO')";
            } elseif ($estadoUpper === 'COMISION_SERVICIO') {
                $where .= " AND a.estado = 'COMISION_SERVICIO'";
            } elseif ($estadoUpper === 'VACACIONES') {
                $where .= " AND a.estado = 'VACACIONES'";
            } elseif ($estadoUpper === 'SALIDA_SIN_MARCAR') {
                $where .= " AND a.estado = 'SALIDA_SIN_MARCAR'";
            } elseif ($estadoUpper === 'ENTRADA_SIN_MARCAR') {
                $where .= " AND a.estado = 'ENTRADA_SIN_MARCAR'";
            } elseif ($estadoUpper === 'INCIDENCIAS') {
                $where .= " AND (a.estado = 'TARDANZA' OR a.estado = 'FALTA' OR a.estado = 'FALTA_INJUSTIFICADA' OR a.estado = 'SALIDA_SIN_MARCAR' OR a.estado = 'ENTRADA_SIN_MARCAR')";
            } else {
                $where .= " AND a.estado = :estado";
                $params[':estado'] = $estadoUpper;
            }
        }

        if ($search) {
            $where .= " AND (e.nombres LIKE :search1 OR e.apellidos LIKE :search2 OR e.dni LIKE :search3 OR e.codigo_reloj LIKE :search4 OR CONCAT(e.apellidos, ' ', e.nombres) LIKE :search5 OR CONCAT(e.nombres, ' ', e.apellidos) LIKE :search6)";
            $params[':search1'] = "%$search%";
            $params[':search2'] = "%$search%";
            $params[':search3'] = "%$search%";
            $params[':search4'] = "%$search%";
            $params[':search5'] = "%$search%";
            $params[':search6'] = "%$search%";
        }

        // Obtener datos del empleado seleccionado (si aplica reporte individual)
        $empleadoSeleccionado = null;
        if ($empleadoId) {
            $empleadoSeleccionado = Database::queryOne("
                SELECT e.*, d.nombre as departamento_nombre, c.nombre as cargo_nombre, t.nombre as turno_nombre
                FROM empleados e
                LEFT JOIN departamentos d ON e.departamento_id = d.id
                LEFT JOIN cargos c ON e.cargo_id = c.id
                LEFT JOIN turnos t ON e.turno_id = t.id
                WHERE e.id = ?
            ", [$empleadoId]);
        }

        // 1. Agregación de KPIs directamente en MySQL para máxima velocidad
        $kpiSql = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN a.estado = 'PRESENTE' AND a.hora_entrada_real IS NOT NULL THEN 1 ELSE 0 END) as presentes,
                SUM(CASE WHEN a.estado = 'TARDANZA' THEN 1 ELSE 0 END) as tardanzas,
                SUM(CASE WHEN a.estado = 'FALTA' OR a.estado = 'FALTA_INJUSTIFICADA' THEN 1 ELSE 0 END) as faltas,
                SUM(CASE WHEN a.estado IN ('JUSTIFICADO', 'PERMISO', 'VACACIONES', 'LICENCIA', 'COMISION_SERVICIO') THEN 1 ELSE 0 END) as justificados,
                SUM(CASE WHEN a.estado = 'SALIDA_SIN_MARCAR' OR a.estado = 'ENTRADA_SIN_MARCAR' THEN 1 ELSE 0 END) as sin_salida,
                SUM(a.minutos_tardanza) as total_minutos_tardanza,
                SUM(a.minutos_trabajados) as total_minutos_trabajados,
                SUM(a.minutos_extra) as total_minutos_extra
            FROM asistencia_diaria a
            JOIN empleados e ON a.id_empleado = e.id
            LEFT JOIN departamentos d ON e.departamento_id = d.id
            LEFT JOIN turnos t ON a.id_turno = t.id
            $where
        ";
        $kpis = Database::queryOne($kpiSql, $params) ?: [
            'total' => 0, 'presentes' => 0, 'tardanzas' => 0, 'faltas' => 0,
            'justificados' => 0, 'sin_salida' => 0,
            'total_minutos_tardanza' => 0, 'total_minutos_trabajados' => 0, 'total_minutos_extra' => 0
        ];

        $totalRecords = (int)($kpis['total'] ?? 0);
        $totalPages = $totalRecords > 0 ? (int)ceil($totalRecords / $perPage) : 1;

        // 2. Exportación (si aplica, procesa el conjunto completo sin paginación)
        if (isset($_GET['export'])) {
            $exportSql = "
                SELECT a.*, 
                       e.nombres, e.apellidos, e.dni, e.codigo_reloj,
                       d.nombre as departamento_nombre,
                       c.nombre as cargo_nombre,
                       t.nombre as turno_nombre
                FROM asistencia_diaria a
                JOIN empleados e ON a.id_empleado = e.id
                LEFT JOIN departamentos d ON e.departamento_id = d.id
                LEFT JOIN cargos c ON e.cargo_id = c.id
                LEFT JOIN turnos t ON a.id_turno = t.id
                $where
                ORDER BY a.fecha DESC, e.apellidos ASC, e.nombres ASC
            ";
            $exportData = Database::query($exportSql, $params);

            $exportType = strtolower(trim($_GET['export']));
            $deptoNombre = null;
            if ($deptoId) {
                $dRow = Database::queryOne("SELECT nombre FROM departamentos WHERE id = ?", [$deptoId]);
                if ($dRow) $deptoNombre = $dRow['nombre'];
            }

            session_write_close(); // Liberar bloqueo de sesión durante la descarga de reportes pesados

            if ($exportType === 'excel' || $exportType === 'xls') {
                $this->exportExcel($exportData, $fechaInicio, $fechaFin, $deptoNombre, $estado, $search, $empleadoSeleccionado);
                return;
            }
        }

        // 3. Consulta de registros paginados
        $sql = "
            SELECT a.*, 
                   e.nombres, e.apellidos, e.dni, e.codigo_reloj,
                   d.nombre as departamento_nombre,
                   t.nombre as turno_nombre
            FROM asistencia_diaria a
            JOIN empleados e ON a.id_empleado = e.id
            LEFT JOIN departamentos d ON e.departamento_id = d.id
            LEFT JOIN turnos t ON a.id_turno = t.id
            $where
            ORDER BY a.fecha DESC, e.apellidos ASC, e.nombres ASC
            LIMIT $perPage OFFSET $offset
        ";

        $asistencias = Database::query($sql, $params);
        if ($userRole === 'SUPERVISOR' && $supervisorDeptoId > 0) {
            $departamentos = Database::query("SELECT * FROM departamentos WHERE id = ?", [$supervisorDeptoId]);
        } else {
            $departamentos = Database::query("SELECT * FROM departamentos WHERE activo = 1 ORDER BY nombre ASC");
        }
        $empleados = Database::query("
            SELECT e.id, e.codigo_reloj, e.dni, e.nombres, e.apellidos, e.departamento_id,
                   d.nombre as departamento_nombre,
                   t.nombre as turno_nombre, t.hora_entrada, t.hora_salida, t.tolerancia_minutos
            FROM empleados e
            LEFT JOIN departamentos d ON e.departamento_id = d.id
            LEFT JOIN turnos t ON e.turno_id = t.id
            WHERE e.activo = 1
            ORDER BY e.apellidos ASC, e.nombres ASC
        ");

        require_once APP_ROOT . '/views/asistencia/index.php';
    }

    public function recalcular(): void {
        AuthController::checkAuth();
        if (!AuthController::hasPermission('asistencia') && !in_array(AuthController::role(), ['ADMIN', 'RRHH', 'SUPERVISOR', 'ASISTENTE'], true)) {
            header("Location: ?route=asistencia&msg=acceso_denegado");
            exit;
        }
        \App\Security\Csrf::validate();

        $fechaInicio = $_POST['fecha_inicio'] ?? date('Y-m-d');
        $fechaFin = $_POST['fecha_fin'] ?? $fechaInicio;

        try {
            $inicio = new \DateTime($fechaInicio);
            $fin = new \DateTime($fechaFin);
        } catch (\Exception $e) {
            header("Location: ?route=asistencia&msg=rango_invalido");
            exit;
        }

        if ($inicio > $fin) {
            header("Location: ?route=asistencia&msg=rango_invalido");
            exit;
        }

        $diffDias = $inicio->diff($fin)->days;
        $maxDiasPermitidos = 62; // ~2 meses por solicitud manual desde la UI
        if ($diffDias > $maxDiasPermitidos) {
            header("Location: ?route=asistencia&msg=rango_muy_amplio");
            exit;
        }

        $calculator = new AttendanceCalculator(ATTENDANCE_DEBOUNCE_MINUTES);
        $results = $calculator->processDateRange($fechaInicio, $fechaFin);

        header("Location: ?route=asistencia&fecha_inicio=$fechaInicio&fecha_fin=$fechaFin&msg=recalculado");
        exit;
    }

    /**
     * Modificación Administrativa de Horarios y Asistencia (Exclusivo Administrador)
     * Permite corregir horas de entrada y salida, recalculando métricas y guardando el valor oficial.
     */
    public function editar(): void {
        AuthController::checkAuth();
        AuthController::requireRole('ADMIN', 'asistencia');
        \App\Csrf::validateRequest();

        $id = (int)($_POST['id'] ?? 0);
        $idEmpleado = (int)($_POST['id_empleado'] ?? 0);
        $fechaInput = trim($_POST['fecha'] ?? '');
        $horaEntradaInput = trim($_POST['hora_entrada_real'] ?? '');
        $horaSalidaInput = trim($_POST['hora_salida_real'] ?? '');
        $estado = trim($_POST['estado'] ?? 'PRESENTE');
        $observaciones = trim($_POST['observaciones'] ?? '');
        $minutosTardanzaCustom = (isset($_POST['minutos_tardanza']) && $_POST['minutos_tardanza'] !== '') ? (int)$_POST['minutos_tardanza'] : null;
        $minutosExtraCustom = (isset($_POST['minutos_extra']) && $_POST['minutos_extra'] !== '') ? (int)$_POST['minutos_extra'] : null;

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
                  || isset($_GET['ajax']) 
                  || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        // Si no se proporcionó id directo pero se tiene empleado y fecha, resolver o crear registro en asistencia_diaria
        if ($id <= 0 && $idEmpleado > 0 && !empty($fechaInput)) {
            $existing = Database::queryOne("SELECT id FROM asistencia_diaria WHERE id_empleado = ? AND fecha = ?", [$idEmpleado, $fechaInput]);
            if ($existing) {
                $id = (int)$existing['id'];
            } else {
                $empInfo = Database::queryOne("
                    SELECT e.id, e.turno_id, t.hora_entrada, t.hora_salida 
                    FROM empleados e 
                    LEFT JOIN turnos t ON e.turno_id = t.id 
                    WHERE e.id = ?
                ", [$idEmpleado]);
                if ($empInfo) {
                    Database::execute("
                        INSERT INTO asistencia_diaria (id_empleado, id_turno, fecha, hora_entrada_programada, hora_salida_programada, estado, manual, procesado_en)
                        VALUES (?, ?, ?, ?, ?, 'PRESENTE', 1, NOW())
                    ", [
                        $empInfo['id'],
                        $empInfo['turno_id'],
                        $fechaInput,
                        $empInfo['hora_entrada'] ?? '08:00:00',
                        $empInfo['hora_salida'] ?? '17:00:00'
                    ]);
                    $id = (int)Database::lastInsertId();
                }
            }
        }

        if ($id <= 0) {
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => 'Registro de asistencia o empleado no especificado.']);
                exit;
            }
            header('Location: ?route=asistencia&error=id_invalido');
            exit;
        }

        try {
            $actual = Database::queryOne("
                SELECT a.*, e.nombres, e.apellidos, e.dni, e.codigo_reloj,
                       t.hora_entrada as turno_hora_entrada, t.hora_salida as turno_hora_salida,
                       t.tolerancia_minutos, t.tolerancia_falta_minutos, t.minutos_refrigerio, t.es_nocturno
                FROM asistencia_diaria a
                JOIN empleados e ON a.id_empleado = e.id
                LEFT JOIN turnos t ON a.id_turno = t.id
                WHERE a.id = ?
            ", [$id]);

            if (!$actual) {
                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'error' => 'Registro de asistencia no encontrado.']);
                    exit;
                }
                header('Location: ?route=asistencia&error=no_encontrado');
                exit;
            }

            $fecha = $actual['fecha'];

            // Formatear Timestamp de Entrada Real Oficial
            $horaEntradaReal = null;
            if (!empty($horaEntradaInput)) {
                $timePart = strlen($horaEntradaInput) === 5 ? "$horaEntradaInput:00" : $horaEntradaInput;
                $horaEntradaReal = "$fecha $timePart";
            }

            // Formatear Timestamp de Salida Real Oficial
            $horaSalidaReal = null;
            if (!empty($horaSalidaInput)) {
                $timePart = strlen($horaSalidaInput) === 5 ? "$horaSalidaInput:00" : $horaSalidaInput;
                $horaSalidaReal = "$fecha $timePart";
            }

            // Formatear Timestamps de Refrigerio Real Oficial
            $horaInicioRefInput = trim($_POST['hora_inicio_refrigerio_real'] ?? '');
            $horaFinRefInput = trim($_POST['hora_fin_refrigerio_real'] ?? '');

            $horaInicioRefReal = null;
            if (!empty($horaInicioRefInput)) {
                $timePart = strlen($horaInicioRefInput) === 5 ? "$horaInicioRefInput:00" : $horaInicioRefInput;
                $horaInicioRefReal = "$fecha $timePart";
            } elseif (isset($_POST['hora_inicio_refrigerio_real']) && $horaInicioRefInput === '') {
                $horaInicioRefReal = null;
            } else {
                $horaInicioRefReal = $actual['hora_inicio_refrigerio_real'] ?? null;
            }

            $horaFinRefReal = null;
            if (!empty($horaFinRefInput)) {
                $timePart = strlen($horaFinRefInput) === 5 ? "$horaFinRefInput:00" : $horaFinRefInput;
                $horaFinRefReal = "$fecha $timePart";
            } elseif (isset($_POST['hora_fin_refrigerio_real']) && $horaFinRefInput === '') {
                $horaFinRefReal = null;
            } else {
                $horaFinRefReal = $actual['hora_fin_refrigerio_real'] ?? null;
            }

            // Recálculo Inteligente de Métricas si no fueron forzadas
            $tolerancia = (int)($actual['tolerancia_minutos'] ?? 10);
            $toleranciaFalta = (int)($actual['tolerancia_falta_minutos'] ?? 60);
            $minutosTardanza = $minutosTardanzaCustom !== null ? $minutosTardanzaCustom : 0;
            $minutosExtra = $minutosExtraCustom !== null ? $minutosExtraCustom : 0;
            $minutosTrabajados = 0;
            $minutosSalidaTemprana = 0;

            if ($horaEntradaReal && !empty($actual['hora_entrada_programada'])) {
                $dtEntryReal = new \DateTime($horaEntradaReal);
                $dtEntryProg = new \DateTime("$fecha {$actual['hora_entrada_programada']}");
                $dtEntryGrace = (clone $dtEntryProg)->modify("+{$tolerancia} minutes");

                if ($minutosTardanzaCustom === null || $minutosTardanzaCustom < 0) {
                    if ($dtEntryReal <= $dtEntryGrace) {
                        $minutosTardanza = 0;
                        if ($estado === 'TARDANZA') $estado = 'PRESENTE';
                    } else {
                        $diff = $dtEntryReal->getTimestamp() - $dtEntryProg->getTimestamp();
                        $minutosTardanza = (int)floor($diff / 60);
                        if ($estado === 'PRESENTE') $estado = 'TARDANZA';
                    }
                }
            }

            if ($horaEntradaReal && $horaSalidaReal) {
                $dtEntryReal = new \DateTime($horaEntradaReal);
                $dtExitReal = new \DateTime($horaSalidaReal);
                $dtProgEntry = !empty($actual['hora_entrada_programada']) ? new \DateTime("$fecha {$actual['hora_entrada_programada']}") : new \DateTime("$fecha 08:00:00");
                $dtEffectiveEntry = ($dtEntryReal < $dtProgEntry) ? $dtProgEntry : $dtEntryReal;
                $diffSec = $dtExitReal->getTimestamp() - $dtEffectiveEntry->getTimestamp();

                if ($diffSec > 0) {
                    $minBrutos = (int)floor($diffSec / 60);
                    $dayOfWeek = (int)(new \DateTime($fecha))->format('N');

                    if ($dayOfWeek === 6) {
                        // Sábado sin refrigerio: Horas trabajadas = Permanencia bruta
                        $minutosTrabajados = $minBrutos;
                    } else {
                        // Lunes a Viernes: HORAS TRABAJADAS CON REGLAS DE REFRIGERIO
                        // Regla 1: Si solo marcó salida de refrigerio y NO retorno -> Descuento de 1 hora (60 min)
                        // Regla 2: En cualquier otro caso -> sí o sí descuento automático obligatorio de 45 min (o tiempo tomado si superó 45 min)
                        if ($horaInicioRefReal && !$horaFinRefReal) {
                            $minRefDeducir = 60; // 1 hora de descuento por omisión de retorno
                        } elseif ($horaInicioRefReal && $horaFinRefReal) {
                            $dtRefSal = new \DateTime($horaInicioRefReal);
                            $dtRefEnt = new \DateTime($horaFinRefReal);
                            $minRefTomados = 0;
                            if ($dtRefEnt > $dtRefSal) {
                                $minRefTomados = (int)floor(($dtRefEnt->getTimestamp() - $dtRefSal->getTimestamp()) / 60);
                            }
                            $minRefDeducir = max(45, $minRefTomados);
                        } else {
                            $minRefDeducir = 45; // sí o sí 45 min automático obligatorio
                        }

                        $minutosTrabajados = max(0, $minBrutos - $minRefDeducir);
                    }
                }

                if (!empty($actual['hora_salida_programada'])) {
                    $dtExitProg = new \DateTime("$fecha {$actual['hora_salida_programada']}");
                    if ($minutosExtraCustom === null || $minutosExtraCustom < 0) {
                        if ($dtExitReal > $dtExitProg) {
                            $diffExtraSec = $dtExitReal->getTimestamp() - $dtExitProg->getTimestamp();
                            $extraCalc = (int)floor($diffExtraSec / 60);
                            $minutosExtra = ($extraCalc >= 15) ? $extraCalc : 0;
                        } else {
                            $minutosExtra = 0;
                        }
                    }
                    if ($dtExitReal < $dtExitProg) {
                        $diffEarlySec = $dtExitProg->getTimestamp() - $dtExitReal->getTimestamp();
                        $minutosSalidaTemprana = (int)floor($diffEarlySec / 60);
                    }
                }
            } else {
                // Sin marcación completa de Entrada y Salida General
                $minutosTrabajados = 0;
                $minutosExtra = 0;
                $minutosSalidaTemprana = 0;

                $isEnJornada = ($estado === 'EN_JORNADA') 
                    || ($horaEntradaReal && !$horaSalidaReal && ($fecha === date('Y-m-d') || in_array($estado, ['PRESENTE', 'EN_JORNADA', 'TARDANZA'])));

                if ($horaEntradaReal && !$horaSalidaReal && $isEnJornada) {
                    // JORNADA EN CURSO: solo se ingresó / corrigió el ingreso matutino
                    // El trabajador se mantiene en jornada oficial para capturar refrigerio y salida en el biométrico
                    $estado = ($minutosTardanza > 0) ? 'TARDANZA' : 'PRESENTE';

                    $hEntFmt = substr($horaEntradaReal, 11, 5);
                    $dayOfWeek = (int)(new \DateTime($fecha))->format('N');
                    $isSat = ($dayOfWeek === 6);
                    $hSalProg = $isSat ? '13:00' : (!empty($actual['hora_salida_programada']) ? substr($actual['hora_salida_programada'], 0, 5) : '17:00');
                    $obsJornada = "Jornada en curso (ingreso a las {$hEntFmt}) - Horas trabajadas se computarán al marcar salida general ({$hSalProg})";

                    if (!empty($observaciones)) {
                        if (!str_contains($observaciones, 'Jornada en curso')) {
                            $observaciones = $obsJornada . " | " . $observaciones;
                        }
                    } else {
                        $observaciones = $obsJornada;
                    }
                } elseif ($horaEntradaReal && !$horaSalidaReal && in_array($estado, ['PRESENTE', 'TARDANZA', 'EN_JORNADA'])) {
                    // Fechas concluidas pasadas donde nunca se registró salida
                    $estado = 'SALIDA_SIN_MARCAR';
                } elseif (!$horaEntradaReal && $horaSalidaReal && $estado === 'PRESENTE') {
                    $estado = 'ENTRADA_SIN_MARCAR';
                }
            }

            $currentUser = AuthController::user();
            $usuario = $currentUser['nombre'] ?? ($currentUser['usuario'] ?? 'Administrador');

            Database::transaction(function() use (
                $id, $actual, $horaEntradaReal, $horaSalidaReal, $horaInicioRefReal, $horaFinRefReal, $estado, $observaciones,
                $minutosTardanza, $minutosTrabajados, $minutosExtra, $minutosSalidaTemprana, $usuario
            ) {
                Database::execute("
                    UPDATE asistencia_diaria 
                    SET hora_entrada_real = :ent_real,
                        hora_salida_real = :sal_real,
                        hora_inicio_refrigerio_real = :ref_sal,
                        hora_fin_refrigerio_real = :ref_ent,
                        estado = :estado, 
                        observaciones = :obs,
                        minutos_tardanza = :tardanza,
                        minutos_trabajados = :trabajados,
                        minutos_extra = :extra,
                        minutos_salida_temprana = :temprana,
                        manual = 1,
                        procesado_en = NOW()
                    WHERE id = :id
                ", [
                    ':ent_real'   => $horaEntradaReal,
                    ':sal_real'   => $horaSalidaReal,
                    ':ref_sal'    => $horaInicioRefReal,
                    ':ref_ent'    => $horaFinRefReal,
                    ':estado'     => $estado,
                    ':obs'        => !empty($observaciones) ? $observaciones : null,
                    ':tardanza'   => $minutosTardanza,
                    ':trabajados' => $minutosTrabajados,
                    ':extra'      => $minutosExtra,
                    ':temprana'   => $minutosSalidaTemprana,
                    ':id'         => $id
                ]);

                // Sincronización oficial bidireccional automática con la tabla marcaciones
                $empId = (int)$actual['id_empleado'];
                $codReloj = (string)($actual['codigo_reloj'] ?? $empId);
                $fecha = $actual['fecha'];

                // 1. Sincronizar Entrada Oficial en marcaciones
                $mEntrada = null;
                if (!empty($horaEntradaReal)) {
                    $mEntrada = Database::queryOne("
                        SELECT id FROM marcaciones 
                        WHERE (id_empleado = :emp_id OR codigo_reloj = :cod_reloj)
                          AND tipo = 'entrada'
                          AND DATE(fecha_hora) = :fecha
                        ORDER BY id ASC LIMIT 1
                    ", [':emp_id' => $empId, ':cod_reloj' => $codReloj, ':fecha' => $fecha]);

                    if (!$mEntrada) {
                        $mEntrada = Database::queryOne("
                            SELECT id FROM marcaciones 
                            WHERE (id_empleado = :emp_id OR codigo_reloj = :cod_reloj)
                              AND DATE(fecha_hora) = :fecha
                            ORDER BY fecha_hora ASC LIMIT 1
                        ", [':emp_id' => $empId, ':cod_reloj' => $codReloj, ':fecha' => $fecha]);
                    }

                    if ($mEntrada) {
                        Database::execute("
                            UPDATE marcaciones 
                            SET fecha_hora = :fhora, tipo = 'entrada', procesado = 1 
                            WHERE id = :mid
                        ", [':fhora' => $horaEntradaReal, ':mid' => $mEntrada['id']]);
                    } else {
                        Database::execute("
                            INSERT INTO marcaciones 
                            (id_empleado, codigo_reloj, id_dispositivo, fecha_hora, tipo, tipo_verificacion, procesado, creado_en)
                            VALUES (?, ?, 1, ?, 'entrada', 'ADMIN_OFICIAL', 1, NOW())
                        ", [$empId, $codReloj, $horaEntradaReal]);
                    }
                }

                // 2. Sincronizar Salida Oficial en marcaciones
                if (!empty($horaSalidaReal)) {
                    $mSalida = Database::queryOne("
                        SELECT id FROM marcaciones 
                        WHERE (id_empleado = :emp_id OR codigo_reloj = :cod_reloj)
                          AND tipo = 'salida'
                          AND (DATE(fecha_hora) = :fecha OR DATE(fecha_hora) = DATE_ADD(:fecha2, INTERVAL 1 DAY))
                        ORDER BY id DESC LIMIT 1
                    ", [':emp_id' => $empId, ':cod_reloj' => $codReloj, ':fecha' => $fecha, ':fecha2' => $fecha]);

                    if (!$mSalida) {
                        $excludeEntId = !empty($mEntrada['id']) ? "AND id != " . (int)$mEntrada['id'] : "";
                        $mSalida = Database::queryOne("
                            SELECT id FROM marcaciones 
                            WHERE (id_empleado = :emp_id OR codigo_reloj = :cod_reloj)
                              AND (DATE(fecha_hora) = :fecha OR DATE(fecha_hora) = DATE_ADD(:fecha2, INTERVAL 1 DAY))
                              $excludeEntId
                            ORDER BY fecha_hora DESC LIMIT 1
                        ", [':emp_id' => $empId, ':cod_reloj' => $codReloj, ':fecha' => $fecha, ':fecha2' => $fecha]);
                    }

                    if ($mSalida) {
                        Database::execute("
                            UPDATE marcaciones 
                            SET fecha_hora = :fhora, tipo = 'salida', procesado = 1 
                            WHERE id = :mid
                        ", [':fhora' => $horaSalidaReal, ':mid' => $mSalida['id']]);
                    } else {
                        Database::execute("
                            INSERT INTO marcaciones 
                            (id_empleado, codigo_reloj, id_dispositivo, fecha_hora, tipo, tipo_verificacion, procesado, creado_en)
                            VALUES (?, ?, 1, ?, 'salida', 'ADMIN_OFICIAL', 1, NOW())
                        ", [$empId, $codReloj, $horaSalidaReal]);
                    }
                } else {
                    // Si la salida se dejó vacía (por ej. En Jornada o Salida Sin Marcar):
                    // Eliminar cualquier marcación artificial ADMIN_OFICIAL creada previamente para no reactivarla en recálculos
                    Database::execute("
                        DELETE FROM marcaciones 
                        WHERE (id_empleado = :emp_id OR codigo_reloj = :cod_reloj)
                          AND tipo = 'salida'
                          AND tipo_verificacion = 'ADMIN_OFICIAL'
                          AND DATE(fecha_hora) = :fecha
                    ", [':emp_id' => $empId, ':cod_reloj' => $codReloj, ':fecha' => $fecha]);
                }

                // 3. Sincronizar Salida a Refrigerio en marcaciones
                if (!empty($horaInicioRefReal)) {
                    $mRefSal = Database::queryOne("
                        SELECT id FROM marcaciones 
                        WHERE (id_empleado = :emp_id OR codigo_reloj = :cod_reloj)
                          AND (tipo = 'refrigerio_salida' OR (tipo LIKE '%refrigerio%' AND TIME(fecha_hora) <= '13:30:00'))
                          AND DATE(fecha_hora) = :fecha
                        ORDER BY id ASC LIMIT 1
                    ", [':emp_id' => $empId, ':cod_reloj' => $codReloj, ':fecha' => $fecha]);

                    if ($mRefSal) {
                        Database::execute("
                            UPDATE marcaciones 
                            SET fecha_hora = :fhora, tipo = 'refrigerio_salida', procesado = 1 
                            WHERE id = :mid
                        ", [':fhora' => $horaInicioRefReal, ':mid' => $mRefSal['id']]);
                    } else {
                        Database::execute("
                            INSERT INTO marcaciones 
                            (id_empleado, codigo_reloj, id_dispositivo, fecha_hora, tipo, tipo_verificacion, procesado, creado_en)
                            VALUES (?, ?, 1, ?, 'refrigerio_salida', 'ADMIN_OFICIAL', 1, NOW())
                        ", [$empId, $codReloj, $horaInicioRefReal]);
                    }
                } else {
                    Database::execute("
                        DELETE FROM marcaciones 
                        WHERE (id_empleado = :emp_id OR codigo_reloj = :cod_reloj)
                          AND tipo = 'refrigerio_salida'
                          AND tipo_verificacion = 'ADMIN_OFICIAL'
                          AND DATE(fecha_hora) = :fecha
                    ", [':emp_id' => $empId, ':cod_reloj' => $codReloj, ':fecha' => $fecha]);
                }

                // 4. Sincronizar Retorno de Refrigerio en marcaciones
                if (!empty($horaFinRefReal)) {
                    $mRefEnt = Database::queryOne("
                        SELECT id FROM marcaciones 
                        WHERE (id_empleado = :emp_id OR codigo_reloj = :cod_reloj)
                          AND (tipo = 'refrigerio_entrada' OR (tipo LIKE '%refrigerio%' AND TIME(fecha_hora) > '13:30:00'))
                          AND DATE(fecha_hora) = :fecha
                        ORDER BY id DESC LIMIT 1
                    ", [':emp_id' => $empId, ':cod_reloj' => $codReloj, ':fecha' => $fecha]);

                    if ($mRefEnt) {
                        Database::execute("
                            UPDATE marcaciones 
                            SET fecha_hora = :fhora, tipo = 'refrigerio_entrada', procesado = 1 
                            WHERE id = :mid
                        ", [':fhora' => $horaFinRefReal, ':mid' => $mRefEnt['id']]);
                    } else {
                        Database::execute("
                            INSERT INTO marcaciones 
                            (id_empleado, codigo_reloj, id_dispositivo, fecha_hora, tipo, tipo_verificacion, procesado, creado_en)
                            VALUES (?, ?, 1, ?, 'refrigerio_entrada', 'ADMIN_OFICIAL', 1, NOW())
                        ", [$empId, $codReloj, $horaFinRefReal]);
                    }
                } else {
                    Database::execute("
                        DELETE FROM marcaciones 
                        WHERE (id_empleado = :emp_id OR codigo_reloj = :cod_reloj)
                          AND tipo = 'refrigerio_entrada'
                          AND tipo_verificacion = 'ADMIN_OFICIAL'
                          AND DATE(fecha_hora) = :fecha
                    ", [':emp_id' => $empId, ':cod_reloj' => $codReloj, ':fecha' => $fecha]);
                }

                // Event Sourcing: Registrar evento inmutable de modificación administrativa de horario
                try {
                    $horaEntradaAnt = !empty($actual['hora_entrada_real']) ? substr($actual['hora_entrada_real'], 11, 8) : 'Sin marcar';
                    $horaEntradaNva = !empty($horaEntradaReal) ? substr($horaEntradaReal, 11, 8) : 'Sin marcar';
                    $horaSalidaAnt = !empty($actual['hora_salida_real']) ? substr($actual['hora_salida_real'], 11, 8) : 'Sin marcar';
                    $horaSalidaNva = !empty($horaSalidaReal) ? substr($horaSalidaReal, 11, 8) : 'Sin marcar';

                    EventStore::recordEvent(
                        'ASISTENCIA_DIARIA',
                        "emp_{$actual['id_empleado']}_{$actual['fecha']}",
                        'ASISTENCIA_MODIFICADA_ADMIN',
                        [
                            'id_asistencia'          => $id,
                            'id_empleado'            => $actual['id_empleado'],
                            'empleado_nombre'        => "{$actual['apellidos']} {$actual['nombres']}",
                            'fecha'                  => $actual['fecha'],
                            'hora_entrada_anterior'  => $horaEntradaAnt,
                            'hora_entrada_nueva'     => $horaEntradaNva,
                            'hora_salida_anterior'   => $horaSalidaAnt,
                            'hora_salida_nueva'      => $horaSalidaNva,
                            'estado_anterior'        => $actual['estado'],
                            'estado_nuevo'           => $estado,
                            'tardanza_anterior'      => $actual['minutos_tardanza'],
                            'tardanza_nueva'         => $minutosTardanza,
                            'minutos_trabajados'     => $minutosTrabajados,
                            'minutos_extra'          => $minutosExtra,
                            'motivo'                 => $observaciones ?: 'Ajuste administrativo oficial de horario de asistencia',
                            'modificado_por'         => $usuario
                        ],
                        $usuario
                    );
                } catch (\Exception $e) {
                    // Silencioso
                }
            });

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => true,
                    'message' => 'Horario de asistencia corregido exitosamente. Los nuevos valores son ahora oficiales en todos los reportes.'
                ]);
                exit;
            }

            header('Location: ?route=asistencia&msg=actualizado');
            exit;
        } catch (\PDOException $e) {
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => 'Error de base de datos al guardar la corrección.']);
                exit;
            }
            header('Location: ?route=asistencia&error=db_error');
            exit;
        }
    }

    /**
     * Registrar Asistencia Justificada por el Administrador con filtro por empleado
     */
    public function justificarAdmin(): void {
        AuthController::checkAuth();
        AuthController::requireRole(['ADMIN', 'RRHH'], 'asistencia');
        \App\Csrf::validateRequest();

        $idEmpleado = (int)($_POST['id_empleado'] ?? 0);
        $fechaInicio = $_POST['fecha_inicio'] ?? date('Y-m-d');
        $fechaFin = $_POST['fecha_fin'] ?? $fechaInicio;
        $tipo = $_POST['tipo'] ?? 'TARDANZA';
        $comisionDestino = trim($_POST['comision_destino'] ?? '');
        $motivo = trim($_POST['motivo'] ?? 'Justificación autorizada por la Administración');

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
                  || isset($_GET['ajax']) 
                  || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if ($idEmpleado <= 0 || empty($motivo)) {
            $err = 'Debe seleccionar un empleado y especificar el motivo de la justificación.';
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => $err]);
                exit;
            }
            header('Location: ?route=asistencia&error=campos_requeridos');
            exit;
        }

        try {
            $emp = Database::queryOne("SELECT id, nombres, apellidos, dni, turno_id FROM empleados WHERE id = ?", [$idEmpleado]);
            if (!$emp) {
                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'error' => 'Empleado no encontrado.']);
                    exit;
                }
                header('Location: ?route=asistencia&error=no_encontrado');
                exit;
            }

            $currentUser = AuthController::user();
            $usuario = $currentUser['nombre'] ?? ($currentUser['usuario'] ?? 'Recursos Humanos');

            Database::transaction(function() use ($idEmpleado, $fechaInicio, $fechaFin, $tipo, $comisionDestino, $motivo, $emp, $usuario, $currentUser) {
                // 1. Insertar en tabla justificaciones con estado APROBADO
                Database::execute("
                    INSERT INTO justificaciones 
                    (id_empleado, tipo, fecha_inicio, fecha_fin, motivo, comision_destino, estado, aprobado_por, fecha_resolucion, creado_en)
                    VALUES (?, ?, ?, ?, ?, ?, 'APROBADO', ?, NOW(), NOW())
                ", [$idEmpleado, $tipo, $fechaInicio, $fechaFin, $motivo, ($tipo === 'COMISION_SERVICIO' ? $comisionDestino : null), $usuario]);

                // 2. Determinar estado correspondiente en asistencia_diaria
                $estadoAsistencia = match($tipo) {
                    'COMISION_SERVICIO' => 'COMISION_SERVICIO',
                    'VACACIONES' => 'VACACIONES',
                    'PERMISO_MEDICO', 'LICENCIA_MATERNIDAD_PATERNIDAD', 'PERMISO' => 'PERMISO',
                    default => 'JUSTIFICADO'
                };

                // 3. Actualizar / Insertar registros de asistencia_diaria en el rango
                $startDt = new \DateTime($fechaInicio);
                $endDt = new \DateTime($fechaFin);
                $endDt->modify('+1 day');
                $interval = new \DateInterval('P1D');
                $period = new \DatePeriod($startDt, $interval, $endDt);

                foreach ($period as $dt) {
                    $dStr = $dt->format('Y-m-d');
                    $dayOfWeek = (int)$dt->format('N');
                    $isSaturday = ($dayOfWeek === 6);

                    // Determinar marcaciones y horas automáticas según el tipo de justificación
                    $hEnt = null;
                    $hSal = null;
                    $hRefSal = null;
                    $hRefEnt = null;
                    $minTrab = 0;
                    $obsFinal = $motivo;
                    $destGuardar = null;

                    if ($tipo === 'COMISION_SERVICIO') {
                        $destGuardar = $comisionDestino ?: null;
                        if ($isSaturday) {
                            $hEnt = "$dStr 08:00:00";
                            $hSal = "$dStr 13:00:00";
                            $minTrab = 300; // 5 horas en sábado
                        } else {
                            // Lunes a Viernes: 08:00 a 17:00, refrigerio 13:00 a 13:45
                            $hEnt = "$dStr 08:00:00";
                            $hRefSal = "$dStr 13:00:00";
                            $hRefEnt = "$dStr 13:45:00";
                            $hSal = "$dStr 17:00:00";
                            $minTrab = 495; // 8h 15m netas laboradas (8.25 hrs normales de jornada)
                        }
                        $obsFinal = "Comisión de Servicio" . ($comisionDestino ? " en $comisionDestino" : "") . ": $motivo";
                    } elseif ($tipo === 'VACACIONES') {
                        if ($isSaturday) {
                            $hEnt = "$dStr 08:00:00";
                            $hSal = "$dStr 13:00:00";
                            $minTrab = 300;
                        } else {
                            $hEnt = "$dStr 08:00:00";
                            $hRefSal = "$dStr 13:00:00";
                            $hRefEnt = "$dStr 13:45:00";
                            $hSal = "$dStr 17:00:00";
                            $minTrab = 495;
                        }
                        $obsFinal = "Vacaciones autorizadas (marcación automática oficial de jornada cumplida)" . ($motivo ? " - $motivo" : "");
                    }
                    
                    // Buscar si ya existe registro para ese día
                    $existing = Database::queryOne("SELECT id, estado, minutos_tardanza FROM asistencia_diaria WHERE id_empleado = ? AND fecha = ?", [$idEmpleado, $dStr]);
                    
                    if ($existing) {
                        Database::execute("
                            UPDATE asistencia_diaria 
                            SET hora_entrada_real = :ent_real,
                                hora_salida_real = :sal_real,
                                hora_inicio_refrigerio_real = :ref_sal,
                                hora_fin_refrigerio_real = :ref_ent,
                                minutos_trabajados = :trabajados,
                                minutos_tardanza = 0,
                                minutos_extra = 0,
                                minutos_salida_temprana = 0,
                                estado = :estado,
                                comision_destino = :comision_dest,
                                observaciones = :obs,
                                manual = 1,
                                procesado_en = NOW()
                            WHERE id = :id
                        ", [
                            ':ent_real'      => $hEnt,
                            ':sal_real'      => $hSal,
                            ':ref_sal'       => $hRefSal,
                            ':ref_ent'       => $hRefEnt,
                            ':trabajados'    => $minTrab,
                            ':estado'        => $estadoAsistencia,
                            ':comision_dest' => $destGuardar,
                            ':obs'           => $obsFinal,
                            ':id'            => $existing['id']
                        ]);
                    } else {
                        // Obtener turno del empleado para guardar la referencia
                        Database::execute("
                            INSERT INTO asistencia_diaria 
                            (id_empleado, id_turno, fecha, hora_entrada_programada, hora_salida_programada,
                             hora_entrada_real, hora_salida_real, hora_inicio_refrigerio_real, hora_fin_refrigerio_real,
                             minutos_tardanza, minutos_trabajados, minutos_extra, minutos_salida_temprana,
                             estado, observaciones, comision_destino, manual, procesado_en)
                            VALUES (?, ?, ?, '08:00:00', '17:00:00', ?, ?, ?, ?, 0, ?, 0, 0, ?, ?, ?, 1, NOW())
                            ON DUPLICATE KEY UPDATE 
                             hora_entrada_real = VALUES(hora_entrada_real),
                             hora_salida_real = VALUES(hora_salida_real),
                             hora_inicio_refrigerio_real = VALUES(hora_inicio_refrigerio_real),
                             hora_fin_refrigerio_real = VALUES(hora_fin_refrigerio_real),
                             minutos_trabajados = VALUES(minutos_trabajados),
                             minutos_tardanza = 0,
                             estado = VALUES(estado),
                             observaciones = VALUES(observaciones),
                             comision_destino = VALUES(comision_destino),
                             manual = 1
                        ", [
                            $idEmpleado, $emp['turno_id'] ?: 1, $dStr,
                            $hEnt, $hSal, $hRefSal, $hRefEnt,
                            $minTrab, $estadoAsistencia, $obsFinal,
                            $destGuardar
                        ]);
                    }

                    // Sincronizar automáticamente en la tabla marcaciones para visualización y auditoría
                    if ($hEnt) {
                        $codReloj = (string)($emp['codigo_reloj'] ?? $idEmpleado);
                        // Entrada
                        Database::execute("
                            INSERT INTO marcaciones 
                            (id_empleado, codigo_reloj, id_dispositivo, fecha_hora, tipo, tipo_verificacion, procesado, creado_en)
                            VALUES (?, ?, 1, ?, 'entrada', ?, 1, NOW())
                            ON DUPLICATE KEY UPDATE tipo_verificacion = VALUES(tipo_verificacion), procesado = 1
                        ", [$idEmpleado, $codReloj, $hEnt, $tipo]);

                        // Salida Refrigerio
                        if ($hRefSal) {
                            Database::execute("
                                INSERT INTO marcaciones 
                                (id_empleado, codigo_reloj, id_dispositivo, fecha_hora, tipo, tipo_verificacion, procesado, creado_en)
                                VALUES (?, ?, 1, ?, 'refrigerio_salida', ?, 1, NOW())
                                ON DUPLICATE KEY UPDATE tipo_verificacion = VALUES(tipo_verificacion), procesado = 1
                            ", [$idEmpleado, $codReloj, $hRefSal, $tipo]);
                        }

                        // Retorno Refrigerio
                        if ($hRefEnt) {
                            Database::execute("
                                INSERT INTO marcaciones 
                                (id_empleado, codigo_reloj, id_dispositivo, fecha_hora, tipo, tipo_verificacion, procesado, creado_en)
                                VALUES (?, ?, 1, ?, 'refrigerio_entrada', ?, 1, NOW())
                                ON DUPLICATE KEY UPDATE tipo_verificacion = VALUES(tipo_verificacion), procesado = 1
                            ", [$idEmpleado, $codReloj, $hRefEnt, $tipo]);
                        }

                        // Salida Final
                        if ($hSal) {
                            Database::execute("
                                INSERT INTO marcaciones 
                                (id_empleado, codigo_reloj, id_dispositivo, fecha_hora, tipo, tipo_verificacion, procesado, creado_en)
                                VALUES (?, ?, 1, ?, 'salida', ?, 1, NOW())
                                ON DUPLICATE KEY UPDATE tipo_verificacion = VALUES(tipo_verificacion), procesado = 1
                            ", [$idEmpleado, $codReloj, $hSal, $tipo]);
                        }
                    }

                    // Event Sourcing
                    try {
                        EventStore::recordEvent(
                            'ASISTENCIA_DIARIA',
                            "emp_{$idEmpleado}_{$dStr}",
                            'JUSTIFICACION_APLICADA',
                            [
                                'id_empleado'        => $idEmpleado,
                                'empleado_nombre'    => "{$emp['apellidos']} {$emp['nombres']}",
                                'fecha'              => $dStr,
                                'tipo_justificacion' => $tipo,
                                'comision_destino'   => $destGuardar,
                                'estado_aplicado'    => $estadoAsistencia,
                                'horas_marcadas'     => $hEnt ? "$hEnt a $hSal" : 'Sin horas',
                                'minutos_trabajados' => $minTrab,
                                'motivo'             => $obsFinal,
                                'aprobado_por'       => $usuario,
                                'rol_usuario'        => $currentUser['rol'] ?? 'RRHH'
                            ],
                            $usuario
                        );
                    } catch (\Exception $e) {
                        // Silencioso
                    }
                }
            });

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => true,
                    'message' => "Asistencia justificada correctamente para {$emp['apellidos']} {$emp['nombres']}."
                ]);
                exit;
            }

            header("Location: ?route=asistencia&fecha_inicio=$fechaInicio&fecha_fin=$fechaFin&msg=justificado");
            exit;
        } catch (\PDOException $e) {
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => 'Error de base de datos al registrar justificación.']);
                exit;
            }
            header('Location: ?route=asistencia&error=db_error');
            exit;
        }
    }

    public function historialEventos(): void {
        AuthController::checkAuth();
        AuthController::requireRole(['ADMIN', 'RRHH'], 'asistencia');
        session_write_close(); // Liberar bloqueo de sesión para consultas AJAX concurrentes

        $empId = (int)($_GET['id_empleado'] ?? 0);
        $fecha = $_GET['fecha'] ?? null;
        $asistId = (int)($_GET['id'] ?? 0);

        if ($asistId > 0 && ($empId <= 0 || empty($fecha))) {
            $asistRow = Database::queryOne("SELECT id_empleado, fecha FROM asistencia_diaria WHERE id = ?", [$asistId]);
            if ($asistRow) {
                $empId = (int)$asistRow['id_empleado'];
                $fecha = $asistRow['fecha'];
            }
        }

        if (empty($fecha)) {
            $fecha = date('Y-m-d');
        }

        if ($empId <= 0) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Empleado no especificado.']);
            exit;
        }

        $streamId = "emp_{$empId}_{$fecha}";
        $eventos = EventStore::getEventsForAggregate($streamId);

        $punchStart = "$fecha 00:00:00";
        $punchEnd = "$fecha 23:59:59";
        $marcacionesCrudas = Database::query("
            SELECT m.*, d.nombre as dispositivo_nombre, d.ip as dispositivo_ip
            FROM marcaciones m
            LEFT JOIN dispositivos d ON m.id_dispositivo = d.id
            WHERE m.id_empleado = ? AND m.fecha_hora BETWEEN ? AND ?
            ORDER BY m.fecha_hora ASC
        ", [$empId, $punchStart, $punchEnd]);

        $asistencia = Database::queryOne("
            SELECT a.*, e.nombres, e.apellidos, e.dni, e.codigo_reloj, t.nombre as turno_nombre
            FROM asistencia_diaria a
            JOIN empleados e ON a.id_empleado = e.id
            LEFT JOIN turnos t ON a.id_turno = t.id
            WHERE a.id_empleado = ? AND a.fecha = ?
        ", [$empId, $fecha]);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'stream_id' => $streamId,
            'fecha' => $fecha,
            'asistencia' => $asistencia,
            'eventos' => $eventos,
            'marcaciones_crudas' => $marcacionesCrudas
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function exportExcel(array $data, string $start, string $end, ?string $deptoNombre = null, ?string $estado = null, ?string $search = null, ?array $empleado = null): void {
        if ($empleado) {
            $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $empleado['apellidos'] . '_' . $empleado['nombres']);
            $filename = "Reporte_Individual_{$safeName}_{$start}_al_{$end}.xls";
        } else {
            $filename = "Reporte_Asistencia_{$start}_al_{$end}.xls";
        }
        header("Content-Type: application/vnd.ms-excel; charset=utf-8");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Pragma: no-cache");
        header("Expires: 0");

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
            $st = $r['estado'];
            if ($st === 'PRESENTE') $totalPresentes++;
            elseif ($st === 'TARDANZA') {
                $totalTardanzas++;
                $sumMinTardanza += (int)$r['minutos_tardanza'];
            } elseif ($st === 'FALTA' || $st === 'FALTA_INJUSTIFICADA') $totalFaltas++;
            elseif (in_array($st, ['JUSTIFICADO', 'PERMISO', 'VACACIONES', 'COMISION_SERVICIO'], true)) $totalJustificados++;
            elseif ($st === 'SALIDA_SIN_MARCAR') $totalSinSalida++;

            $sumMinTrabajados += (int)$r['minutos_trabajados'];
            $sumMinExtra += (int)($r['minutos_extra'] ?? 0);
        }

        $horasTrabajadasTotales = sprintf('%dh %02dm (%s hrs)', floor($sumMinTrabajados / 60), $sumMinTrabajados % 60, number_format($sumMinTrabajados / 60, 2));
        $horasExtraTotales = sprintf('+%dh %02dm (+%s hrs)', floor($sumMinExtra / 60), $sumMinExtra % 60, number_format($sumMinExtra / 60, 2));
        $horasTardanzaTotales = sprintf('%dh %02dm', floor($sumMinTardanza / 60), $sumMinTardanza % 60);
        $tasaPuntualidad = $totalRegistros > 0 ? round(($totalPresentes / $totalRegistros) * 100, 1) : 0;
        $generadoEl = date('d/m/Y H:i:s');
        $usuario = AuthController::user()['nombre'] ?? 'Administrador';

        $colspanMain = $empleado ? 15 : 19;
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
                .text-right { text-align: right; }
                .bg-presente { background-color: #ECFDF5; color: #065F46; font-weight: bold; }
                .bg-tardanza { background-color: #FFFBEB; color: #92400E; font-weight: bold; }
                .bg-falta { background-color: #FEF2F2; color: #991B1B; font-weight: bold; }
                .bg-justificado { background-color: #EFF6FF; color: #1E40AF; }
                .bg-descanso { background-color: #F8FAFC; color: #64748B; }
                .summary-table td { border: 1px solid #CBD5E1; padding: 5px 10px; }
            </style>
        </head>
        <body>
            <table>
                <tr>
                    <td colspan="<?= $colspanMain ?>" class="title-main">JUNTA DE USUARIOS DEL SECTOR HIDRÁULICO MENOR SAN LORENZO (JUSHSAL)</td>
                </tr>
                <tr>
                    <td colspan="<?= $colspanMain ?>" class="title-sub"><?= $empleado ? 'FICHA / REPORTE INDIVIDUAL DE ASISTENCIA LABORAL' : 'SISTEMA INTEGRADO DE CONTROL DE ASISTENCIA Y PERSONAL - REPORTE CONSOLIDADO OFICIAL' ?></td>
                </tr>
                <tr><td colspan="<?= $colspanMain ?>"></td></tr>

                <?php if ($empleado): ?>
                <tr>
                    <td colspan="2" class="meta-header">Trabajador:</td>
                    <td colspan="4" style="font-weight: bold; font-size: 11pt; color: #1e3a8a;"><?= htmlspecialchars($empleado['apellidos'] . ' ' . $empleado['nombres']) ?></td>
                    <td colspan="2" class="meta-header">DNI:</td>
                    <td colspan="2" style="mso-number-format:'\@'; font-weight: bold;"><?= htmlspecialchars($empleado['dni']) ?></td>
                    <td colspan="2" class="meta-header">Cód. Reloj:</td>
                    <td colspan="<?= $colspanMain - 12 ?>" style="mso-number-format:'\@'; font-weight: bold;"><?= htmlspecialchars($empleado['codigo_reloj']) ?></td>
                </tr>
                <tr>
                    <td colspan="2" class="meta-header">Área / Depto:</td>
                    <td colspan="4"><?= htmlspecialchars($empleado['departamento_nombre'] ?? 'Sin Área') ?></td>
                    <td colspan="2" class="meta-header">Cargo:</td>
                    <td colspan="2"><?= htmlspecialchars($empleado['cargo_nombre'] ?? 'Personal') ?></td>
                    <td colspan="2" class="meta-header">Turno:</td>
                    <td colspan="<?= $colspanMain - 12 ?>"><?= htmlspecialchars($empleado['turno_nombre'] ?? 'Turno Regular') ?></td>
                </tr>
                <?php endif; ?>

                <tr>
                    <td colspan="2" class="meta-header">Período Consultado:</td>
                    <td colspan="4"><?= date('d/m/Y', strtotime($start)) ?> al <?= date('d/m/Y', strtotime($end)) ?></td>
                    <td colspan="2" class="meta-header">Generado por:</td>
                    <td colspan="<?= $colspanMain - 8 ?>"><?= htmlspecialchars($usuario) ?> (<?= $generadoEl ?>)</td>
                </tr>
                <?php if (!$empleado): ?>
                <tr>
                    <td colspan="2" class="meta-header">Área / Departamento:</td>
                    <td colspan="4"><?= htmlspecialchars($deptoNombre ?: 'Todos los Departamentos') ?></td>
                    <td colspan="2" class="meta-header">Estado Filtro:</td>
                    <td colspan="<?= $colspanMain - 8 ?>"><?= htmlspecialchars($estado ?: 'Todos') ?></td>
                </tr>
                <?php endif; ?>
                <tr><td colspan="<?= $colspanMain ?>"></td></tr>
            </table>

            <!-- RESUMEN DE INDICADORES -->
            <table class="summary-table" style="width: 95%; margin-bottom: 15px;">
                <tr style="background-color: #E2E8F0; font-weight: bold;">
                    <td colspan="8" class="text-center">INDICADORES GENERALES DE ASISTENCIA DEL PERÍODO</td>
                </tr>
                <tr class="text-center">
                    <td style="background-color: #F8FAFC;">Días / Reg. Evaluados</td>
                    <td style="background-color: #ECFDF5;">Presentes</td>
                    <td style="background-color: #FFFBEB;">Tardanzas</td>
                    <td style="background-color: #FEF2F2;">Faltas</td>
                    <td style="background-color: #EFF6FF;">Justificados</td>
                    <td style="background-color: #ECFDF5;">% Puntualidad</td>
                    <td style="background-color: #ECFDF5;">Horas Trabajadas Netas</td>
                    <td style="background-color: #F0FDF4;">Horas Extras Totales</td>
                </tr>
                <tr class="text-center" style="font-weight: bold; font-size: 11pt;">
                    <td><?= $totalRegistros ?></td>
                    <td style="color: #065F46;"><?= $totalPresentes ?></td>
                    <td style="color: #92400E;"><?= $totalTardanzas ?></td>
                    <td style="color: #991B1B;"><?= $totalFaltas ?></td>
                    <td style="color: #1E40AF;"><?= $totalJustificados ?></td>
                    <td style="color: #065F46;"><?= $tasaPuntualidad ?>%</td>
                    <td style="color: #065F46; font-weight: bold;"><?= $horasTrabajadasTotales ?></td>
                    <td style="color: #047857; font-weight: bold;"><?= $horasExtraTotales ?></td>
                </tr>
            </table>

            <!-- DETALLE DE ASISTENCIAS -->
            <table>
                <thead>
                    <tr>
                        <th class="th-col" style="width: 35px;">#</th>
                        <th class="th-col" style="width: 85px;">Fecha</th>
                        <?php if (!$empleado): ?>
                            <th class="th-col" style="width: 85px;">DNI</th>
                            <th class="th-col" style="width: 80px;">Cód. Reloj</th>
                            <th class="th-col" style="width: 220px;">Apellidos y Nombres</th>
                            <th class="th-col" style="width: 140px;">Departamento</th>
                        <?php endif; ?>
                        <th class="th-col" style="width: 70px;">Prog. Ent.</th>
                        <th class="th-col" style="width: 70px;">Prog. Sal.</th>
                        <th class="th-col" style="width: 70px;">Real Ent.</th>
                        <th class="th-col" style="width: 75px;">Sal. Ref.</th>
                        <th class="th-col" style="width: 75px;">Ret. Ref.</th>
                        <th class="th-col" style="width: 70px;">Real Sal.</th>
                        <th class="th-col" style="width: 80px;">Tardanza</th>
                        <th class="th-col" style="width: 95px;">H. Trabajadas</th>
                        <th class="th-col" style="width: 75px;">H. Decimal</th>
                        <th class="th-col" style="width: 85px;">H. Extras</th>
                        <th class="th-col" style="width: 75px;">H. Ext Dec</th>
                        <th class="th-col" style="width: 100px;">Estado</th>
                        <th class="th-col" style="width: 180px;">Observaciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $i = 1;
                    foreach ($data as $r): 
                        $st = $r['estado'];
                        $classBg = match(true) {
                            $st === 'PRESENTE' => 'bg-presente',
                            $st === 'TARDANZA' => 'bg-tardanza',
                            $st === 'FALTA' || $st === 'FALTA_INJUSTIFICADA' => 'bg-falta',
                            in_array($st, ['JUSTIFICADO', 'PERMISO', 'VACACIONES', 'COMISION_SERVICIO'], true) => 'bg-justificado',
                            default => 'bg-descanso'
                        };
                        $minTrab = (int)$r['minutos_trabajados'];
                        $strTrabajado = sprintf('%02dh %02dm', floor($minTrab / 60), $minTrab % 60);
                        $decTrabajado = number_format($minTrab / 60, 2);
                        $minExtra = (int)($r['minutos_extra'] ?? 0);
                        $strExtra = $minExtra > 0 ? sprintf('+%02dh %02dm', floor($minExtra / 60), $minExtra % 60) : '-';
                        $decExtra = $minExtra > 0 ? ('+' . number_format($minExtra / 60, 2)) : '-';
                        $minTard = (int)$r['minutos_tardanza'];
                        $strTard = $minTard >= 60 ? sprintf('+%dh %02dm', floor($minTard / 60), $minTard % 60) : "+{$minTard} min";
                    ?>
                    <tr>
                        <td class="text-center"><?= $i++ ?></td>
                        <td class="text-center"><?= date('d/m/Y', strtotime($r['fecha'])) ?></td>
                        <?php if (!$empleado): ?>
                            <td class="text-center" style="mso-number-format:'\@';"><?= htmlspecialchars($r['dni'] ?? '-') ?></td>
                            <td class="text-center" style="mso-number-format:'\@';"><?= htmlspecialchars($r['codigo_reloj'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($r['apellidos'] . ' ' . $r['nombres']) ?></td>
                            <td><?= htmlspecialchars($r['departamento_nombre'] ?? 'Sin Área') ?></td>
                        <?php endif; ?>
                        <td class="text-center"><?= $r['hora_entrada_programada'] ? substr($r['hora_entrada_programada'], 0, 5) : '--:--' ?></td>
                        <td class="text-center"><?= $r['hora_salida_programada'] ? substr($r['hora_salida_programada'], 0, 5) : '--:--' ?></td>
                        <td class="text-center" style="font-weight: bold;"><?= $r['hora_entrada_real'] ? substr($r['hora_entrada_real'], 11, 5) : '--:--' ?></td>
                        <td class="text-center"><?= $r['hora_inicio_refrigerio_real'] ? substr($r['hora_inicio_refrigerio_real'], 11, 5) : '--:--' ?></td>
                        <td class="text-center"><?= $r['hora_fin_refrigerio_real'] ? substr($r['hora_fin_refrigerio_real'], 11, 5) : '--:--' ?></td>
                        <td class="text-center" style="font-weight: bold;"><?= $r['hora_salida_real'] ? substr($r['hora_salida_real'], 11, 5) : '--:--' ?></td>
                        <td class="text-center <?= $minTard > 0 ? 'bg-tardanza' : '' ?>"><?= $minTard > 0 ? $strTard : '-' ?></td>
                        <td class="text-center font-weight-bold" style="color: #065F46;"><?= $strTrabajado ?></td>
                        <td class="text-center font-weight-bold" style="color: #1E40AF;"><?= $decTrabajado ?></td>
                        <td class="text-center font-weight-bold" style="color: #047857;"><?= $strExtra ?></td>
                        <td class="text-center font-weight-bold" style="color: #047857;"><?= $decExtra ?></td>
                        <td class="text-center <?= $classBg ?>"><?= $st ?></td>
                        <td><?= htmlspecialchars($r['observaciones'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </body>
        </html>
        <?php
        exit;
    }
}
