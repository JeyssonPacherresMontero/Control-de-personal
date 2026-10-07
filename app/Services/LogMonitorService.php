<?php

namespace App\Services;

class LogMonitorService
{
    private static array $allowedLogs = [
        'sync' => 'storage/logs/sync_current.log',
        'php' => 'storage/logs/php_error.log',
        'audit' => 'storage/logs/audit_purge.log'
    ];

    /**
     * Obtiene la ruta física absoluta de un archivo de log permitido
     */
    public static function getLogPath(string $key): ?string
    {
        if (!isset(self::$allowedLogs[$key])) {
            return null;
        }
        return APP_ROOT . '/' . self::$allowedLogs[$key];
    }

    /**
     * Lee eficientemente las últimas N líneas de un archivo de log sin sobrecargar la memoria RAM
     */
    public static function tail(string $filePath, int $lines = 100): array
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return [];
        }

        $fp = fopen($filePath, 'rb');
        if (!$fp) {
            return [];
        }

        $buffer = '';
        $chunkSize = 4096;
        $fileSize = filesize($filePath);
        if ($fileSize === 0) {
            fclose($fp);
            return [];
        }

        $pos = $fileSize;
        $lineCount = 0;
        $output = '';

        while ($pos > 0 && $lineCount <= $lines) {
            $readSize = min($chunkSize, $pos);
            $pos -= $readSize;
            fseek($fp, $pos);
            $chunk = fread($fp, $readSize);
            $output = $chunk . $output;
            $lineCount = substr_count($output, "\n");
        }

        fclose($fp);

        $allLines = explode("\n", trim($output));
        if (count($allLines) > $lines) {
            $allLines = array_slice($allLines, -$lines);
        }

        return array_values($allLines);
    }

    /**
     * Diagnostica el estado de salud de red de los terminales ZKTeco analizando sync_current.log
     */
    public static function getZkNetworkHealth(): array
    {
        $path = self::getLogPath('sync');
        $rawLines = self::tail($path, 200);

        $totalAttempts = 0;
        $successCount = 0;
        $failureCount = 0;
        $lastError = null;
        $lastErrorTime = null;
        $lastSuccessTime = null;
        $lastDevice = null;
        $lastLatency = null;
        $recentErrors = [];
        $devicesStatus = [];

        foreach ($rawLines as $line) {
            // Extracción de timestamps comunes: 2026-10-07 10:59:35 o [2026-10-07 11:00:29]
            if (preg_match('/(?:\[)?(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})(?:\])?/', $line, $m)) {
                $time = $m[1];
            } else {
                $time = null;
            }

            // Detección de dispositivos e IP
            if (preg_match('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d+)/', $line, $ipMatch)) {
                $deviceIp = $ipMatch[1];
                if (!isset($devicesStatus[$deviceIp])) {
                    $devicesStatus[$deviceIp] = [
                        'ip' => $deviceIp,
                        'puerto' => $ipMatch[2],
                        'estado' => 'UNKNOWN',
                        'ultima_latencia' => null,
                        'ultimo_contacto' => $time
                    ];
                }
                if ($time) {
                    $devicesStatus[$deviceIp]['ultimo_contacto'] = $time;
                }
            }

            // Detección de latencias
            if (preg_match('/Latency:\s*([\d\.]+)ms/i', $line, $latMatch)) {
                $lastLatency = (float)$latMatch[1];
                if (!empty($deviceIp)) {
                    $devicesStatus[$deviceIp]['ultima_latencia'] = $lastLatency;
                }
            }

            // Detección de éxito
            if (stripos($line, 'RESULT: SUCCESS') !== false || stripos($line, 'Conexión exitosa') !== false || stripos($line, 'Conexi?n exitosa') !== false || stripos($line, 'finalizado exitosamente') !== false) {
                $successCount++;
                if ($time) {
                    $lastSuccessTime = $time;
                }
                if (!empty($deviceIp)) {
                    $devicesStatus[$deviceIp]['estado'] = 'ONLINE';
                }
            }

            // Detección de errores y alertas de red ZKTeco
            $isError = false;
            if (stripos($line, 'can not connect') !== false ||
                stripos($line, 'connection refused') !== false ||
                stripos($line, 'timed out') !== false ||
                stripos($line, 'timeout') !== false ||
                stripos($line, 'error de conexi') !== false ||
                stripos($line, 'unreachable') !== false ||
                stripos($line, 'no se puede establecer una conexi') !== false ||
                stripos($line, '[ERROR]') !== false ||
                stripos($line, 'RESULT: FAILED') !== false) {
                $isError = true;
            }

            if ($isError) {
                $failureCount++;
                $lastError = trim($line);
                if ($time) {
                    $lastErrorTime = $time;
                }
                if (!empty($deviceIp)) {
                    $devicesStatus[$deviceIp]['estado'] = 'ERROR';
                }
                $recentErrors[] = [
                    'timestamp' => $time,
                    'mensaje' => trim($line)
                ];
            }
        }

        // Determinar estado de salud global de red
        if ($failureCount === 0 && $successCount > 0) {
            $status = 'HEALTHY';
            $statusLabel = 'Red Óptima (Sin errores)';
            $badgeClass = 'success';
        } elseif ($failureCount > 0 && $successCount > 0 && ($lastSuccessTime >= $lastErrorTime)) {
            $status = 'WARNING';
            $statusLabel = 'Estable con Advertencias Previas';
            $badgeClass = 'warning';
        } elseif ($failureCount > 0) {
            $status = 'CRITICAL';
            $statusLabel = 'Falla de Comunicación Biometría';
            $badgeClass = 'danger';
        } else {
            $status = 'IDLE';
            $statusLabel = 'En Espera de Sincronización';
            $badgeClass = 'secondary';
        }

        return [
            'status' => $status,
            'status_label' => $statusLabel,
            'badge_class' => $badgeClass,
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'last_success_time' => $lastSuccessTime,
            'last_error_time' => $lastErrorTime,
            'last_error_message' => $lastError,
            'last_latency_ms' => $lastLatency,
            'recent_errors' => array_slice(array_reverse($recentErrors), 0, 5),
            'devices' => array_values($devicesStatus)
        ];
    }

    /**
     * Diagnostica el estado de salud del backend analizando php_error.log
     */
    public static function getPhpErrorHealth(): array
    {
        $path = self::getLogPath('php');
        $rawLines = self::tail($path, 150);

        $fatalCount = 0;
        $warningCount = 0;
        $dbConnectionErrors = 0;
        $recentIssues = [];
        $lastIssue = null;
        $lastIssueTime = null;

        foreach ($rawLines as $line) {
            if (preg_match('/\[(\d{2}-\w{3}-\d{4}\s+\d{2}:\d{2}:\d{2})/', $line, $m)) {
                $time = $m[1];
            } else {
                $time = null;
            }

            if (stripos($line, 'Fatal error') !== false || stripos($line, 'Uncaught') !== false) {
                $fatalCount++;
                $lastIssue = trim($line);
                $lastIssueTime = $time;
                $recentIssues[] = ['type' => 'FATAL', 'time' => $time, 'text' => trim($line)];
            } elseif (stripos($line, 'Database connection error') !== false || stripos($line, 'SQLSTATE[HY000] [2002]') !== false) {
                $dbConnectionErrors++;
                $lastIssue = trim($line);
                $lastIssueTime = $time;
                $recentIssues[] = ['type' => 'DATABASE', 'time' => $time, 'text' => trim($line)];
            } elseif (stripos($line, 'Warning') !== false || stripos($line, 'Deprecated') !== false) {
                $warningCount++;
                $recentIssues[] = ['type' => 'WARNING', 'time' => $time, 'text' => trim($line)];
            }
        }

        if ($fatalCount === 0 && $dbConnectionErrors === 0) {
            $status = 'HEALTHY';
            $statusLabel = 'Servidor Estable';
            $badgeClass = 'success';
        } elseif ($dbConnectionErrors > 0) {
            $status = 'CRITICAL';
            $statusLabel = 'Falla de Conexión a Base de Datos';
            $badgeClass = 'danger';
        } else {
            $status = 'WARNING';
            $statusLabel = 'Advertencias en Ejecución';
            $badgeClass = 'warning';
        }

        return [
            'status' => $status,
            'status_label' => $statusLabel,
            'badge_class' => $badgeClass,
            'fatal_count' => $fatalCount,
            'warning_count' => $warningCount,
            'db_connection_errors' => $dbConnectionErrors,
            'last_issue' => $lastIssue,
            'last_issue_time' => $lastIssueTime,
            'recent_issues' => array_slice(array_reverse($recentIssues), 0, 5)
        ];
    }

    /**
     * Retorna información general y tamaño de los archivos de log
     */
    public static function getLogsOverview(): array
    {
        $overview = [];

        foreach (self::$allowedLogs as $key => $relPath) {
            $fullPath = APP_ROOT . '/' . $relPath;
            $exists = file_exists($fullPath);
            $size = $exists ? filesize($fullPath) : 0;
            $mtime = $exists ? filemtime($fullPath) : null;

            $overview[$key] = [
                'key' => $key,
                'path' => $relPath,
                'exists' => $exists,
                'size_bytes' => $size,
                'size_formatted' => self::formatBytes($size),
                'last_modified' => $mtime ? date('Y-m-d H:i:s', $mtime) : 'Sin registros',
                'lines_count' => $exists ? count(self::tail($fullPath, 500)) : 0
            ];
        }

        return $overview;
    }

    /**
     * Vacía el archivo de log con respaldo previo .bak
     */
    public static function clearLog(string $key, string $operator = 'ADMIN'): array
    {
        $path = self::getLogPath($key);
        if (!$path || !file_exists($path)) {
            return ['success' => false, 'error' => 'Archivo de log no encontrado'];
        }

        // Crear respaldo .bak
        $backupPath = $path . '.bak';
        @copy($path, $backupPath);

        // Truncar archivo
        $initialNotice = sprintf(
            "[%s America/Lima] === Log reiniciado y respaldado por el operador '%s' ===\n",
            date('d-M-Y H:i:s'),
            $operator
        );

        $written = @file_put_contents($path, $initialNotice);
        if ($written === false) {
            return ['success' => false, 'error' => 'Permisos insuficientes para truncar el archivo de log'];
        }

        return [
            'success' => true,
            'message' => 'Archivo de log respaldado como .bak y vaciado exitosamente.',
            'backup_created' => file_exists($backupPath)
        ];
    }

    private static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
