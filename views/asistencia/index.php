<?php 
require_once APP_ROOT . '/views/layout/header.php'; 

// Cálculos de KPIs y métricas de asistencia para el período consultado
$kpiTotal = count($asistencias);
$kpiPresentes = 0;
$kpiTardanzas = 0;
$kpiFaltas = 0;
$kpiJustificados = 0;
$kpiSinSalida = 0;
$kpiMinTardanza = 0;
$kpiMinTrabajados = 0;
$kpiMinExtra = 0;

foreach ($asistencias as $r) {
    $st = $r['estado'];
    if ($st === 'PRESENTE') $kpiPresentes++;
    elseif ($st === 'TARDANZA') {
        $kpiTardanzas++;
        $kpiMinTardanza += (int)$r['minutos_tardanza'];
    } elseif ($st === 'FALTA' || $st === 'FALTA_INJUSTIFICADA') $kpiFaltas++;
    elseif (in_array($st, ['JUSTIFICADO', 'PERMISO', 'VACACIONES'], true)) $kpiJustificados++;
    elseif ($st === 'SALIDA_SIN_MARCAR') $kpiSinSalida++;
    
    $kpiMinTrabajados += (int)$r['minutos_trabajados'];
    $kpiMinExtra += (int)$r['minutos_extra'];
}

