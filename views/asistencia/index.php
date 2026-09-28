<?php 
require_once APP_ROOT . '/views/layout/header.php'; 

// Inicialización defensiva de colecciones
$asistencias = $asistencias ?? [];
$departamentos = $departamentos ?? [];
$empleados = $empleados ?? [];

// Cálculos de KPIs y métricas de asistencia para el período consultado (Precalculados en MySQL)
$kpiTotal = (int)($kpis['total'] ?? count($asistencias));
$kpiPresentes = (int)($kpis['presentes'] ?? 0);
$kpiTardanzas = (int)($kpis['tardanzas'] ?? 0);
$kpiFaltas = (int)($kpis['faltas'] ?? 0);
$kpiJustificados = (int)($kpis['justificados'] ?? 0);
$kpiSinSalida = (int)($kpis['sin_salida'] ?? 0);
$kpiMinTardanza = (int)($kpis['total_minutos_tardanza'] ?? 0);
$kpiMinTrabajados = (int)($kpis['total_minutos_trabajados'] ?? 0);
$kpiMinExtra = (int)($kpis['total_minutos_extra'] ?? 0);

$kpiPuntualidad = $kpiTotal > 0 ? round(($kpiPresentes / $kpiTotal) * 100, 1) : 0;
$kpiHorasTrab = sprintf('%dh %02dm', floor($kpiMinTrabajados / 60), $kpiMinTrabajados % 60);
$kpiHorasExt = sprintf('%dh %02dm', floor($kpiMinExtra / 60), $kpiMinExtra % 60);
$kpiHorasTard = sprintf('%dh %02dm', floor($kpiMinTardanza / 60), $kpiMinTardanza % 60);

$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$perPage = $perPage ?? 200;

