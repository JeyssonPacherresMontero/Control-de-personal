<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database;

class UsuariosController {
    public function index(): void {
        AuthController::checkAuth();
        AuthController::requireRole('ADMIN', 'dashboard');

        $usuarios = Database::query("
            SELECT u.id, u.usuario, u.nombre_completo, u.email, u.rol, u.departamento_id, u.permisos, u.activo, 
                   COALESCE(u.permisos_version, 1) as permisos_version, u.ultimo_login, u.creado_en,
                   d.nombre as departamento_nombre,
                   (SELECT COUNT(*) FROM plantillas_biometricas pb WHERE pb.codigo_reloj = u.usuario AND pb.tipo = 'HUELLA') as huellas_count,
                   (SELECT COUNT(*) FROM plantillas_biometricas pb WHERE pb.codigo_reloj = u.usuario AND pb.tipo = 'FACIAL') as facial_count
            FROM usuarios_sistema u 
            LEFT JOIN departamentos d ON u.departamento_id = d.id
            ORDER BY u.id ASC
        ");

        $departamentos = Database::query("SELECT * FROM departamentos WHERE activo = 1 ORDER BY nombre ASC");
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
        $departamentoId = !empty($_POST['departamento_id']) ? (int)$_POST['departamento_id'] : null;
        $activo = isset($_POST['activo']) ? 1 : 0;
        
        // Regla de Seguridad Estricta: Solo puede existir un único Administrador en el sistema.
        // No se permite crear nuevos usuarios con rol ADMIN ni promover usuarios existentes a ADMIN.
        if ($id <= 0) {
            // Creación: Rol forzado a roles no-admin
            if ($rol === 'ADMIN' || !in_array($rol, ['RRHH', 'SUPERVISOR', 'CONSULTA'], true)) {
                $rol = 'RRHH';
            }
        } else {
            // Edición de usuario existente
            $targetUser = Database::queryOne("SELECT id, rol FROM usuarios_sistema WHERE id = ?", [$id]);
            if (!$targetUser) {
                header('Location: ?route=usuarios&msg=error_no_existe');
                exit;
            }
            if ($targetUser['rol'] === 'ADMIN') {
                // El administrador principal preserva su rol y acceso total
                $rol = 'ADMIN';
            } else {
                // Ningún otro usuario puede asignarse rol ADMIN
                if ($rol === 'ADMIN' || !in_array($rol, ['RRHH', 'SUPERVISOR', 'CONSULTA'], true)) {
                    $rol = in_array($targetUser['rol'], ['RRHH', 'SUPERVISOR', 'CONSULTA'], true) ? $targetUser['rol'] : 'RRHH';
                }
            }
        }

        // Permisos seleccionados desde los checkboxes
        $permisosSeleccionados = $_POST['permisos'] ?? [];
        if (!is_array($permisosSeleccionados)) {
            $permisosSeleccionados = [];
        }

        // Si el rol es ADMIN (únicamente el administrador original), forzar acceso total
        if ($rol === 'ADMIN') {
            $permisosJson = json_encode(['*']);
        } else {
            // Limpiar valores y asegurar que sean módulos válidos (excluyendo 'usuarios' que es exclusivo del Admin)
            $modulosValidos = array_diff(array_keys(AuthController::getAvailableModules()), ['usuarios']);
            $permisosFiltrados = array_values(array_intersect($permisosSeleccionados, $modulosValidos));
            
            // Si no seleccionó ningún módulo, asignar los módulos por defecto de su rol
            if (empty($permisosFiltrados)) {
                $permisosFiltrados = AuthController::getDefaultPermissionsForRole($rol);
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
                    $passError = AuthController::validatePasswordStrength($password);
                    if ($passError !== null) {
                        header('Location: ?route=usuarios&msg=error_pass_corta');
                        exit;
                    }
                    $passHash = password_hash($password, PASSWORD_BCRYPT);
                    Database::execute("
                        UPDATE usuarios_sistema 
                        SET usuario = :usr, nombre_completo = :nom, email = :email, 
                            password = :pass, rol = :rol, departamento_id = :depto, permisos = :perms, activo = :act,
                            permisos_version = COALESCE(permisos_version, 1) + 1
                        WHERE id = :id
                    ", [
                        ':usr'   => $usuario,
                        ':nom'   => $nombre,
                        ':email' => $email ?: null,
                        ':pass'  => $passHash,
                        ':rol'   => $rol,
                        ':depto' => $departamentoId,
                        ':perms' => $permisosJson,
                        ':act'   => $activo,
                        ':id'    => $id
                    ]);
                } else {
                    Database::execute("
                        UPDATE usuarios_sistema 
                        SET usuario = :usr, nombre_completo = :nom, email = :email, 
                            rol = :rol, departamento_id = :depto, permisos = :perms, activo = :act,
                            permisos_version = COALESCE(permisos_version, 1) + 1
                        WHERE id = :id
                    ", [
                        ':usr'   => $usuario,
                        ':nom'   => $nombre,
                        ':email' => $email ?: null,
                        ':rol'   => $rol,
                        ':depto' => $departamentoId,
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
                $passError = AuthController::validatePasswordStrength($password);
                if ($passError !== null) {
                    header('Location: ?route=usuarios&msg=error_pass_corta');
                    exit;
                }

                $passHash = password_hash($password, PASSWORD_BCRYPT);

                Database::execute("
                    INSERT INTO usuarios_sistema 
                    (usuario, password, nombre_completo, email, rol, departamento_id, permisos, activo, permisos_version)
                    VALUES (:usr, :pass, :nom, :email, :rol, :depto, :perms, :act, 1)
                ", [
                    ':usr'   => $usuario,
                    ':pass'  => $passHash,
                    ':nom'   => $nombre,
                    ':email' => $email ?: null,
                    ':rol'   => $rol,
                    ':depto' => $departamentoId,
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

        $passError = AuthController::validatePasswordStrength($newPassword);
        if ($passError !== null) {
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => $passError]);
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
        Database::execute(
            "UPDATE usuarios_sistema SET password = ?, permisos_version = COALESCE(permisos_version, 1) + 1 WHERE id = ?", 
            [$newHash, $userId]
        );

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

        $targetUser = Database::queryOne("SELECT id, usuario, rol, activo FROM usuarios_sistema WHERE id = ?", [$id]);
        if (!$targetUser) {
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => 'Usuario no encontrado.']);
                exit;
            }
            header('Location: ?route=usuarios&msg=error_no_existe');
            exit;
        }

        // El Administrador principal no puede ser desactivado
        if ($targetUser['rol'] === 'ADMIN') {
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => 'La cuenta de Administrador principal está protegida y no puede ser desactivada.']);
                exit;
            }
            header('Location: ?route=usuarios&msg=error_admin_protegido');
            exit;
        }

        $nuevoEstado = $targetUser['activo'] ? 0 : 1;
        Database::execute(
            "UPDATE usuarios_sistema SET activo = ?, permisos_version = COALESCE(permisos_version, 1) + 1 WHERE id = ?", 
            [$nuevoEstado, $id]
        );

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

        $targetUser = Database::queryOne("SELECT id, usuario, rol FROM usuarios_sistema WHERE id = ?", [$id]);
        if (!$targetUser) {
            header('Location: ?route=usuarios&msg=error_no_existe');
            exit;
        }

        // El Administrador principal nunca puede ser eliminado
        if ($targetUser['rol'] === 'ADMIN' || $id === (int)($currentUser['id'] ?? 0)) {
            header('Location: ?route=usuarios&msg=error_admin_protegido');
            exit;
        }

        // Soft-delete para mantener integridad histórica y revocar acceso
        Database::execute(
            "UPDATE usuarios_sistema SET activo = 0, permisos_version = COALESCE(permisos_version, 1) + 1 WHERE id = ?", 
            [$id]
        );

        header('Location: ?route=usuarios&msg=eliminado');
        exit;
    }
}

