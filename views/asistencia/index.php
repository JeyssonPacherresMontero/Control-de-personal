<?php require_once APP_ROOT . '/views/layout/header.php'; ?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-sm-6">
                <h1 class="m-0 font-weight-bold"><i class="fa-solid fa-calendar-check mr-2 text-primary"></i> Control de Asistencia Diaria</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
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

        <!-- FILTER CARD -->
        <div class="card card-default card-outline shadow-sm mb-3">
            <div class="card-header">
                <h3 class="card-title font-weight-bold"><i class="fa-solid fa-filter mr-1 text-secondary"></i> Filtros de Búsqueda</h3>
                <div class="card-tools d-flex align-items-center flex-wrap">
                    <?php if (in_array($userRole, ['ADMIN', 'RRHH'], true)): ?>
                        <form method="POST" action="?route=asistencia&action=recalcular" class="d-inline mr-1">
                            <input type="hidden" name="fecha_inicio" value="<?= htmlspecialchars($fechaInicio) ?>">
                            <input type="hidden" name="fecha_fin" value="<?= htmlspecialchars($fechaFin) ?>">
                            <button type="submit" class="btn btn-outline-primary btn-sm shadow-sm" onclick="return confirm('¿Deseas recalcular la asistencia en este rango de fechas?')">
                                <i class="fa-solid fa-calculator mr-1"></i> Recalcular Asistencias
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <a href="?route=asistencia&fecha_inicio=<?= $fechaInicio ?>&fecha_fin=<?= $fechaFin ?>&departamento_id=<?= $deptoId ?>&estado=<?= $estado ?>&search=<?= urlencode($search ?? '') ?>&export=excel" class="btn btn-success btn-sm shadow-sm mr-1" title="Descargar reporte en formato Excel con diseño de tablas y colores">
                        <i class="fa-solid fa-file-excel mr-1"></i> Exportar a Excel
                    </a>

                    <a href="?route=asistencia&fecha_inicio=<?= $fechaInicio ?>&fecha_fin=<?= $fechaFin ?>&departamento_id=<?= $deptoId ?>&estado=<?= $estado ?>&search=<?= urlencode($search ?? '') ?>&export=csv" class="btn btn-outline-secondary btn-sm shadow-sm mr-1" title="Descargar archivo CSV compatible con Excel">
                        <i class="fa-solid fa-file-csv mr-1"></i> CSV
                    </a>

                    <button type="button" class="btn btn-outline-dark btn-sm shadow-sm" onclick="window.print()" title="Imprimir reporte o Guardar como PDF">
                        <i class="fa-solid fa-print mr-1"></i> Imprimir / PDF
                    </button>
                </div>
            </div>
            <div class="card-body py-3">
                <form method="GET" action="" class="row align-items-end">
                    <input type="hidden" name="route" value="asistencia">

                    <div class="col-md-2 mb-2">
                        <label class="small font-weight-bold text-secondary mb-1">Fecha Desde</label>
                        <input type="date" name="fecha_inicio" class="form-control form-control-sm" value="<?= htmlspecialchars($fechaInicio) ?>">
                    </div>

                    <div class="col-md-2 mb-2">
                        <label class="small font-weight-bold text-secondary mb-1">Fecha Hasta</label>
                        <input type="date" name="fecha_fin" class="form-control form-control-sm" value="<?= htmlspecialchars($fechaFin) ?>">
                    </div>

                    <div class="col-md-3 mb-2">
                        <label class="small font-weight-bold text-secondary mb-1">Departamento / Área</label>
                        <select name="departamento_id" class="form-control form-control-sm">
                            <option value="">-- Todos los Departamentos --</option>
                            <?php foreach ($departamentos as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= $deptoId == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2 mb-2">
                        <label class="small font-weight-bold text-secondary mb-1">Estado</label>
                        <select name="estado" class="form-control form-control-sm">
                            <option value="">-- Todos los Estados --</option>
                            <option value="PRESENTE" <?= $estado === 'PRESENTE' ? 'selected' : '' ?>>Presente</option>
                            <option value="TARDANZA" <?= $estado === 'TARDANZA' ? 'selected' : '' ?>>Tardanza</option>
                            <option value="FALTA" <?= $estado === 'FALTA' ? 'selected' : '' ?>>Falta</option>
                            <option value="JUSTIFICADO" <?= $estado === 'JUSTIFICADO' ? 'selected' : '' ?>>Justificado</option>
                            <option value="SALIDA_SIN_MARCAR" <?= $estado === 'SALIDA_SIN_MARCAR' ? 'selected' : '' ?>>Sin Salida</option>
                        </select>
                    </div>

                    <div class="col-md-2 mb-2">
                        <label class="small font-weight-bold text-secondary mb-1">Buscar</label>
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Nombre, DNI..." value="<?= htmlspecialchars($search ?? '') ?>">
                    </div>

                    <div class="col-md-1 mb-2">
                        <button type="submit" class="btn btn-secondary btn-sm btn-block"><i class="fa-solid fa-magnifying-glass"></i></button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MAIN TABLE CARD -->
        <div class="card card-primary card-outline shadow-sm">
            <div class="card-body">
                <table class="table table-bordered table-hover datatable text-nowrap table-sm">
                    <thead class="thead-light">
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
                                <th class="text-center">Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($asistencias as $a): ?>
                            <tr>
                                <td class="font-weight-bold text-dark"><?= $a['fecha'] ?></td>
                                <td>
                                    <div class="font-weight-bold text-dark"><?= htmlspecialchars($a['apellidos'] . ' ' . $a['nombres']) ?></div>
                                    <small class="text-muted">DNI: <?= htmlspecialchars($a['dni']) ?> | ID Reloj: <?= htmlspecialchars($a['codigo_reloj']) ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-light border"><?= htmlspecialchars($a['turno_nombre'] ?? 'Sin Turno') ?></span>
                                </td>
                                <td>
                                    <div><small class="text-muted">Prog.:</small> <?= $a['hora_entrada_programada'] ?? '--:--' ?></div>
                                    <div>
                                        <small class="text-muted">Real:</small> 
                                        <span class="font-weight-bold <?= $a['minutos_tardanza'] > 0 ? 'text-danger' : 'text-success' ?>">
                                            <?= $a['hora_entrada_real'] ? substr($a['hora_entrada_real'], 11, 5) : '--:--' ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div><small class="text-muted">Prog.:</small> <?= $a['hora_salida_programada'] ?? '--:--' ?></div>
                                    <div>
                                        <small class="text-muted">Real:</small> 
                                        <span class="font-weight-bold text-dark">
                                            <?= $a['hora_salida_real'] ? substr($a['hora_salida_real'], 11, 5) : '--:--' ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <?php if ($a['minutos_tardanza'] > 0): ?>
                                        <span class="badge badge-warning text-white font-weight-bold">+<?= $a['minutos_tardanza'] ?> min</span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php
                                        $hrs = floor($a['minutos_trabajados'] / 60);
                                        $min = $a['minutos_trabajados'] % 60;
                                        echo "{$hrs}h {$min}m";
                                    ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($a['minutos_extra'] > 0): ?>
                                        <span class="badge badge-info font-weight-bold">+<?= $a['minutos_extra'] ?> min</span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                        $est = $a['estado'];
                                        $obs = $a['observaciones'] ?? '';
                                        if ($est === 'PRESENTE' && str_contains($obs, 'Jornada en curso')) {
                                            echo '<span class="badge badge-success px-2 py-1"><i class="fa-solid fa-user-clock mr-1"></i>En Jornada</span>';
                                        } elseif ($est === 'PRESENTE') {
                                            echo '<span class="badge badge-success px-2 py-1"><i class="fa-solid fa-check mr-1"></i>Presente</span>';
                                        } elseif ($est === 'TARDANZA') {
                                            echo '<span class="badge badge-warning text-white px-2 py-1"><i class="fa-solid fa-clock mr-1"></i>Tardanza</span>';
                                        } elseif ($est === 'FALTA' || $est === 'FALTA_INJUSTIFICADA') {
                                            echo '<span class="badge badge-danger px-2 py-1"><i class="fa-solid fa-xmark mr-1"></i>Falta</span>';
                                        } elseif ($est === 'JUSTIFICADO') {
                                            echo '<span class="badge badge-primary px-2 py-1"><i class="fa-solid fa-shield mr-1"></i>Justificado</span>';
                                        } elseif ($est === 'PERMISO') {
                                            echo '<span class="badge badge-primary px-2 py-1"><i class="fa-solid fa-id-badge mr-1"></i>Permiso</span>';
                                        } elseif ($est === 'VACACIONES') {
                                            echo '<span class="badge badge-info px-2 py-1"><i class="fa-solid fa-umbrella-beach mr-1"></i>Vacaciones</span>';
                                        } elseif ($est === 'DESCANSO') {
                                            echo '<span class="badge badge-secondary px-2 py-1"><i class="fa-solid fa-bed mr-1"></i>Descanso</span>';
                                        } elseif ($est === 'SALIDA_SIN_MARCAR') {
                                            echo '<span class="badge badge-secondary px-2 py-1"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Sin Salida</span>';
                                        } else {
                                            echo '<span class="badge badge-light border px-2 py-1">' . htmlspecialchars($est) . '</span>';
                                        }
                                    ?>
                                    <?php if (!empty($a['observaciones'])): ?>
                                        <div class="small text-muted mt-1" title="<?= htmlspecialchars($a['observaciones']) ?>">
                                            <i class="far fa-comment-dots mr-1"></i><?= htmlspecialchars(mb_strimwidth($a['observaciones'], 0, 24, '...')) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <?php if (in_array($userRole, ['ADMIN', 'RRHH'], true)): ?>
                                    <td class="text-center">
                                        <button class="btn btn-xs btn-default border" onclick="openEditModal(<?= htmlspecialchars(json_encode($a)) ?>)" title="Ajustar asistencia manualmente">
                                            <i class="fa-solid fa-pen-to-square text-primary"></i>
                                        </button>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
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

<script>
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
