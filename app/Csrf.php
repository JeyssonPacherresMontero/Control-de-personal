<?php
namespace App;

/**
 * ==========================================================
 * SISTEMA DE CONTROL DE PERSONAL Y ASISTENCIA - ZKTECO
 * Módulo de Protección Anti-CSRF (Cross-Site Request Forgery)
 * ==========================================================
 */
class Csrf {
    private const TOKEN_KEY = '_csrf_token';

    /**
     * Obtiene o genera el token CSRF actual almacenado en la sesión
     */
    public static function getToken(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION[self::TOKEN_KEY])) {
            $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::TOKEN_KEY];
    }

    /**
     * Genera un campo HTML hidden con el token CSRF para incrustar en formularios
     */
    public static function field(): string {
        $token = htmlspecialchars(self::getToken(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="_csrf_token" value="' . $token . '">';
    }

    /**
     * Verifica la validez del token CSRF recibido en la petición
     */
    public static function verify(?string $token = null): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $sessionToken = $_SESSION[self::TOKEN_KEY] ?? '';
        if (empty($sessionToken)) {
            return false;
        }

        if ($token === null) {
            $token = $_POST['_csrf_token'] 
                  ?? $_SERVER['HTTP_X_CSRF_TOKEN'] 
                  ?? $_SERVER['HTTP_X_XSRF_TOKEN'] 
                  ?? '';
        }

        if (empty($token) || !is_string($token)) {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    /**
     * Valida de forma estricta las solicitudes POST y aborta si el token es inválido
     */
    public static function validateRequest(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!self::verify()) {
                $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                          || isset($_GET['ajax'])
                          || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

                http_response_code(403);

                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                        'success' => false,
                        'error' => 'Error de seguridad: Token CSRF inválido o sesión expirada. Por favor recarga la página.'
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                }

                die('<!DOCTYPE html>
                <html lang="es">
                <head>
                    <meta charset="utf-8">
                    <title>403 - Solicitud no autorizada</title>
                    <style>
                        body { font-family: "Segoe UI", sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
                        .card { background: #1e293b; padding: 2rem; border-radius: 12px; border: 1px solid #334155; max-width: 480px; text-align: center; box-shadow: 0 10px 25px rgba(0,0,0,0.4); }
                        h2 { color: #f87171; margin-top: 0; }
                        p { color: #cbd5e1; font-size: 0.95rem; line-height: 1.5; }
                        a { display: inline-block; margin-top: 1rem; background: #2563eb; color: #fff; padding: 0.6rem 1.2rem; border-radius: 6px; text-decoration: none; font-weight: bold; }
                        a:hover { background: #1d4ed8; }
                    </style>
                </head>
                <body>
                    <div class="card">
                        <h2>Acceso Denegado (403)</h2>
                        <p>La solicitud no superó la validación de seguridad anti-CSRF o tu sesión ha caducado por inactividad.</p>
                        <a href="javascript:location.reload()">Recargar Página</a>
                    </div>
                </body>
                </html>');
            }
        }
    }
}
