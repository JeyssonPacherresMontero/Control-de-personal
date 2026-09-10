<?php
namespace App\Security;

class Csrf {
    public static function token(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function field(): string {
        $token = htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="_csrf" value="' . $token . '">';
    }

    public static function validate(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $sessionToken = $_SESSION['csrf_token'] ?? '';
        $sent = $_POST['_csrf'] 
             ?? $_POST['_csrf_token'] 
             ?? $_SERVER['HTTP_X_CSRF_TOKEN'] 
             ?? $_SERVER['HTTP_X_XSRF_TOKEN'] 
             ?? '';

        $valid = !empty($sessionToken) && !empty($sent) && hash_equals($sessionToken, (string)$sent);

        if (!$valid) {
            $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                      || isset($_GET['ajax'])
                      || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

            if ($isAjax) {
                http_response_code(419);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error' => 'Token de seguridad expirado o inválido. Recarga la página.'
                ], JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(419);
                echo '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>419 - Sesión de formulario expirada</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .card { background: #1e293b; padding: 2rem; border-radius: 12px; border: 1px solid #334155; max-width: 480px; text-align: center; box-shadow: 0 10px 25px rgba(0,0,0,0.4); }
        h2 { color: #f87171; margin-top: 0; }
        p { color: #cbd5e1; font-size: 0.95rem; line-height: 1.5; }
        a { display: inline-block; margin-top: 1rem; background: #2563eb; color: #fff; padding: 0.6rem 1.2rem; border-radius: 6px; text-decoration: none; font-weight: bold; }
        a:hover { background: #1d4ed8; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Sesión de formulario expirada</h2>
        <p>El token de seguridad ha caducado o no coincide. Por favor, vuelve atrás y reintenta la acción.</p>
        <p><a href="javascript:history.back()">Volver y reintentar</a></p>
    </div>
</body>
</html>';
            }
            exit;
        }
    }
}