$kpiPuntualidad = $kpiTotal > 0 ? round(($kpiPresentes / $kpiTotal) * 100, 1) : 0;
$kpiHorasTrab = sprintf('%dh %02dm', floor($kpiMinTrabajados / 60), $kpiMinTrabajados % 60);
$kpiHorasExt = sprintf('%dh %02dm', floor($kpiMinExtra / 60), $kpiMinExtra % 60);
$kpiHorasTard = sprintf('%dh %02dm', floor($kpiMinTardanza / 60), $kpiMinTardanza % 60);

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
                            <div class="kpi-title">Faltas / Horas Extras</div>
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

        <!-- FILTER CARD -->
        <div class="card mb-3 no-print">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap">
                <h3 class="card-title font-weight-bold">
                    <i class="fa-solid fa-filter mr-2 text-primary"></i> Filtros de Reporte y Asistencia
                </h3>
                <div class="card-tools d-flex align-items-center flex-wrap" style="gap: 5px;">
                    <?php if (in_array($userRole, ['ADMIN', 'RRHH'], true)): ?>
                        <form method="POST" action="?route=asistencia&action=recalcular" class="d-inline">
                            <input type="hidden" name="fecha_inicio" value="<?= htmlspecialchars($fechaInicio) ?>">
                            <input type="hidden" name="fecha_fin" value="<?= htmlspecialchars($fechaFin) ?>">
                            <button type="submit" class="btn btn-outline-primary btn-sm" onclick="return confirm('¿Deseas recalcular la asistencia en este rango de fechas?')">
                                <i class="fa-solid fa-calculator mr-1"></i> Recalcular
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <a href="?route=asistencia&fecha_inicio=<?= $fechaInicio ?>&fecha_fin=<?= $fechaFin ?>&departamento_id=<?= $deptoId ?>&estado=<?= $estado ?>&search=<?= urlencode($search ?? '') ?>&export=excel" class="btn btn-success btn-sm" title="Descargar reporte oficial en formato Excel con diseño institucional">
                        <i class="fa-solid fa-file-excel mr-1"></i> Excel
                    </a>

                    <a href="?route=asistencia&fecha_inicio=<?= $fechaInicio ?>&fecha_fin=<?= $fechaFin ?>&departamento_id=<?= $deptoId ?>&estado=<?= $estado ?>&search=<?= urlencode($search ?? '') ?>&export=csv" class="btn btn-outline-secondary btn-sm" title="Descargar archivo CSV">
                        <i class="fa-solid fa-file-csv mr-1"></i> CSV
                    </a>

                    <button type="button" class="btn btn-outline-dark btn-sm" onclick="window.print()" title="Imprimir reporte oficial o Guardar como PDF">
                        <i class="fa-solid fa-print mr-1"></i> Imprimir / PDF
                    </button>
                </div>
            </div>
            <div class="card-body">
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
                        <label class="form-label-custom"><i class="fa-solid fa-building mr-1"></i> Departamento / Área</label>
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
                            <option value="PRESENTE" <?= $estado === 'PRESENTE' ? 'selected' : '' ?>>Presente</option>
                            <option value="TARDANZA" <?= $estado === 'TARDANZA' ? 'selected' : '' ?>>Tardanza</option>
                            <option value="FALTA" <?= $estado === 'FALTA' ? 'selected' : '' ?>>Falta</option>
                            <option value="JUSTIFICADO" <?= $estado === 'JUSTIFICADO' ? 'selected' : '' ?>>Justificado</option>
                            <option value="SALIDA_SIN_MARCAR" <?= $estado === 'SALIDA_SIN_MARCAR' ? 'selected' : '' ?>>Sin Salida</option>
                        </select>
                    </div>

                    <div class="col-md-2 col-sm-8 mb-2">
                        <label class="form-label-custom"><i class="fa-solid fa-magnifying-glass mr-1"></i> Buscar Empleado</label>
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Nombre, DNI..." value="<?= htmlspecialchars($search ?? '') ?>">
                    </div>

                    <div class="col-md-1 col-sm-4 mb-2">
                        <button type="submit" class="btn btn-primary btn-sm btn-block" title="Filtrar resultados">
                            <i class="fa-solid fa-filter mr-1"></i> Filtrar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MAIN TABLE CARD -->
        <div class="card">
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover datatable text-nowrap table-sm">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Empleado</th>
                            <th>Turno</th>
                            <th>Entrada (Prog. / Real)</th>
                            <th>Salida (Prog. / Real)</th>
                            <th class="text-center">Tardanza</th>
                            <th class="text-center">Tiempo Trabajado</th>
                            <th class="text-center">Horas Extras</th>
                            <th>Estado</th>
                            <?php if (in_array($userRole, ['ADMIN', 'RRHH'], true)): ?>
                                <th class="text-center no-print">Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($asistencias as $a): ?>
                            <tr>
                                <td class="font-weight-bold text-dark"><?= $a['fecha'] ?></td>
                                <td>
                                    <div class="font-weight-bold text-dark"><?= htmlspecialchars($a['apellidos'] . ' ' . $a['nombres']) ?></div>
                                    <small class="text-muted">DNI: <?= htmlspecialchars($a['dni']) ?> &bull; ID Reloj: <?= htmlspecialchars($a['codigo_reloj']) ?></small>
                                </td>
                                <td>
                                    <span class="badge-pill-custom badge-pill-neutral"><?= htmlspecialchars($a['turno_nombre'] ?? 'Sin Turno') ?></span>
                                </td>
                                <td>
                                    <div><small class="text-muted">Prog.:</small> <span class="font-monospace small"><?= $a['hora_entrada_programada'] ?? '--:--' ?></span></div>
                                    <div>
                                        <small class="text-muted">Real:</small> 
                                        <span class="font-monospace small font-weight-bold <?= $a['minutos_tardanza'] > 0 ? 'text-danger' : 'text-success' ?>">
                                            <?= $a['hora_entrada_real'] ? substr($a['hora_entrada_real'], 11, 5) : '--:--' ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div><small class="text-muted">Prog.:</small> <span class="font-monospace small"><?= $a['hora_salida_programada'] ?? '--:--' ?></span></div>
                                    <div>
                                        <small class="text-muted">Real:</small> 
                                        <span class="font-monospace small font-weight-bold text-dark">
                                            <?= $a['hora_salida_real'] ? substr($a['hora_salida_real'], 11, 5) : '--:--' ?>
                                        </span>
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
                                <td>
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
                                        <button class="btn btn-xs btn-default border" onclick="openEditModal(<?= htmlspecialchars(json_encode($a)) ?>)" title="Ajustar asistencia manualmente">
                                            <i class="fa-solid fa-pen-to-square text-primary"></i>
                                        </button>
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
<!-- MODAL PARA EDICIÓN MANUAL DE ASISTENCIA (AdminLTE Modal) -->
<div class="modal fade" id="modalEditarAsistencia" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="?route=asistencia&action=editar" class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold"><i class="fa-solid fa-pen-to-square mr-2"></i> Ajuste Manual de Asistencia</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="form-group">
                    <label class="small font-weight-bold text-secondary">Empleado / Fecha</label>
                    <input type="text" id="edit_empleado" class="form-control form-control-sm bg-light" readonly>
                </div>

                <div class="form-group">
                    <label class="small font-weight-bold text-secondary">Estado de Asistencia</label>
                    <select name="estado" id="edit_estado" class="form-control form-control-sm" required>
                        <option value="PRESENTE">Presente</option>
                        <option value="TARDANZA">Tardanza</option>
                        <option value="FALTA">Falta / Inasistencia</option>
                        <option value="JUSTIFICADO">Justificado</option>
                        <option value="PERMISO">Permiso</option>
                        <option value="VACACIONES">Vacaciones</option>
                        <option value="DESCANSO">Descanso / Feriado</option>
                        <option value="SALIDA_SIN_MARCAR">Sin Salida Registrada</option>
                    </select>
                </div>

                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label class="small font-weight-bold text-secondary">Minutos de Tardanza</label>
                            <input type="number" name="minutos_tardanza" id="edit_tardanza" class="form-control form-control-sm" min="0">
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label class="small font-weight-bold text-secondary">Minutos de Horas Extras</label>
                            <input type="number" name="minutos_extra" id="edit_extra" class="form-control form-control-sm" min="0">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="small font-weight-bold text-secondary">Observaciones / Motivo del Ajuste</label>
                    <textarea name="observaciones" id="edit_obs" class="form-control form-control-sm" rows="3" placeholder="Indicar el sustento o motivo del ajuste manual realizado por RRHH..."></textarea>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-floppy-disk mr-1"></i> Guardar Ajuste</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EVENT SOURCING: LÍNEA DE TIEMPO DE AUDITORÍA Y TRAZABILIDAD -->