$deptoNombreFiltro = 'Todos los Departamentos';
if (!empty($deptoId)) {
    foreach ($departamentos as $d) {
        if ($d['id'] == $deptoId) {
            $deptoNombreFiltro = $d['nombre'];
            break;
        }
    }
}
?><!-- Content Header (Page header) -->
<div class="content-header no-print pb-2">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-sm-6">
                <h1 class="m-0 font-weight-bold text-dark" style="font-size: 1.45rem;">
                    <i class="fa-solid fa-calendar-check mr-2 text-primary"></i> Control de Asistencia Diaria
                </h1>
                <div class="text-muted small mt-1">Consolidado oficial de puntualidad, tolerancias, inasistencias y horas acumuladas.</div>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right mb-0">
                    <li class="breadcrumb-item"><a href="?route=dashboard">Inicio</a></li>
                    <li class="breadcrumb-item active">Asistencia</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">

        <?php if (isset($_GET['msg'])):
            $msgMap = [
                'recalculado' => ['success', 'Asistencia recalculada exitosamente para el período seleccionado.'],
                'guardado' => ['success', 'Registro de asistencia actualizado correctamente.'],
                'justificado' => ['success', 'Justificación administrativa aplicada correctamente.'],
                'rango_muy_amplio' => ['warning', 'El rango de fechas no puede superar los 62 días (~2 meses) para recálculo web. Use la consola CLI para rangos masivos.'],
                'rango_invalido' => ['danger', 'Rango de fechas inválido. La fecha inicial debe ser anterior o igual a la final.'],
                'acceso_denegado' => ['danger', 'No tienes permisos para realizar esta acción.'],
            ];
            if (isset($msgMap[$_GET['msg']])):
                [$type, $text] = $msgMap[$_GET['msg']];
        ?>
            <div class="alert alert-<?= $type ?> alert-dismissible fade show mb-3 shadow-sm no-print">
                <?= htmlspecialchars($text) ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        <?php endif; endif; ?>

        <!-- MEMBRETE OFICIAL DE IMPRESIÓN / PDF (Solo visible al imprimir o guardar como PDF) -->
        <div class="print-only mb-3">
            <table style="width: 100%; border-collapse: collapse; border-bottom: 2px solid #1e3a8a; padding-bottom: 8px;">
                <tr>
                    <td style="width: 120px; vertical-align: middle; text-align: center; padding-right: 15px;">
                        <img src="<?= jushsal_logo_data_uri('full') ?: asset('img/logo_jushsal.png') ?>" alt="JUSHSAL" style="max-height: 60px; max-width: 110px; object-fit: contain;">
                    </td>
                    <td style="vertical-align: middle;">
                        <div style="font-size: 13pt; font-weight: 800; color: #1e3a8a; text-transform: uppercase;">JUNTA DE USUARIOS DEL SECTOR HIDRÁULICO MENOR SAN LORENZO (JUSHSAL)</div>
                        <div style="font-size: 9pt; color: #475569; font-weight: 600;">SISTEMA INTEGRADO DE CONTROL DE PERSONAL Y ASISTENCIA LABORAL</div>
                        <div style="font-size: 11pt; font-weight: 700; color: #0f172a; margin-top: 4px;">REPORTE OFICIAL DETALLADO DE ASISTENCIA LABORAL</div>
                        <?php if (!empty($empleadoSeleccionado)): ?>
                            <div style="font-size: 10pt; font-weight: 700; color: #1d4ed8; margin-top: 3px;">
                                TRABAJADOR: <?= htmlspecialchars($empleadoSeleccionado['apellidos'] . ' ' . $empleadoSeleccionado['nombres']) ?> | DNI: <?= htmlspecialchars($empleadoSeleccionado['dni']) ?> | CÓDIGO: <?= htmlspecialchars($empleadoSeleccionado['codigo_reloj']) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td style="width: 200px; text-align: right; vertical-align: middle; font-size: 8pt; color: #64748b;">
                        <div><b>Período:</b> <?= date('d/m/Y', strtotime($fechaInicio)) ?> al <?= date('d/m/Y', strtotime($fechaFin)) ?></div>
                        <div><b>Área:</b> <?= htmlspecialchars($deptoNombreFiltro) ?></div>
                        <?php if (!empty($empleadoSeleccionado)): ?>
                            <div><b>Personal:</b> <?= htmlspecialchars($empleadoSeleccionado['apellidos']) ?></div>
                        <?php endif; ?>
                        <div><b>Emisión:</b> <?= date('d/m/Y H:i:s') ?></div>
                        <div><b>Usuario:</b> <?= htmlspecialchars($currentUser['nombre'] ?? 'Administrador') ?></div>
                    </td>
                </tr>
            </table>

            <!-- Resumen KPIs para Impresión -->
            <table class="table table-sm table-bordered mt-2 mb-2" style="font-size: 8pt; text-align: center;">
                <thead style="background-color: #f1f5f9;">
                    <tr>
                        <th>Total Registros</th>
                        <th>Asistencias Puntuales</th>
                        <th>% Puntualidad</th>
                        <th>Tardanzas</th>
                        <th>Tiempo Tardanza</th>
                        <th>Faltas</th>
                        <th>Justificados</th>
                        <th>Total Trabajado</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><b><?= $kpiTotal ?></b></td>
                        <td style="color: #166534;"><b><?= $kpiPresentes ?></b></td>
                        <td style="color: #0284c7;"><b><?= $kpiPuntualidad ?>%</b></td>
                        <td style="color: #92400e;"><b><?= $kpiTardanzas ?></b></td>
                        <td style="color: #92400e;"><b><?= $kpiHorasTard ?></b></td>
                        <td style="color: #991b1b;"><b><?= $kpiFaltas ?></b></td>
                        <td style="color: #075985;"><b><?= $kpiJustificados ?></b></td>
                        <td><b><?= $kpiHorasTrab ?></b></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- KPI SUMMARY CARDS (PANTALLA) -->
        <div class="row no-print">
            <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                <div class="kpi-card h-100">
                    <div class="kpi-card-header">
                        <div>
                            <div class="kpi-title">Total Registros</div>
                            <div class="kpi-value"><?= $kpiTotal ?></div>
                            <div class="kpi-subtitle">Puntualidad Global: <b><?= $kpiPuntualidad ?>%</b></div>
                        </div>
                        <div class="kpi-icon-box">
                            <i class="fa-solid fa-users-viewfinder"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                <div class="kpi-card h-100">
                    <div class="kpi-card-header">
                        <div>
                            <div class="kpi-title">Asistencias Puntuales</div>
                            <div class="kpi-value"><?= $kpiPresentes ?></div>
                            <div class="kpi-subtitle">Ingresos dentro de tolerancia</div>
                        </div>
                        <div class="kpi-icon-box">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                <div class="kpi-card h-100">
                    <div class="kpi-card-header">
                        <div>
                            <div class="kpi-title">Tardanzas Acumuladas</div>
                            <div class="kpi-value"><?= $kpiTardanzas ?> <span style="font-size: 0.95rem; color: #64748b; font-weight: 600;">(<?= $kpiHorasTard ?>)</span></div>
                            <div class="kpi-subtitle">Minutos fuera de tolerancia</div>
                        </div>
                        <div class="kpi-icon-box">
                            <i class="fa-solid fa-clock"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                <div class="kpi-card h-100">
                    <div class="kpi-card-header">
                        <div>
                            <div class="kpi-title">Inasistencias (Faltas)</div>
                            <div class="kpi-value"><?= $kpiFaltas ?></div>
                            <div class="kpi-subtitle">Días sin registro de asistencia</div>
                        </div>
                        <div class="kpi-icon-box">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- FILTER AND ACTIONS CARD -->
        <div class="card mb-4 no-print">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap" style="padding: 0.85rem 1.25rem;">
                <h3 class="card-title font-weight-bold text-dark mb-0 d-flex align-items-center" style="font-size: 0.92rem;">
                    <i class="fa-solid fa-filter mr-2" style="color: #1e40af;"></i> Filtros y Gestión de Asistencia
                </h3>
                <div class="d-flex align-items-center flex-wrap" style="gap: 8px; margin-left: auto;">
                    <?php if ($userRole === 'ADMIN'): ?>
                        <button type="button" class="btn btn-primary btn-sm" onclick="openAsignarHorasModal()" title="Asignar u oficializar horas de entrada y salida">
                            <i class="fa-solid fa-clock-medical mr-1"></i> Asignar Horas
                        </button>
                    <?php endif; ?>
                    <?php if ($userRole === 'ADMIN' || $userRole === 'RRHH'): ?>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="openJustificarAdminModal()" title="Registrar justificación oficial">
                            <i class="fa-solid fa-user-shield mr-1 text-primary"></i> Registrar Justificación
                        </button>
                    <?php endif; ?>

                    <?php if ($userRole === 'ADMIN' || $userRole === 'RRHH'): ?>
                        <div class="dropdown d-inline">
                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" id="syncRelojDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Sincronizar marcaciones directamente desde el reloj">
                                <i class="fa-solid fa-fingerprint mr-1 text-primary"></i> Sincronizar Reloj
                            </button>
                            <div class="dropdown-menu dropdown-menu-right shadow-sm border-0" aria-labelledby="syncRelojDropdown">
                                <h6 class="dropdown-header font-weight-bold text-primary" style="font-size: 0.78rem;"><i class="fa-solid fa-clock mr-1"></i> Extracción Biométrico ZKTeco</h6>
                                <form method="POST" action="?route=dispositivos&action=sincronizar" class="px-2 py-1 m-0">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="mode" value="incremental">
                                    <button type="submit" class="dropdown-item py-2" style="font-size: 0.85rem;">
                                        <i class="fa-solid fa-bolt mr-2 text-primary"></i> Sincronizar Hoy / Rápido
                                    </button>
                                </form>
                                <form method="POST" action="?route=dispositivos&action=sincronizar" class="px-2 py-1 m-0">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="mode" value="days">
                                    <input type="hidden" name="days" value="7">
                                    <button type="submit" class="dropdown-item py-2" style="font-size: 0.85rem;">
                                        <i class="fa-solid fa-calendar-week mr-2 text-primary"></i> Sincronizar Últimos 7 Días
                                    </button>
                                </form>
                                <div class="dropdown-divider my-1"></div>
                                <form method="POST" action="?route=dispositivos&action=sincronizar" class="px-2 py-1 m-0" onsubmit="return confirm('¿Deseas extraer el 100% de las marcaciones almacenadas en el reloj físico?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="mode" value="full">
                                    <button type="submit" class="dropdown-item py-2 font-weight-bold" style="font-size: 0.85rem; color: #1e40af;">
                                        <i class="fa-solid fa-cloud-arrow-down mr-2"></i> Histórico Completo del Reloj
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($userRole === 'ADMIN'): ?>
                        <form method="POST" action="?route=asistencia&action=recalcular" class="d-inline m-0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="fecha_inicio" value="<?= htmlspecialchars($fechaInicio) ?>">
                            <input type="hidden" name="fecha_fin" value="<?= htmlspecialchars($fechaFin) ?>">
                            <button type="submit" class="btn btn-outline-secondary btn-sm" onclick="return confirm('¿Deseas recalcular la asistencia en este rango de fechas?')">
                                <i class="fa-solid fa-calculator mr-1"></i> Recalcular
                            </button>
                        </form>
                    <?php endif; ?>

                    <!-- Botón Reporte Individual de Trabajador -->
                    <button type="button" class="btn btn-outline-primary btn-sm font-weight-bold" onclick="openReporteIndividualModal()" title="Generar reporte individual oficial para un trabajador específico">
                        <i class="fa-solid fa-file-invoice mr-1"></i> Reporte por Empleado
                    </button>

                    <!-- Botones de Exportación Agrupados Elegantes -->
                    <div class="btn-group btn-group-sm">
                        <a href="?route=asistencia&fecha_inicio=<?= urlencode($fechaInicio) ?>&fecha_fin=<?= urlencode($fechaFin) ?>&departamento_id=<?= urlencode((string)($deptoId ?? '')) ?>&empleado_id=<?= urlencode((string)($empleadoId ?? '')) ?>&estado=<?= urlencode((string)($estado ?? '')) ?>&search=<?= urlencode($search ?? '') ?>&export=excel" class="btn btn-outline-secondary" title="Descargar reporte oficial en Excel">
                            <i class="fa-solid fa-file-excel mr-1 text-success"></i> Excel
                        </a>
                        <button type="button" class="btn btn-outline-secondary" onclick="window.print()" title="Imprimir reporte oficial o Guardar como PDF">
                            <i class="fa-solid fa-print mr-1 text-secondary"></i> PDF
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body p-4">
                <form method="GET" action="" class="row align-items-end">
                    <input type="hidden" name="route" value="asistencia">

                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <label class="form-label-custom"><i class="fa-regular fa-calendar"></i> Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" class="form-control" value="<?= htmlspecialchars($fechaInicio) ?>" required>
                    </div>

                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <label class="form-label-custom"><i class="fa-regular fa-calendar-check"></i> Fecha Fin</label>
                        <input type="date" name="fecha_fin" class="form-control" value="<?= htmlspecialchars($fechaFin) ?>" required>
                    </div>

                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <label class="form-label-custom"><i class="fa-solid fa-building"></i> Departamento</label>
                        <select name="departamento_id" class="form-control">
                            <option value="">-- Todos los Deptos --</option>
                            <?php foreach ($departamentos as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= $deptoId == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <label class="form-label-custom"><i class="fa-solid fa-tag"></i> Estado</label>
                        <select name="estado" class="form-control">
                            <option value="ASISTIERON" <?= ($estado ?? '') === 'ASISTIERON' ? 'selected' : '' ?>>Asistieron (Predeterminado)</option>
                            <option value="TODOS" <?= ($estado ?? '') === 'TODOS' ? 'selected' : '' ?>>-- Todo el Padrón (Con Faltas) --</option>
                            <option value="PRESENTE" <?= ($estado ?? '') === 'PRESENTE' ? 'selected' : '' ?>>Puntuales (Presente)</option>
                            <option value="COMISION_SERVICIO" <?= ($estado ?? '') === 'COMISION_SERVICIO' ? 'selected' : '' ?>>Comisión de Servicio (JUSHSAL)</option>
                            <option value="VACACIONES" <?= ($estado ?? '') === 'VACACIONES' ? 'selected' : '' ?>>Vacaciones</option>
                            <option value="TARDANZA" <?= ($estado ?? '') === 'TARDANZA' ? 'selected' : '' ?>>Tardanzas</option>
                            <option value="FALTA" <?= ($estado ?? '') === 'FALTA' ? 'selected' : '' ?>>Faltas e Inasistencias</option>
                            <option value="PENDIENTE" <?= ($estado ?? '') === 'PENDIENTE' ? 'selected' : '' ?>>En Espera / Sin Marcar</option>
                            <option value="JUSTIFICADO" <?= ($estado ?? '') === 'JUSTIFICADO' ? 'selected' : '' ?>>Justificados y Permisos</option>
                            <option value="SALIDA_SIN_MARCAR" <?= ($estado ?? '') === 'SALIDA_SIN_MARCAR' ? 'selected' : '' ?>>Salidas sin Marcar</option>
                            <option value="ENTRADA_SIN_MARCAR" <?= ($estado ?? '') === 'ENTRADA_SIN_MARCAR' ? 'selected' : '' ?>>Entradas sin Marcar</option>
                            <option value="INCIDENCIAS" <?= ($estado ?? '') === 'INCIDENCIAS' ? 'selected' : '' ?>>Todas las Incidencias</option>
                        </select>
                    </div>

                    <div class="col-lg-3 col-md-5 col-sm-8 mb-3">
                        <label class="form-label-custom"><i class="fa-solid fa-user-tie"></i> Empleado (Filtro Individual)</label>
                        <select name="empleado_id" class="form-control">
                            <option value="">-- Todos los Trabajadores --</option>
                            <?php foreach ($empleados as $emp): ?>
                                <option value="<?= $emp['id'] ?>" <?= ((int)($empleadoId ?? 0) === (int)$emp['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($emp['apellidos'] . ' ' . $emp['nombres']) ?> (DNI: <?= htmlspecialchars($emp['dni']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-lg-1 col-md-3 col-sm-4 mb-3 d-flex" style="gap: 6px;">
                        <button type="submit" class="btn btn-primary flex-fill" title="Filtrar resultados" style="height: 38px;">
                            <i class="fa-solid fa-filter"></i>
                        </button>
                        <a href="?route=asistencia" class="btn btn-outline-secondary" title="Limpiar filtros" style="height: 38px; width: 38px; display: inline-flex; align-items: center; justify-content: center; padding: 0;">
                            <i class="fa-solid fa-rotate-left"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!empty($empleadoSeleccionado)): ?>
            <!-- TARJETA DESTACADA: REPORTE INDIVIDUAL POR EMPLEADO -->
            <div class="card mb-4 border shadow-sm no-print" style="background: #ffffff; border-color: #cbd5e1 !important; border-left: 4px solid #1e40af !important;">
                <div class="card-body p-3 d-flex align-items-center justify-content-between flex-wrap" style="gap: 12px;">
                    <div class="d-flex align-items-center">
                        <div class="mr-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; font-size: 1.2rem; background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe;">
                            <i class="fa-solid fa-user-check"></i>
                        </div>
                        <div>
                            <div class="small text-uppercase font-weight-bold" style="letter-spacing: 0.04em; color: #1e40af; font-size: 0.72rem;">Reporte Individual de Asistencia Laboral</div>
                            <h5 class="font-weight-bold mb-0" style="color: #0f172a; font-size: 1.1rem;">
                                <?= htmlspecialchars($empleadoSeleccionado['apellidos'] . ' ' . $empleadoSeleccionado['nombres']) ?>
                            </h5>
                            <div class="small mt-1 text-muted">
                                <span><strong>DNI:</strong> <?= htmlspecialchars($empleadoSeleccionado['dni']) ?></span>
                                <span class="mx-2 text-slate-300">&bull;</span>
                                <span><strong>Cód. Reloj:</strong> <?= htmlspecialchars($empleadoSeleccionado['codigo_reloj']) ?></span>
                                <?php $cargoTexto = $empleadoSeleccionado['cargo_nombre'] ?? ($empleadoSeleccionado['cargo'] ?? ''); ?>
                                <?php if (!empty($cargoTexto)): ?>
                                    <span class="mx-2 text-slate-300">&bull;</span>
                                    <span><strong>Cargo:</strong> <?= htmlspecialchars($cargoTexto) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                        <div class="btn-group btn-group-sm">
                            <a href="?route=asistencia&fecha_inicio=<?= urlencode($fechaInicio) ?>&fecha_fin=<?= urlencode($fechaFin) ?>&empleado_id=<?= urlencode((string)$empleadoId) ?>&export=excel" class="btn btn-outline-secondary">
                                <i class="fa-solid fa-file-excel mr-1 text-success"></i> Excel Personal
                            </a>
                            <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                                <i class="fa-solid fa-print mr-1 text-secondary"></i> Imprimir
                            </button>
                        </div>
                        <a href="?route=asistencia&fecha_inicio=<?= urlencode($fechaInicio) ?>&fecha_fin=<?= urlencode($fechaFin) ?>" class="btn btn-sm btn-outline-secondary" title="Quitar filtro y ver todos los empleados">
                            <i class="fa-solid fa-xmark mr-1"></i> Ver Todos
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- MAIN TABLE CARD -->
        <div class="card">
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover text-nowrap table-sm">
                    <thead>
                        <tr>
                            <th class="text-center">Fecha</th>
                            <th>Empleado</th>
                            <th class="text-center">Turno</th>
                            <th class="text-center">Entrada</th>
                            <th class="text-center">Refrigerio</th>
                            <th class="text-center">Salida</th>
                            <th class="text-center">Tardanza</th>
                            <th class="text-center">Tiempo Trabajado</th>
                            <th class="text-center">Estado</th>
                            <?php if ($userRole === 'ADMIN' || $userRole === 'RRHH'): ?>
                                <th class="text-center no-print">Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($asistencias)): ?>
                            <tr>
                                <td colspan="<?= ($userRole === 'ADMIN' || $userRole === 'RRHH') ? '10' : '9' ?>" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-folder-open fa-2x mb-2 d-block text-secondary"></i>
                                    <div>No se encontraron registros de asistencia para los filtros seleccionados.</div>
                                    <a href="?route=asistencia" class="btn btn-xs btn-outline-primary mt-2">
                                        <i class="fa-solid fa-rotate-left mr-1"></i> Reestablecer filtros
                                    </a>
                                </td>
                            </tr>
                        <?php else: ?>
                        <?php foreach ($asistencias as $a): ?>
                            <?php
                                $dayOfWeek = (int)date('w', strtotime($a['fecha']));
                                $isSaturday = ($dayOfWeek === 6);
                            ?>
                            <tr>
                                <td class="text-center font-weight-bold text-dark font-monospace small"><?= $a['fecha'] ?></td>
                                <td>
                                    <div class="font-weight-bold text-dark"><?= htmlspecialchars($a['apellidos'] . ' ' . $a['nombres']) ?></div>
                                    <small class="text-muted">DNI: <?= htmlspecialchars($a['dni']) ?> &bull; ID Reloj: <?= htmlspecialchars($a['codigo_reloj']) ?></small>
                                </td>
                                <td class="text-center">
                                    <span class="badge-pill-custom badge-pill-neutral"><?= htmlspecialchars($a['turno_nombre'] ?? 'Sin Turno') ?></span>
                                </td>
                                <td class="text-center">
                                    <div class="font-monospace font-weight-bold <?= $a['minutos_tardanza'] > 0 ? 'text-danger' : ($a['hora_entrada_real'] ? 'text-success' : 'text-muted') ?>" style="font-size: 0.95rem; line-height: 1.15;">
                                        <?= $a['hora_entrada_real'] ? substr($a['hora_entrada_real'], 11, 5) : '--:--' ?>
                                    </div>
                                    <div class="small text-muted font-monospace" style="font-size: 0.74rem; line-height: 1.2; margin-top: 2px;">
                                        <span style="color: #64748b;">Prog:</span> <?= $a['hora_entrada_programada'] ? substr($a['hora_entrada_programada'], 0, 5) : '--:--' ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <?php if ($isSaturday): ?>
                                        <span class="badge badge-light text-muted" style="font-size: 0.72rem; border: 1px dashed #cbd5e1;">Sin refrigerio</span>
                                    <?php else: ?>
                                        <?php 
                                            $salRef = !empty($a['hora_inicio_refrigerio_real']) ? substr($a['hora_inicio_refrigerio_real'], 11, 5) : null;
                                            $retRef = !empty($a['hora_fin_refrigerio_real']) ? substr($a['hora_fin_refrigerio_real'], 11, 5) : null;
                                        ?>
                                        <?php if ($salRef || $retRef): ?>
                                            <div class="font-monospace small font-weight-bold" style="font-size: 0.82rem; line-height: 1.2;">
                                                <span style="color: #475569;">Sal:</span> <?= $salRef ?: '--:--' ?>
                                                <span class="mx-1">&bull;</span>
                                                <span style="color: #475569;">Ret:</span> <?= $retRef ?: '--:--' ?>
                                            </div>
                                            <div class="small text-muted font-monospace" style="font-size: 0.72rem;">
                                                Prog: 13:00 - 13:45
                                            </div>
                                        <?php elseif (!empty($a['hora_entrada_real']) && !empty($a['hora_salida_real'])): ?>
                                            <span class="badge badge-light text-secondary small" style="font-size: 0.72rem;" title="Refrigerio reglamentario de 45 min descontado del cómputo laboral">
                                                Desc. 45m reg.
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted font-monospace small">--:--</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="font-monospace font-weight-bold <?= empty($a['hora_salida_real']) ? 'text-muted' : 'text-dark' ?>" style="font-size: 0.95rem; line-height: 1.15;">
                                        <?= $a['hora_salida_real'] ? substr($a['hora_salida_real'], 11, 5) : '--:--' ?>
                                    </div>
                                    <div class="small text-muted font-monospace" style="font-size: 0.74rem; line-height: 1.2; margin-top: 2px;">
                                        <span style="color: #64748b;">Prog:</span> <?= $a['hora_salida_programada'] ? substr($a['hora_salida_programada'], 0, 5) : '--:--' ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <?php if ($a['minutos_tardanza'] > 0): ?>
                                        <?php
                                            $tMin = (int)$a['minutos_tardanza'];
                                            $strTard = $tMin >= 60 ? sprintf('+%dh %02dm', floor($tMin / 60), $tMin % 60) : "+{$tMin} min";
                                        ?>
                                        <span class="badge-pill-custom badge-pill-tardanza" title="<?= $tMin ?> minutos"><?= $strTard ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center font-monospace small">
                                    <?php
                                        $hrs = floor($a['minutos_trabajados'] / 60);
                                        $min = $a['minutos_trabajados'] % 60;
                                        $decHrs = number_format($a['minutos_trabajados'] / 60, 2);
                                    ?>
                                    <div class="font-weight-bold" style="color: #0f172a; font-size: 0.92rem;"><?= "{$hrs}h {$min}m" ?></div>
                                    <div class="badge badge-light text-primary font-weight-bold" style="font-size: 0.73rem; border: 1px solid #bfdbfe; margin-top: 1px;" title="Total en horas decimales"><?= "{$decHrs} hrs" ?></div>
                                </td>
                                <td class="text-center">
                                    <?php
                                        $est = $a['estado'];
                                        $obs = $a['observaciones'] ?? '';
                                        if ($est === 'PRESENTE' && !empty($a['hora_entrada_real']) && str_contains($obs, 'Jornada en curso')) {
                                            echo '<span class="badge-pill-custom badge-pill-presente"><i class="fa-solid fa-user-clock mr-1"></i>En Jornada</span>';
                                        } elseif ($est === 'PRESENTE' && !empty($a['hora_entrada_real'])) {
                                            echo '<span class="badge-pill-custom badge-pill-presente"><i class="fa-solid fa-check mr-1"></i>Presente</span>';
                                        } elseif ($est === 'PENDIENTE' || (empty($a['hora_entrada_real']) && !in_array($est, ['FALTA', 'FALTA_INJUSTIFICADA', 'JUSTIFICADO', 'PERMISO', 'VACACIONES', 'DESCANSO', 'COMISION_SERVICIO'], true))) {
                                            echo '<span class="badge-pill-custom badge-pill-neutral"><i class="fa-solid fa-hourglass-half mr-1"></i>En Espera</span>';
                                        } elseif ($est === 'TARDANZA') {
                                            echo '<span class="badge-pill-custom badge-pill-tardanza"><i class="fa-solid fa-clock mr-1"></i>Tardanza</span>';
                                        } elseif ($est === 'FALTA' || $est === 'FALTA_INJUSTIFICADA') {
                                            echo '<span class="badge-pill-custom badge-pill-falta"><i class="fa-solid fa-xmark mr-1"></i>Falta</span>';
                                        } elseif ($est === 'JUSTIFICADO') {
                                            echo '<span class="badge-pill-custom badge-pill-justificado"><i class="fa-solid fa-shield mr-1"></i>Justificado</span>';
                                        } elseif ($est === 'PERMISO') {
                                            echo '<span class="badge-pill-custom badge-pill-justificado"><i class="fa-solid fa-id-badge mr-1"></i>Permiso</span>';
                                        } elseif ($est === 'COMISION_SERVICIO') {
                                            echo '<span class="badge-pill-custom" style="background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd;"><i class="fa-solid fa-briefcase mr-1"></i>Comisión</span>';
                                        } elseif ($est === 'VACACIONES') {
                                            echo '<span class="badge-pill-custom" style="background-color: #ecfdf5; color: #047857; border: 1px solid #a7f3d0;"><i class="fa-solid fa-umbrella-beach mr-1"></i>Vacaciones</span>';
                                        } elseif ($est === 'DESCANSO') {
                                            echo '<span class="badge-pill-custom badge-pill-neutral"><i class="fa-solid fa-bed mr-1"></i>Descanso</span>';
                                        } elseif ($est === 'SALIDA_SIN_MARCAR') {
                                            echo '<span class="badge-pill-custom badge-pill-sin-salida"><i class="fa-solid fa-right-from-bracket mr-1"></i>Sin Salida</span>';
                                        } elseif ($est === 'ENTRADA_SIN_MARCAR') {
                                            echo '<span class="badge-pill-custom" style="background-color: #fef3c7; color: #b45309; border: 1px solid #fde68a;"><i class="fa-solid fa-right-to-bracket mr-1"></i>Sin Entrada</span>';
                                        } else {
                                            echo '<span class="badge-pill-custom badge-pill-neutral">' . htmlspecialchars($est) . '</span>';
                                        }
                                    ?>
                                    <?php if (!empty($a['comision_destino'])): ?>
                                        <div class="small font-weight-bold text-primary mt-1" title="Comisión de Usuarios: <?= htmlspecialchars($a['comision_destino']) ?>">
                                            <i class="fa-solid fa-map-location-dot mr-1"></i><?= htmlspecialchars(mb_strimwidth($a['comision_destino'], 0, 24, '...')) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($a['observaciones'])): ?>
                                        <div class="small text-muted mt-1" title="<?= htmlspecialchars($a['observaciones']) ?>">
                                            <i class="far fa-comment-dots mr-1"></i><?= htmlspecialchars(mb_strimwidth($a['observaciones'], 0, 24, '...')) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <?php if ($userRole === 'ADMIN' || $userRole === 'RRHH'): ?>
                                    <td class="text-center no-print">
                                        <button class="btn btn-xs btn-outline-info mr-1" onclick="openTimelineModal(<?= $a['id_empleado'] ?>, '<?= $a['fecha'] ?>', '<?= htmlspecialchars(addslashes($a['apellidos'] . ' ' . $a['nombres'])) ?>')" title="Ver Trazabilidad y Auditoría de Eventos">
                                            <i class="fa-solid fa-timeline"></i> Eventos
                                        </button>
                                        <?php if ($userRole === 'ADMIN'): ?>
                                        <button class="btn btn-xs btn-primary mr-1" onclick="openAdminEditModal(<?= htmlspecialchars(json_encode($a)) ?>)" title="Modificar horas de entrada/salida y corregir asistencia oficialmente (Solo Administrador)">
                                            <i class="fa-solid fa-clock-rotate-left"></i> Corregir
                                        </button>
                                        <?php endif; ?>
                                        <button class="btn btn-xs btn-outline-warning" onclick="openQuickJustifyModal(<?= htmlspecialchars(json_encode($a)) ?>)" title="Registrar Justificación para este trabajador">
                                            <i class="fa-solid fa-file-shield"></i> Justificar
                                        </button>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="thead-light">
                        <tr class="font-weight-bold">
                            <th colspan="6" class="text-right">TOTALES GENERALES:</th>
                            <th class="text-center text-warning" title="<?= $kpiMinTardanza ?> minutos"><?= $kpiHorasTard ?></th>
                            <th class="text-center text-dark">
                                <div><?= $kpiHorasTrab ?></div>
                                <span class="badge badge-light text-primary" style="font-size: 0.72rem; border: 1px solid #bfdbfe;"><?= number_format($kpiMinTrabajados / 60, 2) ?> hrs</span>
                            </th>
                            <th colspan="<?= $userRole === 'ADMIN' ? '2' : '1' ?>"><?= $kpiTotal ?> registros</th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white border-top py-2 px-3 d-flex flex-wrap justify-content-between align-items-center no-print">
                <div class="small text-muted mb-2 mb-md-0">
                    Mostrando página <b><?= $page ?></b> de <b><?= $totalPages ?></b> (<b><?= number_format($kpiTotal) ?></b> asistencias en total)
                </div>
                <nav aria-label="Paginación de asistencias">
                    <ul class="pagination pagination-sm mb-0">
                        <?php
                            $queryParams = $_GET;
                            $buildPageUrl = function($p) use ($queryParams) {
                                $queryParams['page'] = $p;
                                return '?' . http_build_query($queryParams);
                            };
                        ?>
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $buildPageUrl(1) ?>" title="Primera página"><i class="fa-solid fa-angles-left"></i></a>
                        </li>
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $buildPageUrl($page - 1) ?>" title="Página anterior"><i class="fa-solid fa-angle-left"></i></a>
                        </li>
                        
                        <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <li class="page-item <?= ($i === $page) ? 'active font-weight-bold' : '' ?>">
                                <a class="page-link" href="<?= $buildPageUrl($i) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>

                        <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $buildPageUrl($page + 1) ?>" title="Página siguiente"><i class="fa-solid fa-angle-right"></i></a>
                        </li>
                        <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $buildPageUrl($totalPages) ?>" title="Última página"><i class="fa-solid fa-angles-right"></i></a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>

        <!-- BLOQUE DE FIRMAS OFICIALES DE IMPRESIÓN (Solo visible al imprimir / PDF) -->
        <div class="print-only print-signatures">
            <table style="width: 100%; text-align: center; border: none;">
                <tr>
                    <td style="width: 50%; padding-top: 50px; border: none;">
                        <div style="display: inline-block; width: 260px; border-top: 1.5px solid #334155; padding-top: 6px;">
                            <div style="font-weight: 700; font-size: 8.5pt; color: #0f172a;">RESPONSABLE DE RECURSOS HUMANOS</div>
                            <div style="font-size: 7.5pt; color: #64748b;">Control de Personal y Asistencia - JUSHSAL</div>
                        </div>
                    </td>
                    <td style="width: 50%; padding-top: 50px; border: none;">
                        <div style="display: inline-block; width: 260px; border-top: 1.5px solid #334155; padding-top: 6px;">
                            <div style="font-weight: 700; font-size: 8.5pt; color: #0f172a;">V°B° ADMINISTRACIÓN GENERAL</div>
                            <div style="font-size: 7.5pt; color: #64748b;">Junta de Usuarios San Lorenzo</div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

    </div>
