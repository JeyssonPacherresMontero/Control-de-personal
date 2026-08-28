<?php
/**
 * CLI Script: Ejecuta el procesamiento de asistencia diaria
 * Uso:
 *   php app/Console/process_attendance.php                (Procesa hoy)
 *   php app/Console/process_attendance.php 2026-08-27     (Procesa fecha específica)
 *   php app/Console/process_attendance.php 2026-08-01 2026-08-27 (Procesa rango de fechas)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../Services/AttendanceCalculator.php';

use App\Services\AttendanceCalculator;

$calculator = new AttendanceCalculator(ATTENDANCE_DEBOUNCE_MINUTES);

$arg1 = $argv[1] ?? date('Y-m-d');
$arg2 = $argv[2] ?? null;

if ($arg2 !== null) {
    echo "Procesando rango de asistencia desde $arg1 hasta $arg2...\n";
    $results = $calculator->processDateRange($arg1, $arg2);
    foreach ($results as $r) {
        echo "[{$r['date']}] Procesados: {$r['processed']} | Presentes: {$r['present']} | Tardanzas: {$r['late']} | Faltas: {$r['absent']} | Justificados: {$r['justified']}\n";
    }
} else {
    echo "Procesando asistencia para la fecha: $arg1...\n";
    $r = $calculator->processDate($arg1);
    echo "Completado: {$r['processed']} empleados evaluados.\n";
    echo "  - Presentes:   {$r['present']}\n";
    echo "  - Tardanzas:   {$r['late']}\n";
    echo "  - Faltas:      {$r['absent']}\n";
    echo "  - Justificados: {$r['justified']}\n";
    echo "  - Sin salida:  {$r['missing_exit']}\n";
}
