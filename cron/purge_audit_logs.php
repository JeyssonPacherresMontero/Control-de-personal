<?php
/**
 * Script CLI para Depuración y Archivado Semestral de Auditoría
 *
 * Uso:
 *   php cron/purge_audit_logs.php [--months=6] [--no-archive] [--dry-run]
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Services/AuditPurgeService.php';

use App\Services\AuditPurgeService;

echo "==========================================================\n";
echo "JUSHSAL - Motor de Depuración y Archivado de Auditoría\n";
echo "==========================================================\n";

$options = getopt('', ['months::', 'no-archive', 'dry-run', 'help']);

if (isset($options['help'])) {
    echo "Parámetros disponibles:\n";
    echo "  --months=N     Meses de antigüedad a conservar (por defecto: 6)\n";
    echo "  --no-archive   Eliminar directamente sin copiar a tablas históricas\n";
    echo "  --dry-run      Mostrar registros elegibles sin modificar la base de datos\n";
    echo "  --help         Mostrar esta ayuda\n";
    exit(0);
}

$months = isset($options['months']) ? max(1, (int)$options['months']) : 6;
$archive = !isset($options['no-archive']);
$dryRun = isset($options['dry-run']);

echo "Fecha de corte: Menor a " . date('Y-m-d H:i:s', strtotime("-{$months} months")) . " ({$months} meses)\n";
echo "Modo: " . ($archive ? 'ARCHIVAR Y DEPURAR' : 'DEPURACIÓN DIRECTA') . "\n";
echo "Simulación (Dry Run): " . ($dryRun ? 'SÍ' : 'NO') . "\n\n";

$stats = AuditPurgeService::getAuditStats($months);

echo "1. Métricas Actuales:\n";
echo "   - Eventos activos en eventos_asistencia: " . number_format($stats['eventos']['total']) . " (" . $stats['eventos']['size_mb'] . " MB)\n";
echo "   - Eventos elegibles para depuración:     " . number_format($stats['eventos']['depurables']) . "\n";
echo "   - Elementos en cola procesados/fallidos: " . number_format($stats['cola']['depurables']) . "\n";
echo "   - Elementos en cola pendientes activos:  " . number_format($stats['cola']['pendientes_activos']) . "\n\n";

if ($dryRun) {
    echo "[DRY RUN] Finalizado sin realizar modificaciones.\n";
    exit(0);
}

if ($stats['eventos']['depurables'] === 0 && $stats['cola']['depurables'] === 0) {
    echo "No hay registros con antigüedad superior a {$months} meses para depurar.\n";
    echo "[OK] Base de datos en estado óptimo.\n";
    exit(0);
}

echo "2. Ejecutando depuración y archivado...\n";
$res = AuditPurgeService::purgeAndArchive($months, $archive, 'CRON_SCHEDULER', '127.0.0.1');

if ($res['success']) {
    echo "   [OK] " . $res['message'] . "\n";
    echo "   - Eventos archivados: " . number_format($res['eventos_archivados']) . "\n";
    echo "   - Eventos eliminados: " . number_format($res['eventos_eliminados']) . "\n";
    echo "   - Cola archivada:     " . number_format($res['cola_archivada']) . "\n";
    echo "   - Cola eliminada:     " . number_format($res['cola_eliminada']) . "\n";
    echo "   - Tiempo total:       " . $res['duracion_ms'] . " ms\n";
    echo "\n==========================================================\n";
    echo "¡Mantenimiento de auditoría completado con éxito!\n";
    exit(0);
} else {
    echo "\n[ERROR] " . ($res['error'] ?? 'Falla desconocida') . "\n";
    exit(1);
}
