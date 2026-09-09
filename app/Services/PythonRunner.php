<?php
namespace App\Services;

/**
 * Servicio Centralizado para Ejecución Segura y Aislada de Scripts Python
 * 
 * Previene la contaminación de entorno causada por ZKBioTime (PYTHONHOME/PYTHONPATH),
 * garantiza codificación UTF-8 consistente, captura salidas completas y maneja timeouts.
 */
class PythonRunner {

    /**
     * Construye un array de entorno limpio eliminando PYTHONHOME/PYTHONPATH
     * que ZKBioTime u otros softwares inyectan globalmente y que corrompen
     * cualquier intérprete Python estándar.
     */
    public static function buildCleanEnv(): array {
        $env = [];
        
        // Variables esenciales de Windows
        $systemRoot = getenv('SystemRoot') ?: (getenv('windir') ?: 'C:\\Windows');
        $env['SystemRoot'] = $systemRoot;
        $env['windir'] = $systemRoot;
        $env['COMSPEC'] = getenv('COMSPEC') ?: 'C:\\Windows\\system32\\cmd.exe';
        $env['PATHEXT'] = getenv('PATHEXT') ?: '.COM;.EXE;.BAT;.CMD;.VBS;.VBE;.JS;.JSE;.WSF;.WSH;.MSC';

        // Copiar todas las variables de entorno actuales evitando cabeceras HTTP
        foreach ($_SERVER as $k => $v) {
            if (is_string($v) && !str_starts_with($k, 'HTTP_')) {
                $env[$k] = $v;
            }
        }

        // Combinar con getenv() para cubrir variables del sistema
        foreach (getenv() as $k => $v) {
            if (!isset($env[$k]) && is_string($v)) {
                $env[$k] = $v;
            }
        }
        
        // Eliminar explícitamente variables tóxicas inyectadas por ZKBioTime
        unset($env['PYTHONHOME'], $env['PYTHONPATH']);
        
        // Limpiar PATH: remover cualquier ruta vinculada a ZKBioTime
        $pathKey = isset($env['Path']) ? 'Path' : (isset($env['PATH']) ? 'PATH' : 'Path');
        $rawPath = $env[$pathKey] ?? getenv('Path') ?: getenv('PATH') ?: '';
        
        $paths = explode(';', $rawPath);
        $cleanPaths = array_filter($paths, function($p) {
            return trim($p) !== '' && stripos($p, 'ZKBioTime') === false;
        });

        // Asegurar que System32 esté presente para soporte de sockets y red
        $sys32 = $systemRoot . '\\system32';
        $sys32Lower = strtolower($sys32);
        $hasSys32 = false;
        foreach ($cleanPaths as $cp) {
            if (strtolower(rtrim($cp, '\\/')) === $sys32Lower) {
                $hasSys32 = true;
                break;
            }
        }
        if (!$hasSys32) {
            array_unshift($cleanPaths, $sys32);
        }

        $cleanPathStr = implode(';', $cleanPaths);
        $env[$pathKey] = $cleanPathStr;
        $env['Path'] = $cleanPathStr;
        $env['PATH'] = $cleanPathStr;
        
        // Forzar codificación UTF-8 en E/S de Python
        $env['PYTHONIOENCODING'] = 'utf-8';
        $env['PYTHONUTF8'] = '1';
        $env['PYTHONUNBUFFERED'] = '1';
        
        return $env;
    }

    /**
     * Ejecuta un script Python con argumentos de forma aislada y segura.
     * 
     * @param string $scriptPath Ruta absoluta al script .py
     * @param array $args Argumentos que se pasarán al script
     * @param int $timeoutSegundos Tiempo máximo de espera en segundos
     * @return array [
     *     'success' => bool,
     *     'output' => string,
     *     'stdout' => string,
     *     'stderr' => string,
     *     'exit_code' => int
     * ]
     */
    public static function run(string $scriptPath, array $args = [], int $timeoutSegundos = 60): array {
        $pythonBin = defined('PYTHON_BIN') ? PYTHON_BIN : 'python';
        
        if (!file_exists($scriptPath)) {
            return [
                'success' => false,
                'output' => "El script Python especificado no existe: {$scriptPath}",
                'stdout' => '',
                'stderr' => "Archivo no encontrado: {$scriptPath}",
                'exit_code' => -1
            ];
        }

        $escapedArgs = array_map(function($arg) {
            return escapeshellarg((string)$arg);
        }, $args);

        $cmd = "\"{$pythonBin}\" -E \"{$scriptPath}\" " . implode(' ', $escapedArgs);
        $env = self::buildCleanEnv();
        $cwd = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];

        $process = @proc_open($cmd, $descriptors, $pipes, $cwd, $env);

        if (!is_resource($process)) {
            return [
                'success' => false,
                'output' => 'No se pudo inicializar el proceso de Python (proc_open falló).',
                'stdout' => '',
                'stderr' => 'Error al invocar proc_open',
                'exit_code' => -1
            ];
        }

        // Cerrar stdin de inmediato ya que no requerimos interacción interactiva
        fclose($pipes[0]);

        // Configurar pipes no bloqueantes para poder respetar el timeout
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $startTime = time();

        while (true) {
            $read = [$pipes[1], $pipes[2]];
            $write = null;
            $except = null;

            $numChangedStreams = @stream_select($read, $write, $except, 0, 200000); // 200ms

            if ($numChangedStreams > 0) {
                foreach ($read as $stream) {
                    if ($stream === $pipes[1]) {
                        $chunk = fread($pipes[1], 4096);
                        if ($chunk !== false) {
                            $stdout .= $chunk;
                        }
                    } elseif ($stream === $pipes[2]) {
                        $chunk = fread($pipes[2], 4096);
                        if ($chunk !== false) {
                            $stderr .= $chunk;
                        }
                    }
                }
            }

            $status = proc_get_status($process);
            if (!$status['running']) {
                // Leer remanentes en los pipes
                $remainingOut = stream_get_contents($pipes[1]);
                if ($remainingOut !== false) $stdout .= $remainingOut;

                $remainingErr = stream_get_contents($pipes[2]);
                if ($remainingErr !== false) $stderr .= $remainingErr;

                break;
            }

            if ((time() - $startTime) > $timeoutSegundos) {
                // Terminar proceso por timeout
                @proc_terminate($process, 9);
                fclose($pipes[1]);
                fclose($pipes[2]);
                @proc_close($process);

                return [
                    'success' => false,
                    'output' => "Tiempo de espera agotado ({$timeoutSegundos}s) al ejecutar el script.",
                    'stdout' => self::cleanEncoding($stdout),
                    'stderr' => "Timeout alcanzado ({$timeoutSegundos}s)",
                    'exit_code' => 124
                ];
            }
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $stdoutClean = self::cleanEncoding(trim($stdout));
        $stderrClean = self::cleanEncoding(trim($stderr));
        
        $combinedOutput = trim($stdoutClean . "\n" . $stderrClean);

        return [
            'success' => ($exitCode === 0),
            'output' => $combinedOutput,
            'stdout' => $stdoutClean,
            'stderr' => $stderrClean,
            'exit_code' => $exitCode
        ];
    }

    /**
     * Asegura que el string resultante tenga una codificación UTF-8 válida.
     */
    public static function cleanEncoding(string $str): string {
        if ($str === '') {
            return '';
        }
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($str, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252, CP850');
        }
        return utf8_encode($str);
    }
}
