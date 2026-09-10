<?php 
require_once APP_ROOT . '/views/layout/header.php'; 

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
                    </td>
                    <td style="width: 200px; text-align: right; vertical-align: middle; font-size: 8pt; color: #64748b;">
                        <div><b>Período:</b> <?= date('d/m/Y', strtotime($fechaInicio)) ?> al <?= date('d/m/Y', strtotime($fechaFin)) ?></div>
                        <div><b>Área:</b> <?= htmlspecialchars($deptoNombreFiltro) ?></div>
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
                        <th>Horas Extras</th>
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
                        <td style="color: #0369a1;"><b><?= $kpiHorasExt ?></b></td>
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
                            <div class="kpi-value text-dark"><?= $kpiTotal ?></div>
                            <div class="kpi-subtitle">Puntualidad Global: <b><?= $kpiPuntualidad ?>%</b></div>
                        </div>
                        <div class="kpi-icon-box kpi-icon-blue">
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
                            <div class="kpi-value text-success"><?= $kpiPresentes ?></div>
                            <div class="kpi-subtitle">Ingresos dentro de tolerancia</div>
                        </div>
                        <div class="kpi-icon-box kpi-icon-emerald">
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
                            <div class="kpi-value text-warning"><?= $kpiTardanzas ?> <span style="font-size: 0.95rem; color: #64748b; font-weight: 600;">(<?= $kpiHorasTard ?>)</span></div>
                            <div class="kpi-subtitle">Minutos fuera de tolerancia</div>
                        </div>
                        <div class="kpi-icon-box kpi-icon-amber">
                            <i class="fa-solid fa-clock"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                <div class="kpi-card h-100">
                    <div class="kpi-card-header">
                        <div>
                            <div class="kpi-title">Faltas y Horas Extras</div>
                            <div class="kpi-value text-danger"><?= $kpiFaltas ?> <span style="font-size: 0.95rem; color: #0284c7; font-weight: 600;">| +<?= $kpiHorasExt ?></span></div>
                            <div class="kpi-subtitle">Inasistencias e incidencias</div>
                        </div>
                        <div class="kpi-icon-box kpi-icon-rose">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- FILTER AND ACTIONS CARD -->
        <div class="card mb-3 no-print">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap py-2 px-3">
                <h3 class="card-title font-weight-bold text-dark mb-0 d-flex align-items-center" style="font-size: 0.92rem;">
                    <i class="fa-solid fa-filter mr-2 text-primary"></i> Filtros de Asistencia
                </h3>
                <div class="card-tools d-flex align-items-center flex-wrap my-1" style="gap: 6px;">
                    <?php if ($userRole === 'ADMIN'): ?>
                        <button type="button" class="btn btn-primary btn-sm" onclick="openAsignarHorasModal()" title="Asignar u oficializar horas de entrada y salida">
                            <i class="fa-solid fa-clock-medical mr-1"></i> Asignar Horas
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="openJustificarAdminModal()" title="Registrar justificación oficial">
                            <i class="fa-solid fa-user-shield mr-1"></i> Justificar
                        </button>
                    <?php endif; ?>

                    <?php if (in_array($userRole, ['ADMIN', 'RRHH'], true)): ?>
                        <form method="POST" action="?route=asistencia&action=recalcular" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="fecha_inicio" value="<?= htmlspecialchars($fechaInicio) ?>">
                            <input type="hidden" name="fecha_fin" value="<?= htmlspecialchars($fechaFin) ?>">
                            <button type="submit" class="btn btn-outline-secondary btn-sm" onclick="return confirm('¿Deseas recalcular la asistencia en este rango de fechas?')">
                                <i class="fa-solid fa-calculator mr-1"></i> Recalcular
                            </button>
                        </form>
                    <?php endif; ?>

                    <div class="btn-group btn-group-sm ml-md-1">
                        <a href="?route=asistencia&fecha_inicio=<?= $fechaInicio ?>&fecha_fin=<?= $fechaFin ?>&departamento_id=<?= $deptoId ?>&estado=<?= $estado ?>&search=<?= urlencode($search ?? '') ?>&export=excel" class="btn btn-success btn-sm" title="Descargar reporte oficial en Excel">
                            <i class="fa-solid fa-file-excel mr-1"></i> Excel
                        </a>
                        <a href="?route=asistencia&fecha_inicio=<?= $fechaInicio ?>&fecha_fin=<?= $fechaFin ?>&departamento_id=<?= $deptoId ?>&estado=<?= $estado ?>&search=<?= urlencode($search ?? '') ?>&export=csv" class="btn btn-outline-secondary btn-sm" title="Descargar archivo CSV">
                            <i class="fa-solid fa-file-csv mr-1"></i> CSV
                        </a>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()" title="Imprimir reporte oficial o Guardar como PDF">
                            <i class="fa-solid fa-print mr-1"></i> PDF
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body py-3 px-3">
                <form method="GET" action="" class="row align-items-end">
                    <input type="hidden" name="route" value="asistencia">

                    <div class="col-md-2 col-sm-6 mb-2">
                        <label class="form-label-custom"><i class="fa-regular fa-calendar mr-1"></i> Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" class="form-control form-control-sm" value="<?= htmlspecialchars($fechaInicio) ?>" required>
                    </div>

                    <div class="col-md-2 col-sm-6 mb-2">
                        <label class="form-label-custom"><i class="fa-regular fa-calendar-check mr-1"></i> Fecha Fin</label>
                        <input type="date" name="fecha_fin" class="form-control form-control-sm" value="<?= htmlspecialchars($fechaFin) ?>" required>
                    </div>

                    <div class="col-md-3 col-sm-6 mb-2">
                        <label class="form-label-custom"><i class="fa-solid fa-building mr-1"></i> Departamento</label>
                        <select name="departamento_id" class="form-control form-control-sm">
                            <option value="">-- Todos los Departamentos --</option>
                            <?php foreach ($departamentos as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= $deptoId == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2 col-sm-6 mb-2">
                        <label class="form-label-custom"><i class="fa-solid fa-tag mr-1"></i> Estado</label>
                        <select name="estado" class="form-control form-control-sm">
                            <option value="">-- Todos los Estados --</option>
                            <option value="PRESENTE" <?= ($estado ?? '') === 'PRESENTE' ? 'selected' : '' ?>>Puntuales (Presente)</option>
                            <option value="TARDANZA" <?= ($estado ?? '') === 'TARDANZA' ? 'selected' : '' ?>>Tardanzas</option>
                            <option value="FALTA" <?= ($estado ?? '') === 'FALTA' ? 'selected' : '' ?>>Faltas e Inasistencias</option>
                            <option value="JUSTIFICADO" <?= ($estado ?? '') === 'JUSTIFICADO' ? 'selected' : '' ?>>Justificados</option>
                            <option value="SALIDA_SIN_MARCAR" <?= ($estado ?? '') === 'SALIDA_SIN_MARCAR' ? 'selected' : '' ?>>Salidas sin Marcar</option>
                            <option value="INCIDENCIAS" <?= ($estado ?? '') === 'INCIDENCIAS' ? 'selected' : '' ?>>Todas las Incidencias</option>
                        </select>
                    </div>

                    <div class="col-md-2 col-sm-8 mb-2">
                        <label class="form-label-custom"><i class="fa-solid fa-magnifying-glass mr-1"></i> Buscar Empleado</label>
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Nombre, DNI..." value="<?= htmlspecialchars($search ?? '') ?>">
                    </div>

                    <div class="col-md-1 col-sm-4 mb-2">
                        <button type="submit" class="btn btn-primary btn-sm btn-block" title="Filtrar resultados" style="height: 34px;">
                            <i class="fa-solid fa-filter mr-1"></i> Filtrar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MAIN TABLE CARD -->
        <div class="card">
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover text-nowrap table-sm">
                    <thead>
                        <tr>
                            <th class="text-center">Fecha</th>
                            <th>Empleado</th>
                            <th class="text-center">Turno</th>
                            <th>Entrada (Prog. - Real)</th>
                            <th>Salida (Prog. - Real)</th>
                            <th class="text-center">Tardanza</th>
                            <th class="text-center">Tiempo Trabajado</th>
                            <th class="text-center">Horas Extras</th>
                            <th class="text-center">Estado</th>
                            <?php if (in_array($userRole, ['ADMIN', 'RRHH'], true)): ?>
                                <th class="text-center no-print">Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($asistencias as $a): ?>
                            <tr>
                                <td class="text-center font-weight-bold text-dark font-monospace small"><?= $a['fecha'] ?></td>
                                <td>
                                    <div class="font-weight-bold text-dark"><?= htmlspecialchars($a['apellidos'] . ' ' . $a['nombres']) ?></div>
                                    <small class="text-muted">DNI: <?= htmlspecialchars($a['dni']) ?> &bull; ID Reloj: <?= htmlspecialchars($a['codigo_reloj']) ?></small>
                                </td>
                                <td class="text-center">
                                    <span class="badge-pill-custom badge-pill-neutral"><?= htmlspecialchars($a['turno_nombre'] ?? 'Sin Turno') ?></span>
                                </td>
                                <td>
                                    <div class="text-muted small"><i class="fa-regular fa-clock mr-1 text-secondary"></i>Prog.: <span class="font-monospace"><?= $a['hora_entrada_programada'] ? substr($a['hora_entrada_programada'], 0, 5) : '--:--' ?></span></div>
                                    <div class="d-flex align-items-center justify-content-between mt-1 pt-1 border-top" style="gap: 4px;">
                                        <div>
                                            <span class="text-muted small">Real:</span> 
                                            <span class="font-monospace small font-weight-bold <?= $a['minutos_tardanza'] > 0 ? 'text-danger' : ($a['hora_entrada_real'] ? 'text-success' : 'text-muted') ?>">
                                                <?= $a['hora_entrada_real'] ? substr($a['hora_entrada_real'], 11, 5) : '--:--' ?>
                                            </span>
                                        </div>
                                        <?php if ($userRole === 'ADMIN'): ?>
                                            <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2 shadow-sm font-weight-bold" 
                                                    onclick="openAdminEditModal(<?= htmlspecialchars(json_encode($a)) ?>, 'entrada')" 
                                                    title="Editar Hora de Entrada y Salida en la base de datos">
                                                <i class="fa-solid fa-pen-to-square mr-1"></i>Editar
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-muted small"><i class="fa-regular fa-clock mr-1 text-secondary"></i>Prog.: <span class="font-monospace"><?= $a['hora_salida_programada'] ? substr($a['hora_salida_programada'], 0, 5) : '--:--' ?></span></div>
                                    <div class="d-flex align-items-center justify-content-between mt-1 pt-1 border-top" style="gap: 4px;">
                                        <div>
                                            <span class="text-muted small">Real:</span> 
                                            <span class="font-monospace small font-weight-bold <?= empty($a['hora_salida_real']) ? 'text-muted' : 'text-dark' ?>">
                                                <?= $a['hora_salida_real'] ? substr($a['hora_salida_real'], 11, 5) : '--:--' ?>
                                            </span>
                                        </div>
                                        <?php if ($userRole === 'ADMIN'): ?>
                                            <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2 shadow-sm font-weight-bold" 
                                                    onclick="openAdminEditModal(<?= htmlspecialchars(json_encode($a)) ?>, 'salida')" 
                                                    title="Asignar o editar hora de salida en la base de datos">
                                                <i class="fa-solid fa-pen-to-square mr-1"></i>Editar
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <?php if ($a['minutos_tardanza'] > 0): ?>
                                        <span class="badge-pill-custom badge-pill-tardanza">+<?= $a['minutos_tardanza'] ?> min</span>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center font-monospace small">
                                    <?php
                                        $hrs = floor($a['minutos_trabajados'] / 60);
                                        $min = $a['minutos_trabajados'] % 60;
                                        echo "{$hrs}h {$min}m";
                                    ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($a['minutos_extra'] > 0): ?>
                                        <span class="badge-pill-custom badge-pill-justificado">+<?= $a['minutos_extra'] ?> min</span>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php
                                        $est = $a['estado'];
                                        $obs = $a['observaciones'] ?? '';
                                        if ($est === 'PRESENTE' && str_contains($obs, 'Jornada en curso')) {
                                            echo '<span class="badge-pill-custom badge-pill-presente"><i class="fa-solid fa-user-clock mr-1"></i>En Jornada</span>';
                                        } elseif ($est === 'PRESENTE') {
                                            echo '<span class="badge-pill-custom badge-pill-presente"><i class="fa-solid fa-check mr-1"></i>Presente</span>';
                                        } elseif ($est === 'TARDANZA') {
                                            echo '<span class="badge-pill-custom badge-pill-tardanza"><i class="fa-solid fa-clock mr-1"></i>Tardanza</span>';
                                        } elseif ($est === 'FALTA' || $est === 'FALTA_INJUSTIFICADA') {
                                            echo '<span class="badge-pill-custom badge-pill-falta"><i class="fa-solid fa-xmark mr-1"></i>Falta</span>';
                                        } elseif ($est === 'JUSTIFICADO') {
                                            echo '<span class="badge-pill-custom badge-pill-justificado"><i class="fa-solid fa-shield mr-1"></i>Justificado</span>';
                                        } elseif ($est === 'PERMISO') {
                                            echo '<span class="badge-pill-custom badge-pill-justificado"><i class="fa-solid fa-id-badge mr-1"></i>Permiso</span>';
                                        } elseif ($est === 'VACACIONES') {
                                            echo '<span class="badge-pill-custom badge-pill-justificado"><i class="fa-solid fa-umbrella-beach mr-1"></i>Vacaciones</span>';
                                        } elseif ($est === 'DESCANSO') {
                                            echo '<span class="badge-pill-custom badge-pill-neutral"><i class="fa-solid fa-bed mr-1"></i>Descanso</span>';
                                        } elseif ($est === 'SALIDA_SIN_MARCAR') {
                                            echo '<span class="badge-pill-custom badge-pill-sin-salida"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Sin Salida</span>';
                                        } else {
                                            echo '<span class="badge-pill-custom badge-pill-neutral">' . htmlspecialchars($est) . '</span>';
                                        }
                                    ?>
                                    <?php if (!empty($a['observaciones'])): ?>
                                        <div class="small text-muted mt-1" title="<?= htmlspecialchars($a['observaciones']) ?>">
                                            <i class="far fa-comment-dots mr-1"></i><?= htmlspecialchars(mb_strimwidth($a['observaciones'], 0, 24, '...')) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <?php if (in_array($userRole, ['ADMIN', 'RRHH'], true)): ?>
                                    <td class="text-center no-print">
                                        <button class="btn btn-xs btn-outline-info mr-1" onclick="openTimelineModal(<?= $a['id_empleado'] ?>, '<?= $a['fecha'] ?>', '<?= htmlspecialchars(addslashes($a['apellidos'] . ' ' . $a['nombres'])) ?>')" title="Ver Trazabilidad y Auditoría de Eventos">
                                            <i class="fa-solid fa-timeline"></i> Eventos
                                        </button>
                                        <?php if ($userRole === 'ADMIN'): ?>
                                            <button class="btn btn-xs btn-primary mr-1" onclick="openAdminEditModal(<?= htmlspecialchars(json_encode($a)) ?>)" title="Modificar horas de entrada/salida y corregir asistencia oficialmente (Solo Administrador)">
                                                <i class="fa-solid fa-clock-rotate-left"></i> Corregir
                                            </button>
                                            <button class="btn btn-xs btn-outline-warning" onclick="openQuickJustifyModal(<?= htmlspecialchars(json_encode($a)) ?>)" title="Registrar Justificación para este trabajador">
                                                <i class="fa-solid fa-file-shield"></i> Justificar
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="thead-light">
                        <tr class="font-weight-bold">
                            <th colspan="5" class="text-right">TOTALES GENERALES:</th>
                            <th class="text-center text-warning"><?= $kpiMinTardanza > 0 ? "+{$kpiMinTardanza} min" : "0 min" ?></th>
                            <th class="text-center text-dark"><?= $kpiHorasTrab ?></th>
                            <th class="text-center text-info"><?= $kpiMinExtra > 0 ? "+{$kpiMinExtra} min" : "0 min" ?></th>
                            <th colspan="<?= in_array($userRole, ['ADMIN', 'RRHH'], true) ? '2' : '1' ?>"><?= $kpiTotal ?> registros</th>
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

<?php if (in_array($userRole, ['ADMIN', 'RRHH'], true)): ?>
<!-- MODAL EVENT SOURCING: LÍNEA DE TIEMPO DE AUDITORÍA Y TRAZABILIDAD -->
<div class="modal fade" id="modalTimelineEventos" tabindex="-1" role="dialog" aria-labelledby="timelineTitle" aria-modal="true" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title font-weight-bold" id="timelineTitle">
                    <i class="fa-solid fa-timeline text-info mr-2"></i> Trazabilidad y Event Sourcing
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body p-4 bg-light">
                <!-- CABECERA DE METADATOS -->
                <div class="d-flex justify-content-between align-items-center bg-white p-3 rounded shadow-sm mb-4 border">
                    <div>
                        <h6 class="font-weight-bold mb-1 text-primary" id="timelineEmpleadoNombre">Cargando empleado...</h6>
                        <small class="text-muted"><i class="fa-solid fa-id-card mr-1"></i> DNI: <span id="timelineEmpleadoDni">--</span> | ID Reloj: <span id="timelineEmpleadoReloj">--</span></small>
                    </div>
                    <div class="text-right">
                        <span class="badge badge-light border px-2 py-1 font-weight-bold text-secondary" id="timelineFecha">--</span>
                        <div class="small text-muted mt-1" id="timelineTotalEventos">-- eventos registrados</div>
                    </div>
                </div>

                <!-- CONTENEDOR DE LA LÍNEA DE TIEMPO -->
                <div id="timelineLoading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
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
            <div class="modal-footer justify-content-between bg-white">
                <span class="small text-muted"><i class="fa-solid fa-shield-halved mr-1"></i> Log inmutable respaldado por arquitectura Event Sourcing</span>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
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
            
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title font-weight-bold d-flex align-items-center" id="modalEditarTitle">
                    <i class="fa-solid fa-clock-rotate-left mr-2"></i> Modificar Horario de Asistencia
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            
            <div class="modal-body p-4 bg-light">
                <!-- TARJETA INFORMATIVA DEL REGISTRO SELECCIONADO DESDE TABLA -->
                <div class="card mb-3 border bg-white shadow-none" id="edit_empleado_card" style="border-radius: 8px;">
                    <div class="card-body p-3">
                        <div class="row align-items-center">
                            <div class="col-md-7">
                                <span class="badge badge-primary px-2 py-1 mb-1 font-weight-bold" id="edit_badge_fecha">Fecha: --/--/----</span>
                                <h6 class="font-weight-bold text-dark mb-1" id="edit_empleado_nombre">Empleado...</h6>
                                <small class="text-muted"><i class="fa-solid fa-id-card mr-1"></i> DNI: <span id="edit_empleado_dni">--</span> | Cód. Reloj: <span id="edit_empleado_reloj">--</span></small>
                            </div>
                            <div class="col-md-5 text-md-right border-left pt-2 pt-md-0">
                                <div class="small text-muted">Horario Oficial del Turno:</div>
                                <strong class="text-primary font-monospace" id="edit_turno_info">08:00 - 17:00 (Tol: 10m)</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SELECTOR DE EMPLEADO Y FECHA CUANDO SE ABRE DESDE BOTÓN SUPERIOR -->
                <div class="card mb-3 border bg-white shadow-none" id="edit_empleado_selector_card" style="border-radius: 8px; display: none;">
                    <div class="card-header py-2 bg-white border-bottom">
                        <span class="font-weight-bold text-dark small text-uppercase"><i class="fa-solid fa-user-plus text-primary mr-1"></i> Seleccionar Empleado y Fecha</span>
                    </div>
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-md-8 mb-2">
                                <label class="small font-weight-bold text-secondary mb-1">Buscar y Seleccionar Trabajador <span class="text-danger">*</span></label>
                                <input type="text" id="edit_worker_search" class="form-control form-control-sm mb-1" placeholder="Filtrar por nombre, DNI o reloj..." oninput="filterWorkerSelect(this.value)">
                                <select id="edit_worker_select" class="form-control form-control-sm font-weight-bold" size="3" onchange="onWorkerSelected(this)">
                                    <?php foreach ($empleados as $emp): ?>
                                        <option value="<?= $emp['id'] ?>" 
                                                data-nombres="<?= htmlspecialchars($emp['nombres']) ?>"
                                                data-apellidos="<?= htmlspecialchars($emp['apellidos']) ?>"
                                                data-dni="<?= htmlspecialchars($emp['dni']) ?>"
                                                data-reloj="<?= htmlspecialchars($emp['codigo_reloj']) ?>"
                                                data-turno="<?= htmlspecialchars($emp['turno_nombre'] ?? 'Turno General') ?>"
                                                data-hent="<?= !empty($emp['hora_entrada']) ? substr($emp['hora_entrada'], 0, 5) : '08:00' ?>"
                                                data-hsal="<?= !empty($emp['hora_salida']) ? substr($emp['hora_salida'], 0, 5) : '17:00' ?>"
                                                data-tol="<?= $emp['tolerancia_minutos'] ?? 10 ?>"
                                                data-search="<?= strtolower(htmlspecialchars($emp['apellidos'] . ' ' . $emp['nombres'] . ' ' . $emp['dni'] . ' ' . $emp['codigo_reloj'])) ?>">
                                            <?= htmlspecialchars($emp['apellidos'] . ' ' . $emp['nombres']) ?> &mdash; DNI: <?= htmlspecialchars($emp['dni']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="small font-weight-bold text-secondary mb-1">Fecha de Asistencia <span class="text-danger">*</span></label>
                                <input type="date" id="edit_fecha_picker" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" onchange="onDatePickerChanged(this.value)">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FORMULARIO DE EDICIÓN DIRECTA DE HORAS -->
                <div class="card border bg-white shadow-none mb-3" style="border-radius: 8px;">
                    <div class="card-header py-2 bg-white border-bottom d-flex align-items-center justify-content-between">
                        <span class="font-weight-bold text-dark small text-uppercase"><i class="fa-solid fa-stopwatch text-primary mr-1"></i> Horas Oficiales (Entrada y Salida)</span>
                        <span class="badge badge-warning text-dark px-2 py-1"><i class="fa-solid fa-database mr-1"></i> Guardado directo</span>
                    </div>
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <label class="small font-weight-bold text-success d-flex justify-content-between align-items-center">
                                    <span><i class="fa-solid fa-arrow-right-to-bracket mr-1"></i> Hora de Entrada Real</span>
                                    <div class="btn-group btn-group-xs">
                                        <button type="button" class="btn btn-xs btn-outline-success py-0 px-1" onclick="setProgrammedTime('entrada')" title="Copiar hora programada del turno">
                                            <i class="fa-regular fa-clock mr-1"></i>Turno
                                        </button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1" onclick="clearTime('entrada')" title="Borrar hora">
                                            <i class="fa-solid fa-eraser"></i>
                                        </button>
                                    </div>
                                </label>
                                <input type="time" name="hora_entrada_real" id="edit_hora_entrada" step="1" class="form-control form-control-lg font-monospace text-center font-weight-bold" style="font-size: 1.35rem; color: #047857; background: #f0fdf4;" oninput="calculateRealtimeAdminAttendance()">
                                <small class="text-muted d-block mt-1"><i class="fa-solid fa-circle-info mr-1"></i> Hora efectiva de ingreso.</small>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="small font-weight-bold text-primary d-flex justify-content-between align-items-center">
                                    <span><i class="fa-solid fa-arrow-right-from-bracket mr-1"></i> Hora de Salida Real</span>
                                    <div class="btn-group btn-group-xs">
                                        <button type="button" class="btn btn-xs btn-outline-primary py-0 px-1" onclick="setProgrammedTime('salida')" title="Copiar hora programada del turno">
                                            <i class="fa-regular fa-clock mr-1"></i>Turno
                                        </button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1" onclick="clearTime('salida')" title="Borrar hora">
                                            <i class="fa-solid fa-eraser"></i>
                                        </button>
                                    </div>
                                </label>
                                <input type="time" name="hora_salida_real" id="edit_hora_salida" step="1" class="form-control form-control-lg font-monospace text-center font-weight-bold" style="font-size: 1.35rem; color: #1d4ed8; background: #eff6ff;" oninput="calculateRealtimeAdminAttendance()">
                                <small class="text-muted d-block mt-1"><i class="fa-solid fa-circle-info mr-1"></i> Hora efectiva de retiro.</small>
                            </div>
                        </div>

                        <!-- PREVIEW CALCULADO EN TIEMPO REAL -->
                        <div class="alert alert-info py-2 px-3 mb-0 small mt-2" style="border-radius: 6px;" id="adminCalcAlert">
                            <div class="d-flex align-items-center justify-content-between flex-wrap">
                                <div>
                                    <i class="fa-solid fa-calculator mr-1"></i> <strong>Recálculo Automático:</strong> <span id="adminCalcSummary">Ingresa las horas arriba</span>
                                </div>
                                <div>
                                    Estado sugerido: <strong class="badge badge-primary px-2 py-1" id="adminCalcStateBadge">--</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ESTADO Y OBSERVACIONES -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group mb-2">
                            <label class="small font-weight-bold text-secondary">Estado Oficial de Asistencia</label>
                            <select name="estado" id="edit_estado" class="form-control form-control-sm font-weight-bold" required>
                                <option value="PRESENTE">PRESENTE</option>
                                <option value="TARDANZA">TARDANZA</option>
                                <option value="JUSTIFICADO">JUSTIFICADO</option>
                                <option value="PERMISO">PERMISO</option>
                                <option value="VACACIONES">VACACIONES</option>
                                <option value="FALTA">FALTA</option>
                                <option value="DESCANSO">DESCANSO O FERIADO</option>
                                <option value="SALIDA_SIN_MARCAR">SALIDA SIN MARCAR</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-2">
                            <label class="small font-weight-bold text-secondary">Minutos de Tardanza</label>
                            <input type="number" name="minutos_tardanza" id="edit_tardanza" class="form-control form-control-sm" min="0">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-2">
                            <label class="small font-weight-bold text-secondary">Minutos Horas Extras</label>
                            <input type="number" name="minutos_extra" id="edit_extra" class="form-control form-control-sm" min="0">
                        </div>
                    </div>
                </div>

                <div class="form-group mb-0">
                    <label class="small font-weight-bold text-secondary">Motivo del Ajuste <span class="text-danger">*</span></label>
                    <textarea name="observaciones" id="edit_obs" class="form-control form-control-sm" rows="2" placeholder="Ej: Corrección autorizada de marcación por Administración..." required></textarea>
                </div>
            </div>
            
            <div class="modal-footer justify-content-between bg-white py-2 px-4 border-top">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                <button type="submit" id="btnSaveAdminEdit" class="btn btn-primary btn-sm px-4 font-weight-bold">
                    <i class="fa-solid fa-floppy-disk mr-1"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL REGISTRAR ASISTENCIA JUSTIFICADA CON BUSCADOR (EXCLUSIVO ADMINISTRADOR) -->
<div class="modal fade" id="modalJustificarAdmin" tabindex="-1" role="dialog" aria-labelledby="modalJustificarTitle" aria-modal="true" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form method="POST" action="?route=asistencia&action=justificar_admin" class="modal-content shadow-lg border-0" id="formJustificarAdmin" onsubmit="submitJustificarAdmin(event)">
            <?= csrf_field() ?>
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title font-weight-bold d-flex align-items-center" id="modalJustificarTitle">
                    <i class="fa-solid fa-user-shield mr-2"></i> Registrar Asistencia Justificada
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            
            <div class="modal-body p-4 bg-light">
                <!-- BUSCADOR INTERACTIVO DE TRABAJADOR -->
                <div class="card border bg-white shadow-none mb-3" style="border-radius: 8px;">
                    <div class="card-header py-2 bg-white border-bottom">
                        <span class="font-weight-bold text-dark small text-uppercase"><i class="fa-solid fa-magnifying-glass text-primary mr-1"></i> Seleccionar Trabajador</span>
                    </div>
                    <div class="card-body p-3">
                        <div class="form-group mb-2">
                            <label class="small font-weight-bold text-secondary mb-1">Buscar por Nombres, Apellidos o DNI</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-search"></i></span>
                                </div>
                                <input type="text" id="just_search_input" class="form-control" placeholder="Escribe para filtrar trabajadores al instante..." oninput="filterEmployeeSelect(this.value)">
                            </div>
                        </div>

                        <div class="form-group mb-0">
                            <label class="small font-weight-bold text-secondary mb-1">Trabajador Seleccionado <span class="text-danger">*</span></label>
                            <select name="id_empleado" id="just_empleado_select" class="form-control form-control-sm" size="5" required style="border-radius: 6px;">
                                <?php foreach ($empleados as $emp): ?>
                                    <option value="<?= $emp['id'] ?>" data-search="<?= strtolower(htmlspecialchars($emp['apellidos'] . ' ' . $emp['nombres'] . ' ' . $emp['dni'] . ' ' . $emp['codigo_reloj'])) ?>">
                                        <?= htmlspecialchars($emp['apellidos'] . ' ' . $emp['nombres']) ?> &mdash; DNI: <?= htmlspecialchars($emp['dni']) ?> (ID: <?= htmlspecialchars($emp['codigo_reloj']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- PERÍODO Y TIPO DE JUSTIFICACIÓN -->
                <div class="card border bg-white shadow-none mb-3" style="border-radius: 8px;">
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group mb-2">
                                    <label class="small font-weight-bold text-secondary">Tipo de Justificación <span class="text-danger">*</span></label>
                                    <select name="tipo" id="just_tipo" class="form-control form-control-sm font-weight-bold" required>
                                        <option value="TARDANZA">Tardanza Justificada</option>
                                        <option value="FALTA">Falta Justificada</option>
                                        <option value="PERMISO_MEDICO">Descanso Médico</option>
                                        <option value="COMISION_SERVICIO">Comisión de Servicio</option>
                                        <option value="VACACIONES">Vacaciones</option>
                                        <option value="LICENCIA_MATERNIDAD_PATERNIDAD">Licencia por Paternidad o Maternidad</option>
                                        <option value="OTRO">Otro Motivo</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-2">
                                    <label class="small font-weight-bold text-secondary">Fecha Inicio <span class="text-danger">*</span></label>
                                    <input type="date" name="fecha_inicio" id="just_fecha_inicio" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-2">
                                    <label class="small font-weight-bold text-secondary">Fecha Fin <span class="text-danger">*</span></label>
                                    <input type="date" name="fecha_fin" id="just_fecha_fin" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mb-0">
                            <label class="small font-weight-bold text-secondary">Motivo o Sustento <span class="text-danger">*</span></label>
                            <textarea name="motivo" id="just_motivo" class="form-control form-control-sm" rows="2" placeholder="Detallar el sustento de la justificación autorizada..." required></textarea>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer justify-content-between bg-white py-2 px-4 border-top">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                <button type="submit" id="btnSaveJustifyAdmin" class="btn btn-primary btn-sm px-4">
                    <i class="fa-solid fa-shield-check mr-1"></i> Aplicar Justificación
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// =========================================================================
// GESTIÓN ADMINISTRATIVA DE ASISTENCIA: EDICIÓN DE HORAS Y JUSTIFICACIÓN
// =========================================================================

function openAdminEditModal(rec, focusField = 'entrada') {
    document.getElementById('edit_id').value = rec.id || 0;
    document.getElementById('edit_id_empleado').value = rec.id_empleado || 0;
    document.getElementById('edit_fecha_hidden').value = rec.fecha || '<?= date('Y-m-d') ?>';
    
    document.getElementById('edit_empleado_card').style.display = 'block';
    document.getElementById('edit_empleado_selector_card').style.display = 'none';

    document.getElementById('edit_badge_fecha').innerText = 'Fecha: ' + (rec.fecha || '<?= date('Y-m-d') ?>');
    document.getElementById('edit_empleado_nombre').innerText = `${rec.apellidos || ''} ${rec.nombres || ''}`;
    document.getElementById('edit_empleado_dni').innerText = rec.dni || '--';
    document.getElementById('edit_empleado_reloj').innerText = rec.codigo_reloj || '--';
    
    const progEnt = rec.hora_entrada_programada ? rec.hora_entrada_programada.substr(0, 5) : '08:00';
    const progSal = rec.hora_salida_programada ? rec.hora_salida_programada.substr(0, 5) : '17:00';
    document.getElementById('edit_prog_entrada').value = progEnt;
    document.getElementById('edit_prog_salida').value = progSal;
    document.getElementById('edit_tolerancia').value = rec.tolerancia_minutos || 10;
    document.getElementById('edit_turno_info').innerText = `${progEnt} - ${progSal} (Turno: ${rec.turno_nombre || 'General'})`;

    // Extraer horas reales existentes
    let realEnt = '';
    if (rec.hora_entrada_real) {
        realEnt = rec.hora_entrada_real.substr(11, 5);
    }
    let realSal = '';
    if (rec.hora_salida_real) {
        realSal = rec.hora_salida_real.substr(11, 5);
    }

    document.getElementById('edit_hora_entrada').value = realEnt;
    document.getElementById('edit_hora_salida').value = realSal;
    document.getElementById('edit_estado').value = rec.estado || 'PRESENTE';
    document.getElementById('edit_tardanza').value = rec.minutos_tardanza || 0;
    document.getElementById('edit_extra').value = rec.minutos_extra || 0;
    document.getElementById('edit_obs').value = rec.observaciones || 'Ajuste de horario por el Administrador';

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
    
    document.getElementById('edit_worker_search').value = '';
    filterWorkerSelect('');

    const select = document.getElementById('edit_worker_select');
    if (select.options.length > 0) {
        select.selectedIndex = 0;
        onWorkerSelected(select);
    }

    document.getElementById('edit_hora_entrada').value = '';
    document.getElementById('edit_hora_salida').value = '';
    document.getElementById('edit_estado').value = 'PRESENTE';
    document.getElementById('edit_tardanza').value = 0;
    document.getElementById('edit_extra').value = 0;
    document.getElementById('edit_obs').value = 'Asignación de asistencia autorizada por Administración';

    calculateRealtimeAdminAttendance();
    $('#modalEditarAsistencia').modal('show');
}

function filterWorkerSelect(term) {
    const q = term.toLowerCase().trim();
    const select = document.getElementById('edit_worker_select');
    const options = select.options;
    let firstMatch = null;

    for (let i = 0; i < options.length; i++) {
        const opt = options[i];
        const searchData = opt.getAttribute('data-search') || opt.text.toLowerCase();
        if (q === '' || searchData.includes(q)) {
            opt.style.display = '';
            if (!firstMatch) firstMatch = opt;
        } else {
            opt.style.display = 'none';
        }
    }

    if (firstMatch && q !== '') {
        select.value = firstMatch.value;
        onWorkerSelected(select);
    }
}

function onWorkerSelected(selectEl) {
    const opt = selectEl.options[selectEl.selectedIndex];
    if (!opt) return;

    document.getElementById('edit_id_empleado').value = opt.value;
    document.getElementById('edit_prog_entrada').value = opt.getAttribute('data-hent') || '08:00';
    document.getElementById('edit_prog_salida').value = opt.getAttribute('data-hsal') || '17:00';
    document.getElementById('edit_tolerancia').value = opt.getAttribute('data-tol') || '10';

    calculateRealtimeAdminAttendance();
}

function onDatePickerChanged(val) {
    document.getElementById('edit_fecha_hidden').value = val;
}

function setProgrammedTime(field) {
    if (field === 'entrada') {
        const prog = document.getElementById('edit_prog_entrada').value || '08:00';
        document.getElementById('edit_hora_entrada').value = prog;
    } else if (field === 'salida') {
        const prog = document.getElementById('edit_prog_salida').value || '17:00';
        document.getElementById('edit_hora_salida').value = prog;
    }
    calculateRealtimeAdminAttendance();
}

function clearTime(field) {
    if (field === 'entrada') {
        document.getElementById('edit_hora_entrada').value = '';
    } else if (field === 'salida') {
        document.getElementById('edit_hora_salida').value = '';
    }
    calculateRealtimeAdminAttendance();
}

function calculateRealtimeAdminAttendance() {
    const entVal = document.getElementById('edit_hora_entrada').value;
    const salVal = document.getElementById('edit_hora_salida').value;
    const progEnt = document.getElementById('edit_prog_entrada').value || '08:00';
    const progSal = document.getElementById('edit_prog_salida').value || '17:00';
    const tol = parseInt(document.getElementById('edit_tolerancia').value) || 10;

    const summaryEl = document.getElementById('adminCalcSummary');
    const badgeEl = document.getElementById('adminCalcStateBadge');
    const tardanzaInput = document.getElementById('edit_tardanza');
    const estadoSelect = document.getElementById('edit_estado');

    if (!entVal && !salVal) {
        summaryEl.innerText = 'Sin horas ingresadas (se considerará Inasistencia o Falta Justificada)';
        badgeEl.className = 'badge badge-secondary px-2 py-1';
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
            summaryParts.push(`<span class="text-success font-weight-bold"><i class="fa-solid fa-check mr-1"></i>Puntual (Entrada: ${entVal})</span>`);
        } else {
            computedTardanza = Math.max(0, minReal - minProg);
            suggestedState = 'TARDANZA';
            summaryParts.push(`<span class="text-danger font-weight-bold"><i class="fa-solid fa-clock mr-1"></i>Tardanza: +${computedTardanza} min</span>`);
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
            const diffMin = minEnd - minStart;
            const hrs = Math.floor(diffMin / 60);
            const mins = diffMin % 60;
            summaryParts.push(`<span class="text-primary font-weight-bold"><i class="fa-solid fa-business-time mr-1"></i>Permanencia: ${hrs}h ${mins}m</span>`);

            if (minEnd > minProgExit + 15) {
                const diffExtra = minEnd - minProgExit;
                summaryParts.push(`<span class="text-info font-weight-bold">+${diffExtra}m Extra</span>`);
            }
        }
    } else if (entVal && !salVal) {
        summaryParts.push(`<span class="text-warning font-weight-bold"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Salida sin registrar</span>`);
    }

    tardanzaInput.value = computedTardanza;
    summaryEl.innerHTML = summaryParts.join(' &bull; ');

    if (['PRESENTE', 'TARDANZA', 'FALTA', 'SALIDA_SIN_MARCAR'].includes(estadoSelect.value)) {
        estadoSelect.value = suggestedState;
    }
    
    badgeEl.className = suggestedState === 'PRESENTE' ? 'badge badge-success px-2 py-1' : 'badge badge-danger px-2 py-1';
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

function openJustificarAdminModal() {
    $('#formJustificarAdmin')[0].reset();
    document.getElementById('just_search_input').value = '';
    filterEmployeeSelect('');
    document.getElementById('just_fecha_inicio').value = '<?= date('Y-m-d') ?>';
    document.getElementById('just_fecha_fin').value = '<?= date('Y-m-d') ?>';
    $('#modalJustificarAdmin').modal('show');
}

function openQuickJustifyModal(rec) {
    $('#formJustificarAdmin')[0].reset();
    document.getElementById('just_search_input').value = '';
    filterEmployeeSelect('');
    document.getElementById('just_empleado_select').value = rec.id_empleado;
    document.getElementById('just_fecha_inicio').value = rec.fecha;
    document.getElementById('just_fecha_fin').value = rec.fecha;
    document.getElementById('just_motivo').value = `Justificación oficial de asistencia del día ${rec.fecha}`;
    $('#modalJustificarAdmin').modal('show');
}

function filterEmployeeSelect(term) {
    const q = term.toLowerCase().trim();
    const select = document.getElementById('just_empleado_select');
    const options = select.options;
    let firstMatch = null;

    for (let i = 0; i < options.length; i++) {
        const opt = options[i];
        const searchData = opt.getAttribute('data-search') || opt.text.toLowerCase();
        if (q === '' || searchData.includes(q)) {
            opt.style.display = '';
            if (!firstMatch) firstMatch = opt;
        } else {
            opt.style.display = 'none';
        }
    }

    if (firstMatch && q !== '') {
        select.value = firstMatch.value;
    }
}

function submitJustificarAdmin(e) {
    e.preventDefault();
    const empId = $('#just_empleado_select').val();
    if (!empId) {
        Swal.fire({
            icon: 'warning',
            title: 'Trabajador requerido',
            text: 'Por favor selecciona un trabajador de la lista.',
            confirmButtonColor: '#1d4ed8'
        });
        return;
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
</script>

<?php require_once APP_ROOT . '/views/layout/footer.php'; ?>
