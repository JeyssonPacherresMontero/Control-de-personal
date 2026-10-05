<?php
namespace App;

/**
 * ==========================================================
 * SISTEMA DE CONTROL DE PERSONAL Y ASISTENCIA - ZKTECO
 * Módulo de Protección Anti-CSRF (Cross-Site Request Forgery)
 * ==========================================================
 */
class Csrf extends \App\Security\Csrf {
    public static function getToken(): string {
        return self::token();
    }

    public static function verify(?string $token = null): bool {
        if ($token === null) {
            $token = $_POST['_csrf'] 
                  ?? $_POST['_csrf_token'] 
                  ?? $_SERVER['HTTP_X_CSRF_TOKEN'] 
                  ?? $_SERVER['HTTP_X_XSRF_TOKEN'] 
                  ?? '';
        }
        $sessionToken = self::token();
        return !empty($token) && hash_equals($sessionToken, (string)$token);
    }

    public static function validateRequest(): void {
        self::validate();
    }
}