</section>

<?php if ($userRole === 'ADMIN'): ?>
<!-- MODAL EVENT SOURCING: LÍNEA DE TIEMPO DE AUDITORÍA Y TRAZABILIDAD -->
<div class="modal fade" id="modalTimelineEventos" tabindex="-1" role="dialog" aria-labelledby="timelineTitle" aria-modal="true" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold" id="timelineTitle">
                    <i class="fa-solid fa-timeline mr-2"></i> Trazabilidad y Auditoría de Marcaciones
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body p-4">
                <!-- CABECERA DE METADATOS -->
                <div class="d-flex justify-content-between align-items-center p-3 rounded mb-4" style="background-color: #f8fafc; border: 1px solid #e2e8f0;">
                    <div>
                        <h6 class="font-weight-bold mb-1 text-dark" id="timelineEmpleadoNombre">Cargando empleado...</h6>
                        <small class="text-muted"><i class="fa-solid fa-id-card mr-1 text-secondary"></i> DNI: <span id="timelineEmpleadoDni">--</span> | ID Reloj: <span id="timelineEmpleadoReloj">--</span></small>
                    </div>
                    <div class="text-right">
                        <span class="badge badge-light border px-2 py-1 font-weight-bold text-secondary" id="timelineFecha">--</span>
                        <div class="small text-muted mt-1" id="timelineTotalEventos">-- eventos registrados</div>
                    </div>
                </div>

                <!-- CONTENEDOR DE LA LÍNEA DE TIEMPO -->
                <div id="timelineLoading" class="text-center py-5">
                    <div class="spinner-border text-secondary" role="status"></div>
                    <div class="small text-muted mt-2 font-weight-bold">Recuperando flujo de eventos inmutables desde el Event Store...</div>
                </div>

                <div id="timelineContent" class="timeline" style="display: none;">
                    <!-- Los eventos se inyectan dinámicamente vía JavaScript -->
                </div>

                <div id="timelineEmpty" class="alert alert-secondary text-center py-4" style="display: none;">
                    <i class="fa-solid fa-inbox fa-2x mb-2 text-muted"></i>
                    <div>No se encontraron eventos registrados para este día.</div>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <span class="small text-muted"><i class="fa-solid fa-shield-halved mr-1 text-secondary"></i> Log inmutable respaldado por arquitectura Event Sourcing</span>
                <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($userRole === 'ADMIN'): ?>
