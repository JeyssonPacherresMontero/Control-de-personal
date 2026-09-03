<?php
\App\Controllers\AuthController::checkAuth();
$currentUser = \App\Controllers\AuthController::user();
$userRole = $currentUser['rol'] ?? 'CONSULTA';
$currentRoute = $_GET['route'] ?? 'dashboard';

// Configuración de badges y títulos según rol
$roleBadgeClass = match($userRole) {
    'ADMIN' => 'badge-danger',
    'RRHH' => 'badge-primary',
    'SUPERVISOR' => 'badge-info',
    default => 'badge-secondary'
};

$roleLabel = match($userRole) {
    'ADMIN' => 'Administrador (TI)',
    'RRHH' => 'Recursos Humanos',
    'SUPERVISOR' => 'Supervisor de Área',
    'CONSULTA' => 'Solo Consulta',
    default => $userRole
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(APP_NAME) ?> | Panel de Control</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= jushsal_logo_data_uri('favicon') ?: asset('img/favicon.png') ?>">
    <link rel="apple-touch-icon" href="<?= jushsal_logo_data_uri('icon') ?: asset('img/logo_icon.png') ?>">

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- overlayScrollbars -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.4.4/styles/overlayscrollbars.min.css">
    <!-- DataTables Bootstrap 4 -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap4.min.css">
    <!-- AdminLTE v3.2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <style>
        .brand-link {
            padding: 0.8rem 0.8rem !important;
            display: flex !important;
            align-items: center;
            border-bottom: 1px solid rgba(255,255,255,0.1) !important;
        }
        .brand-link .brand-image-logo {
            width: 36px;
            height: 36px;
            object-fit: contain;
            background: #ffffff;
            border-radius: 8px;
            padding: 2px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.25);
            margin-right: 0.75rem;
            flex-shrink: 0;
        }
        .brand-text-container {
            display: flex;
            flex-direction: column;
            line-height: 1.1;
        }
        .brand-main-title {
            font-size: 1.05rem;
            font-weight: 800;
            letter-spacing: 0.8px;
            color: #ffffff;
        }
        .brand-sub-title {
            font-size: 0.65rem;
            color: #94a3b8;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 140px;
        }
        .table td, .table th {
            vertical-align: middle !important;
        }
        .small-box .icon>i {
            font-size: 70px;
            top: 15px;
        }
        .badge-status {
            padding: 5px 10px;
            font-size: 85%;
            font-weight: 600;
            border-radius: 4px;
        }

        /* ESTILOS DE IMPRESIÓN / EXPORTACIÓN PDF */
        @media print {
            .main-sidebar, .main-header, .main-footer, .breadcrumb, .card-tools, .card-body form, .btn, .dataTables_length, .dataTables_filter, .dataTables_info, .dataTables_paginate, .alert, .preloader {
                display: none !important;
            }
            body, .content-wrapper, .wrapper {
                background: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .content-wrapper {
                margin-left: 0 !important;
            }
            .card {
                border: none !important;
                box-shadow: none !important;
                margin-bottom: 0 !important;
            }
            .card-header {
                border-bottom: 2px solid #333 !important;
                padding: 5px 0 !important;
            }
            .card-body {
                padding: 10px 0 !important;
            }
            .table {
                width: 100% !important;
                border-collapse: collapse !important;
                font-size: 9pt !important;
            }
            .table th, .table td {
                border: 1px solid #999 !important;
                padding: 4px 6px !important;
            }
            .badge {
                border: 1px solid #777 !important;
                color: #000 !important;
                background: transparent !important;
                font-weight: bold !important;
            }
            @page {
                size: landscape;
                margin: 1cm;
            }
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed layout-footer-fixed">
<div class="wrapper">

    <!-- Preloader opcional -->
    <div class="preloader flex-column justify-content-center align-items-center bg-light">
        <img class="animation__shake mb-3" src="<?= jushsal_logo_data_uri('icon') ?: asset('img/logo_icon.png') ?>" alt="JUSHSAL" height="80" width="80" style="object-fit: contain;">
        <span class="font-weight-bold text-dark h5 mb-0">JUSHSAL</span>
        <span class="text-muted small">Junta de Usuarios San Lorenzo &bull; Control de Asistencia</span>
    </div>

    <!-- NAVBAR -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light border-bottom">
        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="?route=dashboard" class="nav-link"><i class="fa-solid fa-gauge-high mr-1"></i> Panel Principal</a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="?route=asistencia" class="nav-link"><i class="fa-solid fa-calendar-check mr-1"></i> Asistencia</a>
            </li>
        </ul>

        <!-- Right navbar links -->
        <ul class="navbar-nav ml-auto align-items-center">
            <!-- Sync Button: Solo visible para ADMIN y RRHH -->
            <?php if (in_array($userRole, ['ADMIN', 'RRHH'], true)): ?>
                <li class="nav-item mr-2">
                    <a href="javascript:void(0)" onclick="typeof openSyncModal === 'function' ? openSyncModal() : (typeof syncAllDevices === 'function' ? syncAllDevices('today', this) : window.location.href='?route=dispositivos')" class="btn btn-sm btn-outline-success font-weight-bold" title="Sincronización rápida de relojes ZKTeco">
                        <i class="fa-solid fa-bolt mr-1"></i> Sincronizar Hoy
                    </a>
                </li>
            <?php endif; ?>

            <!-- Date badge -->
            <li class="nav-item d-none d-md-inline-block mr-3">
                <span class="badge badge-light border px-2 py-1 text-muted">
                    <i class="far fa-calendar-alt mr-1"></i> <?= date('d/m/Y') ?>
                </span>
            </li>

            <!-- User Dropdown Menu -->
            <li class="nav-item dropdown user-menu">
                <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown">
                    <i class="fa-solid fa-circle-user fa-lg mr-1 text-primary"></i>
                    <span class="d-none d-md-inline font-weight-bold"><?= htmlspecialchars($currentUser['nombre'] ?? 'Usuario') ?></span>
                    <span class="badge <?= $roleBadgeClass ?> ml-1" style="font-size: 75%;"><?= htmlspecialchars($userRole) ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right shadow border-0">
                    <!-- User image -->
                    <li class="user-header bg-primary">
                        <img src="<?= jushsal_logo_data_uri('icon') ?: asset('img/logo_icon.png') ?>" class="img-circle elevation-2 bg-white p-1 mb-2" alt="User Image" style="width: 60px; height: 60px; object-fit: contain;">
                        <p>
                            <?= htmlspecialchars($currentUser['nombre'] ?? 'Usuario') ?>
                            <small class="d-block mt-1 font-weight-bold badge <?= $roleBadgeClass ?> text-white"><?= htmlspecialchars($roleLabel) ?></small>
                        </p>
                    </li>
                    <!-- Menu Footer-->
                    <li class="user-footer d-flex justify-content-between">
                        <span class="text-muted small align-self-center"><i class="fa-solid fa-building-user mr-1 text-primary"></i> JUSHSAL</span>
                        <a href="?route=logout" class="btn btn-default btn-flat text-danger">
                            <i class="fa-solid fa-right-from-bracket mr-1"></i> Salir
                        </a>
                    </li>
                </ul>
            </li>

            <li class="nav-item">
                <a class="nav-link" data-widget="fullscreen" href="#" role="button">
                    <i class="fas fa-expand-arrows-alt"></i>
                </a>
            </li>
        </ul>
    </nav>
    <!-- /.navbar -->

    <!-- MAIN SIDEBAR CONTAINER -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <!-- Brand Logo -->
        <a href="?route=dashboard" class="brand-link" title="Junta de Usuarios San Lorenzo">
            <img src="<?= jushsal_logo_data_uri('icon') ?: asset('img/logo_icon.png') ?>" alt="JUSHSAL Logo" class="brand-image-logo">
            <div class="brand-text-container">
                <span class="brand-main-title">JUSHSAL</span>
                <span class="brand-sub-title">San Lorenzo &bull; Personal</span>
            </div>
        </a>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Sidebar user panel -->
            <div class="user-panel mt-3 pb-3 mb-3 d-flex align-items-center">
                <div class="image text-white pl-2">
                    <i class="fa-solid fa-user-circle fa-2x text-light"></i>
                </div>
                <div class="info">
                    <a href="#" class="d-block font-weight-bold text-truncate" style="max-width: 160px;"><?= htmlspecialchars($currentUser['nombre'] ?? 'Usuario') ?></a>
                    <span class="badge <?= $roleBadgeClass ?> mr-1" style="font-size: 70%;"><?= htmlspecialchars($userRole) ?></span>
                    <span class="badge badge-success" style="font-size: 70%;"><i class="fa-solid fa-circle mr-1" style="font-size: 6px;"></i>En línea</span>
                </div>
            </div>

            <!-- Sidebar Menu -->
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column nav-child-indent" data-widget="treeview" role="menu" data-accordion="false">
                    
                    <!-- MONITOREO & REPORTES: Visible para todos -->
                    <li class="nav-header">MONITOREO & REPORTES</li>
                    
                    <li class="nav-item">
                        <a href="?route=dashboard" class="nav-link <?= $currentRoute === 'dashboard' ? 'active' : '' ?>">
                            <i class="nav-icon fa-solid fa-chart-pie"></i>
                            <p>
                                <?= match($userRole) {
                                    'ADMIN' => 'Panel TI & Red',
                                    'RRHH' => 'Panel de Control RRHH',
                                    'SUPERVISOR' => 'Panel de Supervisión',
                                    default => 'Panel Principal'
                                } ?>
                            </p>
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a href="?route=asistencia" class="nav-link <?= $currentRoute === 'asistencia' ? 'active' : '' ?>">
                            <i class="nav-icon fa-solid fa-calendar-check"></i>
                            <p>Asistencia Diaria</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="?route=marcaciones" class="nav-link <?= $currentRoute === 'marcaciones' ? 'active' : '' ?>">
                            <i class="nav-icon fa-solid fa-clock-rotate-left"></i>
                            <p>Registro de Marcaciones</p>
                        </a>
                    </li>

                    <!-- GESTIÓN DE PERSONAL: Visible para ADMIN, RRHH y SUPERVISOR -->
                    <?php if (in_array($userRole, ['ADMIN', 'RRHH', 'SUPERVISOR'], true)): ?>
                        <li class="nav-header">GESTIÓN DE PERSONAL</li>

                        <li class="nav-item">
                            <a href="?route=empleados" class="nav-link <?= $currentRoute === 'empleados' ? 'active' : '' ?>">
                                <i class="nav-icon fa-solid fa-users"></i>
                                <p>Directorio de Personal</p>
                            </a>
                        </li>

                        <!-- Turnos y Horarios: Solo visible para ADMIN y RRHH -->
                        <?php if (in_array($userRole, ['ADMIN', 'RRHH'], true)): ?>
                            <li class="nav-item">
                                <a href="?route=turnos" class="nav-link <?= $currentRoute === 'turnos' ? 'active' : '' ?>">
                                    <i class="nav-icon fa-solid fa-business-time"></i>
                                    <p>Turnos y Horarios</p>
                                </a>
                            </li>
                        <?php endif; ?>

                        <li class="nav-item">
                            <a href="?route=justificaciones" class="nav-link <?= $currentRoute === 'justificaciones' ? 'active' : '' ?>">
                                <i class="nav-icon fa-solid fa-file-signature"></i>
                                <p>Justificaciones y Permisos</p>
                            </a>
                        </li>
                    <?php endif; ?>

                    <!-- Hardware & Red (Relojes ZKTeco): Exclusivo para ADMIN -->
                    <?php if ($userRole === 'ADMIN'): ?>
                        <li class="nav-header">HARDWARE & RED</li>

                        <li class="nav-item">
                            <a href="?route=dispositivos" class="nav-link <?= $currentRoute === 'dispositivos' ? 'active' : '' ?>">
                                <i class="nav-icon fa-solid fa-network-wired text-info"></i>
                                <p>
                                    Relojes Biométricos
                                    <span class="badge badge-danger right">TI</span>
                                </p>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <!-- /.sidebar-menu -->
        </div>
        <!-- /.sidebar -->
    </aside>

    <!-- CONTENT WRAPPER. Contains page content -->
    <div class="content-wrapper">
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'acceso_denegado'): ?>
            <div class="container-fluid pt-3">
                <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                    <i class="fa-solid fa-shield-halved mr-2"></i>
                    <strong>Acceso Restringido:</strong> Tu rol actual (<strong><?= htmlspecialchars($roleLabel) ?></strong>) no tiene permisos para acceder a ese módulo o ejecutar esa acción.
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>
        <?php endif; ?>
