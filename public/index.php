<?php
/**
 * ==========================================================
 * SISTEMA DE CONTROL DE PERSONAL Y ASISTENCIA - ZKTECO
 * Front Controller & Router Principal
 * ==========================================================
 */

// Encabezados de Seguridad HTTP (Ciberseguridad OWASP)
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com https://cdn.datatables.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com https://cdnjs.cloudflare.com https://cdn.datatables.net; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com data:; img-src 'self' data: https: blob:;");
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Security/Csrf.php';
require_once __DIR__ . '/../app/Services/AttendanceCalculator.php';

// Validar CSRF en toda petición POST, antes de llegar a cualquier controlador
\App\Security\Csrf::validate();

// Cargar Controladores
require_once __DIR__ . '/../app/Controllers/AuthController.php';
require_once __DIR__ . '/../app/Controllers/DashboardController.php';
require_once __DIR__ . '/../app/Controllers/AsistenciaController.php';
require_once __DIR__ . '/../app/Controllers/MarcacionesController.php';
require_once __DIR__ . '/../app/Controllers/DispositivosController.php';
require_once __DIR__ . '/../app/Controllers/EmpleadosController.php';
require_once __DIR__ . '/../app/Controllers/TurnosController.php';
require_once __DIR__ . '/../app/Controllers/JustificacionesController.php';
require_once __DIR__ . '/../app/Controllers/UsuariosController.php';

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\AsistenciaController;
use App\Controllers\MarcacionesController;
use App\Controllers\DispositivosController;
use App\Controllers\EmpleadosController;
use App\Controllers\TurnosController;
use App\Controllers\JustificacionesController;
use App\Controllers\UsuariosController;
use App\Security\Csrf;

$route = $_GET['route'] ?? AuthController::getFirstAccessibleRoute();
$action = $_GET['action'] ?? 'index';

switch ($route) {
    case 'login':
        (new AuthController())->login();
        break;

    case 'logout':
        (new AuthController())->logout();
        break;

    case 'perfil':
        AuthController::checkAuth();
        $controller = new AuthController();
        if ($action === 'cambiar_password') {
            $controller->cambiarPassword();
        } else {
            $firstRoute = AuthController::getFirstAccessibleRoute();
            header("Location: ?route=$firstRoute");
            exit;
        }
        break;

    case 'dashboard':
        AuthController::requirePermission('dashboard');
        (new DashboardController())->index();
        break;

    case 'asistencia':
        AuthController::requirePermission('asistencia');
        $controller = new AsistenciaController();
        if ($action === 'recalcular') {
            AuthController::requireRole(['ADMIN', 'RRHH'], 'asistencia');
            $controller->recalcular();
        } elseif ($action === 'editar') {
            AuthController::requireRole('ADMIN', 'asistencia');
            $controller->editar();
        } elseif ($action === 'justificar_admin') {
            AuthController::requireRole('ADMIN', 'asistencia');
            $controller->justificarAdmin();
        } elseif ($action === 'historial_eventos') {
            $controller->historialEventos();
        } else {
            $controller->index();
        }
        break;

    case 'marcaciones':
        AuthController::requirePermission('marcaciones');
        $controller = new MarcacionesController();
        if ($action === 'guardar_manual') {
            AuthController::requireRole(['ADMIN', 'RRHH'], 'marcaciones');
            $controller->guardarManual();
        } else {
            $controller->index();
        }
        break;

    case 'dispositivos':
        $controller = new DispositivosController();
        if ($action === 'sincronizar') {
            AuthController::requireRole(['ADMIN', 'RRHH'], 'dashboard');
            $controller->sincronizar();
        } elseif ($action === 'sync_status' || $action === 'status') {
            AuthController::requireRole(['ADMIN', 'RRHH'], 'dashboard');
            $controller->syncStatus();
        } elseif ($action === 'enviar_usuario_reloj') {
            AuthController::requireRole(['ADMIN', 'RRHH']);
            $controller->enviarUsuarioReloj();
        } elseif ($action === 'enrolar_huella') {
            AuthController::requireRole(['ADMIN', 'RRHH']);
            $controller->enrolarHuella();
        } elseif ($action === 'sincronizar_biometria') {
            AuthController::requireRole(['ADMIN', 'RRHH']);
            $controller->sincronizarBiometria();
        } elseif ($action === 'obtener_biometria_usuario') {
            AuthController::requireRole(['ADMIN', 'RRHH']);
            $controller->obtenerBiometriaUsuario();
        } else {
            // Administración de hardware exclusiva de ADMIN
            AuthController::requirePermission('dispositivos', 'dashboard');
            if ($action === 'guardar') {
                $controller->guardar();
            } elseif ($action === 'eliminar') {
                $controller->eliminar();
            } elseif ($action === 'limpiar_memoria') {
                $controller->limpiarMemoria();
            } elseif ($action === 'test') {
                $controller->testConexion();
            } else {
                $controller->index();
            }
        }
        break;

    case 'empleados':
        AuthController::requirePermission('empleados');
        $controller = new EmpleadosController();
        if ($action === 'guardar') {
            AuthController::requireRole(['ADMIN', 'RRHH'], 'empleados');
            $controller->guardar();
        } elseif ($action === 'eliminar') {
            AuthController::requireRole(['ADMIN', 'RRHH'], 'empleados');
            $controller->eliminar();
        } else {
            $controller->index();
        }
        break;

    case 'turnos':
        AuthController::requirePermission('turnos');
        $controller = new TurnosController();
        if ($action === 'guardar') {
            AuthController::requireRole(['ADMIN', 'RRHH'], 'turnos');
            $controller->guardar();
        } elseif ($action === 'eliminar') {
            AuthController::requireRole(['ADMIN', 'RRHH'], 'turnos');
            $controller->eliminar();
        } else {
            $controller->index();
        }
        break;

    case 'justificaciones':
        AuthController::requirePermission('justificaciones');
        $controller = new JustificacionesController();
        if ($action === 'guardar') {
            AuthController::requireRole(['ADMIN', 'RRHH', 'SUPERVISOR'], 'justificaciones');
            $controller->guardar();
        } elseif ($action === 'resolver') {
            AuthController::requireRole(['ADMIN', 'RRHH'], 'justificaciones');
            $controller->resolver();
        } elseif ($action === 'ver_adjunto' || $action === 'descargar_adjunto') {
            $controller->verAdjunto();
        } else {
            $controller->index();
        }
        break;

    case 'usuarios':
        // Módulo exclusivo de Administrador
        AuthController::requireRole('ADMIN', 'dashboard');
        $controller = new UsuariosController();
        if ($action === 'guardar') {
            $controller->guardar();
        } elseif ($action === 'cambiar_estado') {
            $controller->cambiarEstado();
        } elseif ($action === 'restablecer_password') {
            $controller->restablecerPassword();
        } elseif ($action === 'eliminar') {
            $controller->eliminar();
        } else {
            $controller->index();
        }
        break;

    default:
        $defaultRoute = AuthController::getFirstAccessibleRoute();
        header("Location: ?route=$defaultRoute");
        exit;
}