<!-- MODAL CORRECCIÓN / ASIGNACIÓN ADMINISTRATIVA DE HORARIO Y ASISTENCIA (EXCLUSIVO ADMINISTRADOR) -->
<div class="modal fade" id="modalEditarAsistencia" tabindex="-1" role="dialog" aria-labelledby="modalEditarTitle" aria-modal="true" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form method="POST" action="?route=asistencia&action=editar" class="modal-content shadow-lg border-0" id="formAdminEditAsistencia" onsubmit="submitAdminEditAttendance(event)">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="edit_id" value="0">
            <input type="hidden" name="id_empleado" id="edit_id_empleado" value="0">
            <input type="hidden" name="fecha" id="edit_fecha_hidden" value="<?= date('Y-m-d') ?>">
            <input type="hidden" id="edit_prog_entrada" value="08:00">
            <input type="hidden" id="edit_prog_salida" value="17:00">
            <input type="hidden" id="edit_tolerancia" value="10">
            
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold" id="modalEditarTitle">
                    <i class="fa-solid fa-pen-to-square mr-2"></i> Modificar Horario de Asistencia
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            
            <div class="modal-body p-4">
                <!-- CABECERA DEL TRABAJADOR SELECCIONADO DESDE LA TABLA -->
                <div id="edit_empleado_card" class="p-3 mb-3 rounded" style="background-color: #f8fafc; border: 1px solid #e2e8f0;">
                    <div class="row align-items-center">
                        <div class="col-md-7">
                            <span class="badge badge-light border text-secondary px-2 py-1 mb-1 font-weight-bold" id="edit_badge_fecha">Fecha: --/--/----</span>
                            <h6 class="font-weight-bold text-dark mb-1" id="edit_empleado_nombre" style="font-size: 1.05rem;">Empleado...</h6>
                            <div class="small text-muted"><i class="fa-solid fa-id-card mr-1 text-secondary"></i> DNI: <strong class="text-dark" id="edit_empleado_dni">--</strong> <span class="mx-1">&bull;</span> Cód. Reloj: <strong class="text-dark" id="edit_empleado_reloj">--</strong></div>
                        </div>
                        <div class="col-md-5 text-md-right border-left pt-2 pt-md-0">
                            <div class="small text-muted">Horario Oficial Asignado:</div>
                            <strong class="font-monospace" id="edit_turno_info" style="color: #0f172a; font-size: 0.95rem;">08:00 - 17:00 (Tol: 10m)</strong>
                        </div>
                    </div>
                </div>

                <!-- SELECTOR DE EMPLEADO Y FECHA CUANDO SE ABRE DESDE BOTÓN SUPERIOR -->
                <div id="edit_empleado_selector_card" class="p-3 mb-3 rounded" style="background-color: #f8fafc; border: 1px solid #e2e8f0; display: none;">
                    <div class="modal-section-title mt-0 mb-3">
                        <i class="fa-solid fa-user-check"></i> Seleccionar Empleado y Fecha
                    </div>
                    <div class="row align-items-center">
                        <div class="col-md-8 mb-2">
                            <label class="small font-weight-bold text-secondary mb-1">Buscar Trabajador <span class="text-danger">*</span></label>
                            
                            <div class="worker-search-wrapper" id="edit_search_container">
                                <input type="text" 
                                       id="edit_worker_search_input" 
                                       class="form-control" 
                                       placeholder="Escribe nombre, apellido o DNI del trabajador..." 
                                       autocomplete="off"
                                       oninput="filtrarTrabajadoresEnVivo(this.value, 'edit')"
                                       onkeydown="navegarResultadosTrabajador(event, 'edit')"
                                       onfocus="filtrarTrabajadoresEnVivo(this.value, 'edit')">

                                <!-- Dropdown flotante con resultados filtrados -->
                                <div id="edit_worker_results" class="worker-search-dropdown" style="display: none;"></div>
                            </div>

                            <!-- Tarjeta de confirmación del trabajador seleccionado -->
                            <div id="edit_worker_selected_badge" class="mt-2" style="display: none;">
                                <div class="d-flex align-items-center justify-content-between p-2 rounded" style="background-color: #ffffff; border: 1px solid #cbd5e1;">
                                    <div>
                                        <div class="font-weight-bold text-dark" id="edit_badge_nombre" style="font-size: 0.88rem;">--</div>
                                        <div class="text-muted small" style="font-size: 0.76rem;">
                                            DNI: <strong class="text-dark" id="edit_badge_dni">--</strong>
                                            <span class="mx-1">&bull;</span>
                                            Reloj ID: <strong class="text-dark" id="edit_badge_reloj">--</strong>
                                            <span class="mx-1">&bull;</span>
                                            Turno: <strong class="text-dark" id="edit_badge_turno">--</strong>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary font-weight-bold" onclick="deseleccionarTrabajador('edit')">
                                        Cambiar
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="small font-weight-bold text-secondary mb-1">Fecha de Asistencia <span class="text-danger">*</span></label>
                            <input type="date" id="edit_fecha_picker" class="form-control" value="<?= date('Y-m-d') ?>" onchange="onDatePickerChanged(this.value)">
                        </div>
                    </div>
                </div>

                <!-- MARCACIONES OFICIALES Y EDICIÓN DIRECTA -->
                <div class="modal-section-title">
                    <i class="fa-solid fa-clock-rotate-left"></i> Marcaciones Oficiales de Huella / Reloj
                </div>

                <div class="row">
                    <div class="col-md-3 col-sm-6 mb-3">
                        <label class="small font-weight-bold text-dark d-flex justify-content-between align-items-center mb-1">
                            <span><i class="fa-solid fa-arrow-right-to-bracket mr-1 text-secondary"></i> 1. Entrada</span>
                            <div class="btn-group btn-group-xs">
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1" onclick="setProgrammedTime('entrada')" title="Copiar hora programada del turno">Prog</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1" onclick="clearTime('entrada')" title="Borrar hora"><i class="fa-solid fa-eraser"></i></button>
                            </div>
                        </label>
                        <input type="time" name="hora_entrada_real" id="edit_hora_entrada" step="1" class="form-control font-monospace text-center font-weight-bold" style="font-size: 1.1rem; color: #0f172a;" oninput="calculateRealtimeAdminAttendance()">
                        <small class="text-muted d-block mt-1">Ingreso laboral</small>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <label class="small font-weight-bold text-dark d-flex justify-content-between align-items-center mb-1">
                            <span><i class="fa-solid fa-utensils mr-1 text-secondary"></i> 2. Salida Ref.</span>
                            <div class="btn-group btn-group-xs">
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1" onclick="setProgrammedTime('salida_ref')" title="Copiar 13:00">13:00</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1" onclick="clearTime('salida_ref')" title="Borrar hora"><i class="fa-solid fa-eraser"></i></button>
                            </div>
                        </label>
                        <input type="time" name="hora_inicio_refrigerio_real" id="edit_hora_inicio_refrigerio" step="1" class="form-control font-monospace text-center font-weight-bold" style="font-size: 1.1rem; color: #0f172a;" oninput="calculateRealtimeAdminAttendance()">
                        <small class="text-muted d-block mt-1">Salida a refrigerio</small>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <label class="small font-weight-bold text-dark d-flex justify-content-between align-items-center mb-1">
                            <span><i class="fa-solid fa-clock-rotate-left mr-1 text-secondary"></i> 3. Retorno Ref.</span>
                            <div class="btn-group btn-group-xs">
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1" onclick="setProgrammedTime('retorno_ref')" title="Copiar 13:45">13:45</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1" onclick="clearTime('retorno_ref')" title="Borrar hora"><i class="fa-solid fa-eraser"></i></button>
                            </div>
                        </label>
                        <input type="time" name="hora_fin_refrigerio_real" id="edit_hora_fin_refrigerio" step="1" class="form-control font-monospace text-center font-weight-bold" style="font-size: 1.1rem; color: #0f172a;" oninput="calculateRealtimeAdminAttendance()">
                        <small class="text-muted d-block mt-1">Retorno de refrigerio</small>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <label class="small font-weight-bold text-dark d-flex justify-content-between align-items-center mb-1">
                            <span><i class="fa-solid fa-arrow-right-from-bracket mr-1 text-secondary"></i> 4. Salida Final</span>
                            <div class="btn-group btn-group-xs">
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1" onclick="setProgrammedTime('salida')" title="Copiar hora programada del turno">Prog</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1" onclick="clearTime('salida')" title="Borrar hora"><i class="fa-solid fa-eraser"></i></button>
                            </div>
                        </label>
                        <input type="time" name="hora_salida_real" id="edit_hora_salida" step="1" class="form-control font-monospace text-center font-weight-bold" style="font-size: 1.1rem; color: #0f172a;" oninput="calculateRealtimeAdminAttendance()">
                        <small class="text-muted d-block mt-1">Retiro de la jornada</small>
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-between text-muted small mb-3 px-1">
                    <span><i class="fa-solid fa-circle-info text-secondary mr-1"></i> Lun-Vie: 08:00 a 17:00 (Refrigerio 13:00 - 13:45, 45 min). Sáb: 08:00 a 13:00 (Sin refrigerio).</span>
                    <span class="badge badge-light border text-secondary font-weight-bold" id="edit_tipo_dia_badge">Día Regular</span>
                </div>

                <!-- PREVIEW CALCULADO EN TIEMPO REAL CON COLORES CORPORATIVOS -->
                <div class="p-3 mb-3 rounded" style="background-color: #f8fafc; border: 1px solid #e2e8f0;" id="adminCalcAlert">
                    <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap: 8px;">
                        <div class="text-dark" style="font-size: 0.86rem;">
                            <span class="text-muted text-uppercase mr-2 font-weight-bold" style="font-size: 0.72rem; letter-spacing: 0.05em;">
                                <i class="fa-solid fa-calculator mr-1 text-secondary"></i>Recálculo:
                            </span> 
                            <span id="adminCalcSummary" style="color: #0f172a; font-weight: 600;">Ingresa las horas de entrada y salida</span>
                        </div>
                        <div class="d-flex align-items-center" style="gap: 6px;">
                            <span class="text-muted small" style="font-size: 0.76rem;">Estado sugerido:</span>
                            <strong class="badge px-2 py-1 font-weight-bold" id="adminCalcStateBadge" style="font-size: 0.78rem;">--</strong>
                        </div>
                    </div>
                </div>

                <!-- ESTADO Y OBSERVACIONES -->
                <div class="modal-section-title">
                    <i class="fa-solid fa-clipboard-check"></i> Resolución y Observaciones
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-secondary">Estado Oficial de Asistencia <span class="text-danger">*</span></label>
                            <select name="estado" id="edit_estado" class="form-control font-weight-bold" required>
                                <option value="PRESENTE">PRESENTE</option>
                                <option value="TARDANZA">TARDANZA</option>
                                <option value="JUSTIFICADO">JUSTIFICADO</option>
                                <option value="PERMISO">PERMISO</option>
                                <option value="COMISION_SERVICIO">COMISIÓN DE SERVICIO</option>
                                <option value="VACACIONES">VACACIONES</option>
                                <option value="FALTA">FALTA</option>
                                <option value="DESCANSO">DESCANSO O FERIADO</option>
                                <option value="SALIDA_SIN_MARCAR">SALIDA SIN MARCAR</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-secondary">Minutos de Tardanza</label>
                            <input type="number" name="minutos_tardanza" id="edit_tardanza" class="form-control" min="0">
                        </div>
                    </div>
                    <input type="hidden" name="minutos_extra" id="edit_extra" value="0">
                </div>

                <div class="form-group mb-0">
                    <label class="small font-weight-bold text-secondary">Observaciones <span class="text-muted font-weight-normal">(Opcional - dejar en blanco para reporte limpio sin anotaciones)</span></label>
                    <textarea name="observaciones" id="edit_obs" class="form-control" rows="2" placeholder="Dejar en blanco para que el registro oficial permanezca limpio sin observaciones..."></textarea>
                </div>
            </div>
            
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-dismiss="modal">Cancelar</button>
                <button type="submit" id="btnSaveAdminEdit" class="btn btn-primary btn-sm px-4 font-weight-bold">
                    <i class="fa-solid fa-floppy-disk mr-1"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($userRole === 'ADMIN' || $userRole === 'RRHH'): ?>
