<?php
namespace App\Controllers;

use App\Database;

class UsuariosController {
    public function index(): void {
        AuthController::checkAuth();
        AuthController::requireRole('ADMIN', 'dashboard');

        $usuarios = Database::query("
            SELECT u.*,
                   (SELECT COUNT(*) FROM plantillas_biometricas pb WHERE pb.codigo_reloj = u.usuario AND pb.tipo = 'HUELLA') as huellas_count,
                   (SELECT COUNT(*) FROM plantillas_biometricas pb WHERE pb.codigo_reloj = u.usuario AND pb.tipo = 'FACIAL') as facial_count
            FROM usuarios_sistema u 
            ORDER BY u.id ASC
        ");

        $dispositivos = Database::query("SELECT * FROM dispositivos WHERE activo = 1 ORDER BY id ASC");
        $modulosDisponibles = AuthController::getAvailableModules();
        $currentUser = AuthController::user();

        require_once APP_ROOT . '/views/usuarios/index.php';
    }

    public function guardar(): void {
        AuthController::checkAuth();
        AuthController::requireRole('ADMIN', 'dashboard');
        \App\Csrf::validateRequest();

        $id = (int)($_POST['id'] ?? 0);
        $usuario = trim($_POST['usuario'] ?? '');
        $nombre = trim($_POST['nombre_completo'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $rol = $_POST['rol'] ?? 'RRHH';
        $activo = isset($_POST['activo']) ? 1 : 0;
        
        // Permisos seleccionados desde los checkboxes
        $permisosSeleccionados = $_POST['permisos'] ?? [];
        if (!is_array($permisosSeleccionados)) {
            $permisosSeleccionados = [];
        }

        // Si el rol es ADMIN, forzar acceso total
        if ($rol === 'ADMIN') {
            $permisosJson = json_encode(['*']);
        } else {
            // Limpiar valores y asegurar que sean módulos válidos
            $modulosValidos = array_keys(AuthController::getAvailableModules());
            $permisosFiltrados = array_values(array_intersect($permisosSeleccionados, $modulosValidos));
            
            // Si no seleccionó ningún módulo, asignar al menos asistencia por defecto
            if (empty($permisosFiltrados)) {
                $permisosFiltrados = ['asistencia'];
            }
            $permisosJson = json_encode($permisosFiltrados);
        }

        if (empty($usuario) || empty($nombre)) {
            header('Location: ?route=usuarios&msg=error_campos');
            exit;
        }

        // Validar unicidad de nombre de usuario
        $existente = Database::queryOne("SELECT id FROM usuarios_sistema WHERE usuario = ? AND id != ?", [$usuario, $id]);
        if ($existente) {
            header('Location: ?route=usuarios&msg=usuario_duplicado');
            exit;
        }

        try {
            if ($id > 0) {
                // Actualizar usuario existente
                if (!empty($password)) {
                    if (strlen($password) < 5) {
                        header('Location: ?route=usuarios&msg=error_pass_corta');
                        exit;
                    }
                    $passHash = password_hash($password, PASSWORD_BCRYPT);
                    Database::execute("
                        UPDATE usuarios_sistema 
                        SET usuario = :usr, nombre_completo = :nom, email = :email, 
                            password = :pass, rol = :rol, permisos = :perms, activo = :act
                        WHERE id = :id
                    ", [
                        ':usr'   => $usuario,
                        ':nom'   => $nombre,
                        ':email' => $email ?: null,
                        ':pass'  => $passHash,
                        ':rol'   => $rol,
                        ':perms' => $permisosJson,
                        ':act'   => $activo,
                        ':id'    => $id
                    ]);
                } else {
                    Database::execute("
                        UPDATE usuarios_sistema 
                        SET usuario = :usr, nombre_completo = :nom, email = :email, 
                            rol = :rol, permisos = :perms, activo = :act
                        WHERE id = :id
                    ", [
                        ':usr'   => $usuario,
                        ':nom'   => $nombre,
                        ':email' => $email ?: null,
                        ':rol'   => $rol,
                        ':perms' => $permisosJson,
                        ':act'   => $activo,
                        ':id'    => $id
                    ]);
                }
            } else {
                // Crear nuevo usuario: CONTRASEÑA OBLIGATORIA (No se autogenera)
                if (empty($password)) {
                    header('Location: ?route=usuarios&msg=error_password_requerida');
                    exit;
                }
                if (strlen($password) < 5) {
                    header('Location: ?route=usuarios&msg=error_pass_corta');
                    exit;
                }

                $passHash = password_hash($password, PASSWORD_BCRYPT);

                Database::execute("
                    INSERT INTO usuarios_sistema 
                    (usuario, password, nombre_completo, email, rol, permisos, activo)
                    VALUES (:usr, :pass, :nom, :email, :rol, :perms, :act)
                ", [
                    ':usr'   => $usuario,
                    ':pass'  => $passHash,
                    ':nom'   => $nombre,
                    ':email' => $email ?: null,
                    ':rol'   => $rol,
                    ':perms' => $permisosJson,
                    ':act'   => $activo
                ]);
            }

            header('Location: ?route=usuarios&msg=guardado');
            exit;
        } catch (\PDOException $e) {
            $errorCode = (string)$e->getCode();
            $errorInfo = $e->errorInfo[1] ?? 0;
            if ($errorCode === '23000' || $errorInfo === 1062) {
                header('Location: ?route=usuarios&msg=usuario_duplicado');
            } else {
                header('Location: ?route=usuarios&msg=error_db');
            }
            exit;
        }
    }

