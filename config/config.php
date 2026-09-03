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

// Constantes de Aplicación & Identidad Institucional
define('APP_NAME', $_ENV['APP_NAME'] ?? 'JUSHSAL - Control de Personal y Asistencia');
define('COMPANY_NAME', 'JUSHSAL');
define('COMPANY_FULL_NAME', 'Junta de Usuarios del Sector Hidráulico Menor San Lorenzo');
define('COMPANY_LOGO', 'public/img/logo_jushsal.png');
define('COMPANY_ICON', 'public/img/logo_icon.png');
define('COMPANY_FAVICON', 'public/img/favicon.png');
define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost:8080/control_personal');
define('APP_ROOT', dirname(__DIR__));
define('ATTENDANCE_DEBOUNCE_MINUTES', (int)($_ENV['ATTENDANCE_DEBOUNCE_MINUTES'] ?? 3));

/**
 * Obtener Data URI (base64) del logo institucional para garantizar
 * que SIEMPRE se muestre sin depender de rutas relativas o configuraciones de servidor.
 */
function jushsal_logo_data_uri(string $type = 'icon'): string {
    static $cache = [];
    if (isset($cache[$type])) {
        return $cache[$type];
    }
    $fileMap = [
        'icon' => APP_ROOT . '/public/img/logo_icon.png',
        'full' => APP_ROOT . '/public/img/logo_jushsal.png',
        'clean' => APP_ROOT . '/public/img/logo_jushsal.png',
        'favicon' => APP_ROOT . '/public/img/favicon.png',
    ];
    $filePath = $fileMap[$type] ?? $fileMap['icon'];
    if (!file_exists($filePath)) {
        $filePath = APP_ROOT . '/img/' . basename($filePath);
    }
    if (file_exists($filePath)) {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mime = match($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            default => 'image/png'
        };
        $data = base64_encode(file_get_contents($filePath));
        $cache[$type] = 'data:' . $mime . ';base64,' . $data;
        return $cache[$type];
    }
    return '';
}

/**
 * Genera la URL relativa correcta para cualquier archivo estático según el entorno de ejecución
 */
function asset(string $path): string {
    $path = ltrim($path, '/');
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    $scriptDir = str_replace('\\', '/', $scriptDir);
    $scriptDir = rtrim($scriptDir, '/');
    
    if (str_ends_with($scriptDir, '/public') && str_starts_with($path, 'public/')) {
        $path = substr($path, 7);
    }
    
    $base = $scriptDir !== '' ? $scriptDir : '';
    return $base . '/' . $path;
}

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

