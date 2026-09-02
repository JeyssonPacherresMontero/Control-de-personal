<?php
/**
 * ==========================================================
 * SISTEMA DE CONTROL DE PERSONAL Y ASISTENCIA - ZKTECO
 * Front Controller & Router Principal
 * ==========================================================
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Services/AttendanceCalculator.php';

// Cargar Controladores
require_once __DIR__ . '/../app/Controllers/AuthController.php';
require_once __DIR__ . '/../app/Controllers/DashboardController.php';
require_once __DIR__ . '/../app/Controllers/AsistenciaController.php';
require_once __DIR__ . '/../app/Controllers/MarcacionesController.php';
require_once __DIR__ . '/../app/Controllers/DispositivosController.php';
require_once __DIR__ . '/../app/Controllers/EmpleadosController.php';
require_once __DIR__ . '/../app/Controllers/TurnosController.php';
require_once __DIR__ . '/../app/Controllers/JustificacionesController.php';

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\AsistenciaController;
use App\Controllers\MarcacionesController;
use App\Controllers\DispositivosController;
use App\Controllers\EmpleadosController;
use App\Controllers\TurnosController;
use App\Controllers\JustificacionesController;

$route = $_GET['route'] ?? 'dashboard';
$action = $_GET['action'] ?? 'index';

switch ($route) {
    case 'login':
        (new AuthController())->login();
        break;

    case 'logout':
        (new AuthController())->logout();
        break;

    case 'dashboard':
        (new DashboardController())->index();
        break;

    case 'asistencia':
        $controller = new AsistenciaController();
        if ($action === 'recalcular') {
            AuthController::requireRole(['ADMIN', 'RRHH'], 'asistencia');
            $controller->recalcular();
        } elseif ($action === 'editar') {
            AuthController::requireRole(['ADMIN', 'RRHH'], 'asistencia');
            $controller->editar();
        } else {
            $controller->index();
        }
        break;

    case 'marcaciones':
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
        } else {
            // Toda la administración de hardware y vistas de dispositivos es exclusiva de ADMIN
            AuthController::requireRole('ADMIN', 'dashboard');
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
        // Turnos y horarios solo son accesibles para ADMIN y RRHH
        AuthController::requireRole(['ADMIN', 'RRHH'], 'dashboard');
        $controller = new TurnosController();
        if ($action === 'guardar') {
            $controller->guardar();
        } elseif ($action === 'eliminar') {
            $controller->eliminar();
        } else {
            $controller->index();
        }
        break;

    case 'justificaciones':
        $controller = new JustificacionesController();
        if ($action === 'guardar') {
            AuthController::requireRole(['ADMIN', 'RRHH', 'SUPERVISOR'], 'justificaciones');
            $controller->guardar();
        } elseif ($action === 'resolver') {
            AuthController::requireRole(['ADMIN', 'RRHH'], 'justificaciones');
            $controller->resolver();
        } else {
            $controller->index();
        }
        break;

    default:
        header('Location: ?route=dashboard');
        exit;
}
