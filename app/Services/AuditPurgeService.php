<?php

namespace App\Services;

use App\Database;
use PDO;

class AuditPurgeService
{
    /**
     * Obtiene estadísticas completas de tablas de auditoría y archivo histórico
     */
    public static function getAuditStats(int $cutoffMonths = 6): array
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$cutoffMonths} months"));

        // Métricas de eventos_asistencia
        $eventosStats = Database::queryOne("
            SELECT 
                COUNT(*) as total,
                MIN(created_at) as min_fecha,
                MAX(created_at) as max_fecha,
                SUM(CASE WHEN created_at < :cutoff THEN 1 ELSE 0 END) as depurables
            FROM eventos_asistencia
        ", [':cutoff' => $cutoffDate]) ?: ['total' => 0, 'min_fecha' => null, 'max_fecha' => null, 'depurables' => 0];

        // Métricas de cola_eventos_asistencia
        $colaStats = Database::queryOne("
            SELECT 
                COUNT(*) as total,
                MIN(creado_en) as min_fecha,
                MAX(creado_en) as max_fecha,
                SUM(CASE WHEN estado IN ('PROCESSED', 'FAILED') AND creado_en < :cutoff THEN 1 ELSE 0 END) as depurables,
                SUM(CASE WHEN estado IN ('PENDING', 'RETRY', 'PROCESSING') THEN 1 ELSE 0 END) as pendientes_activos
            FROM cola_eventos_asistencia
        ", [':cutoff' => $cutoffDate]) ?: ['total' => 0, 'min_fecha' => null, 'max_fecha' => null, 'depurables' => 0, 'pendientes_activos' => 0];

        // Métricas de tablas históricas
        $histEventosTotal = Database::queryOne("SELECT COUNT(*) as c FROM eventos_asistencia_historico")['c'] ?? 0;
        $histColaTotal = Database::queryOne("SELECT COUNT(*) as c FROM cola_eventos_asistencia_historico")['c'] ?? 0;

        // Tamaño físico estimado en MySQL (Data + Index size en MB)
        $sizes = Database::query("
            SELECT table_name, ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
            FROM information_schema.TABLES
            WHERE table_schema = DATABASE()
              AND table_name IN ('eventos_asistencia', 'cola_eventos_asistencia', 'eventos_asistencia_historico', 'cola_eventos_asistencia_historico')
        ");
        $tableSizes = [];
        foreach ($sizes as $s) {
            $tableSizes[$s['table_name']] = (float)$s['size_mb'];
        }

        // Últimos 5 mantenimientos realizados
        $ultimosMantenimientos = Database::query("
            SELECT * FROM mantenimiento_auditoria_logs
            ORDER BY id DESC
            LIMIT 5
        ");

        return [
            'cutoff_months' => $cutoffMonths,
            'cutoff_date' => $cutoffDate,
            'eventos' => [
                'total' => (int)($eventosStats['total'] ?? 0),
                'min_fecha' => $eventosStats['min_fecha'],
                'max_fecha' => $eventosStats['max_fecha'],
                'depurables' => (int)($eventosStats['depurables'] ?? 0),
                'size_mb' => $tableSizes['eventos_asistencia'] ?? 0.0
            ],
            'cola' => [
                'total' => (int)($colaStats['total'] ?? 0),
                'min_fecha' => $colaStats['min_fecha'],
                'max_fecha' => $colaStats['max_fecha'],
                'depurables' => (int)($colaStats['depurables'] ?? 0),
                'pendientes_activos' => (int)($colaStats['pendientes_activos'] ?? 0),
                'size_mb' => $tableSizes['cola_eventos_asistencia'] ?? 0.0
            ],
            'historico' => [
                'eventos_total' => (int)$histEventosTotal,
                'eventos_size_mb' => $tableSizes['eventos_asistencia_historico'] ?? 0.0,
                'cola_total' => (int)$histColaTotal,
                'cola_size_mb' => $tableSizes['cola_eventos_asistencia_historico'] ?? 0.0
            ],
            'historial_mantenimientos' => $ultimosMantenimientos
        ];
    }

    /**
     * Ejecuta el proceso de archivado y depuración segura de tablas de auditoría
     */
    public static function purgeAndArchive(
        int $months = 6,
        bool $archive = true,
        string $operator = 'SYSTEM',
        ?string $ip = null,
        string $notes = ''
    ): array {
        $startTime = microtime(true);
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$months} months"));
        $modo = $archive ? 'ARCHIVAR_Y_DEPURAR' : 'DEPURACION_DIRECTA';

        $archivedEvents = 0;
        $deletedEvents = 0;
        $archivedQueue = 0;
        $deletedQueue = 0;

        try {
            // Iniciar transacción de base de datos
            Database::beginTransaction();

            // 1. PROCESAR eventos_asistencia
            if ($archive) {
                // Copiar a tabla histórica (ON DUPLICATE KEY UPDATE o INSERT IGNORE para idempotencia)
                $archivedEvents = Database::execute("
                    INSERT INTO eventos_asistencia_historico 
                        (id, aggregate_type, aggregate_id, event_type, event_data, version, created_by, ip_address, created_at, archived_at)
                    SELECT id, aggregate_type, aggregate_id, event_type, event_data, version, created_by, ip_address, created_at, NOW()
                    FROM eventos_asistencia
                    WHERE created_at < :cutoff
                    ON DUPLICATE KEY UPDATE archived_at = VALUES(archived_at)
                ", [':cutoff' => $cutoffDate]);
            }

            // Eliminar de la tabla activa
            $deletedEvents = Database::execute("
                DELETE FROM eventos_asistencia
                WHERE created_at < :cutoff
            ", [':cutoff' => $cutoffDate]);

            // 2. PROCESAR cola_eventos_asistencia (solo registros terminados: PROCESSED o FAILED)
            if ($archive) {
                $archivedQueue = Database::execute("
                    INSERT INTO cola_eventos_asistencia_historico
                        (id, id_dispositivo, device_serial, user_id, timestamp, punch_type, verify_type, uid_dispositivo, origen, idempotency_key, estado, intentos, ultimo_error, creado_en, actualizado_en, archived_at)
                    SELECT id, id_dispositivo, device_serial, user_id, timestamp, punch_type, verify_type, uid_dispositivo, origen, idempotency_key, estado, intentos, ultimo_error, creado_en, actualizado_en, NOW()
                    FROM cola_eventos_asistencia
                    WHERE estado IN ('PROCESSED', 'FAILED')
                      AND creado_en < :cutoff
                    ON DUPLICATE KEY UPDATE archived_at = VALUES(archived_at)
                ", [':cutoff' => $cutoffDate]);
            }

            $deletedQueue = Database::execute("
                DELETE FROM cola_eventos_asistencia
                WHERE estado IN ('PROCESSED', 'FAILED')
                  AND creado_en < :cutoff
            ", [':cutoff' => $cutoffDate]);

            $durationMs = (int)round((microtime(true) - $startTime) * 1000);

            // Registrar log de mantenimiento en la tabla de control
            Database::execute("
                INSERT INTO mantenimiento_auditoria_logs
                    (usuario, meses_retencion, modo, eventos_archivados, eventos_eliminados, cola_archivada, cola_eliminada, duracion_ms, ip_origen, detalles)
                VALUES
                    (:usr, :meses, :modo, :ea, :ed, :ca, :cd, :dur, :ip, :det)
            ", [
                ':usr' => $operator,
                ':meses' => $months,
                ':modo' => $modo,
                ':ea' => $archivedEvents,
                ':ed' => $deletedEvents,
                ':ca' => $archivedQueue,
                ':cd' => $deletedQueue,
                ':dur' => $durationMs,
                ':ip' => $ip,
                ':det' => $notes ?: sprintf("Depuración semestral/periódica de registros anteriores a %s", $cutoffDate)
            ]);

            Database::commit();

            // Optimización de tablas (fuera de transacción)
            try {
                Database::execute("OPTIMIZE TABLE eventos_asistencia, cola_eventos_asistencia");
            } catch (\Throwable $optEx) {
                // Silencioso si el storage engine no requiere optimize en línea
            }

            // Registrar en log físico de auditoría
            $logMsg = sprintf(
                "[%s] [AUDIT_PURGE] Modo: %s | Operador: %s | Corte: %s (%d meses) | Eventos: %d arch / %d del | Cola: %d arch / %d del | Tiempo: %d ms\n",
                date('Y-m-d H:i:s'),
                $modo,
                $operator,
                $cutoffDate,
                $months,
                $archivedEvents,
                $deletedEvents,
                $archivedQueue,
                $deletedQueue,
                $durationMs
            );
            @file_put_contents(APP_ROOT . '/storage/logs/audit_purge.log', $logMsg, FILE_APPEND);

            return [
                'success' => true,
                'modo' => $modo,
                'cutoff_date' => $cutoffDate,
                'months' => $months,
                'eventos_archivados' => $archivedEvents,
                'eventos_eliminados' => $deletedEvents,
                'cola_archivada' => $archivedQueue,
                'cola_eliminada' => $deletedQueue,
                'duracion_ms' => $durationMs,
                'message' => sprintf(
                    'Depuración completada: %d eventos y %d elementos de cola procesados en %d ms.',
                    $deletedEvents,
                    $deletedQueue,
                    $durationMs
                )
            ];

        } catch (\Throwable $e) {
            Database::rollBack();
            $errorMsg = sprintf("[%s] [ERROR_PURGE] Falló depuración: %s\n", date('Y-m-d H:i:s'), $e->getMessage());
            @file_put_contents(APP_ROOT . '/storage/logs/audit_purge.log', $errorMsg, FILE_APPEND);

            return [
                'success' => false,
                'error' => 'Error al ejecutar depuración de auditoría: ' . $e->getMessage()
            ];
        }
    }
}
