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

        $fechaInicio = !empty($_GET['fecha_inicio']) ? trim($_GET['fecha_inicio']) : date('Y-m-01');
        $fechaFin = !empty($_GET['fecha_fin']) ? trim($_GET['fecha_fin']) : date('Y-m-d');
        if ($fechaInicio > $fechaFin) {
            $tmp = $fechaInicio;
            $fechaInicio = $fechaFin;
            $fechaFin = $tmp;
        }

        // Auto-calcular días faltantes en asistencia_diaria para el rango solicitado (hasta 31 días)
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
                    if (!isset($existingSet[$currDate])) {
                        $stmtMarc = $db->prepare("SELECT 1 FROM marcaciones WHERE fecha_hora >= :d1 AND fecha_hora <= :d2 LIMIT 1");
                        $stmtMarc->execute([':d1' => "$currDate 00:00:00", ':d2' => "$currDate 23:59:59"]);
                        if ($stmtMarc->fetchColumn() || $diffDias === 0) {
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

        $estado = !empty($_GET['estado']) ? trim($_GET['estado']) : null;
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

        if ($deptoId) {
            $where .= " AND e.departamento_id = :depto_id";
            $params[':depto_id'] = $deptoId;
        }

        if (!empty($estado) && strtolower($estado) !== 'todos') {
            $estadoUpper = strtoupper($estado);
            if ($estadoUpper === 'PRESENTE') {
                $where .= " AND a.estado = 'PRESENTE' AND a.hora_entrada_real IS NOT NULL";
            } elseif ($estadoUpper === 'PENDIENTE') {
                $where .= " AND (a.estado = 'PENDIENTE' OR a.hora_entrada_real IS NULL) AND a.estado NOT IN ('FALTA', 'FALTA_INJUSTIFICADA', 'JUSTIFICADO', 'PERMISO', 'VACACIONES', 'DESCANSO')";
            } elseif ($estadoUpper === 'FALTA') {
                $where .= " AND (a.estado = 'FALTA' OR a.estado = 'FALTA_INJUSTIFICADA')";
            } elseif ($estadoUpper === 'JUSTIFICADO') {
                $where .= " AND (a.estado = 'JUSTIFICADO' OR a.estado = 'PERMISO' OR a.estado = 'VACACIONES' OR a.estado = 'LICENCIA')";
            } elseif ($estadoUpper === 'INCIDENCIAS') {
                $where .= " AND (a.estado = 'TARDANZA' OR a.estado = 'FALTA' OR a.estado = 'FALTA_INJUSTIFICADA' OR a.estado = 'SALIDA_SIN_MARCAR')";
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

        // 1. Agregación de KPIs directamente en MySQL para máxima velocidad
        $kpiSql = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN a.estado = 'PRESENTE' AND a.hora_entrada_real IS NOT NULL THEN 1 ELSE 0 END) as presentes,
                SUM(CASE WHEN a.estado = 'TARDANZA' THEN 1 ELSE 0 END) as tardanzas,
                SUM(CASE WHEN a.estado = 'FALTA' OR a.estado = 'FALTA_INJUSTIFICADA' THEN 1 ELSE 0 END) as faltas,
                SUM(CASE WHEN a.estado IN ('JUSTIFICADO', 'PERMISO', 'VACACIONES', 'LICENCIA') THEN 1 ELSE 0 END) as justificados,
                SUM(CASE WHEN a.estado = 'SALIDA_SIN_MARCAR' THEN 1 ELSE 0 END) as sin_salida,
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
                       t.nombre as turno_nombre
                FROM asistencia_diaria a
                JOIN empleados e ON a.id_empleado = e.id
                LEFT JOIN departamentos d ON e.departamento_id = d.id
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
                $this->exportExcel($exportData, $fechaInicio, $fechaFin, $deptoNombre, $estado, $search);
                return;
            } elseif ($exportType === 'csv') {
                $this->exportCSV($exportData, $fechaInicio, $fechaFin, $deptoNombre, $estado);
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
        AuthController::requireRole('ADMIN', 'asistencia');
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
                $diffSec = $dtExitReal->getTimestamp() - $dtEntryReal->getTimestamp();

                if ($diffSec > 0) {
                    $minBrutos = (int)floor($diffSec / 60);
                    $minRef = ($minBrutos >= 300) ? (int)($actual['minutos_refrigerio'] ?? 60) : 0;
                    $minutosTrabajados = max(0, $minBrutos - $minRef);
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
            }

            $currentUser = AuthController::user();
            $usuario = $currentUser['nombre'] ?? ($currentUser['usuario'] ?? 'Administrador');

            Database::transaction(function() use (
                $id, $actual, $horaEntradaReal, $horaSalidaReal, $estado, $observaciones,
                $minutosTardanza, $minutosTrabajados, $minutosExtra, $minutosSalidaTemprana, $usuario
            ) {
                Database::execute("
                    UPDATE asistencia_diaria 
                    SET hora_entrada_real = :ent_real,
                        hora_salida_real = :sal_real,
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
        AuthController::requireRole('ADMIN', 'asistencia');
        \App\Csrf::validateRequest();

        $idEmpleado = (int)($_POST['id_empleado'] ?? 0);
        $fechaInicio = $_POST['fecha_inicio'] ?? date('Y-m-d');
        $fechaFin = $_POST['fecha_fin'] ?? $fechaInicio;
        $tipo = $_POST['tipo'] ?? 'TARDANZA';
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
            $usuario = $currentUser['nombre'] ?? ($currentUser['usuario'] ?? 'Administrador');

            Database::transaction(function() use ($idEmpleado, $fechaInicio, $fechaFin, $tipo, $motivo, $emp, $usuario) {
                // 1. Insertar en tabla justificaciones con estado APROBADO
                Database::execute("
                    INSERT INTO justificaciones 
                    (id_empleado, tipo, fecha_inicio, fecha_fin, motivo, estado, aprobado_por, fecha_resolucion, creado_en)
                    VALUES (?, ?, ?, ?, ?, 'APROBADO', ?, NOW(), NOW())
                ", [$idEmpleado, $tipo, $fechaInicio, $fechaFin, $motivo, $usuario]);

                // 2. Determinar estado correspondiente en asistencia_diaria
                $estadoAsistencia = match($tipo) {
                    'VACACIONES' => 'VACACIONES',
                    'PERMISO_MEDICO', 'COMISION_SERVICIO', 'LICENCIA_MATERNIDAD_PATERNIDAD', 'PERMISO' => 'PERMISO',
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
                    
                    // Buscar si ya existe registro para ese día
                    $existing = Database::queryOne("SELECT id, estado, minutos_tardanza FROM asistencia_diaria WHERE id_empleado = ? AND fecha = ?", [$idEmpleado, $dStr]);
                    
                    if ($existing) {
                        Database::execute("
                            UPDATE asistencia_diaria 
                            SET estado = :estado,
                                minutos_tardanza = 0,
                                observaciones = :obs,
                                manual = 1,
                                procesado_en = NOW()
                            WHERE id = :id
                        ", [
                            ':estado' => $estadoAsistencia,
                            ':obs'    => $motivo,
                            ':id'     => $existing['id']
                        ]);
                    } else {
                        // Obtener turno del empleado para guardar la referencia
                        Database::execute("
                            INSERT INTO asistencia_diaria 
                            (id_empleado, id_turno, fecha, estado, observaciones, manual, procesado_en)
                            VALUES (?, ?, ?, ?, ?, 1, NOW())
                            ON DUPLICATE KEY UPDATE estado = VALUES(estado), observaciones = VALUES(observaciones), manual = 1
                        ", [$idEmpleado, $emp['turno_id'] ?: 1, $dStr, $estadoAsistencia, $motivo]);
                    }

                    // Event Sourcing
                    try {
                        EventStore::recordEvent(
                            'ASISTENCIA_DIARIA',
                            "emp_{$idEmpleado}_{$dStr}",
                            'JUSTIFICACION_APLICADA',
                            [
                                'id_empleado'       => $idEmpleado,
                                'empleado_nombre'   => "{$emp['apellidos']} {$emp['nombres']}",
                                'fecha'             => $dStr,
                                'tipo_justificacion'=> $tipo,
                                'estado_aplicado'   => $estadoAsistencia,
                                'motivo'            => $motivo,
                                'aprobado_por'      => $usuario
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
        AuthController::requireRole('ADMIN', 'asistencia');
        session_write_close(); // Liberar bloqueo de sesión para consultas AJAX concurrentes

        $empId = (int)($_GET['id_empleado'] ?? 0);
        $fecha = $_GET['fecha'] ?? date('Y-m-d');

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

    private function exportExcel(array $data, string $start, string $end, ?string $deptoNombre = null, ?string $estado = null, ?string $search = null): void {
        $filename = "Reporte_Asistencia_{$start}_al_{$end}.xls";
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
            elseif (in_array($st, ['JUSTIFICADO', 'PERMISO', 'VACACIONES'], true)) $totalJustificados++;
            elseif ($st === 'SALIDA_SIN_MARCAR') $totalSinSalida++;

            $sumMinTrabajados += (int)$r['minutos_trabajados'];
            $sumMinExtra += (int)$r['minutos_extra'];
        }

        $horasTrabajadasTotales = sprintf('%dh %02dm', floor($sumMinTrabajados / 60), $sumMinTrabajados % 60);
        $horasExtrasTotales = sprintf('%dh %02dm', floor($sumMinExtra / 60), $sumMinExtra % 60);
        $horasTardanzaTotales = sprintf('%dh %02dm', floor($sumMinTardanza / 60), $sumMinTardanza % 60);
        $tasaPuntualidad = $totalRegistros > 0 ? round(($totalPresentes / $totalRegistros) * 100, 1) : 0;
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
                    <td colspan="12" class="title-main">JUNTA DE USUARIOS DEL SECTOR HIDRÁULICO MENOR SAN LORENZO (JUSHSAL)</td>
                </tr>
                <tr>
                    <td colspan="12" class="title-sub">SISTEMA INTEGRADO DE CONTROL DE ASISTENCIA Y PERSONAL - REPORTE CONSOLIDADO OFICIAL</td>
                </tr>
                <tr><td colspan="12"></td></tr>
                <tr>
                    <td colspan="2" class="meta-header">Período Consultado:</td>
                    <td colspan="4"><?= date('d/m/Y', strtotime($start)) ?> al <?= date('d/m/Y', strtotime($end)) ?></td>
                    <td colspan="2" class="meta-header">Generado por:</td>
                    <td colspan="4"><?= htmlspecialchars($usuario) ?></td>
                </tr>
                <tr>
                    <td colspan="2" class="meta-header">Área / Departamento:</td>
                    <td colspan="4"><?= htmlspecialchars($deptoNombre ?: 'Todos los Departamentos') ?></td>
                    <td colspan="2" class="meta-header">Fecha de Emisión:</td>
                    <td colspan="4"><?= $generadoEl ?></td>
                </tr>
                <tr><td colspan="12"></td></tr>
            </table>

            <!-- RESUMEN DE INDICADORES -->
            <table class="summary-table" style="width: 80%; margin-bottom: 15px;">
                <tr style="background-color: #E2E8F0; font-weight: bold;">
                    <td colspan="7" class="text-center">INDICADORES GENERALES DE ASISTENCIA DEL PERÍODO</td>
                </tr>
                <tr class="text-center">
                    <td style="background-color: #F8FAFC;">Total Evaluados</td>
                    <td style="background-color: #ECFDF5;">Presentes</td>
                    <td style="background-color: #FFFBEB;">Tardanzas</td>
                    <td style="background-color: #FEF2F2;">Faltas</td>
                    <td style="background-color: #EFF6FF;">Justificados</td>
                    <td style="background-color: #ECFDF5;">% Puntualidad</td>
                    <td style="background-color: #EFF6FF;">Horas Extras Totales</td>
                </tr>
                <tr class="text-center" style="font-weight: bold; font-size: 11pt;">
                    <td><?= $totalRegistros ?></td>
                    <td style="color: #065F46;"><?= $totalPresentes ?></td>
                    <td style="color: #92400E;"><?= $totalTardanzas ?></td>
                    <td style="color: #991B1B;"><?= $totalFaltas ?></td>
                    <td style="color: #1E40AF;"><?= $totalJustificados ?></td>
                    <td style="color: #065F46;"><?= $tasaPuntualidad ?>%</td>
                    <td style="color: #1E40AF;"><?= $horasExtrasTotales ?></td>
                </tr>
            </table>

            <!-- DETALLE DE ASISTENCIAS -->
            <table>
                <thead>
                    <tr>
                        <th class="th-col" style="width: 35px;">#</th>
                        <th class="th-col" style="width: 85px;">Fecha</th>
                        <th class="th-col" style="width: 85px;">DNI</th>
                        <th class="th-col" style="width: 80px;">Cód. Reloj</th>
                        <th class="th-col" style="width: 220px;">Apellidos y Nombres</th>
                        <th class="th-col" style="width: 140px;">Departamento</th>
                        <th class="th-col" style="width: 70px;">Prog. Ent.</th>
                        <th class="th-col" style="width: 70px;">Prog. Sal.</th>
                        <th class="th-col" style="width: 70px;">Real Ent.</th>
                        <th class="th-col" style="width: 70px;">Real Sal.</th>
                        <th class="th-col" style="width: 80px;">Tardanza</th>
                        <th class="th-col" style="width: 80px;">Trabajado</th>
                        <th class="th-col" style="width: 80px;">H. Extra</th>
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
                            in_array($st, ['JUSTIFICADO', 'PERMISO', 'VACACIONES'], true) => 'bg-justificado',
                            default => 'bg-descanso'
                        };
                        $minTrab = (int)$r['minutos_trabajados'];
                        $strTrabajado = sprintf('%02dh %02dm', floor($minTrab / 60), $minTrab % 60);
                        $minTard = (int)$r['minutos_tardanza'];
                        $minExt = (int)$r['minutos_extra'];
                    ?>
                    <tr>
                        <td class="text-center"><?= $i++ ?></td>
                        <td class="text-center"><?= date('d/m/Y', strtotime($r['fecha'])) ?></td>
                        <td class="text-center" style="mso-number-format:'\@';"><?= htmlspecialchars($r['dni'] ?? '-') ?></td>
                        <td class="text-center" style="mso-number-format:'\@';"><?= htmlspecialchars($r['codigo_reloj'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($r['apellidos'] . ' ' . $r['nombres']) ?></td>
                        <td><?= htmlspecialchars($r['departamento_nombre'] ?? 'Sin Área') ?></td>
                        <td class="text-center"><?= $r['hora_entrada_programada'] ? substr($r['hora_entrada_programada'], 0, 5) : '--:--' ?></td>
                        <td class="text-center"><?= $r['hora_salida_programada'] ? substr($r['hora_salida_programada'], 0, 5) : '--:--' ?></td>
                        <td class="text-center" style="font-weight: bold;"><?= $r['hora_entrada_real'] ? substr($r['hora_entrada_real'], 11, 5) : '--:--' ?></td>
                        <td class="text-center" style="font-weight: bold;"><?= $r['hora_salida_real'] ? substr($r['hora_salida_real'], 11, 5) : '--:--' ?></td>
                        <td class="text-center <?= $minTard > 0 ? 'bg-tardanza' : '' ?>"><?= $minTard > 0 ? "+{$minTard} min" : '-' ?></td>
                        <td class="text-center"><?= $strTrabajado ?></td>
                        <td class="text-center <?= $minExt > 0 ? 'bg-presente' : '' ?>"><?= $minExt > 0 ? "+{$minExt} min" : '-' ?></td>
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

    private function exportCSV(array $data, string $start, string $end, ?string $deptoNombre = null, ?string $estado = null): void {
        $filename = "Reporte_Asistencia_{$start}_al_{$end}.csv";
        header("Content-Type: text/csv; charset=utf-8");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Pragma: no-cache");
        header("Expires: 0");

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

        $delimiter = ";";

        // Encabezado institucional
        fputcsv($output, ['JUNTA DE USUARIOS DEL SECTOR HIDRAULICO MENOR SAN LORENZO (JUSHSAL)'], $delimiter);
        fputcsv($output, ['REPORTE CONSOLIDADO OFICIAL DE ASISTENCIA LABORAL'], $delimiter);
        fputcsv($output, ['Periodo:', "Del $start al $end"], $delimiter);
        fputcsv($output, ['Area / Departamento:', $deptoNombre ?: 'Todos los Departamentos'], $delimiter);
        fputcsv($output, ['Fecha de Emision:', date('d/m/Y H:i:s')], $delimiter);
        fputcsv($output, [], $delimiter);

        // Cabeceras de columnas
        fputcsv($output, [
            'N°', 'Fecha', 'DNI', 'Codigo Reloj', 'Apellidos y Nombres', 'Departamento / Area',
            'Turno Asignado', 'Entrada Programada', 'Salida Programada', 'Entrada Real', 'Salida Real',
            'Minutos Tardanza', 'Tiempo Tardanza', 'Tiempo Trabajado', 'Minutos Trabajados',
            'Minutos Horas Extras', 'Horas Extras', 'Estado Asistencia', 'Observaciones / Sustento'
        ], $delimiter);

        $i = 1;
        $totalRegistros = count($data);
        $sumMinTardanza = 0;
        $sumMinTrabajados = 0;
        $sumMinExtra = 0;

        foreach ($data as $r) {
            $minTrab = (int)$r['minutos_trabajados'];
            $strTrabajado = sprintf('%02dh %02dm', floor($minTrab / 60), $minTrab % 60);
            $minTard = (int)$r['minutos_tardanza'];
            $strTardanza = sprintf('%02dh %02dm', floor($minTard / 60), $minTard % 60);
            $minExt = (int)$r['minutos_extra'];
            $strExtra = sprintf('%02dh %02dm', floor($minExt / 60), $minExt % 60);

            $sumMinTardanza += $minTard;
            $sumMinTrabajados += $minTrab;
            $sumMinExtra += $minExt;

            fputcsv($output, [
                $i++,
                date('d/m/Y', strtotime($r['fecha'])),
                '="' . ($r['dni'] ?? '') . '"',
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
                $r['estado'],
                $r['observaciones'] ?? ''
            ], $delimiter);
        }

        // Fila de totales
        fputcsv($output, [], $delimiter);
        $horasTardanzaTotales = sprintf('%02dh %02dm', floor($sumMinTardanza / 60), $sumMinTardanza % 60);
        $horasTrabajadasTotales = sprintf('%02dh %02dm', floor($sumMinTrabajados / 60), $sumMinTrabajados % 60);
        $horasExtrasTotales = sprintf('%02dh %02dm', floor($sumMinExtra / 60), $sumMinExtra % 60);

        fputcsv($output, ['TOTALES GENERALES:', '', '', '', '', '', '', '', '', '', '', $sumMinTardanza, $horasTardanzaTotales, $horasTrabajadasTotales, $sumMinTrabajados, $sumMinExtra, $horasExtrasTotales, $totalRegistros . ' registros', ''], $delimiter);

        fclose($output);
        exit;
    }
}
