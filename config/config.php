<?php
/**
 * ==========================================================
 * SISTEMA DE CONTROL DE PERSONAL Y ASISTENCIA - ZKTECO
 * Archivo Principal de Configuración
 * ==========================================================
 */

// Cargar variables de entorno desde archivo .env si existe
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            list($key, $val) = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val);
            // Quitar comillas si las tiene
            $val = trim($val, "\"'");
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $val;
                putenv("$key=$val");
            }
        }
    }
}

// Configuración de Zona Horaria
$timezone = $_ENV['APP_TIMEZONE'] ?? 'America/Lima';
date_default_timezone_set($timezone);

// Constantes de Base de Datos
define('DB_HOST', $_ENV['DB_HOST'] ?? '127.0.0.1');
define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'control_personal');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

// Constantes de Aplicación
define('APP_NAME', $_ENV['APP_NAME'] ?? 'Sistema de Control de Asistencia');
define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost:8080/control_personal');
define('APP_ROOT', dirname(__DIR__));
define('ATTENDANCE_DEBOUNCE_MINUTES', (int)($_ENV['ATTENDANCE_DEBOUNCE_MINUTES'] ?? 3));

// Configuración del ejecutable de Python (autodetección robusta)
// NOTA: ZKBioTime instala un Python embebido en C:\ZKBioTime\Python311 que corrompe
// el entorno global via PYTHONHOME. Se excluyen explícitamente esas rutas.
$pythonCandidate = $_ENV['PYTHON_BIN'] ?? null;
if ($pythonCandidate && stripos($pythonCandidate, 'ZKBioTime') !== false) {
    $pythonCandidate = null; // Ignorar Python de ZKBioTime
}
if (!$pythonCandidate || !file_exists($pythonCandidate)) {
    $possiblePaths = [
        'C:/Python313/python.exe',
        'C:/Python312/python.exe',
        'C:/Python311/python.exe',
        'C:/Python310/python.exe',
        'C:/Program Files/Python313/python.exe',
        'C:/Program Files/Python312/python.exe',
        'C:/Program Files/Python311/python.exe',
    ];
    $pythonCandidate = null;
    foreach ($possiblePaths as $p) {
        // Saltar cualquier ruta que contenga ZKBioTime
        if (stripos($p, 'ZKBioTime') !== false) {
            continue;
        }
        if (file_exists($p)) {
            $pythonCandidate = $p;
            break;
        }
    }
    if (!$pythonCandidate) {
        $pythonCandidate = 'python'; // Fallback al PATH del sistema
    }
}
define('PYTHON_BIN', $pythonCandidate);

// Autoloader para clases con namespace App\
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = APP_ROOT . '/app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// Configuración de Sesión Segura
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