<!-- MODAL REGISTRAR ASISTENCIA JUSTIFICADA / COMISIÓN DE SERVICIO -->
<div class="modal fade" id="modalJustificarAdmin" tabindex="-1" role="dialog" aria-labelledby="modalJustificarTitle" aria-modal="true" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form method="POST" action="?route=asistencia&action=justificar_admin" class="modal-content shadow-lg border-0" id="formJustificarAdmin" onsubmit="submitJustificarAdmin(event)">
            <?= csrf_field() ?>
            <div class="modal-header">
                <div class="d-flex align-items-center">
                    <h5 class="modal-title font-weight-bold mb-0" id="modalJustificarTitle">
                        <i class="fa-solid fa-file-shield mr-2"></i> Registrar Justificación
                    </h5>
                    <span class="badge ml-2 font-weight-normal px-2 py-0.5" style="background: rgba(255,255,255,0.12); color: #e2e8f0; border: 1px solid rgba(255,255,255,0.2); font-size: 0.72rem;">Auditoría RRHH / Admin</span>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            
            <div class="modal-body p-4">
                <!-- BUSCADOR INTERACTIVO EN VIVO DE TRABAJADOR POR NOMBRE, DNI O RELOJ -->
                <div class="modal-section-title mt-0 mb-3">
                    <i class="fa-solid fa-user-check"></i> 1. Seleccionar Trabajador
                </div>
                
                <input type="hidden" name="id_empleado" id="just_empleado_select" value="" required>

                <!-- Input de búsqueda por nombre o DNI -->
                <div class="worker-search-wrapper mb-2" id="just_search_container">
                    <input type="text" 
                           id="just_worker_search_input" 
                           class="form-control" 
                           placeholder="Escribe nombre, apellido o DNI del trabajador..." 
                           autocomplete="off"
                           oninput="filtrarTrabajadoresEnVivo(this.value, 'just')"
                           onkeydown="navegarResultadosTrabajador(event, 'just')"
                           onfocus="filtrarTrabajadoresEnVivo(this.value, 'just')">

                    <!-- Dropdown flotante con resultados filtrados -->
                    <div id="just_worker_results" class="worker-search-dropdown" style="display: none;"></div>
                </div>

                <!-- Tarjeta del Trabajador Seleccionado -->
                <div id="just_worker_selected_badge" class="mb-3" style="display: none;">
                    <div class="d-flex align-items-center justify-content-between p-2.5 rounded" style="background-color: #f8fafc; border: 1px solid #cbd5e1;">
                        <div>
                            <div class="font-weight-bold text-dark" id="just_badge_nombre" style="font-size: 0.92rem;">--</div>
                            <div class="text-muted small" style="font-size: 0.78rem;">
                                DNI: <strong class="text-dark" id="just_badge_dni">--</strong>
                                <span class="mx-1">&bull;</span>
                                Reloj ID: <strong class="text-dark" id="just_badge_reloj">--</strong>
                                <span class="mx-1">&bull;</span>
                                Área: <span id="just_badge_depto" class="text-dark font-weight-bold">--</span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary font-weight-bold" onclick="deseleccionarTrabajador('just')" title="Cambiar de trabajador">
                            Cambiar
                        </button>
                    </div>
                </div>

                <div class="modal-section-title">
                    <i class="fa-solid fa-calendar-week"></i> 2. Parámetros de la Justificación
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-secondary">Tipo de Justificación <span class="text-danger">*</span></label>
                            <select name="tipo" id="just_tipo" class="form-control font-weight-bold" onchange="onJustTipoChanged(this.value)" required>
                                <option value="COMISION_SERVICIO">Comisión de Servicio (Otras Comisiones)</option>
                                <option value="VACACIONES">Vacaciones</option>
                                <option value="TARDANZA">Tardanza Justificada</option>
                                <option value="FALTA">Falta Justificada</option>
                                <option value="PERMISO_MEDICO">Descanso Médico</option>
                                <option value="LICENCIA_MATERNIDAD_PATERNIDAD">Licencia por Paternidad o Maternidad</option>
                                <option value="OTRO">Otro Motivo</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-secondary">Fecha Inicio <span class="text-danger">*</span></label>
                            <input type="date" name="fecha_inicio" id="just_fecha_inicio" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-secondary">Fecha Fin <span class="text-danger">*</span></label>
                            <input type="date" name="fecha_fin" id="just_fecha_fin" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                </div>

                <!-- SELECTOR COMISIÓN DE DESTINO (JUSHSAL) -->
                <div id="comision_destino_container" class="form-group mb-3 p-3 rounded" style="background-color: #f8fafc; border: 1px solid #e2e8f0;">
                    <label class="small font-weight-bold text-dark mb-1">
                        <i class="fa-solid fa-map-location-dot mr-1 text-secondary"></i> Comisión de Usuarios de Destino (JUSHSAL) <span class="text-danger">*</span>
                    </label>
                    <select name="comision_destino" id="just_comision_destino" class="form-control font-weight-bold">
                        <option value="">-- Seleccionar Comisión de Usuarios --</option>
                        <option value="Comisión de Usuarios Hualtaco I-II">Comisión de Usuarios Hualtaco I-II</option>
                        <option value="Comisión de Usuarios Hualtaco III">Comisión de Usuarios Hualtaco III</option>
                        <option value="Comisión de Usuarios Hualtaco IV">Comisión de Usuarios Hualtaco IV</option>
                        <option value="Comisión de Usuarios TG-Malingas">Comisión de Usuarios TG-Malingas</option>
                        <option value="Comisión de Usuarios M-Malingas">Comisión de Usuarios M-Malingas</option>
                        <option value="Comisión de Usuarios Valle de los Incas">Comisión de Usuarios Valle de los Incas</option>
                        <option value="Comisión de Usuarios Tejedores">Comisión de Usuarios Tejedores</option>
                        <option value="Comisión de Usuarios San Isidro I y II">Comisión de Usuarios San Isidro I y II</option>
                        <option value="Comisión de Usuarios Quiroz Paimas">Comisión de Usuarios Quiroz Paimas</option>
                        <option value="Comisión de Usuarios Chipillico Margen Derecha">Comisión de Usuarios Chipillico Margen Derecha</option>
                        <option value="Comisión de Usuarios Chipillico Margen Izquierda">Comisión de Usuarios Chipillico Margen Izquierda</option>
                        <option value="Comisión de Usuarios Tambogrande">Comisión de Usuarios Tambogrande</option>
                        <option value="Comisión de Usuarios Quebrada Totoral Pampelera Alta">Comisión de Usuarios Quebrada Totoral Pampelera Alta</option>
                        <option value="Comisión de Usuarios Somate Alto">Comisión de Usuarios Somate Alto</option>
                        <option value="Comisión de Usuarios Somate Bajo">Comisión de Usuarios Somate Bajo</option>
                        <option value="Comisión de Usuarios Algarrobo - Yuscay">Comisión de Usuarios Algarrobo - Yuscay</option>
                        <option value="Represa Los Quiroz / Bocatoma Zamba / Partidores">Represa Los Quiroz / Bocatoma Zamba / Partidores</option>
                        <option value="Otra Sede o Entidad Externa">Otra Sede o Entidad Externa</option>
                    </select>
                    <small class="text-muted d-block mt-1">
                        <i class="fa-solid fa-clock mr-1 text-secondary"></i> Se generará automáticamente el horario oficial de jornada: <strong>08:00 a 17:00</strong> (Refrigerio 13:00 - 13:45, 8h 15m laboradas).
                    </small>
                </div>

                <!-- AVISO INFORMATIVO DE VACACIONES -->
                <div id="vacaciones_auto_info" class="p-3 rounded small text-secondary mb-3" style="display: none; background-color: #f8fafc; border: 1px solid #e2e8f0;">
                    <i class="fa-solid fa-umbrella-beach text-secondary mr-1"></i>
                    <strong>Período Vacacional:</strong> Se registrarán automáticamente las marcaciones oficiales completas (08:00 a 17:00, refrigerio 13:00 - 13:45) por cada día hábil comprendido en las fechas con estado <strong>VACACIONES</strong>.
                </div>

                <div class="modal-section-title">
                    <i class="fa-solid fa-file-lines"></i> 3. Motivo y Sustento
                </div>

                <div class="form-group mb-0">
                    <label class="small font-weight-bold text-secondary">Detalle del Sustento <span class="text-danger">*</span></label>
                    <textarea name="motivo" id="just_motivo" class="form-control" rows="3" placeholder="Detallar el sustento de la justificación autorizada..." required></textarea>
                </div>
            </div>
            
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-dismiss="modal">Cancelar</button>
                <button type="submit" id="btnSaveJustifyAdmin" class="btn btn-primary btn-sm px-4 font-weight-bold">
                    <i class="fa-solid fa-shield-halved mr-1"></i> Aplicar Justificación
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.worker-search-wrapper {
    position: relative;
}
.worker-search-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 1060;
    background: #ffffff;
    max-height: 270px;
    overflow-y: auto;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.15), 0 8px 10px -6px rgba(0,0,0,0.1);
}
.worker-search-item {
    cursor: pointer;
    padding: 8px 12px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: background-color 0.15s ease;
}
.worker-search-item:last-child {
    border-bottom: none;
}
.worker-search-item:hover, .worker-search-item.active {
    background-color: #eff6ff !important;
}
.worker-search-item mark {
    background-color: #fef08a;
    color: #0f172a;
    font-weight: 700;
    padding: 0 2px;
    border-radius: 2px;
}
</style>

<script>
// =========================================================================
// GESTIÓN ADMINISTRATIVA DE ASISTENCIA: MOTOR DE BÚSQUEDA RÁPIDA DE TRABAJADOR
// =========================================================================

// Catálogo de trabajadores para búsqueda instantánea en memoria (0ms latencia)
const LISTA_EMPLEADOS = <?= json_encode(array_values(array_map(function($emp) {
    return [
        'id' => (int)$emp['id'],
        'nombres' => $emp['nombres'] ?? '',
        'apellidos' => $emp['apellidos'] ?? '',
        'nombre_completo' => trim(($emp['apellidos'] ?? '') . ' ' . ($emp['nombres'] ?? '')),
        'dni' => $emp['dni'] ?? '',
        'codigo_reloj' => (string)($emp['codigo_reloj'] ?? ''),
        'departamento' => $emp['departamento_nombre'] ?? 'General',
        'turno' => $emp['turno_nombre'] ?? 'Turno General',
        'hora_entrada' => !empty($emp['hora_entrada']) ? substr($emp['hora_entrada'], 0, 5) : '08:00',
        'hora_salida' => !empty($emp['hora_salida']) ? substr($emp['hora_salida'], 0, 5) : '17:00',
        'tolerancia' => (int)($emp['tolerancia_minutos'] ?? 10),
    ];
}, $empleados)), JSON_UNESCAPED_UNICODE) ?>;

function normalizarTexto(txt) {
    if (!txt) return '';
    return txt.toString().toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").trim();
}

