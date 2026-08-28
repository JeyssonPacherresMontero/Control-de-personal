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
            $controller->recalcular();
        } elseif ($action === 'editar') {
            $controller->editar();
        } else {
            $controller->index();
        }
        break;

    case 'marcaciones':
        $controller = new MarcacionesController();
        if ($action === 'guardar_manual') {
            $controller->guardarManual();
        } else {
            $controller->index();
        }
        break;

    case 'dispositivos':
        $controller = new DispositivosController();
        if ($action === 'guardar') {
            $controller->guardar();
        } elseif ($action === 'eliminar') {
            $controller->eliminar();
        } elseif ($action === 'sincronizar') {
            $controller->sincronizar();
        } elseif ($action === 'test') {
            $controller->testConexion();
        } else {
            $controller->index();
        }
        break;

    case 'empleados':
        $controller = new EmpleadosController();
        if ($action === 'guardar') {
            $controller->guardar();
        } elseif ($action === 'eliminar') {
            $controller->eliminar();
        } else {
            $controller->index();
        }
        break;

    case 'turnos':
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
            $controller->guardar();
        } elseif ($action === 'resolver') {
            $controller->resolver();
        } else {
            $controller->index();
        }
        break;

    default:
        header('Location: ?route=dashboard');
        exit;
}
