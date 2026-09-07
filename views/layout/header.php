<?php
\App\Controllers\AuthController::checkAuth();
$currentUser = \App\Controllers\AuthController::user();
$userRole = $currentUser['rol'] ?? 'CONSULTA';
$currentRoute = $_GET['route'] ?? 'dashboard';

// Configuración de badges y títulos según rol
$roleBadgeClass = match($userRole) {
    'ADMIN' => 'badge-role-admin',
    'RRHH' => 'badge-role-rrhh',
    'SUPERVISOR' => 'badge-role-supervisor',
    default => 'badge-role-consulta'
};

$roleLabel = match($userRole) {
    'ADMIN' => 'Administrador',
    'RRHH' => 'Recursos Humanos',
    'SUPERVISOR' => 'Supervisor de Área',
    'CONSULTA' => 'Consulta',
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

    <!-- Google Font: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

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
        :root {
            --font-sans: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --sidebar-bg: #0f172a;
            --sidebar-active-bg: rgba(37, 99, 235, 0.16);
            --sidebar-active-text: #60a5fa;
            --sidebar-hover-bg: rgba(255, 255, 255, 0.05);
            --brand-primary: #1e40af;
            --brand-primary-light: #eff6ff;
            --brand-accent: #2563eb;
            --slate-50: #f8fafc;
            --slate-100: #f1f5f9;
            --slate-200: #e2e8f0;
            --slate-300: #cbd5e1;
            --slate-600: #475569;
            --slate-700: #334155;
            --slate-800: #1e293b;
            --slate-900: #0f172a;
        }

        body {
            font-family: var(--font-sans) !important;
            background-color: #f4f6f9 !important;
            color: #1e293b !important;
            font-size: 0.885rem;
            letter-spacing: -0.01em;
        }

        /* SIDEBAR Y BRAND */
        .main-sidebar {
            background-color: var(--sidebar-bg) !important;
            border-right: 1px solid #1e293b;
        }
        .brand-link {
            padding: 0.85rem 1rem !important;
            display: flex !important;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
            background: transparent !important;
            overflow: hidden;
            height: 57px;
            transition: all 0.3s ease-in-out;
        }
        .brand-link .brand-image-logo {
            width: 36px;
            height: 36px;
            object-fit: contain;
            background: #ffffff;
            border-radius: 8px;
            padding: 3px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            margin-right: 0.8rem;
            flex-shrink: 0;
            transition: all 0.3s ease-in-out;
        }
        .brand-text-container {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            transition: opacity 0.2s ease-in-out;
        }
        .brand-main-title {
            font-size: 1.12rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            color: #ffffff;
        }
        .brand-sub-title {
            font-size: 0.76rem;
            color: #94a3b8;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            letter-spacing: 0.2px;
        }

        /* AJUSTES PARA MENÚ LATERAL COLAPSADO (SIDEBAR-COLLAPSE) */
        .sidebar-collapse .main-sidebar:not(:hover) .brand-link {
            justify-content: center !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
            text-align: center !important;
        }
        .sidebar-collapse .main-sidebar:not(:hover) .brand-link .brand-image-logo {
            margin-right: 0 !important;
            margin-left: 0 !important;
        }
        .sidebar-collapse .main-sidebar:not(:hover) .brand-text-container {
            display: none !important;
            opacity: 0 !important;
            visibility: hidden !important;
            width: 0 !important;
            height: 0 !important;
        }
        .sidebar-collapse .main-sidebar:hover .brand-link {
            justify-content: flex-start !important;
            padding: 0.85rem 1rem !important;
        }
        .sidebar-collapse .main-sidebar:hover .brand-link .brand-image-logo {
            margin-right: 0.8rem !important;
        }
        .sidebar-collapse .main-sidebar:hover .brand-text-container {
            display: flex !important;
            opacity: 1 !important;
            visibility: visible !important;
            width: auto !important;
        }

        /* SIDEBAR USER PANEL */
        .user-panel {
            padding: 0.85rem 0.75rem !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
            margin-top: 0 !important;
            margin-bottom: 0.5rem !important;
        }
        .user-panel .info {
            padding-left: 0.75rem !important;
        }
        .user-panel-name {
            font-size: 0.875rem;
            font-weight: 700;
            color: #f8fafc;
            line-height: 1.2;
            margin-bottom: 3px;
            display: block;
        }

        /* SIDEBAR NAV ITEMS */
        .sidebar .nav-header {
            font-size: 0.72rem !important;
            font-weight: 700 !important;
            letter-spacing: 0.08em !important;
            text-transform: uppercase !important;
            color: #64748b !important;
            padding: 1rem 1rem 0.4rem 1rem !important;
        }
        .sidebar .nav-sidebar .nav-item {
            margin-bottom: 2px;
        }
        .sidebar .nav-sidebar .nav-link {
            border-radius: 6px;
            padding: 0.55rem 0.85rem;
            font-size: 0.865rem;
            font-weight: 500;
            color: #cbd5e1;
            display: flex;
            align-items: center;
            transition: all 0.15s ease-in-out;
        }
        .sidebar .nav-sidebar .nav-link .nav-icon {
            font-size: 0.95rem;
            width: 22px;
            margin-right: 0.65rem;
            text-align: center;
            color: #94a3b8;
            transition: color 0.15s ease-in-out;
        }
        .sidebar .nav-sidebar .nav-link:hover {
            background-color: var(--sidebar-hover-bg);
            color: #ffffff;
        }
        .sidebar .nav-sidebar .nav-link:hover .nav-icon {
            color: #60a5fa;
        }
        .sidebar .nav-sidebar .nav-link.active {
            background-color: var(--sidebar-active-bg) !important;
            color: #60a5fa !important;
            font-weight: 600;
            border-left: 3px solid #3b82f6;
        }
        .sidebar .nav-sidebar .nav-link.active .nav-icon {
            color: #60a5fa !important;
        }

        /* TOP NAVBAR */
        .main-header.navbar {
            background-color: #ffffff !important;
            border-bottom: 1px solid #e2e8f0 !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
            min-height: 56px;
            padding: 0.35rem 1rem;
        }
        .navbar-nav .nav-link {
            font-weight: 500;
            font-size: 0.865rem;
            color: #334155 !important;
        }
        .navbar-nav .nav-link:hover {
            color: #1d4ed8 !important;
        }

        /* TARJETAS Y CONTENEDORES */
        .card {
            border: 1px solid #e2e8f0 !important;
            border-radius: 10px !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04), 0 1px 2px rgba(0, 0, 0, 0.02) !important;
            background-color: #ffffff;
            margin-bottom: 1.25rem;
        }
        .card-header {
            background-color: #ffffff !important;
            border-bottom: 1px solid #f1f5f9 !important;
            padding: 0.85rem 1.15rem !important;
        }
        .card-header .card-title {
            font-size: 0.95rem !important;
            font-weight: 700 !important;
            color: #0f172a !important;
            margin-bottom: 0;
            display: flex;
            align-items: center;
        }
        .card-body {
            padding: 1.15rem !important;
        }

        /* KPI / STAT CARDS ESTANDARIZADOS */
        .kpi-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 1.1rem 1.15rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 118px;
            position: relative;
            overflow: hidden;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.06);
        }
        .kpi-card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
        }
        .kpi-title {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
            margin-bottom: 0.25rem;
        }
        .kpi-value {
            font-size: 1.7rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.15;
            margin-bottom: 0.25rem;
        }
        .kpi-subtitle {
            font-size: 0.78rem;
            color: #64748b;
            font-weight: 500;
        }
        .kpi-icon-box {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        .kpi-icon-blue { background: #eff6ff; color: #2563eb; }
        .kpi-icon-emerald { background: #ecfdf5; color: #059669; }
        .kpi-icon-amber { background: #fffbeb; color: #d97706; }
        .kpi-icon-rose { background: #fef2f2; color: #dc2626; }
        .kpi-icon-slate { background: #f1f5f9; color: #475569; }
        .kpi-icon-teal { background: #f0fdfa; color: #0d9488; }
        .kpi-icon-indigo { background: #eef2ff; color: #4f46e5; }

        .kpi-footer-link {
            display: inline-flex;
            align-items: center;
            font-size: 0.77rem;
            font-weight: 600;
            color: #2563eb;
            margin-top: 0.5rem;
            text-decoration: none;
        }
        .kpi-footer-link:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }

        /* BADGES TIPO PILL REFINADOS */
        .badge-pill-custom {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 9px;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 9999px;
            line-height: 1.3;
        }
        .badge-pill-presente { background-color: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .badge-pill-tardanza { background-color: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
        .badge-pill-falta { background-color: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .badge-pill-justificado { background-color: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
        .badge-pill-sin-salida { background-color: #f5f3ff; color: #5b21b6; border: 1px solid #ddd6fe; }
        .badge-pill-online { background-color: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .badge-pill-offline { background-color: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .badge-pill-neutral { background-color: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; }

        /* ROLES */
        .badge-role-admin { background-color: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .badge-role-rrhh { background-color: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
        .badge-role-supervisor { background-color: #f0fdfa; color: #0f766e; border: 1px solid #99f6e4; }
        .badge-role-consulta { background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }

        /* TABLAS MODERNAS */
        .table {
            color: #334155 !important;
            margin-bottom: 0 !important;
        }
        .table thead th {
            background-color: #f8fafc !important;
            color: #475569 !important;
            font-size: 0.76rem !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.04em !important;
            border-top: none !important;
            border-bottom: 1px solid #e2e8f0 !important;
            padding: 0.75rem 0.85rem !important;
            vertical-align: middle !important;
        }
        .table td {
            padding: 0.65rem 0.85rem !important;
            vertical-align: middle !important;
            font-size: 0.865rem !important;
            border-top: 1px solid #f1f5f9 !important;
        }
        .table-hover tbody tr:hover {
            background-color: #f8fafc !important;
        }

        /* FORMULARIOS Y FILTROS */
        .form-control, .custom-select {
            border: 1px solid #cbd5e1 !important;
            border-radius: 6px !important;
            font-size: 0.865rem !important;
            color: #1e293b !important;
            height: 38px;
        }
        .form-control:focus, .custom-select:focus {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12) !important;
        }
        .form-control-sm, .custom-select-sm {
            height: 34px !important;
            font-size: 0.825rem !important;
        }
        label.form-label-custom {
            font-size: 0.78rem;
            font-weight: 700;
            color: #475569;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
        }

        /* BOTONES */
        .btn {
            border-radius: 6px !important;
            font-weight: 600 !important;
            font-size: 0.84rem !important;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: all 0.15s ease-in-out;
        }
        .btn-sm {
            padding: 0.35rem 0.75rem !important;
            font-size: 0.825rem !important;
        }
        .btn-primary {
            background-color: #1d4ed8 !important;
            border-color: #1d4ed8 !important;
        }
        .btn-primary:hover {
            background-color: #1e40af !important;
            border-color: #1e40af !important;
        }

        /* MODALES */
        .modal-content {
            border-radius: 12px !important;
            border: 1px solid #e2e8f0 !important;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1) !important;
            overflow: hidden;
        }
        .modal-header {
            padding: 1rem 1.25rem !important;
            border-bottom: 1px solid #e2e8f0 !important;
        }
        .modal-footer {
            background-color: #f8fafc !important;
            border-top: 1px solid #e2e8f0 !important;
            padding: 0.85rem 1.25rem !important;
        }

        /* BANNER INSTITUCIONAL */
        .banner-jushsal {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #1d4ed8 !important;
            border-radius: 10px;
            padding: 0.85rem 1.25rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }

        /* CLASES DE REPORTE E IMPRESIÓN OFICIAL */
        .print-only {
            display: none !important;
        }

        @media print {
            .no-print, .main-sidebar, .main-header, .main-footer, .breadcrumb, .card-tools, .card-body form, .btn, .dataTables_length, .dataTables_filter, .dataTables_info, .dataTables_paginate, .alert, .preloader, .modal {
                display: none !important;
            }
            .print-only {
                display: block !important;
            }
            body, .content-wrapper, .wrapper {
                background: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
                color: #000000 !important;
            }
            .content-wrapper {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            .card {
                border: none !important;
                box-shadow: none !important;
                margin-bottom: 0 !important;
            }
            .card-header {
                display: none !important;
            }
            .card-body {
                padding: 0 !important;
            }
            .table {
                width: 100% !important;
                border-collapse: collapse !important;
                font-size: 8.5pt !important;
                margin-top: 10px !important;
            }
            .table th {
                background-color: #f1f5f9 !important;
                color: #0f172a !important;
                border: 1px solid #475569 !important;
                padding: 5px 4px !important;
                text-align: center !important;
            }
            .table td {
                border: 1px solid #cbd5e1 !important;
                padding: 4px 5px !important;
                color: #0f172a !important;
            }
            .badge {
                border: 1px solid #64748b !important;
                color: #0f172a !important;
                background: transparent !important;
                font-weight: bold !important;
                font-size: 8pt !important;
            }
            thead {
                display: table-header-group;
            }
            tr {
                page-break-inside: avoid;
            }
            .print-header-container {
                border-bottom: 2px solid #1e3a8a;
                padding-bottom: 10px;
                margin-bottom: 12px;
            }
            .print-signatures {
                page-break-inside: avoid;
                margin-top: 40px;
                width: 100%;
            }
            @page {
                size: landscape;
                margin: 0.8cm;
            }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed layout-footer-fixed">
<div class="wrapper">

    <!-- Preloader Institucional -->
    <div class="preloader flex-column justify-content-center align-items-center bg-white">
        <img class="mb-3" src="<?= jushsal_logo_data_uri('icon') ?: asset('img/logo_icon.png') ?>" alt="JUSHSAL" height="70" width="70" style="object-fit: contain;">
        <span class="font-weight-bold text-dark h5 mb-1" style="letter-spacing: 0.5px;">JUSHSAL</span>
        <span class="text-muted small">Control de Personal y Asistencia Laboral</span>
    </div>

    <!-- NAVBAR SUPERIOR -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <?php if (\App\Controllers\AuthController::hasPermission('dashboard')): ?>
                <li class="nav-item d-none d-sm-inline-block">
                    <a href="?route=dashboard" class="nav-link <?= $currentRoute === 'dashboard' ? 'text-primary font-weight-bold' : '' ?>">
                        <i class="fa-solid fa-chart-pie mr-1"></i> Tablero Principal
                    </a>
                </li>
            <?php endif; ?>
            <?php if (\App\Controllers\AuthController::hasPermission('asistencia')): ?>
                <li class="nav-item d-none d-sm-inline-block">
                    <a href="?route=asistencia" class="nav-link <?= $currentRoute === 'asistencia' ? 'text-primary font-weight-bold' : '' ?>">
                        <i class="fa-solid fa-calendar-check mr-1"></i> Control de Asistencia
                    </a>
                </li>
            <?php endif; ?>
        </ul>

        <!-- Right navbar links -->
        <ul class="navbar-nav ml-auto align-items-center">
            <!-- Sync Button: Solo visible si tiene permiso de dispositivos/hardware o ADMIN -->
            <?php if (\App\Controllers\AuthController::hasPermission('dispositivos') || in_array($userRole, ['ADMIN', 'RRHH'], true)): ?>
                <li class="nav-item mr-2">
                    <a href="javascript:void(0)" onclick="typeof openSyncModal === 'function' ? openSyncModal() : (typeof syncAllDevices === 'function' ? syncAllDevices('today', this) : window.location.href='?route=dispositivos')" class="btn btn-sm btn-outline-success" title="Sincronización rápida de relojes biométricos">
                        <i class="fa-solid fa-bolt mr-1"></i> Sincronizar Hoy
                    </a>
                </li>
            <?php endif; ?>

            <!-- Date badge -->
            <li class="nav-item d-none d-md-inline-block mr-3">
                <span class="badge-pill-custom badge-pill-neutral">
                    <i class="far fa-calendar-alt mr-1"></i> <?= date('d/m/Y') ?>
                </span>
            </li>

            <!-- User Dropdown Menu Clean -->
            <li class="nav-item dropdown">
                <a href="#" class="nav-link px-2 d-flex align-items-center" data-toggle="dropdown" title="Mi Cuenta" aria-expanded="false">
                    <div class="d-flex align-items-center justify-content-center bg-light rounded-circle border" style="width: 34px; height: 34px;">
                        <i class="fa-solid fa-user text-primary" style="font-size: 0.9rem;"></i>
                    </div>
                </a>
                <div class="dropdown-menu dropdown-menu-right shadow-sm border-0 py-2" style="border-radius: 10px; min-width: 220px;">
                    <div class="px-3 py-2 border-bottom">
                        <div class="font-weight-bold text-dark text-truncate" style="font-size: 0.88rem;"><?= htmlspecialchars($currentUser['nombre'] ?? 'Usuario') ?></div>
                        <div class="d-flex align-items-center mt-1">
                            <span class="badge-pill-custom <?= $roleBadgeClass ?>" style="font-size: 0.68rem;"><?= htmlspecialchars($roleLabel) ?></span>
                        </div>
                    </div>
                    <a href="javascript:void(0)" onclick="openMiPerfilModal()" class="dropdown-item py-2 small">
                        <i class="fa-solid fa-user-gear mr-2 text-primary"></i> Mi Perfil y Seguridad
                    </a>
                    <div class="dropdown-divider my-1"></div>
                    <a href="?route=logout" class="dropdown-item py-2 small text-danger" onclick="return confirm('¿Deseas cerrar tu sesión actual?')">
                        <i class="fa-solid fa-right-from-bracket mr-2"></i> Cerrar Sesión
                    </a>
                </div>
            </li>

            <li class="nav-item ml-1">
                <a class="nav-link" data-widget="fullscreen" href="#" role="button" title="Pantalla completa">
                    <i class="fas fa-expand-arrows-alt"></i>
                </a>
            </li>
        </ul>
    </nav>
    <!-- /.navbar -->

    <!-- MAIN SIDEBAR CONTAINER -->
    <aside class="main-sidebar elevation-0">
        <!-- Brand Logo -->
        <a href="?route=<?= \App\Controllers\AuthController::getFirstAccessibleRoute() ?>" class="brand-link" title="Junta de Usuarios del Sector Hidráulico Menor San Lorenzo">
            <img src="<?= jushsal_logo_data_uri('icon') ?: asset('img/logo_icon.png') ?>" alt="JUSHSAL Logo" class="brand-image-logo">
            <div class="brand-text-container brand-text">
                <span class="brand-main-title">JUSHSAL</span>
                <span class="brand-sub-title">Control de Personal</span>
            </div>
        </a>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Sidebar Menu -->
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                    
                    <!-- 1. MONITOREO Y CONTROL -->
                    <?php 
                        $hasMonitoreo = \App\Controllers\AuthController::hasPermission('dashboard') 
                                     || \App\Controllers\AuthController::hasPermission('asistencia') 
                                     || \App\Controllers\AuthController::hasPermission('marcaciones');
                    ?>
                    <?php if ($hasMonitoreo): ?>
                        <li class="nav-header">MONITOREO Y CONTROL</li>
                        
                        <?php if (\App\Controllers\AuthController::hasPermission('dashboard')): ?>
                            <li class="nav-item">
                                <a href="?route=dashboard" class="nav-link <?= $currentRoute === 'dashboard' ? 'active' : '' ?>">
                                    <i class="nav-icon fa-solid fa-chart-pie"></i>
                                    <p>Tablero Principal</p>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php if (\App\Controllers\AuthController::hasPermission('asistencia')): ?>
                            <li class="nav-item">
                                <a href="?route=asistencia" class="nav-link <?= $currentRoute === 'asistencia' ? 'active' : '' ?>">
                                    <i class="nav-icon fa-solid fa-calendar-check"></i>
                                    <p>Control de Asistencia</p>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php if (\App\Controllers\AuthController::hasPermission('marcaciones')): ?>
                            <li class="nav-item">
                                <a href="?route=marcaciones" class="nav-link <?= $currentRoute === 'marcaciones' ? 'active' : '' ?>">
                                    <i class="nav-icon fa-solid fa-clock-rotate-left"></i>
                                    <p>Registro de Marcaciones</p>
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- 2. GESTIÓN DE PERSONAL -->
                    <?php 
                        $hasPersonal = \App\Controllers\AuthController::hasPermission('empleados') 
                                    || \App\Controllers\AuthController::hasPermission('turnos') 
                                    || \App\Controllers\AuthController::hasPermission('justificaciones');
                    ?>
                    <?php if ($hasPersonal): ?>
                        <li class="nav-header">GESTIÓN DE PERSONAL</li>

                        <?php if (\App\Controllers\AuthController::hasPermission('empleados')): ?>
                            <li class="nav-item">
                                <a href="?route=empleados" class="nav-link <?= $currentRoute === 'empleados' ? 'active' : '' ?>">
                                    <i class="nav-icon fa-solid fa-users"></i>
                                    <p>Directorio de Personal</p>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php if (\App\Controllers\AuthController::hasPermission('turnos')): ?>
                            <li class="nav-item">
                                <a href="?route=turnos" class="nav-link <?= $currentRoute === 'turnos' ? 'active' : '' ?>">
                                    <i class="nav-icon fa-solid fa-business-time"></i>
                                    <p>Turnos y Horarios</p>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php if (\App\Controllers\AuthController::hasPermission('justificaciones')): ?>
                            <li class="nav-item">
                                <a href="?route=justificaciones" class="nav-link <?= $currentRoute === 'justificaciones' ? 'active' : '' ?>">
                                    <i class="nav-icon fa-solid fa-file-signature"></i>
                                    <p>Permisos y Justificaciones</p>
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- 3. EQUIPOS Y RED (Relojes ZKTeco) -->
                    <?php if (\App\Controllers\AuthController::hasPermission('dispositivos') || $userRole === 'ADMIN'): ?>
                        <li class="nav-header">EQUIPOS Y RED</li>

                        <li class="nav-item">
                            <a href="?route=dispositivos" class="nav-link <?= $currentRoute === 'dispositivos' ? 'active' : '' ?>">
                                <i class="nav-icon fa-solid fa-network-wired"></i>
                                <p>Relojes Biométricos</p>
                            </a>
                        </li>
                    <?php endif; ?>

                    <!-- 4. SISTEMA Y SEGURIDAD (Exclusivo Administrador) -->
                    <?php if ($userRole === 'ADMIN' || \App\Controllers\AuthController::hasPermission('usuarios')): ?>
                        <li class="nav-header">SISTEMA Y SEGURIDAD</li>

                        <li class="nav-item">
                            <a href="?route=usuarios" class="nav-link <?= $currentRoute === 'usuarios' ? 'active' : '' ?>">
                                <i class="nav-icon fa-solid fa-user-shield"></i>
                                <p>Usuarios del Sistema</p>
                            </a>
                        </li>
                    <?php endif; ?>

                    <!-- 5. MI CUENTA Y PERFIL -->
                    <li class="nav-header">MI CUENTA</li>

                    <li class="nav-item">
                        <a href="javascript:void(0)" onclick="openMiPerfilModal()" class="nav-link">
                            <i class="nav-icon fa-solid fa-circle-user text-primary"></i>
                            <p>Mi Perfil</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="?route=logout" class="nav-link text-danger" onclick="return confirm('¿Deseas cerrar tu sesión actual?')">
                            <i class="nav-icon fa-solid fa-right-from-bracket text-danger"></i>
                            <p>Cerrar Sesión</p>
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
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'acceso_denegado'): ?>
            <div class="container-fluid pt-3">
                <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                    <i class="fa-solid fa-shield-halved mr-2"></i>
                    <strong>Acceso Restringido:</strong> Tu cuenta o rol actual no tiene permisos autorizados para acceder a ese módulo.
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>
        <?php endif; ?>

