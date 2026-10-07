<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Services\AttendanceCalculator;


class DashboardController {
    public function index(): void {
        AuthController::checkAuth();
        $currentUser = AuthController::user();

        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');
        // El rol del dashboard es estrictamente el rol asignado al usuario en su sesión
        $activeRoleView = $currentUser['rol'] ?? 'RRHH';

        // Disparar cálculo en segundo plano si aún no existe registro de asistencia para hoy
        $asistenciaExiste = Database::queryOne("SELECT id FROM asistencia_diaria WHERE fecha = ? LIMIT 1", [$today]);
        if (!$asistenciaExiste) {
            $this->triggerAsyncCalculation($today);
        }

        // 1. Estadísticas Generales de Hoy
        $totalEmpleados = (int)(Database::queryOne("SELECT COUNT(*) as c FROM empleados WHERE activo = 1")['c'] ?? 0);
        
        $statsHoy = Database::queryOne("
            SELECT 
                COALESCE(SUM(CASE WHEN estado = 'PRESENTE' AND hora_entrada_real IS NOT NULL THEN 1 ELSE 0 END), 0) as presentes,
                COALESCE(SUM(CASE WHEN estado = 'TARDANZA' THEN 1 ELSE 0 END), 0) as tardanzas,
                COALESCE(SUM(CASE WHEN estado = 'FALTA' OR estado = 'FALTA_INJUSTIFICADA' THEN 1 ELSE 0 END), 0) as faltas,
                COALESCE(SUM(CASE WHEN estado = 'JUSTIFICADO' OR estado = 'PERMISO' OR estado = 'VACACIONES' THEN 1 ELSE 0 END), 0) as justificados,
                COALESCE(SUM(CASE WHEN estado = 'SALIDA_SIN_MARCAR' THEN 1 ELSE 0 END), 0) as sin_salida,
                COALESCE(SUM(minutos_tardanza), 0) as total_minutos_tardanza,
                COALESCE(SUM(minutos_extra), 0) as total_minutos_extra
            FROM asistencia_diaria 
            WHERE fecha = ?
        ", [$today]) ?: [
            'presentes' => 0, 'tardanzas' => 0, 'faltas' => 0, 'justificados' => 0,
            'sin_salida' => 0, 'total_minutos_tardanza' => 0, 'total_minutos_extra' => 0
        ];

        // Tasa de Puntualidad de Hoy
        $presentesHoy = (int)($statsHoy['presentes'] ?? 0);
        $tardanzasHoy = (int)($statsHoy['tardanzas'] ?? 0);
        $puntualidadHoyPorc = $totalEmpleados > 0 ? round(($presentesHoy / $totalEmpleados) * 100, 1) : 0;

        // 2. Métricas del Mes en Curso (RRHH)
        $statsMes = Database::queryOne("
            SELECT 
                COALESCE(SUM(minutos_extra), 0) as total_minutos_extra_mes,
                COALESCE(SUM(minutos_tardanza), 0) as total_minutos_tardanza_mes,
                COALESCE(SUM(CASE WHEN estado = 'FALTA' OR estado = 'FALTA_INJUSTIFICADA' THEN 1 ELSE 0 END), 0) as total_faltas_mes,
                COALESCE(SUM(CASE WHEN estado = 'TARDANZA' THEN 1 ELSE 0 END), 0) as total_tardanzas_mes
            FROM asistencia_diaria 
            WHERE fecha BETWEEN ? AND ?
        ", [$monthStart, $today]) ?: [
            'total_minutos_extra_mes' => 0, 'total_minutos_tardanza_mes' => 0,
            'total_faltas_mes' => 0, 'total_tardanzas_mes' => 0
        ];

        $horasExtraMes = round(($statsMes['total_minutos_extra_mes'] ?? 0) / 60, 1);
        $horasTardanzaMes = round(($statsMes['total_minutos_tardanza_mes'] ?? 0) / 60, 1);

        // Justificaciones pendientes
        $justificacionesPendientes = (int)(Database::queryOne("
            SELECT COUNT(*) as c FROM justificaciones WHERE estado = 'PENDIENTE'
        ")['c'] ?? 0);

        // 3. Top 5 Empleados con Mayor Tardanza en el Mes
        $topTardanzasMes = Database::query("
            SELECT 
                e.id, e.nombres, e.apellidos, e.codigo_reloj, e.dni,
                d.nombre as depto_nombre,
                COUNT(a.id) as veces_tarde,
                SUM(a.minutos_tardanza) as total_minutos
            FROM asistencia_diaria a
            JOIN empleados e ON a.id_empleado = e.id
            LEFT JOIN departamentos d ON e.departamento_id = d.id
            WHERE a.fecha BETWEEN ? AND ? AND a.minutos_tardanza > 0
            GROUP BY e.id, e.nombres, e.apellidos, e.codigo_reloj, e.dni, d.nombre
            ORDER BY total_minutos DESC
            LIMIT 5
        ", [$monthStart, $today]);

        // Top 5 Tardanzas de Hoy
        $topTardanzasHoy = Database::query("
            SELECT a.*, e.nombres, e.apellidos, e.codigo_reloj, t.nombre as turno_nombre, d.nombre as depto_nombre
            FROM asistencia_diaria a
            JOIN empleados e ON a.id_empleado = e.id
            LEFT JOIN turnos t ON a.id_turno = t.id
            LEFT JOIN departamentos d ON e.departamento_id = d.id
            WHERE a.fecha = ? AND a.minutos_tardanza > 0
            ORDER BY a.minutos_tardanza DESC
            LIMIT 5
        ", [$today]);

        // 4. Métricas por Departamento (Supervisores y RRHH)
        $deptosStats = Database::query("
            SELECT 
                d.id, d.nombre as depto_nombre,
                COUNT(DISTINCT e.id) as total_empleados,
                SUM(CASE WHEN a.estado = 'PRESENTE' THEN 1 ELSE 0 END) as presentes,
                SUM(CASE WHEN a.estado = 'TARDANZA' THEN 1 ELSE 0 END) as tardanzas,
                SUM(CASE WHEN a.estado = 'FALTA' OR a.estado = 'FALTA_INJUSTIFICADA' THEN 1 ELSE 0 END) as faltas,
                SUM(CASE WHEN a.estado = 'JUSTIFICADO' OR a.estado = 'PERMISO' OR a.estado = 'VACACIONES' THEN 1 ELSE 0 END) as justificados
            FROM departamentos d
            LEFT JOIN empleados e ON e.departamento_id = d.id AND e.activo = 1
            LEFT JOIN asistencia_diaria a ON a.id_empleado = e.id AND a.fecha = ?
            WHERE d.activo = 1
            GROUP BY d.id, d.nombre
            ORDER BY total_empleados DESC
        ", [$today]);

        // 5. Tendencia de Asistencia de los Últimos 7 Días (Chart.js Series)
        $diasAtras = 6;
        $fechaInicioTendencia = date('Y-m-d', strtotime("-$diasAtras days"));
        $supervisorDeptoId = (($currentUser['rol'] ?? '') === 'SUPERVISOR' && !empty($currentUser['departamento_id'])) 
            ? (int)$currentUser['departamento_id'] 
            : null;
        
        $tendenciaParams = [$fechaInicioTendencia, $today];
        $deptoJoin = "";
        $deptoWhere = "";
        if ($supervisorDeptoId) {
            $deptoJoin = "JOIN empleados e ON a.id_empleado = e.id";
            $deptoWhere = "AND e.departamento_id = ?";
            $tendenciaParams[] = $supervisorDeptoId;
        }

        $tendenciaRows = Database::query("
            SELECT 
                a.fecha,
                SUM(CASE WHEN a.estado = 'PRESENTE' AND a.hora_entrada_real IS NOT NULL THEN 1 ELSE 0 END) as presentes,
                SUM(CASE WHEN a.estado = 'TARDANZA' THEN 1 ELSE 0 END) as tardanzas,
                SUM(CASE WHEN a.estado = 'FALTA' OR a.estado = 'FALTA_INJUSTIFICADA' THEN 1 ELSE 0 END) as faltas
            FROM asistencia_diaria a
            $deptoJoin
            WHERE a.fecha BETWEEN ? AND ? $deptoWhere
            GROUP BY a.fecha
            ORDER BY a.fecha ASC
        ", $tendenciaParams);

        // Indexar por fecha para rellenar días sin registros
        $tendenciaMap = [];
        foreach ($tendenciaRows as $tr) {
            $tendenciaMap[$tr['fecha']] = $tr;
        }

        $chartLabels = [];
        $chartPresentes = [];
        $chartTardanzas = [];
        $chartFaltas = [];

        for ($i = $diasAtras; $i >= 0; $i--) {
            $dStr = date('Y-m-d', strtotime("-$i days"));
            $chartLabels[] = date('d/m', strtotime($dStr));
            if (isset($tendenciaMap[$dStr])) {
                $chartPresentes[] = (int)$tendenciaMap[$dStr]['presentes'];
                $chartTardanzas[] = (int)$tendenciaMap[$dStr]['tardanzas'];
                $chartFaltas[] = (int)$tendenciaMap[$dStr]['faltas'];
            } else {
                $chartPresentes[] = 0;
                $chartTardanzas[] = 0;
                $chartFaltas[] = 0;
            }
        }

        $totalesTendencia = [
            'presentes' => array_sum($chartPresentes),
            'tardanzas' => array_sum($chartTardanzas),
            'faltas'    => array_sum($chartFaltas)
        ];
        $totalesTendencia['total'] = $totalesTendencia['presentes'] + $totalesTendencia['tardanzas'] + $totalesTendencia['faltas'];
        $totalesTendencia['porc_presentes'] = $totalesTendencia['total'] > 0 ? round(($totalesTendencia['presentes'] / $totalesTendencia['total']) * 100, 1) : 0;
        $totalesTendencia['porc_tardanzas'] = $totalesTendencia['total'] > 0 ? round(($totalesTendencia['tardanzas'] / $totalesTendencia['total']) * 100, 1) : 0;
        $totalesTendencia['porc_faltas']    = $totalesTendencia['total'] > 0 ? round(($totalesTendencia['faltas'] / $totalesTendencia['total']) * 100, 1) : 0;

        $listaDepartamentos = Database::query("SELECT id, nombre FROM departamentos WHERE activo = 1 ORDER BY nombre ASC");

        // 6. TI & Dispositivos Biométricos
        $dispositivos = Database::query("SELECT * FROM dispositivos ORDER BY id ASC");
        $dispositivosOnline = 0;
        foreach ($dispositivos as $d) {
            if ($d['estado_conexion'] === 'ONLINE') $dispositivosOnline++;
        }

        // Estadísticas de logs de sincronización (últimos 7 días)
        $syncStats = Database::queryOne("
            SELECT 
                COUNT(*) as total_syncs,
                SUM(CASE WHEN estado = 'EXITO' THEN 1 ELSE 0 END) as exitos,
                SUM(CASE WHEN estado = 'ERROR' THEN 1 ELSE 0 END) as errores,
                SUM(total_insertados) as total_insertados,
                AVG(duracion_segundos) as promedio_duracion
            FROM log_sincronizacion
            WHERE fecha_hora >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ") ?: ['total_syncs' => 0, 'exitos' => 0, 'errores' => 0, 'total_insertados' => 0, 'promedio_duracion' => 0];

        $tasaExitoSync = ($syncStats['total_syncs'] ?? 0) > 0 
            ? round((($syncStats['exitos'] ?? 0) / $syncStats['total_syncs']) * 100, 1) 
            : 100;

        // Total marcaciones hoy (Optimizado con índice B-Tree)
        $totalMarcacionesHoy = (int)(Database::queryOne("
            SELECT COUNT(*) as c FROM marcaciones WHERE fecha_hora >= ? AND fecha_hora <= ?
        ", [$today . ' 00:00:00', $today . ' 23:59:59'])['c'] ?? 0);

        // 7. Últimas 10 marcaciones en vivo
        $ultimasMarcaciones = Database::query("
            SELECT m.*, e.nombres, e.apellidos, d.nombre as dispositivo_nombre
            FROM marcaciones m
            LEFT JOIN empleados e ON m.id_empleado = e.id
            LEFT JOIN dispositivos d ON m.id_dispositivo = d.id
            ORDER BY m.fecha_hora DESC
            LIMIT 10
        ");

        // 8. Logs recientes de sincronización (para TI & Administrador)
        $ultimosLogsSync = Database::query("
            SELECT l.*, d.nombre as dispositivo_nombre, d.ip as dispositivo_ip
            FROM log_sincronizacion l
            LEFT JOIN dispositivos d ON l.id_dispositivo = d.id
            ORDER BY l.id DESC
            LIMIT 5
        ");

        $totalUsuarios = (int)(Database::queryOne("SELECT COUNT(*) as c FROM usuarios_sistema WHERE activo = 1")['c'] ?? 0);

        require_once APP_ROOT . '/views/dashboard/index.php';
    }

    private function triggerAsyncCalculation(string $date): void {
        // Lock a nivel de MySQL: evita que 2 requests simultáneos disparen el cálculo
        $lockName = 'attendance_calc_' . $date;
        $gotLock = Database::queryOne("SELECT GET_LOCK(?, 0) as ok", [$lockName]);

        if (($gotLock['ok'] ?? 0) != 1) {
            return; // otro proceso ya lo está calculando, no hacer nada
        }

        try {
            // Lanzar el cálculo en background vía CLI en vez de bloquear el request
            $phpBin = defined('PHP_BIN') ? PHP_BIN : (PHP_BINARY ?: 'php');
            $script = APP_ROOT . '/app/Console/process_attendance.php';
            $cmd = "\"$phpBin\" \"$script\" \"$date\"";

            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                pclose(popen("start /B \"\" $cmd", "r"));
            } else {
                exec("$cmd > /dev/null 2>&1 &");
            }
        } finally {
            Database::execute("SELECT RELEASE_LOCK(?)", [$lockName]);
        }
    }

    /**
     * Endpoint AJAX: Retorna datos de tendencia filtrados por rango de tiempo y departamento
     */
    public function getTendenciaDatos(): void {
        AuthController::checkAuth();
        AuthController::requirePermission('dashboard');

        $currentUser = AuthController::user();
        $periodo = $_GET['periodo'] ?? '7d';
        $deptoId = !empty($_GET['departamento_id']) && $_GET['departamento_id'] !== 'all' 
            ? (int)$_GET['departamento_id'] 
            : null;

        // Si el usuario es SUPERVISOR con departamento asignado, forzar su departamento
        if (($currentUser['rol'] ?? '') === 'SUPERVISOR' && !empty($currentUser['departamento_id'])) {
            $deptoId = (int)$currentUser['departamento_id'];
        }

        $today = date('Y-m-d');
        $labelPeriodo = 'Últimos 7 Días';

        switch ($periodo) {
            case '15d':
                $fechaInicio = date('Y-m-d', strtotime("-14 days"));
                $fechaFin = $today;
                $labelPeriodo = 'Últimos 15 Días';
                break;
            case '30d':
                $fechaInicio = date('Y-m-d', strtotime("-29 days"));
                $fechaFin = $today;
                $labelPeriodo = 'Últimos 30 Días';
                break;
            case 'mes_actual':
                $fechaInicio = date('Y-m-01');
                $fechaFin = $today;
                $labelPeriodo = 'Este Mes (' . date('m/Y') . ')';
                break;
            case 'mes_anterior':
                $fechaInicio = date('Y-m-01', strtotime('first day of last month'));
                $fechaFin = date('Y-m-t', strtotime('last month'));
                $labelPeriodo = 'Mes Anterior (' . date('m/Y', strtotime('last month')) . ')';
                break;
            case '7d':
            default:
                $periodo = '7d';
                $fechaInicio = date('Y-m-d', strtotime("-6 days"));
                $fechaFin = $today;
                $labelPeriodo = 'Últimos 7 Días';
                break;
        }

        $params = [$fechaInicio, $fechaFin];
        $deptoSql = "";
        if ($deptoId !== null) {
            $deptoSql = "AND e.departamento_id = ?";
            $params[] = $deptoId;
        }

        $tendenciaRows = Database::query("
            SELECT 
                a.fecha,
                SUM(CASE WHEN a.estado = 'PRESENTE' AND a.hora_entrada_real IS NOT NULL THEN 1 ELSE 0 END) as presentes,
                SUM(CASE WHEN a.estado = 'TARDANZA' THEN 1 ELSE 0 END) as tardanzas,
                SUM(CASE WHEN a.estado = 'FALTA' OR a.estado = 'FALTA_INJUSTIFICADA' THEN 1 ELSE 0 END) as faltas
            FROM asistencia_diaria a
            JOIN empleados e ON e.id = a.id_empleado
            WHERE a.fecha BETWEEN ? AND ?
            $deptoSql
            GROUP BY a.fecha
            ORDER BY a.fecha ASC
        ", $params);

        $tendenciaMap = [];
        foreach ($tendenciaRows as $tr) {
            $tendenciaMap[$tr['fecha']] = $tr;
        }

        $chartLabels = [];
        $chartPresentes = [];
        $chartTardanzas = [];
        $chartFaltas = [];

        $current = strtotime($fechaInicio);
        $end = strtotime($fechaFin);

        while ($current <= $end) {
            $dStr = date('Y-m-d', $current);
            $chartLabels[] = date('d/m', $current);
            if (isset($tendenciaMap[$dStr])) {
                $chartPresentes[] = (int)$tendenciaMap[$dStr]['presentes'];
                $chartTardanzas[] = (int)$tendenciaMap[$dStr]['tardanzas'];
                $chartFaltas[] = (int)$tendenciaMap[$dStr]['faltas'];
            } else {
                $chartPresentes[] = 0;
                $chartTardanzas[] = 0;
                $chartFaltas[] = 0;
            }
            $current = strtotime("+1 day", $current);
        }

        $totalPresentes = array_sum($chartPresentes);
        $totalTardanzas = array_sum($chartTardanzas);
        $totalFaltas = array_sum($chartFaltas);
        $totalGeneral = $totalPresentes + $totalTardanzas + $totalFaltas;

        $porcPresentes = $totalGeneral > 0 ? round(($totalPresentes / $totalGeneral) * 100, 1) : 0;
        $porcTardanzas = $totalGeneral > 0 ? round(($totalTardanzas / $totalGeneral) * 100, 1) : 0;
        $porcFaltas = $totalGeneral > 0 ? round(($totalFaltas / $totalGeneral) * 100, 1) : 0;

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'periodo' => $periodo,
            'labelPeriodo' => $labelPeriodo,
            'labels' => $chartLabels,
            'datasets' => [
                'presentes' => $chartPresentes,
                'tardanzas' => $chartTardanzas,
                'faltas' => $chartFaltas
            ],
            'series' => [
                'presentes' => $chartPresentes,
                'tardanzas' => $chartTardanzas,
                'faltas' => $chartFaltas
            ],
            'totales' => [
                'presentes' => $totalPresentes,
                'tardanzas' => $totalTardanzas,
                'faltas' => $totalFaltas,
                'total_general' => $totalGeneral,
                'porc_presentes' => $porcPresentes,
                'porc_tardanzas' => $porcTardanzas,
                'porc_faltas' => $porcFaltas
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
