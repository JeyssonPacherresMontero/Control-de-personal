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
