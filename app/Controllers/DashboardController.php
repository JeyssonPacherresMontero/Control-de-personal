<?php
namespace App\Controllers;

use App\Database;
use App\Services\AttendanceCalculator;

class DashboardController {
    public function index(): void {
        AuthController::checkAuth();

        $today = date('Y-m-d');

        // Auto procesar asistencia de hoy para que las métricas estén actualizadas al segundo
        $calculator = new AttendanceCalculator(ATTENDANCE_DEBOUNCE_MINUTES);
        $calculator->processDate($today);

        // Estadísticas de hoy
        $totalEmpleados = (int)(Database::queryOne("SELECT COUNT(*) as c FROM empleados WHERE activo = 1")['c'] ?? 0);
        
        $statsHoy = Database::queryOne("
            SELECT 
                SUM(CASE WHEN estado = 'PRESENTE' THEN 1 ELSE 0 END) as presentes,
                SUM(CASE WHEN estado = 'TARDANZA' THEN 1 ELSE 0 END) as tardanzas,
                SUM(CASE WHEN estado = 'FALTA' OR estado = 'FALTA_INJUSTIFICADA' THEN 1 ELSE 0 END) as faltas,
                SUM(CASE WHEN estado = 'JUSTIFICADO' OR estado = 'PERMISO' OR estado = 'VACACIONES' THEN 1 ELSE 0 END) as justificados,
                SUM(CASE WHEN estado = 'SALIDA_SIN_MARCAR' THEN 1 ELSE 0 END) as sin_salida,
                SUM(minutos_tardanza) as total_minutos_tardanza,
                SUM(minutos_extra) as total_minutos_extra
            FROM asistencia_diaria 
            WHERE fecha = ?
        ", [$today]);

        // Dispositivos biométricos
        $dispositivos = Database::query("SELECT * FROM dispositivos ORDER BY id ASC");
        $dispositivosOnline = 0;
        foreach ($dispositivos as $d) {
            if ($d['estado_conexion'] === 'ONLINE') $dispositivosOnline++;
        }

        // Últimas 10 marcaciones en vivo
        $ultimasMarcaciones = Database::query("
            SELECT m.*, e.nombres, e.apellidos, d.nombre as dispositivo_nombre
            FROM marcaciones m
            LEFT JOIN empleados e ON m.id_empleado = e.id
            LEFT JOIN dispositivos d ON m.id_dispositivo = d.id
            ORDER BY m.fecha_hora DESC
            LIMIT 10
        ");

        // Top 5 tardanzas de hoy
        $topTardanzas = Database::query("
            SELECT a.*, e.nombres, e.apellidos, e.codigo_reloj, t.nombre as turno_nombre, d.nombre as depto_nombre
            FROM asistencia_diaria a
            JOIN empleados e ON a.id_empleado = e.id
            LEFT JOIN turnos t ON a.id_turno = t.id
            LEFT JOIN departamentos d ON e.departamento_id = d.id
            WHERE a.fecha = ? AND a.minutos_tardanza > 0
            ORDER BY a.minutos_tardanza DESC
            LIMIT 5
        ", [$today]);

        require_once APP_ROOT . '/views/dashboard/index.php';
    }
}