function escapeHtml(text) {
    if (!text) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return text.toString().replace(/[&<>"']/g, m => map[m]);
}

function resaltarCoincidencias(textoOriginal, query) {
    if (!query || !textoOriginal) return escapeHtml(textoOriginal);
    const tokens = normalizarTexto(query).split(/\s+/).filter(t => t.length > 0);
    if (tokens.length === 0) return escapeHtml(textoOriginal);
    const escaped = tokens.map(t => t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
    const regex = new RegExp(`(${escaped.join('|')})`, 'gi');
    return escapeHtml(textoOriginal).replace(regex, '<mark>$1</mark>');
}

// Filtrar trabajadores en tiempo real al escribir
function filtrarTrabajadoresEnVivo(query, tipo) {
    const resultsContainer = document.getElementById(`${tipo}_worker_results`);
    if (!resultsContainer) return;

    const qNorm = normalizarTexto(query);
    const tokens = qNorm.split(/\s+/).filter(t => t.length > 0);

    let matches = [];
    if (tokens.length === 0) {
        // Si el buscador está vacío, mostrar los primeros 15 trabajadores
        matches = LISTA_EMPLEADOS.slice(0, 15);
    } else {
        matches = LISTA_EMPLEADOS.filter(emp => {
            const nomNorm = normalizarTexto(emp.nombre_completo);
            const dniNorm = normalizarTexto(emp.dni);
            const relojNorm = normalizarTexto(emp.codigo_reloj);
            const deptoNorm = normalizarTexto(emp.departamento);
            const haystack = `${nomNorm} ${dniNorm} ${relojNorm} ${deptoNorm}`;

            return tokens.every(tok => haystack.includes(tok));
        }).slice(0, 25);
    }

    if (matches.length === 0) {
        resultsContainer.innerHTML = `
            <div class="p-3 text-center text-muted small">
                No se encontraron trabajadores con "<strong>${escapeHtml(query)}</strong>"
            </div>
        `;
    } else {
        let html = '';
        matches.forEach((emp, idx) => {
            const activeClass = idx === 0 && tokens.length > 0 ? 'active' : '';
            html += `
                <div class="worker-search-item ${activeClass}" data-id="${emp.id}" onclick="seleccionarTrabajador(${emp.id}, '${tipo}')">
                    <div class="py-1">
                        <div class="font-weight-bold text-dark mb-0" style="font-size: 0.88rem;">
                            ${resaltarCoincidencias(emp.nombre_completo, query)}
                        </div>
                        <div class="text-muted small" style="font-size: 0.76rem;">
                            DNI: <strong>${resaltarCoincidencias(emp.dni, query)}</strong>
                            <span class="mx-1">&bull;</span>
                            Reloj: <strong>${resaltarCoincidencias(emp.codigo_reloj, query)}</strong>
                            <span class="mx-1">&bull;</span>
                            ${escapeHtml(emp.departamento)}
                        </div>
                    </div>
                    <div>
                        <span class="badge badge-light border text-primary font-weight-bold" style="font-size: 0.72rem;">
                            Elegir
                        </span>
                    </div>
                </div>
            `;
        });
        resultsContainer.innerHTML = html;
    }

    resultsContainer.style.display = 'block';
}

// Seleccionar un trabajador desde la lista
function seleccionarTrabajador(empId, tipo) {
    const emp = LISTA_EMPLEADOS.find(e => e.id == empId);
    if (!emp) return;

    // Cerrar dropdown de búsqueda
    const resultsContainer = document.getElementById(`${tipo}_worker_results`);
    if (resultsContainer) resultsContainer.style.display = 'none';

    // Ocultar wrapper del buscador y mostrar tarjeta de seleccionado
    const searchContainer = document.getElementById(`${tipo}_search_container`);
    const badgeContainer = document.getElementById(`${tipo}_worker_selected_badge`);

    if (searchContainer) searchContainer.style.display = 'none';
    if (badgeContainer) badgeContainer.style.display = 'block';

    const nombreEl = document.getElementById(`${tipo}_badge_nombre`);
    if (nombreEl) nombreEl.innerText = emp.nombre_completo;

    const dniEl = document.getElementById(`${tipo}_badge_dni`);
    if (dniEl) dniEl.innerText = emp.dni || '--';

    const relojEl = document.getElementById(`${tipo}_badge_reloj`);
    if (relojEl) relojEl.innerText = emp.codigo_reloj || '--';

    const deptoEl = document.getElementById(`${tipo}_badge_depto`);
    if (deptoEl) deptoEl.innerText = emp.departamento || 'General';

    const turnoEl = document.getElementById(`${tipo}_badge_turno`);
    if (turnoEl) turnoEl.innerText = emp.turno || 'General';

    if (tipo === 'just') {
        const inputHidden = document.getElementById('just_empleado_select');
        if (inputHidden) inputHidden.value = emp.id;

        const hintEl = document.getElementById('just_worker_hint');
        if (hintEl) hintEl.style.display = 'none';

        // Auto-enfocar el siguiente campo (Tipo de Justificación)
        setTimeout(() => {
            const nextField = document.getElementById('just_tipo');
            if (nextField) nextField.focus();
        }, 150);
    } else if (tipo === 'edit') {
        const inputHidden = document.getElementById('edit_id_empleado');
        if (inputHidden) inputHidden.value = emp.id;

        document.getElementById('edit_prog_entrada').value = emp.hora_entrada || '08:00';
        document.getElementById('edit_prog_salida').value = emp.hora_salida || '17:00';
        document.getElementById('edit_tolerancia').value = emp.tolerancia || 10;
        document.getElementById('edit_turno_info').innerText = `${emp.hora_entrada || '08:00'} - ${emp.hora_salida || '17:00'} (Turno: ${emp.turno || 'General'})`;

        calculateRealtimeAdminAttendance();

        setTimeout(() => {
            const nextField = document.getElementById('edit_hora_entrada');
            if (nextField) nextField.focus();
        }, 150);
    }
}

// Deseleccionar trabajador para buscar otro
function deseleccionarTrabajador(tipo) {
    const resultsContainer = document.getElementById(`${tipo}_worker_results`);
    if (resultsContainer) resultsContainer.style.display = 'none';

    const searchContainer = document.getElementById(`${tipo}_search_container`);
    const badgeContainer = document.getElementById(`${tipo}_worker_selected_badge`);

    if (searchContainer) searchContainer.style.display = 'block';
    if (badgeContainer) badgeContainer.style.display = 'none';

    if (tipo === 'just') {
        const inputHidden = document.getElementById('just_empleado_select');
        if (inputHidden) inputHidden.value = '';

        const hintEl = document.getElementById('just_worker_hint');
        if (hintEl) hintEl.style.display = 'block';
    } else if (tipo === 'edit') {
        const inputHidden = document.getElementById('edit_id_empleado');
        if (inputHidden) inputHidden.value = '0';
    }

    const input = document.getElementById(`${tipo}_worker_search_input`);
    if (input) {
        input.value = '';
        input.focus();
        filtrarTrabajadoresEnVivo('', tipo);
    }
}

// Limpiar campo de búsqueda
function limpiarBusquedaTrabajador(tipo) {
    const input = document.getElementById(`${tipo}_worker_search_input`);
    if (input) {
        input.value = '';
        input.focus();
        filtrarTrabajadoresEnVivo('', tipo);
    }
}

// Navegación con teclado en los resultados (flechas arriba/abajo, Enter, Escape)
function navegarResultadosTrabajador(e, tipo) {
    const resultsContainer = document.getElementById(`${tipo}_worker_results`);
    if (!resultsContainer || resultsContainer.style.display === 'none') return;

    const items = Array.from(resultsContainer.querySelectorAll('.worker-search-item'));
    if (items.length === 0) return;

    let currentIndex = items.findIndex(item => item.classList.contains('active'));

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (currentIndex === -1 || currentIndex >= items.length - 1) {
            currentIndex = 0;
        } else {
            currentIndex++;
        }
        items.forEach((it, idx) => it.classList.toggle('active', idx === currentIndex));
        if (items[currentIndex]) items[currentIndex].scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (currentIndex <= 0) {
            currentIndex = items.length - 1;
        } else {
            currentIndex--;
        }
        items.forEach((it, idx) => it.classList.toggle('active', idx === currentIndex));
        if (items[currentIndex]) items[currentIndex].scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (currentIndex >= 0 && items[currentIndex]) {
            items[currentIndex].click();
        } else if (items.length > 0) {
            items[0].click();
        }
    } else if (e.key === 'Escape') {
        resultsContainer.style.display = 'none';
    }
}

// Cerrar desplegable al hacer clic fuera
document.addEventListener('click', function(e) {
    const justContainer = document.getElementById('just_search_container');
    const editContainer = document.getElementById('edit_search_container');

    if (justContainer && !justContainer.contains(e.target)) {
        const res = document.getElementById('just_worker_results');
        if (res) res.style.display = 'none';
    }
    if (editContainer && !editContainer.contains(e.target)) {
        const res = document.getElementById('edit_worker_results');
        if (res) res.style.display = 'none';
    }
});

function openAdminEditModal(rec, focusField = 'entrada') {
    document.getElementById('edit_id').value = rec.id || 0;
    document.getElementById('edit_id_empleado').value = rec.id_empleado || 0;
    const fecha = rec.fecha || '<?= date('Y-m-d') ?>';
    document.getElementById('edit_fecha_hidden').value = fecha;
    
    document.getElementById('edit_empleado_card').style.display = 'block';
    document.getElementById('edit_empleado_selector_card').style.display = 'none';

    document.getElementById('edit_badge_fecha').innerText = 'Fecha: ' + fecha;
    document.getElementById('edit_empleado_nombre').innerText = `${rec.apellidos || ''} ${rec.nombres || ''}`;
    document.getElementById('edit_empleado_dni').innerText = rec.dni || '--';
    document.getElementById('edit_empleado_reloj').innerText = rec.codigo_reloj || '--';
    
    // Detectar si la fecha es sábado
    let isSat = false;
    if (fecha) {
        const p = fecha.split('-');
        if (p.length === 3) {
            const dt = new Date(parseInt(p[0]), parseInt(p[1]) - 1, parseInt(p[2]));
            isSat = (dt.getDay() === 6);
        }
    }

    const progEnt = isSat ? '08:00' : (rec.hora_entrada_programada ? rec.hora_entrada_programada.substr(0, 5) : '08:00');
    const progSal = isSat ? '13:00' : (rec.hora_salida_programada ? rec.hora_salida_programada.substr(0, 5) : '17:00');
    document.getElementById('edit_prog_entrada').value = progEnt;
    document.getElementById('edit_prog_salida').value = progSal;
    document.getElementById('edit_tolerancia').value = rec.tolerancia_minutos || 10;
    document.getElementById('edit_turno_info').innerText = isSat 
        ? `08:00 - 13:00 (Sábado sin refrigerio)` 
        : `${progEnt} - ${progSal} (Refrig: 13:00-13:45 / Tol: ${rec.tolerancia_minutos || 10}m)`;

    // Extraer horas reales existentes
    let realEnt = '';
    if (rec.hora_entrada_real) {
        realEnt = rec.hora_entrada_real.substr(11, 5);
    }
    let realSalRef = '';
    if (rec.hora_inicio_refrigerio_real) {
        realSalRef = rec.hora_inicio_refrigerio_real.substr(11, 5);
    }
    let realRetRef = '';
    if (rec.hora_fin_refrigerio_real) {
        realRetRef = rec.hora_fin_refrigerio_real.substr(11, 5);
    }
    let realSal = '';
    if (rec.hora_salida_real) {
        realSal = rec.hora_salida_real.substr(11, 5);
    }

    document.getElementById('edit_hora_entrada').value = realEnt;
    document.getElementById('edit_hora_inicio_refrigerio').value = realSalRef;
    document.getElementById('edit_hora_fin_refrigerio').value = realRetRef;
    document.getElementById('edit_hora_salida').value = realSal;
    document.getElementById('edit_estado').value = rec.estado || 'PRESENTE';
    document.getElementById('edit_tardanza').value = rec.minutos_tardanza || 0;
    document.getElementById('edit_extra').value = rec.minutos_extra || 0;
    document.getElementById('edit_obs').value = rec.observaciones || '';

    calculateRealtimeAdminAttendance();
    $('#modalEditarAsistencia').modal('show');

    setTimeout(() => {
        if (focusField === 'salida') {
            document.getElementById('edit_hora_salida').focus();
        } else {
            document.getElementById('edit_hora_entrada').focus();
        }
    }, 400);
}

function openAsignarHorasModal() {
    document.getElementById('edit_id').value = 0;
    document.getElementById('edit_id_empleado').value = 0;
    document.getElementById('edit_fecha_hidden').value = '<?= date('Y-m-d') ?>';
    
    document.getElementById('edit_empleado_card').style.display = 'none';
    document.getElementById('edit_empleado_selector_card').style.display = 'block';

    deseleccionarTrabajador('edit');

    document.getElementById('edit_hora_entrada').value = '';
    document.getElementById('edit_hora_inicio_refrigerio').value = '';
    document.getElementById('edit_hora_fin_refrigerio').value = '';
    document.getElementById('edit_hora_salida').value = '';
    document.getElementById('edit_estado').value = 'PRESENTE';
    document.getElementById('edit_tardanza').value = 0;
    document.getElementById('edit_extra').value = 0;
    document.getElementById('edit_obs').value = '';

    calculateRealtimeAdminAttendance();
    $('#modalEditarAsistencia').modal('show');
}

function onWorkerSelected(selectEl) {
    const opt = selectEl.options[selectEl.selectedIndex];
    if (!opt || !opt.value) return;

    document.getElementById('edit_id_empleado').value = opt.value;
    document.getElementById('edit_prog_entrada').value = opt.getAttribute('data-hent') || '08:00';
    document.getElementById('edit_prog_salida').value = opt.getAttribute('data-hsal') || '17:00';
    document.getElementById('edit_tolerancia').value = opt.getAttribute('data-tol') || '10';

    calculateRealtimeAdminAttendance();
}

function onDatePickerChanged(val) {
    document.getElementById('edit_fecha_hidden').value = val;
    calculateRealtimeAdminAttendance();
}

function setProgrammedTime(field) {
    const fechaStr = document.getElementById('edit_fecha_hidden').value;
    let isSaturday = false;
    if (fechaStr) {
        const parts = fechaStr.split('-');
        if (parts.length === 3) {
            const dt = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
            isSaturday = (dt.getDay() === 6);
        }
    }

    if (field === 'entrada') {
        const prog = document.getElementById('edit_prog_entrada').value || '08:00';
        document.getElementById('edit_hora_entrada').value = prog;
    } else if (field === 'salida_ref') {
        document.getElementById('edit_hora_inicio_refrigerio').value = isSaturday ? '' : '13:00';
    } else if (field === 'retorno_ref') {
        document.getElementById('edit_hora_fin_refrigerio').value = isSaturday ? '' : '13:45';
    } else if (field === 'salida') {
        const prog = isSaturday ? '13:00' : (document.getElementById('edit_prog_salida').value || '17:00');
        document.getElementById('edit_hora_salida').value = prog;
    }
    calculateRealtimeAdminAttendance();
}

function clearTime(field) {
    if (field === 'entrada') {
        document.getElementById('edit_hora_entrada').value = '';
    } else if (field === 'salida_ref') {
        document.getElementById('edit_hora_inicio_refrigerio').value = '';
    } else if (field === 'retorno_ref') {
        document.getElementById('edit_hora_fin_refrigerio').value = '';
    } else if (field === 'salida') {
        document.getElementById('edit_hora_salida').value = '';
    }
    calculateRealtimeAdminAttendance();
}

function calculateRealtimeAdminAttendance() {
    const entVal = document.getElementById('edit_hora_entrada').value;
    const salRefVal = document.getElementById('edit_hora_inicio_refrigerio') ? document.getElementById('edit_hora_inicio_refrigerio').value : '';
    const retRefVal = document.getElementById('edit_hora_fin_refrigerio') ? document.getElementById('edit_hora_fin_refrigerio').value : '';
    const salVal = document.getElementById('edit_hora_salida').value;
    const fechaVal = document.getElementById('edit_fecha_hidden').value;

    let isSaturday = false;
    if (fechaVal) {
        const parts = fechaVal.split('-');
        if (parts.length === 3) {
            const dt = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
            isSaturday = (dt.getDay() === 6);
        }
    }

    const tipoDiaBadge = document.getElementById('edit_tipo_dia_badge');
    if (tipoDiaBadge) {
        if (isSaturday) {
            tipoDiaBadge.className = 'badge badge-warning text-dark font-weight-bold';
            tipoDiaBadge.innerText = 'Sábado (08:00 a 13:00 - Sin Refrigerio)';
        } else {
            tipoDiaBadge.className = 'badge badge-info font-weight-bold';
            tipoDiaBadge.innerText = 'Lun-Vie (08:00 a 17:00 - Ref. 13:00 a 13:45)';
        }
    }

    const progEnt = document.getElementById('edit_prog_entrada').value || '08:00';
    const progSal = isSaturday ? '13:00' : (document.getElementById('edit_prog_salida').value || '17:00');
    const tol = parseInt(document.getElementById('edit_tolerancia').value) || 10;

    const summaryEl = document.getElementById('adminCalcSummary');
    const badgeEl = document.getElementById('adminCalcStateBadge');
    const tardanzaInput = document.getElementById('edit_tardanza');
    const estadoSelect = document.getElementById('edit_estado');

    if (!entVal && !salVal) {
        summaryEl.innerText = 'Sin horas ingresadas';
        badgeEl.className = 'badge badge-secondary px-2 py-1 font-weight-bold';
        badgeEl.innerText = 'SIN MARCAR';
        return;
    }

    let computedTardanza = 0;
    let suggestedState = 'PRESENTE';
    let summaryParts = [];

    if (entVal) {
        const [hEnt, mEnt] = entVal.split(':').map(Number);
        const [hProg, mProg] = progEnt.split(':').map(Number);

        const minReal = hEnt * 60 + mEnt;
        const minProg = hProg * 60 + mProg;
        const minGrace = minProg + tol;

        if (minReal <= minGrace) {
            computedTardanza = 0;
            suggestedState = 'PRESENTE';
            summaryParts.push(`<span class="font-weight-bold text-dark"><i class="fa-solid fa-circle-check mr-1 text-success"></i>Puntual (${entVal})</span>`);
        } else {
            computedTardanza = Math.max(0, minReal - minProg);
            suggestedState = 'TARDANZA';
            summaryParts.push(`<span class="font-weight-bold text-dark"><i class="fa-solid fa-circle-exclamation mr-1 text-warning"></i>Tardanza (+${computedTardanza} min)</span>`);
        }
    }

    if (entVal && salVal) {
        const [hEnt, mEnt] = entVal.split(':').map(Number);
        const [hSal, mSal] = salVal.split(':').map(Number);
        const [hProgSal, mProgSal] = progSal.split(':').map(Number);

        const minStart = hEnt * 60 + mEnt;
        const minEnd = hSal * 60 + mSal;
        const minProgExit = hProgSal * 60 + mProgSal;

        if (minEnd > minStart) {
            const rawDiffMin = minEnd - minStart;
            let minutesDiscount = 0;

            if (!isSaturday) {
                if (salRefVal && retRefVal) {
                    const [hSR, mSR] = salRefVal.split(':').map(Number);
                    const [hRR, mRR] = retRefVal.split(':').map(Number);
                    const minSalRef = hSR * 60 + mSR;
                    const minRetRef = hRR * 60 + mRR;
                    if (minRetRef > minSalRef) {
                        minutesDiscount = minRetRef - minSalRef;
                    }
                } else if (rawDiffMin >= 300) {
                    minutesDiscount = 45;
                }
            }

            const netMinutes = Math.max(0, rawDiffMin - minutesDiscount);
            const netHrs = Math.floor(netMinutes / 60);
            const netMins = netMinutes % 60;

            let descText = minutesDiscount > 0 ? ` (Desc. ${minutesDiscount}m ref)` : '';
            summaryParts.push(`<span class="font-weight-bold text-dark"><i class="fa-solid fa-business-time mr-1 text-muted"></i>${netHrs}h ${netMins}m laboradas${descText}</span>`);
        }
    } else if (entVal && !salVal) {
        summaryParts.push(`<span class="font-weight-bold text-dark"><i class="fa-solid fa-triangle-exclamation mr-1 text-warning"></i>Salida pendiente</span>`);
    }

    tardanzaInput.value = computedTardanza;
    summaryEl.innerHTML = summaryParts.join(' &bull; ');

    if (['PRESENTE', 'TARDANZA', 'FALTA', 'SALIDA_SIN_MARCAR'].includes(estadoSelect.value)) {
        estadoSelect.value = suggestedState;
    }
    
    badgeEl.className = suggestedState === 'PRESENTE' ? 'badge badge-success px-2 py-1 font-weight-bold' : 'badge badge-danger px-2 py-1 font-weight-bold';
    badgeEl.innerText = suggestedState;
}

function submitAdminEditAttendance(e) {
    e.preventDefault();
    const btn = $('#btnSaveAdminEdit');
    const origText = btn.html();
    btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-1"></i> Guardando...');

    $.ajax({
        url: '?route=asistencia&action=editar',
        type: 'POST',
        data: $('#formAdminEditAsistencia').serialize(),
        dataType: 'json',
        success: function(res) {
            btn.prop('disabled', false).html(origText);
            if (res.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Horario Guardado!',
                    text: res.message || 'La asistencia ha sido registrada y actualizada directamente en la base de datos.',
                    confirmButtonColor: '#1d4ed8'
                }).then(() => {
                    $('#modalEditarAsistencia').modal('hide');
                    window.location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al Guardar',
                    text: res.error || 'No se pudo guardar la modificación.',
                    confirmButtonColor: '#1d4ed8'
                });
            }
        },
        error: function() {
            btn.prop('disabled', false).html(origText);
            Swal.fire({
                icon: 'error',
                title: 'Error de Conexión',
                text: 'Ocurrió un error inesperado al comunicarse con el servidor.',
                confirmButtonColor: '#1d4ed8'
            });
        }
    });
}