<div class="modal fade" id="modalTimelineEventos" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title font-weight-bold">
                    <i class="fa-solid fa-timeline text-info mr-2"></i> Trazabilidad y Event Sourcing
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
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

            if (!data.success || !data.events || data.events.length === 0) {
                document.getElementById('timelineEmpty').style.display = 'block';
                document.getElementById('timelineTotalEventos').innerText = '0 eventos';
                return;
            }

            if (data.empleado) {
                document.getElementById('timelineEmpleadoNombre').innerText = `${data.empleado.apellidos} ${data.empleado.nombres}`;
                document.getElementById('timelineEmpleadoDni').innerText = data.empleado.dni || '--';
                document.getElementById('timelineEmpleadoReloj').innerText = data.empleado.codigo_reloj || '--';
            }

            document.getElementById('timelineTotalEventos').innerText = `${data.total} evento(s) inmutable(s)`;

            const container = document.getElementById('timelineContent');
            container.innerHTML = '';

            let timelineHtml = `
                <div class="time-label">
                    <span class="bg-primary text-white font-weight-bold px-3 py-1 rounded shadow-sm">${data.fecha}</span>
                </div>
            `;

            data.events.forEach((ev, idx) => {
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

function openEditModal(record) {
    document.getElementById('edit_id').value = record.id;
    document.getElementById('edit_empleado').value = `${record.apellidos} ${record.nombres} (${record.fecha})`;
    document.getElementById('edit_estado').value = record.estado;
    document.getElementById('edit_tardanza').value = record.minutos_tardanza;
    document.getElementById('edit_extra').value = record.minutos_extra;
    document.getElementById('edit_obs').value = record.observaciones || '';
    
    $('#modalEditarAsistencia').modal('show');
}
</script>
<?php endif; ?>

<?php require_once APP_ROOT . '/views/layout/footer.php'; ?>
