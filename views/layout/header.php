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
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title><?= htmlspecialchars(APP_NAME) ?> | Panel de Control</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= jushsal_logo_data_uri('favicon') ?: asset('img/favicon.png') ?>">
    <link rel="apple-touch-icon" href="<?= jushsal_logo_data_uri('icon') ?: asset('img/logo_icon.png') ?>">

    <!-- Google Font: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
    <!-- overlayScrollbars -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.4.4/styles/overlayscrollbars.min.css" crossorigin="anonymous">
    <!-- DataTables Bootstrap 4 -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap4.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap4.min.css" crossorigin="anonymous">
    <!-- AdminLTE v3.2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css" crossorigin="anonymous">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" crossorigin="anonymous">
    <!-- Select2 CSS & Bootstrap 4 Theme -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css" crossorigin="anonymous">

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

        /* TARJETAS Y CONTENEDORES ULTRA LIMPIOS */
        .card {
            border: 1px solid #eef2f6 !important;
            border-radius: 12px !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02), 0 1px 2px rgba(0, 0, 0, 0.01) !important;
            background-color: #ffffff;
            margin-bottom: 0.85rem;
            transition: border-color 0.15s ease;
        }
        .card-header {
            background-color: #ffffff !important;
            border-bottom: 1px solid #f1f5f9 !important;
            padding: 0.75rem 1.25rem !important;
        }
        .card-header::after,
        .card-header::before,
        .card-header.d-flex::after,
        .card-header.d-flex::before {
            display: none !important;
            content: none !important;
        }
        .card-header .card-title {
            font-size: 0.88rem !important;
            font-weight: 700 !important;
            color: #1e293b !important;
            margin-bottom: 0;
            display: flex;
            align-items: center;
        }
        .card-header .card-tools,
        .card-tools {
            margin-left: auto !important;
            margin-right: 0 !important;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        .card-body {
            padding: 0.85rem 1rem !important;
        }

        /* KPI / STAT CARDS MINIMALISTAS Y LIGEROS */
        .kpi-card {
            background: #ffffff;
            border: 1px solid #eef2f6;
            border-radius: 12px;
            padding: 0.75rem 0.95rem;
            margin-bottom: 0.85rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: auto;
            position: relative;
            overflow: hidden;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
            border-color: #e2e8f0;
        }
        .kpi-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .kpi-title {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #64748b;
            margin-bottom: 0.15rem;
        }
        .kpi-value {
            font-size: 1.45rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.15;
            margin-bottom: 0.1rem;
        }
        .kpi-subtitle {
            font-size: 0.72rem;
            color: #94a3b8;
            font-weight: 500;
            line-height: 1.2;
        }
        .kpi-icon-box {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
            margin-left: 0.5rem;
        }
        .kpi-icon-blue { background: #eff6ff; color: #2563eb; }
        .kpi-icon-emerald { background: #ecfdf5; color: #059669; }
        .kpi-icon-amber { background: #fffbeb; color: #d97706; }
        .kpi-icon-rose { background: #fef2f2; color: #dc2626; }
        .kpi-icon-slate { background: #f8fafc; color: #475569; }
        .kpi-icon-teal { background: #f0fdfa; color: #0d9488; }
        .kpi-icon-indigo { background: #eef2ff; color: #4f46e5; }

        .kpi-footer-link {
            display: inline-flex;
            align-items: center;
            font-size: 0.72rem;
            font-weight: 600;
            color: #2563eb;
            margin-top: 0.35rem;
            padding-top: 0.35rem;
            border-top: 1px dashed #f1f5f9;
            text-decoration: none;
        }
        .kpi-footer-link:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }

        /* BADGES MINIMALISTAS Y ELEGANTES (SIN BORDES PESADOS) */
        .badge-pill-custom {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            font-size: 0.72rem;
            font-weight: 600;
            border-radius: 6px;
            line-height: 1.3;
        }
        .badge-pill-presente { background-color: #ecfdf5; color: #065f46; }
        .badge-pill-tardanza { background-color: #fffbeb; color: #92400e; }
        .badge-pill-falta { background-color: #fef2f2; color: #991b1b; }
        .badge-pill-justificado { background-color: #eff6ff; color: #1e40af; }
        .badge-pill-sin-salida { background-color: #f5f3ff; color: #5b21b6; }
        .badge-pill-online { background-color: #ecfdf5; color: #065f46; }
        .badge-pill-offline { background-color: #fef2f2; color: #991b1b; }
        .badge-pill-neutral { background-color: #f1f5f9; color: #475569; }

        /* ROLES */
        .badge-role-admin { background-color: #fef2f2; color: #991b1b; }
        .badge-role-rrhh { background-color: #eff6ff; color: #1e40af; }
        .badge-role-supervisor { background-color: #f0fdfa; color: #0f766e; }
        .badge-role-consulta { background-color: #f1f5f9; color: #475569; }

        /* TABLAS MODERNAS Y LIGERAS */
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
            letter-spacing: 0.03em !important;
            border-top: none !important;
            border-bottom: 1px solid #e2e8f0 !important;
            padding: 0.6rem 0.85rem !important;
            vertical-align: middle !important;
        }
        .table td {
            padding: 0.55rem 0.85rem !important;
            vertical-align: middle !important;
            font-size: 0.85rem !important;
            border-top: 1px solid #f1f5f9 !important;
        }
        .table-hover tbody tr:hover {
            background-color: #f8fafc !important;
        }

        /* DATATABLES REFINEMENT */
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border-radius: 6px !important;
            padding: 0.35rem 0.65rem !important;
            font-size: 0.82rem !important;
            font-weight: 600 !important;
            border: 1px solid #e2e8f0 !important;
            background: #ffffff !important;
            color: #475569 !important;
            margin: 0 2px !important;
            transition: all 0.15s ease !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current, 
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: #1d4ed8 !important;
            color: #ffffff !important;
            border-color: #1d4ed8 !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover:not(.current):not(.disabled) {
            background: #f1f5f9 !important;
            color: #1d4ed8 !important;
            border-color: #cbd5e1 !important;
        }
        .dataTables_wrapper .dataTables_filter input {
            border-radius: 6px !important;
            border: 1px solid #cbd5e1 !important;
            padding: 0.3rem 0.65rem !important;
            font-size: 0.85rem !important;
            margin-left: 0.4rem !important;
            transition: all 0.15s ease !important;
        }
        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12) !important;
            outline: none !important;
        }
        .dataTables_wrapper .dataTables_length select {
            border-radius: 6px !important;
            border: 1px solid #cbd5e1 !important;
            padding: 0.25rem 1.65rem 0.25rem 0.65rem !important;
            min-width: 68px !important;
            font-size: 0.85rem !important;
            margin: 0 0.35rem !important;
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

        /* SELECT2 BOOTSTRAP 4 CUSTOM THEME & REFINEMENT */
        .select2-container {
            width: 100% !important;
        }
        .select2-container--bootstrap4 .select2-selection--single {
            height: 34px !important;
            padding: 0.35rem 0.65rem !important;
            font-size: 0.835rem !important;
            line-height: 1.5 !important;
            border-radius: 6px !important;
            border: 1px solid #cbd5e1 !important;
            background-color: #ffffff !important;
            font-weight: 600;
            color: #1e293b !important;
            display: flex !important;
            align-items: center;
            transition: all 0.16s ease !important;
        }
        .select2-container--bootstrap4.select2-container--focus .select2-selection--single,
        .select2-container--bootstrap4.select2-container--open .select2-selection--single {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12) !important;
        }
        .select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow {
            top: 50% !important;
            transform: translateY(-50%) !important;
            right: 8px !important;
        }
        .select2-container--bootstrap4 .select2-dropdown {
            border: 1px solid #cbd5e1 !important;
            border-radius: 8px !important;
            box-shadow: 0 12px 28px -4px rgba(0, 0, 0, 0.12), 0 8px 10px -6px rgba(0, 0, 0, 0.08) !important;
            font-size: 0.835rem !important;
            z-index: 99999 !important;
            overflow: hidden;
            background: #ffffff !important;
        }
        .select2-container--bootstrap4 .select2-search--dropdown {
            padding: 6px 8px !important;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .select2-container--bootstrap4 .select2-search--dropdown .select2-search__field {
            border-radius: 6px !important;
            border: 1px solid #cbd5e1 !important;
            padding: 0.35rem 0.65rem !important;
            font-size: 0.825rem !important;
            width: 100% !important;
            outline: none;
        }
        .select2-container--bootstrap4 .select2-search--dropdown .select2-search__field:focus {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15) !important;
        }
        .select2-container--bootstrap4 .select2-results__option {
            padding: 7px 12px !important;
            font-size: 0.835rem !important;
            color: #334155 !important;
            transition: background-color 0.12s ease;
        }
        .select2-container--bootstrap4 .select2-results__option--highlighted[aria-selected] {
            background-color: #1d4ed8 !important;
            color: #ffffff !important;
        }
        .select2-container--bootstrap4 .select2-results__option[aria-selected=true] {
            background-color: #eff6ff !important;
            color: #1d4ed8 !important;
            font-weight: 700 !important;
        }

        /* BREADCRUMB ELEGANTE SIN SLASH */
        .breadcrumb-item + .breadcrumb-item::before {
            content: "›" !important;
            padding: 0 0.45rem !important;
            color: #94a3b8 !important;
            font-size: 1rem !important;
            font-weight: 700 !important;
            line-height: 1 !important;
        }

        /* BOTONES Y TOOLBARS UI/UX MEJORADOS */
        .btn {
            border-radius: 7px !important;
            font-weight: 600 !important;
            font-size: 0.835rem !important;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1) !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
            white-space: nowrap;
        }
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.08);
        }
        .btn:active {
            transform: translateY(0);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        .btn-sm {
            padding: 0.4rem 0.85rem !important;
            font-size: 0.825rem !important;
        }
        .btn-xs {
            padding: 0.25rem 0.55rem !important;
            font-size: 0.75rem !important;
            border-radius: 5px !important;
        }
        .btn-primary {
            background-color: #1d4ed8 !important;
            border-color: #1d4ed8 !important;
            color: #ffffff !important;
        }
        .btn-primary:hover {
            background-color: #1e40af !important;
            border-color: #1e40af !important;
            color: #ffffff !important;
        }
        .btn-success {
            background-color: #059669 !important;
            border-color: #059669 !important;
            color: #ffffff !important;
        }
        .btn-success:hover {
            background-color: #047857 !important;
            border-color: #047857 !important;
            color: #ffffff !important;
        }
        .btn-outline-secondary {
            background-color: #ffffff !important;
            border-color: #cbd5e1 !important;
            color: #334155 !important;
        }
        .btn-outline-secondary:hover {
            background-color: #f1f5f9 !important;
            border-color: #94a3b8 !important;
            color: #0f172a !important;
        }
        .btn-outline-primary {
            background-color: #ffffff !important;
            border-color: #bfdbfe !important;
            color: #1d4ed8 !important;
        }
        .btn-outline-primary:hover {
            background-color: #eff6ff !important;
            border-color: #1d4ed8 !important;
            color: #1e40af !important;
        }
        .btn-soft-primary {
            background-color: #eff6ff !important;
            border: 1px solid #bfdbfe !important;
            color: #1d4ed8 !important;
        }
        .btn-soft-primary:hover {
            background-color: #dbeafe !important;
            border-color: #93c5fd !important;
            color: #1e40af !important;
        }
        .btn-soft-info {
            background-color: #f0fdfa !important;
            border: 1px solid #99f6e4 !important;
            color: #0f766e !important;
        }
        .btn-soft-info:hover {
            background-color: #ccfbf1 !important;
            border-color: #5eead4 !important;
            color: #115e59 !important;
        }

        /* TOOLBARS DE ACCIONES ESPACIADAS Y CENTRADAS */
        .actions-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 1rem;
            padding: 0.75rem 1rem;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
        }
        .actions-toolbar-group {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }

        /* MODALES CENTRADOS PERFECTOS */
        .modal {
            text-align: center;
            padding: 0 !important;
        }
        .modal:before {
            content: '';
            display: inline-block;
            height: 100%;
            vertical-align: middle;
            margin-right: -4px;
        }
        .modal-dialog {
            display: inline-block !important;
            text-align: left !important;
            vertical-align: middle !important;
            margin: 1.75rem auto !important;
            max-width: 95%;
        }
        @media (min-width: 576px) {
            .modal-dialog {
                max-width: 500px;
            }
            .modal-dialog.modal-sm {
                max-width: 380px;
            }
            .modal-dialog.modal-lg {
                max-width: 780px;
            }
            .modal-dialog.modal-xl {
                max-width: 1050px;
            }
        }
        .modal-content {
            border-radius: 14px !important;
            border: 1px solid #e2e8f0 !important;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.2) !important;
            overflow: hidden;
        }
        .modal-header {
            padding: 0.95rem 1.25rem !important;
            border-bottom: 1px solid #e2e8f0 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
        }
        .modal-footer {
            background-color: #f8fafc !important;
            border-top: 1px solid #e2e8f0 !important;
            padding: 0.85rem 1.25rem !important;
            display: flex !important;
            align-items: center !important;
        }

        /* ALINEACIÓN Y CENTRADO DE TABLAS */
        .table th, .table td {
            vertical-align: middle !important;
        }
        .table th.text-center, .table td.text-center {
            text-align: center !important;
        }
        .table td .badge-pill-custom {
            vertical-align: middle;
        }
        .table .btn-group, .table .actions-group {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            vertical-align: middle !important;
        }

        /* BANNER INSTITUCIONAL */
        .banner-jushsal {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #1d4ed8 !important;
            border-radius: 8px;
            padding: 0.5rem 0.95rem;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
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
        }

        /* CUSTOM SCROLLBARS */
        ::-webkit-scrollbar {
            width: 7px;
            height: 7px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* INTERACTIVE HIT AREAS & TRANSITIONS */
        .btn, .nav-link, .dropdown-item, .custom-select, .form-control {
            transition: all 0.16s cubic-bezier(0.16, 1, 0.3, 1) !important;
        }
        .table .btn-xs, .table .btn-sm {
            min-height: 28px;
            min-width: 28px;
            padding: 0.2rem 0.45rem !important;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            border-radius: 6px !important;
        }
        .table .btn-xs:hover, .table .btn-sm:hover {
            transform: translateY(-1px);
        }

        /* LIVE STATUS PULSE INDICATOR */
        @keyframes statusPulse {
            0% { transform: scale(0.95); opacity: 0.8; }
            50% { transform: scale(1.2); opacity: 1; filter: drop-shadow(0 0 3px rgba(16, 185, 129, 0.6)); }
            100% { transform: scale(0.95); opacity: 0.8; }
        }
        .badge-pill-online i {
            animation: statusPulse 2s infinite ease-in-out;
            color: #10b981 !important;
        }

        /* ACCESSIBILITY FOCUS VISIBLE */
        a:focus-visible, button:focus-visible, input:focus-visible, select:focus-visible {
            outline: 2px solid #2563eb !important;
            outline-offset: 2px !important;
        }

        /* ==========================================================
           DATATABLES CONTROLS STYLING (GLOBAL FIX FOR ALL VIEWS)
           ========================================================== */
        .dataTables_wrapper .dataTables_length {
            margin-bottom: 0.75rem;
        }

        .dataTables_wrapper .dataTables_length label {
            display: inline-flex !important;
            align-items: center !important;
            font-size: 0.85rem !important;
            color: #475569 !important;
            font-weight: 500 !important;
            margin-bottom: 0 !important;
            gap: 6px;
        }

        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_length select.custom-select,
        .dataTables_wrapper .dataTables_length select.form-control,
        div.dataTables_wrapper div.dataTables_length select {
            display: inline-block !important;
            width: auto !important;
            min-width: 66px !important;
            height: 32px !important;
            padding: 0.25rem 1.65rem 0.25rem 0.65rem !important;
            margin: 0 4px !important;
            font-size: 0.85rem !important;
            font-weight: 600 !important;
            line-height: 1.5 !important;
            color: #1e293b !important;
            vertical-align: middle !important;
            background-color: #ffffff !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' width='4' height='5' viewBox='0 0 4 5'%3e%3cpath fill='%2364748b' d='M2 0L0 2h4zm0 5L0 3h4z'/%3e%3c/svg%3e") !important;
            background-repeat: no-repeat !important;
            background-position: right 0.5rem center !important;
            background-size: 8px 10px !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 6px !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
            cursor: pointer !important;
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
            appearance: none !important;
        }

        .dataTables_wrapper .dataTables_length select:focus,
        div.dataTables_wrapper div.dataTables_length select:focus {
            border-color: #3b82f6 !important;
            outline: 0 !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.18) !important;
        }

        /* DataTables Search Input */
        .dataTables_wrapper .dataTables_filter {
            margin-bottom: 0.75rem;
        }

        .dataTables_wrapper .dataTables_filter label {
            display: inline-flex !important;
            align-items: center !important;
            font-size: 0.85rem !important;
            color: #475569 !important;
            font-weight: 500 !important;
            margin-bottom: 0 !important;
            gap: 6px;
        }

        .dataTables_wrapper .dataTables_filter input,
        .dataTables_wrapper .dataTables_filter input.form-control,
        div.dataTables_wrapper div.dataTables_filter input {
            display: inline-block !important;
            width: auto !important;
            min-width: 180px !important;
            height: 32px !important;
            padding: 0.25rem 0.65rem !important;
            margin-left: 4px !important;
            font-size: 0.85rem !important;
            color: #1e293b !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 6px !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
        }

        .dataTables_wrapper .dataTables_filter input:focus,
        div.dataTables_wrapper div.dataTables_filter input:focus {
            border-color: #3b82f6 !important;
            outline: 0 !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.18) !important;
        }

        /* DataTables Info & Pagination */
        .dataTables_wrapper .dataTables_info,
        div.dataTables_wrapper div.dataTables_info {
            font-size: 0.83rem !important;
            color: #64748b !important;
            padding-top: 0.6rem !important;
        }

        .dataTables_wrapper .dataTables_paginate,
        div.dataTables_wrapper div.dataTables_paginate {
            padding-top: 0.4rem !important;
        }

        .dataTables_wrapper .dataTables_paginate .page-link,
        div.dataTables_wrapper div.dataTables_paginate .page-link {
            font-size: 0.83rem !important;
            padding: 0.3rem 0.65rem !important;
            border-radius: 6px !important;
            margin: 0 2px !important;
            color: #334155 !important;
            border-color: #e2e8f0 !important;
        }

        .dataTables_wrapper .dataTables_paginate .page-item.active .page-link,
        div.dataTables_wrapper div.dataTables_paginate .page-item.active .page-link {
            background-color: #2563eb !important;
            border-color: #2563eb !important;
            color: #ffffff !important;
            font-weight: 600 !important;
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
                    <a href="javascript:void(0)" onclick="typeof openSyncModal === 'function' ? openSyncModal() : (typeof syncAllDevices === 'function' ? syncAllDevices('incremental', this) : window.location.href='?route=dispositivos')" class="btn btn-sm btn-outline-primary" title="Sincronizar marcaciones de todos los relojes biométricos">
                        <i class="fa-solid fa-arrows-rotate mr-1"></i> Sincronizar Relojes
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

