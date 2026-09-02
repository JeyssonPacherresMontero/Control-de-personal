<?php
namespace App\Controllers;

use App\Database;

class AuthController {
    public static function checkAuth(): void {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ?route=login');
            exit;
        }
    }

    public static function user(): ?array {
        if (isset($_SESSION['user_id'])) {
            return [
                'id' => $_SESSION['user_id'],
                'usuario' => $_SESSION['user_username'] ?? '',
                'nombre' => $_SESSION['user_name'] ?? 'Usuario',
                'rol' => $_SESSION['user_role'] ?? 'RRHH'
            ];
        }
        return null;
    }

    public static function role(): string {
        return $_SESSION['user_role'] ?? 'CONSULTA';
    }

    /**
     * Verifica si el usuario actual tiene alguno de los roles indicados.
     * @param string|array $roles
     * @return bool
     */
    public static function hasRole($roles): bool {
        self::checkAuth();
        $userRole = self::role();
        if (is_array($roles)) {
            return in_array($userRole, $roles, true);
        }
        return $userRole === $roles;
    }

    /**
     * Exige que el usuario tenga alguno de los roles indicados.
     * Si no cumple, redirige al dashboard con un mensaje de alerta.
     * @param string|array $roles
     * @param string $redirectRoute
     */
    public static function requireRole($roles, string $redirectRoute = 'dashboard'): void {
        self::checkAuth();
        if (!self::hasRole($roles)) {
            $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
                      || isset($_GET['ajax']) 
                      || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

            if ($isAjax) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Acceso denegado: No tienes permisos para ejecutar esta acción.'
                ]);
                exit;
            }

            header("Location: ?route=$redirectRoute&msg=acceso_denegado");
            exit;
        }
    }

    public function login(): void {
        if (isset($_SESSION['user_id'])) {
            header('Location: ?route=dashboard');
            exit;
        }

        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['usuario'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($username) || empty($password)) {
                $error = "Por favor ingrese usuario y contraseña.";
            } else {
                $user = Database::queryOne("SELECT * FROM usuarios_sistema WHERE usuario = ? AND activo = 1", [$username]);
                
                // Si es la primera vez y el hash coincide con admin123 o password_verify
                if ($user && password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_username'] = $user['usuario'];
                    $_SESSION['user_name'] = $user['nombre_completo'];
                    $_SESSION['user_role'] = $user['rol'];

                    Database::execute("UPDATE usuarios_sistema SET ultimo_login = NOW() WHERE id = ?", [$user['id']]);

                    header('Location: ?route=dashboard');
                    exit;
                } else {
                    $error = "Credenciales incorrectas o usuario inactivo.";
                }
            }
        }

        require_once APP_ROOT . '/views/auth/login.php';
    }

    public function logout(): void {
        session_destroy();
        header('Location: ?route=login');
        exit;
    }
}
