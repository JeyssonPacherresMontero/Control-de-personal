<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database;

class AuthController {
    public static function checkAuth(): void {
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                  || isset($_GET['ajax'])
                  || isset($_POST['ajax'])
                  || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));


        if (!isset($_SESSION['user_id'])) {
            if ($isAjax) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => 'session_expired', 'message' => 'Tu sesión ha expirado.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            header('Location: ?route=login');
            exit;
        }

        // Timeout de inactividad (4 horas por defecto)
        $maxInactivity = (int)($_ENV['SESSION_IDLE_TIMEOUT'] ?? (4 * 3600));
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $maxInactivity) {
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
            }
            session_destroy();

            if ($isAjax) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => 'session_expired', 'message' => 'Tu sesión ha expirado por inactividad.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            header('Location: ?route=login&msg=sesion_expirada');
            exit;
        }

        // Validación en tiempo real de estado activo e invalidación de permisos
        try {
            $userRow = Database::queryOne(
                "SELECT id, permisos, rol, activo, COALESCE(permisos_version, 1) as permisos_version 
                 FROM usuarios_sistema WHERE id = ?", 
                [$_SESSION['user_id']]
            );

            if (!$userRow || !(int)$userRow['activo']) {
                $_SESSION = [];
                if (ini_get("session.use_cookies")) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000,
                        $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
                }
                session_destroy();

                if ($isAjax) {
                    http_response_code(401);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'error' => 'account_deactivated', 'message' => 'Tu cuenta ha sido desactivada o eliminada.'], JSON_UNESCAPED_UNICODE);
                    exit;
                }

                header('Location: ?route=login&msg=cuenta_desactivada');
                exit;
            }

            // Si los permisos o rol fueron actualizados por el Administrador, sincronizar en caliente
            $currentSessionVersion = (int)($_SESSION['user_permisos_version'] ?? 1);
            $dbVersion = (int)$userRow['permisos_version'];
            if ($dbVersion !== $currentSessionVersion || !isset($_SESSION['user_permissions'])) {
                $_SESSION['user_permisos_version'] = $dbVersion;
                $_SESSION['user_role'] = $userRow['rol'];
                $perms = !empty($userRow['permisos']) ? json_decode($userRow['permisos'], true) : null;
                if (!is_array($perms)) {
                    $perms = self::getDefaultPermissionsForRole($userRow['rol']);
                }
                $_SESSION['user_permissions'] = $perms;
            }
        } catch (\Exception $e) {
            // Silencioso ante fallos temporales de conexión para no interrumpir
        }

        $_SESSION['last_activity'] = time();
    }

    /**
     * Valida la complejidad y robustez de contraseñas
     * Regla: Mínimo 8 caracteres, al menos 1 letra mayúscula y al menos 1 número.
     */
    public static function validatePasswordStrength(string $password): ?string {
        if (strlen($password) < 8) {
            return 'La contraseña debe tener al menos 8 caracteres.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return 'La contraseña debe contener al menos una letra mayúscula.';
        }
        if (!preg_match('/[0-9]/', $password)) {
            return 'La contraseña debe contener al menos un número.';
        }
        return null;
    }

    public static function user(): ?array {
        if (isset($_SESSION['user_id'])) {
            return [
                'id' => $_SESSION['user_id'],
                'usuario' => $_SESSION['user_username'] ?? '',
                'nombre' => $_SESSION['user_name'] ?? 'Usuario',
                'rol' => $_SESSION['user_role'] ?? 'RRHH',
                'departamento_id' => (int)($_SESSION['user_departamento_id'] ?? 0),
                'email' => $_SESSION['user_email'] ?? '',
                'ultimo_login' => $_SESSION['user_ultimo_login'] ?? '',
                'permisos' => self::getPermissions()
            ];
        }
        return null;
    }


    public static function role(): string {
        return $_SESSION['user_role'] ?? 'CONSULTA';
    }

    /**
     * Catálogo maestro de módulos del sistema con metadatos para UI y menús
     */
    public static function getAvailableModules(): array {
        return [
            'dashboard' => [
                'name' => 'Tablero Principal',
                'icon' => 'fa-solid fa-chart-pie',
                'category' => 'Monitoreo y Control',
                'description' => 'Indicadores clave en tiempo real, métricas de puntualidad y gráficos.'
            ],
            'asistencia' => [
                'name' => 'Control de Asistencia',
                'icon' => 'fa-solid fa-calendar-check',
                'category' => 'Monitoreo y Control',
                'description' => 'Consolidado diario de asistencias, tardanzas, faltas y reportes oficiales.'
            ],
            'marcaciones' => [
                'name' => 'Registro de Marcaciones',
                'icon' => 'fa-solid fa-clock-rotate-left',
                'category' => 'Monitoreo y Control',
                'description' => 'Auditoría y consulta de registros crudos de los relojes biométricos.'
            ],
            'empleados' => [
                'name' => 'Directorio de Personal',
                'icon' => 'fa-solid fa-users',
                'category' => 'Gestión de Personal',
                'description' => 'Padrón de trabajadores, documentos de identidad, cargos y biometría.'
            ],
            'turnos' => [
                'name' => 'Turnos y Horarios',
                'icon' => 'fa-solid fa-business-time',
                'category' => 'Gestión de Personal',
                'description' => 'Configuración de horarios laborales, tolerancias y refrigerios.'
            ],
            'justificaciones' => [
                'name' => 'Permisos y Justificaciones',
                'icon' => 'fa-solid fa-file-signature',
                'category' => 'Gestión de Personal',
                'description' => 'Gestión de descansos médicos, comisiones de servicio y permisos.'
            ],
            'dispositivos' => [
                'name' => 'Relojes Biométricos',
                'icon' => 'fa-solid fa-network-wired',
                'category' => 'Equipos y Red',
                'description' => 'Administración de terminales biométricos, conectividad IP y sincronización.'
            ],
            'usuarios' => [
                'name' => 'Usuarios del Sistema',
                'icon' => 'fa-solid fa-user-shield',
                'category' => 'Sistema y Seguridad',
                'description' => 'Gestión de cuentas de acceso, roles y asignación de permisos.'
            ]
        ];
    }

    /**
     * Retorna los permisos por defecto según el rol del usuario
     */
    public static function getDefaultPermissionsForRole(string $role): array {
        return match($role) {
            'ADMIN' => ['*'],
            'RRHH' => ['dashboard', 'asistencia', 'marcaciones', 'empleados', 'turnos', 'justificaciones'],
            'SUPERVISOR' => ['dashboard', 'asistencia', 'marcaciones', 'empleados', 'justificaciones'],
            'ASISTENTE' => ['dashboard', 'asistencia', 'marcaciones', 'justificaciones'],
            'USER', 'USUARIO', 'CONSULTA' => ['dashboard', 'asistencia', 'marcaciones'],
            default => ['asistencia']
        };
    }

    /**
     * Retorna la lista de módulos autorizados para el usuario actual
     */
    public static function getPermissions(): array {
        if (!isset($_SESSION['user_id'])) {
            return [];
        }

        if (isset($_SESSION['user_permissions']) && is_array($_SESSION['user_permissions'])) {
            return $_SESSION['user_permissions'];
        }

        // Si no está en sesión, consultar en base de datos
        $row = Database::queryOne("SELECT permisos, rol FROM usuarios_sistema WHERE id = ?", [$_SESSION['user_id']]);
        if ($row) {
            $perms = !empty($row['permisos']) ? json_decode($row['permisos'], true) : null;
            if (!is_array($perms)) {
                $perms = self::getDefaultPermissionsForRole($row['rol']);
            }
            $_SESSION['user_permissions'] = $perms;
            return $perms;
        }

        return ['asistencia'];
    }

    /**
     * Verifica si el usuario actual tiene permiso para acceder a un módulo específico
     */
    public static function hasPermission(string $module): bool {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        $userRole = self::role();
        // El administrador siempre tiene acceso a todo
        if ($userRole === 'ADMIN') {
            return true;
        }

        // El módulo de usuarios del sistema es exclusivo de administradores
        if ($module === 'usuarios') {
            return false;
        }

        $permissions = self::getPermissions();
        if (in_array('*', $permissions, true)) {
            return true;
        }

        return in_array($module, $permissions, true);
    }

    /**
     * Retorna la primera ruta a la que el usuario tiene acceso (para redirecciones seguras)
     */
    public static function getFirstAccessibleRoute(): string {
        if (self::hasPermission('dashboard')) {
            return 'dashboard';
        }
        foreach (array_keys(self::getAvailableModules()) as $mod) {
            if ($mod !== 'dashboard' && self::hasPermission($mod)) {
                return $mod;
            }
        }
        return 'login';
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
     */
    public static function requireRole($roles, ?string $redirectRoute = null): void {
        self::checkAuth();
        if (!self::hasRole($roles)) {
            $targetRoute = $redirectRoute ?? self::getFirstAccessibleRoute();
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

            header("Location: ?route=$targetRoute&msg=acceso_denegado");
            exit;
        }
    }

    /**
     * Exige que el usuario tenga permiso para el módulo indicado.
     */
    public static function requirePermission(string $module, ?string $redirectRoute = null): void {
        self::checkAuth();
        if (!self::hasPermission($module)) {
            $targetRoute = $redirectRoute ?? self::getFirstAccessibleRoute();
            // Evitar bucle de redirección si la ruta de redirección coincide con el módulo denegado
            if ($targetRoute === $module) {
                $targetRoute = 'login';
            }

            $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
                      || isset($_GET['ajax']) 
                      || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

            if ($isAjax) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Acceso denegado: No tienes permisos para acceder a este módulo.'
                ]);
                exit;
            }

            header("Location: ?route=$targetRoute&msg=acceso_denegado");
            exit;
        }
    }

    /**
     * Extrae de forma segura la IP real del cliente considerando proxies y balanceadores confiables.
     */
    public static function getClientIp(): string {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR'
        ];
        foreach ($headers as $h) {
            if (!empty($_SERVER[$h])) {
                $ips = explode(',', $_SERVER[$h]);
                foreach ($ips as $rawIp) {
                    $ip = trim($rawIp);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $ip;
                    }
                }
                $firstIp = trim($ips[0]);
                if (filter_var($firstIp, FILTER_VALIDATE_IP)) {
                    return $firstIp;
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    public function login(): void {
        if (isset($_SESSION['user_id'])) {
            $dest = self::getFirstAccessibleRoute();
            header("Location: ?route=$dest");
            exit;
        }

        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            \App\Csrf::validateRequest();

            $ip = self::getClientIp();
            $username = trim($_POST['usuario'] ?? '');
            $password = $_POST['password'] ?? '';

            // Rate Limiting por combinación de IP y Usuario (para evitar bloqueo global por proxy)
            $attemptRow = null;
            try {
                if (!empty($username)) {
                    $attemptRow = Database::queryOne(
                        "SELECT * FROM login_intentos WHERE ip = ? AND usuario = ?", 
                        [$ip, $username]
                    );
                }
                if (!$attemptRow) {
                    $attemptRow = Database::queryOne(
                        "SELECT * FROM login_intentos WHERE ip = ? AND (usuario IS NULL OR usuario = '')", 
                        [$ip]
                    );
                }

                if ($attemptRow && !empty($attemptRow['bloqueado_hasta'])) {
                    $blockedUntil = strtotime($attemptRow['bloqueado_hasta']);
                    if (time() < $blockedUntil) {
                        $secondsLeft = $blockedUntil - time();
                        $minutesLeft = max(1, (int)ceil($secondsLeft / 60));
                        $error = "Demasiados intentos fallidos para esta cuenta. Acceso temporalmente bloqueado por {$minutesLeft} minuto(s).";
                        require_once APP_ROOT . '/views/auth/login.php';
                        return;
                    }
                }
            } catch (\Exception $e) {
                // Silencioso ante fallos de tabla para no bloquear el inicio de sesión
            }

            if (empty($username) || empty($password)) {
                $error = "Por favor ingrese usuario y contraseña.";
            } else {
                $user = Database::queryOne("SELECT * FROM usuarios_sistema WHERE usuario = ? AND activo = 1", [$username]);
                
                if ($user && password_verify($password, $user['password'])) {
                    // Éxito: Limpiar intentos fallidos en BD para esta IP y usuario
                    try {
                        Database::execute("DELETE FROM login_intentos WHERE ip = ? AND (usuario = ? OR usuario IS NULL)", [$ip, $username]);
                    } catch (\Exception $e) {
                        // Silencioso
                    }

                    // Regenerar ID de sesión para prevenir Session Fixation
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_username'] = $user['usuario'];
                    $_SESSION['user_name'] = $user['nombre_completo'];
                    $_SESSION['user_role'] = $user['rol'];
                    $_SESSION['user_departamento_id'] = (int)($user['departamento_id'] ?? 0);
                    $_SESSION['user_email'] = $user['email'] ?? '';
                    $_SESSION['user_ultimo_login'] = $user['ultimo_login'] ?? '';
                    $_SESSION['user_permisos_version'] = (int)($user['permisos_version'] ?? 1);
                    $_SESSION['last_activity'] = time();


                    $perms = !empty($user['permisos']) ? json_decode($user['permisos'], true) : null;
                    if (!is_array($perms)) {
                        $perms = self::getDefaultPermissionsForRole($user['rol']);
                    }
                    $_SESSION['user_permissions'] = $perms;

                    try {
                        Database::execute("UPDATE usuarios_sistema SET ultimo_login = NOW() WHERE id = ?", [$user['id']]);
                    } catch (\Exception $e) {
                        // Silencioso
                    }

                    $firstRoute = self::getFirstAccessibleRoute();
                    header("Location: ?route=$firstRoute");
                    exit;
                } else {
                    $currentAttempts = $attemptRow ? ((int)$attemptRow['intentos'] + 1) : 1;
                    $lockUntil = null;

                    if ($currentAttempts >= 5) {
                        $lockUntil = date('Y-m-d H:i:s', time() + (5 * 60)); // 5 minutos de bloqueo
                        $error = "Has superado el límite de 5 intentos fallidos. Tu acceso para esta cuenta ha sido bloqueado por 5 minutos.";
                    } else {
                        $remaining = 5 - $currentAttempts;
                        $error = "Credenciales incorrectas o usuario inactivo. (Intentos restantes: {$remaining})";
                    }

                    try {
                        if ($attemptRow) {
                            Database::execute("
                                UPDATE login_intentos 
                                SET usuario = :usr, intentos = :att, ultimo_intento = NOW(), bloqueado_hasta = :lock 
                                WHERE id = :id
                            ", [
                                ':usr'  => $username,
                                ':att'  => $currentAttempts,
                                ':lock' => $lockUntil,
                                ':id'   => $attemptRow['id']
                            ]);
                        } else {
                            Database::execute("
                                INSERT INTO login_intentos (ip, usuario, intentos, ultimo_intento, bloqueado_hasta)
                                VALUES (:ip, :usr, :att, NOW(), :lock)
                            ", [
                                ':ip'   => $ip,
                                ':usr'  => $username ?: null,
                                ':att'  => $currentAttempts,
                                ':lock' => $lockUntil
                            ]);
                        }
                    } catch (\Exception $e) {
                        // Silencioso
                    }
                }
            }
        }

        require_once APP_ROOT . '/views/auth/login.php';
    }

    public function cambiarPassword(): void {
        self::checkAuth();
        \App\Csrf::validateRequest();
        
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $oldPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
                  || isset($_GET['ajax']) 
                  || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if (empty($oldPassword) || empty($newPassword) || empty($confirmPassword)) {
            $msg = 'Por favor, completa todos los campos de contraseña.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $msg]);
                exit;
            }
            header('Location: ?route=' . self::getFirstAccessibleRoute() . '&msg=error_campos&msg_perfil_error=' . urlencode($msg));
            exit;
        }

        $passError = self::validatePasswordStrength($newPassword);
        if ($passError !== null) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $passError]);
                exit;
            }
            header('Location: ?route=' . self::getFirstAccessibleRoute() . '&msg=error_pass_corta&msg_perfil_error=' . urlencode($passError));
            exit;
        }

        if ($newPassword !== $confirmPassword) {
            $msg = 'La nueva contraseña y su confirmación no coinciden.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $msg]);
                exit;
            }
            header('Location: ?route=' . self::getFirstAccessibleRoute() . '&msg=error_pass_no_coinciden&msg_perfil_error=' . urlencode($msg));
            exit;
        }

        $user = Database::queryOne("SELECT * FROM usuarios_sistema WHERE id = ?", [$userId]);
        if (!$user || !password_verify($oldPassword, $user['password'])) {
            $msg = 'La contraseña actual ingresada es incorrecta.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $msg]);
                exit;
            }
            header('Location: ?route=' . self::getFirstAccessibleRoute() . '&msg=error_actual&msg_perfil_error=' . urlencode($msg));
            exit;
        }

        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        Database::execute("UPDATE usuarios_sistema SET password = ? WHERE id = ?", [$newHash, $userId]);

        // Regenerar ID de sesión por seguridad tras cambio de credenciales
        session_regenerate_id(true);

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Tu contraseña ha sido actualizada con éxito.']);
            exit;
        }

        header('Location: ?route=' . self::getFirstAccessibleRoute() . '&msg=guardado&msg_perfil_ok=1');
        exit;
    }

    public function logout(): void {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        header('Location: ?route=login');
        exit;
    }
}


