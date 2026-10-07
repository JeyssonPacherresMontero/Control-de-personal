<?php
declare(strict_types=1);

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
define('APP_DEBUG', filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN));
define('API_SECRET_KEY', $_ENV['API_SECRET_KEY'] ?? 'zk_push_secret_key_8e94a1b89df50c3a218f4a');

// Detección de binario PHP para tareas CLI en background
if (!defined('PHP_BIN')) {
    $detectedBin = $_ENV['PHP_BIN'] ?? (PHP_BINARY ?: 'php');
    if (str_ends_with(strtolower($detectedBin), 'httpd.exe') || !file_exists($detectedBin)) {
        if (file_exists('C:/xampp/php/php.exe')) {
            $detectedBin = 'C:/xampp/php/php.exe';
        }
    }
    define('PHP_BIN', $detectedBin);
}

// Manejo Global de Errores y Excepciones para Producción
if (!defined('APP_ERROR_HANDLER_REGISTERED')) {
    define('APP_ERROR_HANDLER_REGISTERED', true);

    $storageLogsDir = APP_ROOT . '/storage/logs';
    if (!file_exists($storageLogsDir)) {
        @mkdir($storageLogsDir, 0777, true);
    }

    ini_set('log_errors', '1');
    ini_set('error_log', $storageLogsDir . '/php_error.log');

    if (!APP_DEBUG) {
        error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
    } else {
        error_reporting(E_ALL);
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
    }

    set_exception_handler(function (\Throwable $e) {
        $logEntry = sprintf(
            "[%s] Uncaught Exception: %s in %s:%d\nStack trace:\n%s\n\n",
            date('Y-m-d H:i:s'),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );
        @error_log($logEntry);

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
                  || isset($_GET['ajax']) 
                  || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if (!headers_sent()) {
            http_response_code(500);
        }

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => APP_DEBUG ? $e->getMessage() : 'Ocurrió un error interno al procesar la solicitud.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (APP_DEBUG) {
            echo "<div style='font-family: monospace; background: #fee2e2; color: #991b1b; padding: 20px; border: 2px solid #ef4444; border-radius: 8px; margin: 20px;'>";
            echo "<h2 style='margin-top: 0;'>Excepción no capturada</h2>";
            echo "<p><b>Mensaje:</b> " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p><b>Archivo:</b> " . htmlspecialchars($e->getFile()) . " en la línea " . $e->getLine() . "</p>";
            echo "<pre style='background: white; padding: 10px; border-radius: 4px; overflow: auto;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
            echo "</div>";
            exit;
        }

        echo "<!DOCTYPE html><html lang='es'><head><meta charset='utf-8'><title>Error del Servidor</title><style>body{font-family: sans-serif; background: #f8fafc; color: #334155; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0;}.card{background: white; padding: 35px 40px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.06); text-align: center; max-width: 480px;}h1{color: #e11d48; margin-top: 0;}a{display: inline-block; margin-top: 15px; background: #0284c7; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: bold;}</style></head><body><div class='card'><h1>500 - Error del Sistema</h1><p>Ha ocurrido una situación inesperada al procesar tu solicitud. El incidente ha sido registrado para revisión técnica.</p><a href='?route=dashboard'>Volver al Tablero Principal</a></div></body></html>";
        exit;
    });
}

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

// Configuración de Sesión Segura (Hardening OWASP)
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,   // true en producción con HTTPS real
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_name('ZKCTRL_SESSID'); // evita exponer que es PHP
    session_start();
}

// Control de Inactividad de Sesión (Idle Timeout: 4 Horas = 14400 segundos por defecto)
if (isset($_SESSION['user_id'])) {
    $maxIdleTime = (int)($_ENV['SESSION_IDLE_TIMEOUT'] ?? 14400);
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $maxIdleTime)) {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();
        header('Location: ?route=login&msg=sesion_expirada');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

/**
 * Helpers globales para CSRF
 */
function csrf_token(): string {
    return \App\Security\Csrf::token();
}

function csrf_field(): string {
    return \App\Security\Csrf::field();
}