    /**
     * Permite al Administrador restablecer la contraseña de cualquier usuario
     */
    public function restablecerPassword(): void {
        AuthController::checkAuth();
        AuthController::requireRole('ADMIN', 'dashboard');
        \App\Csrf::validateRequest();

        $userId = (int)($_POST['id'] ?? 0);
        $newPassword = $_POST['new_password'] ?? '';

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
                  || isset($_GET['ajax']) 
                  || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if ($userId <= 0 || empty($newPassword)) {
            $msg = 'Debes ingresar una contraseña válida.';
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => $msg]);
                exit;
            }
            header('Location: ?route=usuarios&msg=error_campos');
            exit;
        }

        if (strlen($newPassword) < 5) {
            $msg = 'La nueva contraseña debe tener al menos 5 caracteres.';
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => $msg]);
                exit;
            }
            header('Location: ?route=usuarios&msg=error_pass_corta');
            exit;
        }

        $user = Database::queryOne("SELECT id, usuario, nombre_completo FROM usuarios_sistema WHERE id = ?", [$userId]);
        if (!$user) {
            $msg = 'Usuario no encontrado en el sistema.';
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => $msg]);
                exit;
            }
            header('Location: ?route=usuarios&msg=error_usuario_no_existe');
            exit;
        }

        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        Database::execute("UPDATE usuarios_sistema SET password = ? WHERE id = ?", [$newHash, $userId]);

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'message' => "La contraseña para el usuario '{$user['usuario']}' ha sido actualizada exitosamente."
            ]);
            exit;
        }

        header('Location: ?route=usuarios&msg=pass_restablecido');
        exit;
    }

    public function cambiarEstado(): void {
        AuthController::checkAuth();
        AuthController::requireRole('ADMIN', 'dashboard');
        \App\Csrf::validateRequest();

        $id = (int)($_POST['id'] ?? ($_GET['id'] ?? 0));
        $currentUser = AuthController::user();

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
                  || isset($_GET['ajax']) 
                  || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        // Evitar que el admin se desactive a sí mismo
        if ($id === (int)($currentUser['id'] ?? 0)) {
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => 'No puedes desactivar tu propio usuario administrador.']);
                exit;
            }
            header('Location: ?route=usuarios&msg=error_auto_desactivar');
            exit;
        }

        $nuevoEstado = 0;
        if ($id > 0) {
            $user = Database::queryOne("SELECT activo FROM usuarios_sistema WHERE id = ?", [$id]);
            if ($user) {
                $nuevoEstado = $user['activo'] ? 0 : 1;
                Database::execute("UPDATE usuarios_sistema SET activo = ? WHERE id = ?", [$nuevoEstado, $id]);
            }
        }

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'nuevo_estado' => $nuevoEstado,
                'message' => 'Estado del usuario actualizado exitosamente.'
            ]);
            exit;
        }

        header('Location: ?route=usuarios&msg=estado_actualizado');
        exit;
    }

    public function eliminar(): void {
        AuthController::checkAuth();
        AuthController::requireRole('ADMIN', 'dashboard');
        \App\Csrf::validateRequest();

        $id = (int)($_POST['id'] ?? 0);
        $currentUser = AuthController::user();

        // Evitar que el admin se elimine a sí mismo
        if ($id === (int)($currentUser['id'] ?? 0)) {
            header('Location: ?route=usuarios&msg=error_auto_eliminar');
            exit;
        }

        if ($id > 0) {
            Database::execute("DELETE FROM usuarios_sistema WHERE id = ?", [$id]);
        }

        header('Location: ?route=usuarios&msg=eliminado');
        exit;
    }
}