function onJustTipoChanged(tipo) {
    const comisionBox = document.getElementById('comision_destino_container');
    const comisionSelect = document.getElementById('just_comision_destino');
    const vacInfo = document.getElementById('vacaciones_auto_info');

    if (tipo === 'COMISION_SERVICIO') {
        if (comisionBox) comisionBox.style.display = 'block';
        if (comisionSelect) comisionSelect.setAttribute('required', 'required');
        if (vacInfo) vacInfo.style.display = 'none';
    } else if (tipo === 'VACACIONES') {
        if (comisionBox) comisionBox.style.display = 'none';
        if (comisionSelect) {
            comisionSelect.removeAttribute('required');
            comisionSelect.value = '';
        }
        if (vacInfo) vacInfo.style.display = 'block';
    } else {
        if (comisionBox) comisionBox.style.display = 'none';
        if (comisionSelect) {
            comisionSelect.removeAttribute('required');
            comisionSelect.value = '';
        }
        if (vacInfo) vacInfo.style.display = 'none';
    }
}

function openJustificarAdminModal() {
    $('#formJustificarAdmin')[0].reset();
    deseleccionarTrabajador('just');
    document.getElementById('just_fecha_inicio').value = '<?= date('Y-m-d') ?>';
    document.getElementById('just_fecha_fin').value = '<?= date('Y-m-d') ?>';
    document.getElementById('just_tipo').value = 'COMISION_SERVICIO';
    onJustTipoChanged('COMISION_SERVICIO');
    $('#modalJustificarAdmin').modal('show');
}

function openQuickJustifyModal(rec) {
    $('#formJustificarAdmin')[0].reset();
    seleccionarTrabajador(rec.id_empleado, 'just');
    document.getElementById('just_fecha_inicio').value = rec.fecha;
    document.getElementById('just_fecha_fin').value = rec.fecha;
    document.getElementById('just_tipo').value = 'COMISION_SERVICIO';
    onJustTipoChanged('COMISION_SERVICIO');
    document.getElementById('just_motivo').value = `Comisión de servicio oficial del día ${rec.fecha}`;
    $('#modalJustificarAdmin').modal('show');
}

