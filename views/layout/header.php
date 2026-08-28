<?php
$currentUser = \App\Controllers\AuthController::user();
$currentRoute = $_GET['route'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(APP_NAME) ?> | Panel de Control</title>

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
        .brand-link .brand-image {
            float: left;
            line-height: .8;
            margin-left: .8rem;
            margin-right: .5rem;
            margin-top: -3px;
            max-height: 33px;
            width: auto;
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
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed layout-footer-fixed">
<div class="wrapper">

    <!-- Preloader opcional -->
    <div class="preloader flex-column justify-content-center align-items-center">
        <i class="fa-solid fa-fingerprint fa-3x text-primary animation__wobble"></i>
        <span class="mt-2 font-weight-bold text-secondary">Cargando Control de Asistencia...</span>
    </div>

    <!-- NAVBAR -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light border-bottom">
        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="?route=dashboard" class="nav-link"><i class="fa-solid fa-gauge-high mr-1"></i> Dashboard</a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="?route=asistencia" class="nav-link"><i class="fa-solid fa-calendar-check mr-1"></i> Asistencia</a>
            </li>
        </ul>

        <!-- Right navbar links -->
        <ul class="navbar-nav ml-auto align-items-center">
            <!-- Sync Button -->
            <li class="nav-item mr-2">
                <a href="?route=dispositivos&action=sincronizar" class="btn btn-sm btn-outline-primary" title="Sincronizar todos los relojes ZKTeco">
                    <i class="fa-solid fa-arrows-rotate mr-1"></i> Sincronizar Relojes
                </a>
            </li>

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
                </a>
                <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right shadow border-0">
                    <!-- User image -->
                    <li class="user-header bg-primary">
                        <i class="fa-solid fa-user-shield fa-3x mb-2 text-white"></i>
                        <p>
                            <?= htmlspecialchars($currentUser['nombre'] ?? 'Usuario') ?>
                            <small>Rol: <?= htmlspecialchars($currentUser['rol'] ?? 'RRHH') ?></small>
                        </p>
                    </li>
                    <!-- Menu Footer-->
                    <li class="user-footer d-flex justify-content-between">
                        <span class="text-muted small align-self-center"><i class="fa-solid fa-shield-halved mr-1"></i> ZK-Control</span>
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
        <a href="?route=dashboard" class="brand-link">
            <i class="fa-solid fa-fingerprint brand-image text-primary fa-2x mt-0"></i>
            <span class="brand-text font-weight-bold">ZK-Control</span>
        </a>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Sidebar user panel -->
            <div class="user-panel mt-3 pb-3 mb-3 d-flex align-items-center">
                <div class="image text-white pl-2">
                    <i class="fa-solid fa-user-circle fa-2x text-light"></i>
                </div>
                <div class="info">
                    <a href="#" class="d-block font-weight-bold"><?= htmlspecialchars($currentUser['nombre'] ?? 'Administrador') ?></a>
                    <span class="badge badge-success" style="font-size: 70%;"><i class="fa-solid fa-circle mr-1" style="font-size: 6px;"></i>En línea</span>
                </div>
            </div>

            <!-- Sidebar Menu -->
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column nav-child-indent" data-widget="treeview" role="menu" data-accordion="false">
                    
                    <li class="nav-header">MONITOREO & REPORTES</li>
                    
                    <li class="nav-item">
                        <a href="?route=dashboard" class="nav-link <?= $currentRoute === 'dashboard' ? 'active' : '' ?>">
                            <i class="nav-icon fa-solid fa-chart-pie"></i>
                            <p>Dashboard</p>
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
                            <p>Marcaciones Crudas</p>
                        </a>
                    </li>

                    <li class="nav-header">GESTIÓN DE PERSONAL</li>

                    <li class="nav-item">
                        <a href="?route=empleados" class="nav-link <?= $currentRoute === 'empleados' ? 'active' : '' ?>">
                            <i class="nav-icon fa-solid fa-users"></i>
                            <p>Empleados</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="?route=turnos" class="nav-link <?= $currentRoute === 'turnos' ? 'active' : '' ?>">
                            <i class="nav-icon fa-solid fa-business-time"></i>
                            <p>Turnos y Horarios</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="?route=justificaciones" class="nav-link <?= $currentRoute === 'justificaciones' ? 'active' : '' ?>">
                            <i class="nav-icon fa-solid fa-file-signature"></i>
                            <p>Justificaciones</p>
                        </a>
                    </li>

                    <li class="nav-header">HARDWARE & RED</li>

                    <li class="nav-item">
                        <a href="?route=dispositivos" class="nav-link <?= $currentRoute === 'dispositivos' ? 'active' : '' ?>">
                            <i class="nav-icon fa-solid fa-network-wired"></i>
                            <p>
                                Relojes ZKTeco
                                <span class="badge badge-info right">Auto</span>
                            </p>
                        </a>
                    </li>
                </ul>
            </nav>
            <!-- /.sidebar-menu -->
        </div>
        <!-- /.sidebar -->
    </aside>

    <!-- CONTENT WRAPPER. Contains page content -->
    <div class="content-wrapper">
