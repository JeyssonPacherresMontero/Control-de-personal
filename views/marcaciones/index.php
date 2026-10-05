<?php 
require_once APP_ROOT . '/views/layout/header.php'; 

$kpiTotalMarcaciones = (int)($kpis['total'] ?? count($marcaciones));
$kpiEntradas = (int)($kpis['entradas'] ?? 0);
$kpiSalidas = (int)($kpis['salidas'] ?? 0);
$kpiRefrigerios = (int)($kpis['refrigerios'] ?? 0);
$kpiOtros = (int)($kpis['otros'] ?? 0);
$kpiProcesados = (int)($kpis['procesados'] ?? 0);
$kpiPctProcesado = $kpiTotalMarcaciones > 0 ? round(($kpiProcesados / $kpiTotalMarcaciones) * 100, 1) : 0;

$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$perPage = $perPage ?? 250;

$dispNombreFiltro = 'Todos los Relojes Biométricos';
if (!empty($dispositivoId)) {
    foreach ($dispositivos as $d) {
        if ($d['id'] == $dispositivoId) {
            $dispNombreFiltro = $d['nombre'] . ' (' . $d['ip'] . ')';
            break;
        }
    }
}
?>

<!-- Content Header (Page header) -->
<div class="content-header no-print pb-2">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-sm-6">
                <h1 class="m-0 font-weight-bold text-dark" style="font-size: 1.45rem;">
                    <i class="fa-solid fa-clock-rotate-left mr-2 text-primary"></i> Registro de Marcaciones de Relojes Biométricos
                </h1>
                <div class="text-muted small mt-1">Auditoría y trazabilidad de eventos crudos registrados en terminales biométricas.</div>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right mb-0">
                    <li class="breadcrumb-item"><a href="?route=dashboard">Inicio</a></li>
                    <li class="breadcrumb-item active">Marcaciones</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">

        <!-- MEMBRETE OFICIAL DE IMPRESIÓN Y PDF (Solo visible al imprimir o exportar a PDF) -->
        <div class="print-only mb-3">
            <table style="width: 100%; border-collapse: collapse; border-bottom: 2px solid #0f766e; padding-bottom: 8px;">
                <tr>
                    <td style="width: 120px; vertical-align: middle; text-align: center; padding-right: 15px;">
                        <img src="<?= jushsal_logo_data_uri('full') ?: asset('img/logo_jushsal.png') ?>" alt="JUSHSAL" style="max-height: 60px; max-width: 110px; object-fit: contain;">
                    </td>
                    <td style="vertical-align: middle;">
                        <div style="font-size: 13pt; font-weight: 800; color: #0f766e; text-transform: uppercase;">JUNTA DE USUARIOS DEL SECTOR HIDRÁULICO MENOR SAN LORENZO (JUSHSAL)</div>
                        <div style="font-size: 9pt; color: #475569; font-weight: 600;">SISTEMA INTEGRADO DE CONTROL DE PERSONAL Y ASISTENCIA LABORAL</div>
                        <div style="font-size: 11pt; font-weight: 700; color: #0f172a; margin-top: 4px;">REGISTRO OFICIAL DE MARCACIONES DE RELOJES BIOMÉTRICOS</div>
                    </td>
                    <td style="width: 200px; text-align: right; vertical-align: middle; font-size: 8pt; color: #64748b;">
                        <div><b>Período:</b> <?= date('d/m/Y', strtotime($fechaInicio)) ?> al <?= date('d/m/Y', strtotime($fechaFin)) ?></div>
                        <div><b>Reloj:</b> <?= htmlspecialchars($dispNombreFiltro) ?></div>
                        <div><b>Emisión:</b> <?= date('d/m/Y H:i:s') ?></div>
                        <div><b>Usuario:</b> <?= htmlspecialchars($currentUser['nombre'] ?? 'Administrador') ?></div>
                    </td>
                </tr>
            </table>

            <!-- Resumen de Marcaciones para Impresión -->
            <table class="table table-sm table-bordered mt-2 mb-2" style="font-size: 8pt; text-align: center;">
                <thead style="background-color: #f1f5f9;">
                    <tr>
                        <th>Total Marcaciones</th>
                        <th>Entradas</th>
                        <th>Salidas</th>
                        <th>Refrigerios</th>
                        <th>Otras</th>
                        <th>Procesadas en Asistencia</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><b><?= $kpiTotalMarcaciones ?></b></td>
                        <td style="color: #166534;"><b><?= $kpiEntradas ?></b></td>
                        <td style="color: #3730a3;"><b><?= $kpiSalidas ?></b></td>
                        <td style="color: #075985;"><b><?= $kpiRefrigerios ?></b></td>
                        <td><b><?= $kpiOtros ?></b></td>
                        <td style="color: #15803d;"><b><?= $kpiProcesados ?> (<?= $kpiPctProcesado ?>%)</b></td>
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
                            <div class="kpi-title">Total Marcaciones</div>
                            <div class="kpi-value"><?= $kpiTotalMarcaciones ?></div>
                            <div class="kpi-subtitle">Registros capturados en el período</div>
                        </div>
                        <div class="kpi-icon-box">
                            <i class="fa-solid fa-fingerprint"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                <div class="kpi-card h-100">
                    <div class="kpi-card-header">
                        <div>
                            <div class="kpi-title">Entradas Registradas</div>
                            <div class="kpi-value"><?= $kpiEntradas ?></div>
                            <div class="kpi-subtitle">Marcaciones de inicio de jornada</div>
                        </div>
                        <div class="kpi-icon-box">
                            <i class="fa-solid fa-arrow-right-to-bracket"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                <div class="kpi-card h-100">
                    <div class="kpi-card-header">
                        <div>
                            <div class="kpi-title">Salidas Registradas</div>
                            <div class="kpi-value"><?= $kpiSalidas ?></div>
                            <div class="kpi-subtitle">Marcaciones de fin de jornada</div>
                        </div>
                        <div class="kpi-icon-box">
                            <i class="fa-solid fa-arrow-right-from-bracket"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                <div class="kpi-card h-100">
                    <div class="kpi-card-header">
                        <div>
                            <div class="kpi-title">Procesadas en Asistencia</div>
                            <div class="kpi-value"><?= $kpiProcesados ?> <span style="font-size: 0.95rem; color: #64748b; font-weight: 600;">(<?= $kpiPctProcesado ?>%)</span></div>
                            <div class="kpi-subtitle">Eventos procesados por el motor</div>
                        </div>
                        <div class="kpi-icon-box">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ACTIONS TOOLBAR -->
        <div class="actions-toolbar no-print mb-3">
            <div class="actions-toolbar-group">
                <span class="font-weight-bold text-dark" style="font-size: 0.95rem;">
                    <i class="fa-solid fa-fingerprint mr-2 text-primary"></i> Operaciones y Auditoría de Marcaciones
                </span>
            </div>
            <div class="actions-toolbar-group flex-wrap">
                <?php if (in_array($userRole, ['ADMIN', 'RRHH'], true)): ?>
                    <div class="dropdown d-inline">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" id="syncRelojDropdownMarc" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Sincronizar marcaciones directamente desde el reloj">
                            <i class="fa-solid fa-fingerprint mr-1 text-primary"></i> Sincronizar Reloj
                        </button>
                        <div class="dropdown-menu dropdown-menu-right shadow-sm border-0" aria-labelledby="syncRelojDropdownMarc">
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

                    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalNuevaMarcacion">
                        <i class="fa-solid fa-plus mr-1"></i> Registrar Marcación
                    </button>
                <?php endif; ?>

                <?php if (in_array($userRole, ['ADMIN', 'RRHH', 'SUPERVISOR', 'ASISTENTE'], true) || AuthController::hasPermission('asistencia')): ?>
                    <form method="POST" action="?route=asistencia&action=recalcular" class="d-inline" onsubmit="return confirm('¿Deseas recalcular y consolidar la asistencia laboral para el período del <?= date('d/m/Y', strtotime($fechaInicio)) ?> al <?= date('d/m/Y', strtotime($fechaFin)) ?>?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="fecha_inicio" value="<?= htmlspecialchars($fechaInicio) ?>">
                        <input type="hidden" name="fecha_fin" value="<?= htmlspecialchars($fechaFin) ?>">
                        <button type="submit" class="btn btn-outline-secondary btn-sm" title="Recalcular asistencia para el período filtrado">
                            <i class="fa-solid fa-calculator mr-1"></i> Recalcular Asistencia
                        </button>
                    </form>
                <?php endif; ?>

                <div class="btn-group btn-group-sm">
                    <a href="?route=marcaciones&fecha_inicio=<?= urlencode($fechaInicio) ?>&fecha_fin=<?= urlencode($fechaFin) ?>&departamento_id=<?= urlencode((string)($departamentoId ?? '')) ?>&dispositivo_id=<?= urlencode((string)($dispositivoId ?? '')) ?>&tipo=<?= urlencode((string)($tipo ?? '')) ?>&search=<?= urlencode($search ?? '') ?>&export=excel" class="btn btn-outline-secondary" title="Descargar reporte en Excel">
                        <i class="fa-solid fa-file-excel mr-1 text-success"></i> Excel
                    </a>
                    <button type="button" class="btn btn-outline-secondary" onclick="window.print()" title="Imprimir reporte oficial o Guardar como PDF">
                        <i class="fa-solid fa-print mr-1 text-secondary"></i> PDF
                    </button>
                </div>
            </div>
        </div>

        <!-- FILTER CARD -->
        <div class="card mb-4 no-print">
            <div class="card-header py-2 px-3">
                <h3 class="card-title font-weight-bold text-dark mb-0 d-flex align-items-center" style="font-size: 0.92rem;">
                    <i class="fa-solid fa-filter mr-2 text-primary"></i> Filtros de Auditoría de Marcaciones
                </h3>
            </div>
            <div class="card-body p-4">
                <form method="GET" action="" class="row align-items-end">
                    <input type="hidden" name="route" value="marcaciones">

                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <label class="form-label-custom"><i class="fa-regular fa-calendar"></i> Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" class="form-control" value="<?= htmlspecialchars($fechaInicio) ?>" required>
                    </div>

                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <label class="form-label-custom"><i class="fa-regular fa-calendar-check"></i> Fecha Fin</label>
                        <input type="date" name="fecha_fin" class="form-control" value="<?= htmlspecialchars($fechaFin) ?>" required>
                    </div>

                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <label class="form-label-custom"><i class="fa-solid fa-building"></i> Área / Dpto.</label>
                        <select name="departamento_id" class="form-control" <?= ($userRole === 'SUPERVISOR') ? 'disabled' : '' ?>>
                            <?php if ($userRole !== 'SUPERVISOR'): ?>
                                <option value="">-- Todas las Áreas --</option>
                            <?php endif; ?>
                            <?php foreach ($departamentos as $dep): ?>
                                <option value="<?= $dep['id'] ?>" <?= ($departamentoId ?? '') == $dep['id'] ? 'selected' : '' ?>><?= htmlspecialchars($dep['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($userRole === 'SUPERVISOR' && !empty($departamentoId)): ?>
                            <input type="hidden" name="departamento_id" value="<?= htmlspecialchars((string)$departamentoId) ?>">
                        <?php endif; ?>
                    </div>

                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <label class="form-label-custom"><i class="fa-solid fa-network-wired"></i> Reloj Biométrico</label>
                        <select name="dispositivo_id" class="form-control">
                            <option value="">-- Todos los Relojes --</option>
                            <?php foreach ($dispositivos as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= $dispositivoId == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['nombre']) ?> (<?= htmlspecialchars($d['ip']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <label class="form-label-custom"><i class="fa-solid fa-list-check"></i> Tipo Marcación</label>
                        <select name="tipo" class="form-control">
                            <option value="">-- Todas las Marcaciones --</option>
                            <option value="entrada" <?= ($tipo ?? '') === 'entrada' ? 'selected' : '' ?>>Entrada (Inicio Jornada)</option>
                            <option value="salida" <?= ($tipo ?? '') === 'salida' ? 'selected' : '' ?>>Salida (Fin Jornada)</option>
                            <option value="refrigerio_salida" <?= ($tipo ?? '') === 'refrigerio_salida' ? 'selected' : '' ?>>Salida a Refrigerio (~13:00)</option>
                            <option value="refrigerio_entrada" <?= ($tipo ?? '') === 'refrigerio_entrada' ? 'selected' : '' ?>>Retorno de Refrigerio (~13:45)</option>
                            <option value="refrigerio" <?= ($tipo ?? '') === 'refrigerio' ? 'selected' : '' ?>>Todos los Refrigerios</option>
                            <option value="comision_servicio" <?= ($tipo ?? '') === 'comision_servicio' ? 'selected' : '' ?>>Comisiones de Servicio</option>
                            <option value="vacaciones" <?= ($tipo ?? '') === 'vacaciones' ? 'selected' : '' ?>>Vacaciones Oficiales</option>
                            <option value="sin_vincular" <?= ($tipo ?? '') === 'sin_vincular' ? 'selected' : '' ?>>Sin Vincular a Empleado</option>
                            <option value="desconocido" <?= ($tipo ?? '') === 'desconocido' ? 'selected' : '' ?>>Otras / Desconocidas</option>
                        </select>
                    </div>

                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <label class="form-label-custom"><i class="fa-solid fa-magnifying-glass"></i> Buscar</label>
                        <div class="d-flex" style="gap: 6px;">
                            <input type="text" name="search" class="form-control" placeholder="DNI, nombre..." value="<?= htmlspecialchars($search ?? '') ?>">
                            <button type="submit" class="btn btn-primary" title="Filtrar resultados" style="height: 38px; width: 38px; padding: 0; flex-shrink: 0;">
                                <i class="fa-solid fa-filter"></i>
                            </button>
                            <a href="?route=marcaciones" class="btn btn-outline-secondary" title="Limpiar filtros" style="height: 38px; width: 38px; display: inline-flex; align-items: center; justify-content: center; padding: 0; flex-shrink: 0;">
                                <i class="fa-solid fa-rotate-left"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- MAIN TABLE CARD -->
        <div class="card">
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover table-sm table-marcaciones-fit mb-0">
                    <thead>
                        <tr>
                            <th class="text-center cell-nowrap" style="width: 55px;">N° Reg</th>
                            <th class="text-center cell-nowrap" style="width: 90px;">Fecha</th>
                            <th class="text-center cell-nowrap" style="width: 80px;">Hora</th>
                            <th class="text-center cell-nowrap" style="width: 85px;">ID Reloj</th>
                            <th style="min-width: 160px;">Empleado Identificado</th>
                            <th style="min-width: 120px;">Área / Dpto.</th>
                            <th style="min-width: 130px;">Reloj Biométrico</th>
                            <th class="text-center cell-nowrap" style="width: 100px;">Tipo</th>
                            <th class="text-center cell-nowrap" style="width: 115px;">Verificación</th>
                            <th class="text-center cell-nowrap" style="width: 85px;">Estado</th>
                            <th class="text-center cell-nowrap no-print" style="width: 80px;">Auditoría</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($marcaciones)): ?>
                            <tr>
                                <td colspan="11" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-folder-open fa-2x mb-2 d-block text-secondary"></i>
                                    <div>No se encontraron marcaciones para los filtros seleccionados.</div>
                                    <a href="?route=marcaciones" class="btn btn-xs btn-outline-primary mt-2">
                                        <i class="fa-solid fa-rotate-left mr-1"></i> Reestablecer filtros
                                    </a>
                                </td>
                            </tr>
                        <?php else: ?>
                        <?php foreach ($marcaciones as $m): ?>
                            <?php
                                $fechaRaw = !empty($m['fecha_hora']) ? substr($m['fecha_hora'], 0, 10) : '';
                                $horaRaw  = !empty($m['fecha_hora']) ? substr($m['fecha_hora'], 11, 8) : '--:--:--';
                                $fechaFmt = $fechaRaw ? date('d/m/Y', strtotime($fechaRaw)) : '--';
                            ?>
                            <tr>
                                <td class="text-center text-muted font-monospace small cell-nowrap">#<?= $m['id'] ?></td>
                                <td class="text-center font-monospace small text-dark font-weight-bold cell-nowrap">
                                    <i class="fa-regular fa-calendar mr-1 text-muted"></i><?= $fechaFmt ?>
                                </td>
                                <td class="text-center font-monospace small text-primary font-weight-bold cell-nowrap">
                                    <i class="fa-regular fa-clock mr-1 text-secondary"></i><?= $horaRaw ?>
                                </td>
                                <td class="text-center cell-nowrap"><span class="badge-pill-custom badge-pill-neutral">ID: <?= htmlspecialchars($m['codigo_reloj']) ?></span></td>
                                <td>
                                    <?php if (!empty($m['nombres'])): ?>
                                        <div class="font-weight-bold text-dark" style="line-height: 1.25;"><?= htmlspecialchars($m['apellidos'] . ' ' . $m['nombres']) ?></div>
                                        <small class="text-muted">DNI: <?= htmlspecialchars($m['dni']) ?></small>
                                    <?php else: ?>
                                        <span class="badge-pill-custom badge-pill-tardanza"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Sin vincular</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($m['departamento_nombre'])): ?>
                                        <span class="badge-pill-custom badge-pill-neutral"><i class="fa-solid fa-building-user mr-1 text-secondary"></i><?= htmlspecialchars($m['departamento_nombre']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">Sin asignar</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="text-dark font-weight-bold" style="line-height: 1.25;"><?= htmlspecialchars($m['dispositivo_nombre'] ?? 'Desconocido') ?></div>
                                    <small class="text-muted font-monospace"><?= htmlspecialchars($m['dispositivo_ip'] ?? '') ?></small>
                                </td>
                                <td class="text-center cell-nowrap">
                                    <?php
                                        $t = strtolower($m['tipo'] ?? '');
                                        $v = strtoupper($m['tipo_verificacion'] ?? '');

                                        if ($v === 'COMISION_SERVICIO' || str_contains($t, 'comision')) {
                                            echo '<span class="badge-pill-custom badge-pill-justificado"><i class="fa-solid fa-briefcase mr-1"></i> Comisión</span>';
                                        } elseif ($v === 'VACACIONES' || str_contains($t, 'vacacion')) {
                                            echo '<span class="badge-pill-custom" style="background:#dcfce7; color:#166534;"><i class="fa-solid fa-umbrella-beach mr-1"></i> Vacaciones</span>';
                                        } elseif ($t === 'entrada') {
                                            echo '<span class="badge-pill-custom badge-pill-presente"><i class="fa-solid fa-arrow-right-to-bracket mr-1"></i> Entrada</span>';
                                        } elseif ($t === 'salida') {
                                            echo '<span class="badge-pill-custom badge-pill-justificado"><i class="fa-solid fa-arrow-right-from-bracket mr-1"></i> Salida</span>';
                                        } elseif ($t === 'refrigerio_salida') {
                                            echo '<span class="badge-pill-custom" style="background:#fef3c7; color:#92400e; border:1px solid #fde68a;"><i class="fa-solid fa-utensils mr-1"></i> Salida Ref.</span>';
                                        } elseif ($t === 'refrigerio_entrada') {
                                            echo '<span class="badge-pill-custom" style="background:#e0e7ff; color:#3730a3; border:1px solid #c7d2fe;"><i class="fa-solid fa-clock-rotate-left mr-1"></i> Retorno Ref.</span>';
                                        } elseif (str_contains($t, 'refrigerio')) {
                                            echo '<span class="badge-pill-custom badge-pill-neutral"><i class="fa-solid fa-utensils mr-1"></i> Refrigerio</span>';
                                        } else {
                                            echo '<span class="badge-pill-custom badge-pill-neutral">Marcación</span>';
                                        }
                                    ?>
                                </td>
                                <td class="text-center cell-nowrap">
                                    <span class="small">
                                    <?php
                                        $verif = strtolower($m['tipo_verificacion'] ?? '');
                                        if (str_contains($verif, 'comision')) {
                                            echo '<span class="badge-pill-custom badge-pill-justificado"><i class="fa-solid fa-briefcase mr-1"></i> Comisión Oficial</span>';
                                        } elseif (str_contains($verif, 'vacacion')) {
                                            echo '<span class="badge-pill-custom" style="background:#dcfce7; color:#166534;"><i class="fa-solid fa-umbrella-beach mr-1"></i> Vacaciones Oficial</span>';
                                        } elseif (str_contains($verif, 'edicion_admin')) {
                                            echo '<span class="badge-pill-custom badge-pill-neutral text-warning font-weight-bold"><i class="fa-solid fa-pen-to-square mr-1"></i> Edición Admin</span>';
                                        } elseif (str_contains($verif, 'manual_admin')) {
                                            echo '<span class="badge-pill-custom badge-pill-neutral text-danger font-weight-bold"><i class="fa-solid fa-user-shield mr-1"></i> Manual Admin</span>';
                                        } elseif (str_contains($verif, 'manual_rrhh')) {
                                            echo '<span class="badge-pill-custom badge-pill-neutral text-info font-weight-bold"><i class="fa-solid fa-user-pen mr-1"></i> Manual RRHH</span>';
                                        } elseif (str_contains($verif, 'huella') || $verif === 'fingerprint') {
                                            echo '<i class="fa-solid fa-fingerprint text-primary mr-1"></i> Huella';
                                        } elseif (str_contains($verif, 'facial') || str_contains($verif, 'face')) {
                                            echo '<i class="fa-solid fa-camera text-info mr-1"></i> Facial';
                                        } elseif (str_contains($verif, 'tarjeta') || str_contains($verif, 'card') || str_contains($verif, 'rfid')) {
                                            echo '<i class="fa-solid fa-id-card text-success mr-1"></i> Tarjeta';
                                        } elseif (str_contains($verif, 'manual')) {
                                            echo '<i class="fa-solid fa-keyboard text-secondary mr-1"></i> Manual';
                                        } elseif (str_contains($verif, 'clave') || str_contains($verif, 'pin') || str_contains($verif, 'password')) {
                                            echo '<i class="fa-solid fa-key text-warning mr-1"></i> PIN';
                                        } else {
                                            echo '<i class="fa-solid fa-check text-muted mr-1"></i> ' . htmlspecialchars($m['tipo_verificacion'] ?: 'Biométrico');
                                        }
                                    ?>
                                    </span>
                                </td>
                                <td class="text-center cell-nowrap">
                                    <?php if ($m['procesado']): ?>
                                        <span class="badge-pill-custom badge-pill-presente"><i class="fa-solid fa-circle-check mr-1"></i> Procesado</span>
                                    <?php else: ?>
                                        <span class="badge-pill-custom badge-pill-tardanza"><i class="fa-solid fa-clock mr-1"></i> Pendiente</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center cell-nowrap no-print">
                                    <?php if (!empty($m['id_empleado'])): ?>
                                        <button type="button" class="btn btn-xs btn-outline-info" 
                                                onclick="openTimelineModal(<?= (int)$m['id_empleado'] ?>, '<?= $fechaRaw ?>', '<?= htmlspecialchars(addslashes(($m['apellidos'] ?? '') . ' ' . ($m['nombres'] ?? ''))) ?>')"
                                                title="Ver bitácora de eventos y auditoría de este día">
                                            <i class="fa-solid fa-timeline mr-1"></i> Eventos
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="thead-light">
                        <tr class="font-weight-bold">
                            <th colspan="10" class="text-right">TOTAL DE MARCACIONES EN EL PERÍODO:</th>
                            <th class="text-center text-success"><?= $kpiTotalMarcaciones ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white border-top py-2 px-3 d-flex flex-wrap justify-content-between align-items-center no-print">
                <div class="small text-muted mb-2 mb-md-0">
                    Mostrando página <b><?= $page ?></b> de <b><?= $totalPages ?></b> (<b><?= number_format($kpiTotalMarcaciones) ?></b> marcaciones en total)
                </div>
                <nav aria-label="Paginación de marcaciones">
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
                            <div style="font-weight: 700; font-size: 8.5pt; color: #0f172a;">RESPONSABLE DE CONTROL DE ASISTENCIA</div>
                            <div style="font-size: 7.5pt; color: #64748b;">Recursos Humanos - JUSHSAL</div>
                        </div>
                    </td>
                    <td style="width: 50%; padding-top: 50px; border: none;">
                        <div style="display: inline-block; width: 260px; border-top: 1.5px solid #334155; padding-top: 6px;">
                            <div style="font-weight: 700; font-size: 8.5pt; color: #0f172a;">RESPONSABLE DE TI & SISTEMAS</div>
                            <div style="font-size: 7.5pt; color: #64748b;">Auditoría de Dispositivos Biométricos</div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

    </div>
</section>

<?php if (in_array($userRole, ['ADMIN', 'RRHH'], true)): ?>
<!-- MODAL NUEVA MARCACION MANUAL -->
<div class="modal fade" id="modalNuevaMarcacion" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 580px;">
        <form method="POST" action="?route=marcaciones&action=guardar_manual" class="modal-content shadow-lg border-0">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold"><i class="fa-solid fa-fingerprint mr-2"></i> Registrar Marcación Manual</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body p-4">
                <div class="form-group mb-3">
                    <label class="small font-weight-bold text-secondary">Empleado <span class="text-danger">*</span></label>
                    <select name="id_empleado" class="form-control select2-worker" style="width: 100%;" required>
                        <option value="">-- Seleccionar Empleado --</option>
                        <?php foreach ($empleados as $e): ?>
                            <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['apellidos'] . ' ' . $e['nombres']) ?> (ID Reloj: <?= htmlspecialchars($e['codigo_reloj']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-secondary">Fecha y Hora de Marcación <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="fecha_hora" class="form-control" value="<?= date('Y-m-d\TH:i') ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-secondary">Tipo de Marcación <span class="text-danger">*</span></label>
                            <select name="tipo" class="form-control font-weight-bold" required>
                                <option value="entrada">Entrada Jornada (~08:00)</option>
                                <option value="refrigerio_salida">Salida a Refrigerio (~13:00)</option>
                                <option value="refrigerio_entrada">Retorno de Refrigerio (~13:45)</option>
                                <option value="salida">Salida Final (~17:00)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label class="small font-weight-bold text-secondary">Motivo / Justificación <span class="text-danger">*</span></label>
                    <textarea name="motivo" class="form-control" rows="2" placeholder="Motivo de la marcación manual (ej. Regularización oficial, auditoría, etc.)" required></textarea>
                </div>

                <div class="form-group mb-0">
                    <label class="small font-weight-bold text-secondary">Dispositivo Asociado</label>
                    <select name="id_dispositivo" class="form-control">
                        <?php foreach ($dispositivos as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nombre']) ?> (<?= htmlspecialchars($d['ip']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm px-4 font-weight-bold"><i class="fa-solid fa-floppy-disk mr-1"></i> Guardar Marcación</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- MODAL EVENT SOURCING: LÍNEA DE TIEMPO DE AUDITORÍA Y TRAZABILIDAD -->
<div class="modal fade" id="modalTimelineEventos" tabindex="-1" role="dialog" aria-labelledby="timelineTitle" aria-modal="true" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
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
                        <span class="badge-pill-custom badge-pill-neutral font-weight-bold" id="timelineFecha">--</span>
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
                    <div>No se encontraron eventos de auditoría registrados para este día.</div>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <span class="small text-muted"><i class="fa-solid fa-shield-halved mr-1 text-secondary"></i> Log inmutable respaldado por arquitectura Event Sourcing</span>
                <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    if ($.fn.select2) {
        $('#modalNuevaMarcacion select[name="id_empleado"]').select2({
            theme: 'bootstrap4',
            dropdownParent: $('#modalNuevaMarcacion'),
            placeholder: '-- Seleccionar Empleado --',
            width: '100%'
        });
    }
});

function openTimelineModal(empId, fecha, nombre) {
    if (!empId || !fecha) return;

    document.getElementById('timelineFecha').innerText = fecha;
    document.getElementById('timelineEmpleadoNombre').innerText = nombre || 'Empleado';
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
            if (emp.nombres || emp.apellidos) {
                document.getElementById('timelineEmpleadoNombre').innerText = `${emp.apellidos || ''} ${emp.nombres || ''}`;
            }
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
</script>

<?php require_once APP_ROOT . '/views/layout/footer.php'; ?>