function submitJustificarAdmin(e) {
    e.preventDefault();
    const empId = $('#just_empleado_select').val();
    if (!empId) {
        Swal.fire({
            icon: 'warning',
            title: 'Trabajador requerido',
            text: 'Por favor busca y selecciona un trabajador de la lista antes de guardar.',
            confirmButtonColor: '#1d4ed8'
        });
        const inp = document.getElementById('just_worker_search_input');
        if (inp) {
            inp.focus();
            filtrarTrabajadoresEnVivo('', 'just');
        }
        return;
    }

    const tipo = document.getElementById('just_tipo')?.value;
    if (tipo === 'COMISION_SERVICIO') {
        const destino = document.getElementById('just_comision_destino')?.value;
        if (!destino) {
            Swal.fire({
                icon: 'warning',
                title: 'Comisión de Usuarios Requerida',
                text: 'Por favor selecciona la Comisión de Usuarios de destino a la que fue asignado el trabajador.',
                confirmButtonColor: '#1d4ed8'
            });
            document.getElementById('just_comision_destino')?.focus();
            return;
        }
    }

    const btn = $('#btnSaveJustifyAdmin');
    const origText = btn.html();
    btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-1"></i> Aplicando...');

    $.ajax({
        url: '?route=asistencia&action=justificar_admin',
        type: 'POST',
        data: $('#formJustificarAdmin').serialize(),
        dataType: 'json',
        success: function(res) {
            btn.prop('disabled', false).html(origText);
            if (res.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Asistencia Justificada!',
                    text: res.message || 'La justificación se ha registrado y aplicado exitosamente.',
                    confirmButtonColor: '#1d4ed8'
                }).then(() => {
                    $('#modalJustificarAdmin').modal('hide');
                    window.location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: res.error || 'No se pudo aplicar la justificación.',
                    confirmButtonColor: '#1d4ed8'
                });
            }
        },
        error: function() {
            btn.prop('disabled', false).html(origText);
            Swal.fire({
                icon: 'error',
                title: 'Error de Comunicación',
                text: 'Ocurrió un error al procesar la solicitud con el servidor.',
                confirmButtonColor: '#1d4ed8'
            });
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    if (window.jQuery) {
        $('#modalJustificarAdmin').on('shown.bs.modal', function () {
            const empId = document.getElementById('just_empleado_select')?.value;
            if (!empId) {
                const inp = document.getElementById('just_worker_search_input');
                if (inp) {
                    inp.focus();
                    filtrarTrabajadoresEnVivo('', 'just');
                }
            }
        });

        $('#modalEditarAsistencia').on('shown.bs.modal', function () {
            const selectorCard = document.getElementById('edit_empleado_selector_card');
            if (selectorCard && selectorCard.style.display !== 'none') {
                const empId = document.getElementById('edit_id_empleado')?.value;
                if (!empId || empId === '0') {
                    const inp = document.getElementById('edit_worker_search_input');
                    if (inp) {
                        inp.focus();
                        filtrarTrabajadoresEnVivo('', 'edit');
                    }
                }
            }
        });
    }
});
</script>
<?php endif; ?>

<script>
function openTimelineModal(empId, fecha, nombreEmp) {
    document.getElementById('timelineEmpleadoNombre').innerText = nombreEmp;
    document.getElementById('timelineFecha').innerText = fecha;
    document.getElementById('timelineEmpleadoDni').innerText = '...';
    document.getElementById('timelineEmpleadoReloj').innerText = '...';
    document.getElementById('timelineTotalEventos').innerText = 'Cargando...';
    
    document.getElementById('timelineLoading').style.display = 'block';
    document.getElementById('timelineContent').style.display = 'none';
    document.getElementById('timelineEmpty').style.display = 'none';

    $('#modalTimelineEventos').modal('show');

    fetch(`?route=asistencia&action=historial_eventos&id_empleado=${empId}&fecha=${fecha}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('timelineLoading').style.display = 'none';

            if (!data.success || !data.eventos || data.eventos.length === 0) {
                document.getElementById('timelineEmpty').style.display = 'block';
                document.getElementById('timelineTotalEventos').innerText = '0 eventos';
                return;
            }

            const emp = data.asistencia || {};
            document.getElementById('timelineEmpleadoNombre').innerText = `${emp.apellidos || ''} ${emp.nombres || ''}`;
            document.getElementById('timelineEmpleadoDni').innerText = emp.dni || '--';
            document.getElementById('timelineEmpleadoReloj').innerText = emp.codigo_reloj || '--';
            document.getElementById('timelineTotalEventos').innerText = `${data.eventos.length} evento(s) inmutable(s)`;

            const container = document.getElementById('timelineContent');
            container.innerHTML = '';

            let timelineHtml = `
                <div class="time-label">
                    <span class="bg-primary text-white font-weight-bold px-3 py-1 rounded shadow-sm">${data.fecha || fecha}</span>
                </div>
            `;

            data.eventos.forEach((ev) => {
                const disp = ev.display || {};
                const hora = ev.created_at ? ev.created_at.substr(11, 8) : '--:--';
                const version = ev.version ? `<span class="badge badge-light border ml-1">v${ev.version}</span>` : '';
                
                let detailsHtml = '';
                if (disp.details && Object.keys(disp.details).length > 0) {
                    detailsHtml = '<div class="row mt-2 pt-2 border-top small text-muted">';
                    for (const [k, v] of Object.entries(disp.details)) {
                        detailsHtml += `<div class="col-sm-6 mb-1"><strong>${k}:</strong> <span class="text-dark">${v}</span></div>`;
                    }
                    detailsHtml += '</div>';
                }

                timelineHtml += `
                    <div>
                        <i class="fa-solid ${disp.icon || 'fa-circle'} bg-info"></i>
                        <div class="timeline-item shadow-sm">
                            <span class="time font-weight-bold text-secondary"><i class="fas fa-clock mr-1"></i>${hora} ${version}</span>
                            <h3 class="timeline-header">
                                <span class="badge ${disp.badgeClass || 'badge-secondary'} mr-2">${ev.event_type}</span>
                                <strong>${disp.title || ev.event_type}</strong>
                            </h3>
                            <div class="timeline-body">
                                <p class="mb-0 text-dark">${disp.description || ''}</p>
                                ${detailsHtml}
                            </div>
                            <div class="timeline-footer py-1 px-3 bg-light d-flex justify-content-between align-items-center">
                                <small class="text-muted"><i class="fa-solid fa-user-shield mr-1"></i> Originado por: <strong>${ev.created_by || 'SYSTEM'}</strong></small>
                                <small class="text-muted"><i class="fa-solid fa-network-wired mr-1"></i> IP: ${ev.ip_address || '127.0.0.1'}</small>
                            </div>
                        </div>
                    </div>
                `;
            });

            timelineHtml += `
                <div>
                    <i class="fas fa-clock bg-gray"></i>
                </div>
            `;

            container.innerHTML = timelineHtml;
            container.style.display = 'block';
        })
        .catch(err => {
            console.error(err);
            document.getElementById('timelineLoading').style.display = 'none';
            document.getElementById('timelineEmpty').innerText = 'Error al cargar los eventos de auditoría.';
            document.getElementById('timelineEmpty').style.display = 'block';
        });
}

// ==========================================================
// CONTROLADOR DEL MODAL DE REPORTE INDIVIDUAL POR EMPLEADO
// ==========================================================
function openReporteIndividualModal() {
    $('#modalReporteIndividual').modal('show');
}

function setPeriodoReporte(tipo) {
    const dInicio = document.getElementById('rep_ind_inicio');
    const dFin = document.getElementById('rep_ind_fin');
    const now = new Date();
    
    if (tipo === 'mes_actual') {
        const y = now.getFullYear();
        const m = String(now.getMonth() + 1).padStart(2, '0');
        const d = String(now.getDate()).padStart(2, '0');
        dInicio.value = `${y}-${m}-01`;
        dFin.value = `${y}-${m}-${d}`;
    } else if (tipo === 'mes_anterior') {
        const prevMonth = new Date(now.getFullYear(), now.getMonth(), 0);
        const y = prevMonth.getFullYear();
        const m = String(prevMonth.getMonth() + 1).padStart(2, '0');
        const lastDay = String(prevMonth.getDate()).padStart(2, '0');
        dInicio.value = `${y}-${m}-01`;
        dFin.value = `${y}-${m}-${lastDay}`;
    } else if (tipo === 'ultimos_30') {
        const past = new Date();
        past.setDate(past.getDate() - 30);
        dInicio.value = past.toISOString().slice(0, 10);
        dFin.value = now.toISOString().slice(0, 10);
    } else if (tipo === 'hoy') {
        const todayStr = now.toISOString().slice(0, 10);
        dInicio.value = todayStr;
        dFin.value = todayStr;
    }
}

function ejecutarReporteIndividual(formato) {
    const empId = document.getElementById('rep_ind_empleado').value;
    if (!empId) {
        alert('Por favor selecciona a un trabajador para generar su reporte individual.');
        document.getElementById('rep_ind_empleado').focus();
        return;
    }
    const fIni = document.getElementById('rep_ind_inicio').value;
    const fFin = document.getElementById('rep_ind_fin').value;

    let url = `?route=asistencia&empleado_id=${encodeURIComponent(empId)}&fecha_inicio=${encodeURIComponent(fIni)}&fecha_fin=${encodeURIComponent(fFin)}`;

    if (formato === 'excel') {
        window.location.href = url + '&export=excel';
    } else if (formato === 'pdf') {
        window.open(url + '&print_auto=1', '_blank');
    } else {
        window.location.href = url;
    }
}

<?php if (!empty($_GET['print_auto'])): ?>
window.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        window.print();
    }, 500);
});
<?php endif; ?>
</script>

<!-- MODAL GENERAR REPORTE INDIVIDUAL POR EMPLEADO -->
<div class="modal fade" id="modalReporteIndividual" tabindex="-1" role="dialog" aria-labelledby="modalReporteIndTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 580px;">
        <div class="modal-content shadow-lg border-0" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold d-flex align-items-center" id="modalReporteIndTitle">
                    <i class="fa-solid fa-file-invoice mr-2 text-primary"></i> Generar Reporte por Empleado
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-3">Selecciona al trabajador y el período deseado para generar su reporte individual oficial con membrete y firmas JUSHSAL.</p>

                <div class="form-group mb-3">
                    <label class="small font-weight-bold text-dark">Trabajador <span class="text-danger">*</span></label>
                    <select id="rep_ind_empleado" class="form-control" style="height: 38px;">
                        <option value="">-- Seleccionar Trabajador --</option>
                        <?php foreach ($empleados as $emp): ?>
                            <option value="<?= $emp['id'] ?>" <?= ((int)($empleadoId ?? 0) === (int)$emp['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($emp['apellidos'] . ' ' . $emp['nombres']) ?> (DNI: <?= htmlspecialchars($emp['dni']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group mb-3">
                    <label class="small font-weight-bold text-dark mb-1">Período del Reporte</label>
                    <div class="d-flex flex-wrap mb-2" style="gap: 6px;">
                        <button type="button" class="btn btn-xs btn-outline-secondary font-weight-bold" onclick="setPeriodoReporte('mes_actual')">
                            Este Mes
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-secondary font-weight-bold" onclick="setPeriodoReporte('mes_anterior')">
                            Mes Anterior
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-secondary font-weight-bold" onclick="setPeriodoReporte('ultimos_30')">
                            Últimos 30 días
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-secondary font-weight-bold" onclick="setPeriodoReporte('hoy')">
                            Hoy
                        </button>
                    </div>

                    <div class="row">
                        <div class="col-6">
                            <label class="small text-muted mb-0">Desde:</label>
                            <input type="date" id="rep_ind_inicio" class="form-control" value="<?= date('Y-m-01') ?>" style="height: 38px;">
                        </div>
                        <div class="col-6">
                            <label class="small text-muted mb-0">Hasta:</label>
                            <input type="date" id="rep_ind_fin" class="form-control" value="<?= date('Y-m-d') ?>" style="height: 38px;">
                        </div>
                    </div>
                </div>

                <div class="border-top pt-3">
                    <label class="small font-weight-bold text-dark mb-2 d-block">Selecciona el Formato de Salida:</label>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                        <button type="button" class="btn btn-primary btn-sm py-2 font-weight-bold text-truncate" onclick="ejecutarReporteIndividual('pantalla')" title="Ver en Pantalla">
                            <i class="fa-solid fa-desktop mr-1"></i> Ver en Pantalla
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm py-2 font-weight-bold text-truncate" onclick="ejecutarReporteIndividual('pdf')" title="Imprimir / PDF">
                            <i class="fa-solid fa-print mr-1 text-primary"></i> Imprimir / PDF
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm py-2 font-weight-bold text-truncate" onclick="ejecutarReporteIndividual('excel')" title="Descargar Excel">
                            <i class="fa-solid fa-file-excel mr-1 text-success"></i> Descargar Excel
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-end py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php require_once APP_ROOT . '/views/layout/footer.php'; ?>
