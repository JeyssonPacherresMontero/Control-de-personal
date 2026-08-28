<?php require_once APP_ROOT . '/views/layout/header.php'; ?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-sm-6">
                <h1 class="m-0 font-weight-bold"><i class="fa-solid fa-file-signature mr-2 text-primary"></i> Justificaciones, Permisos y Licencias</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="?route=dashboard">Inicio</a></li>
                    <li class="breadcrumb-item active">Justificaciones</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="text-secondary font-weight-bold mb-0">Solicitudes y Registros</h5>
            <button class="btn btn-primary btn-sm shadow-sm" data-toggle="modal" data-target="#modalJustificacion">
                <i class="fa-solid fa-plus mr-1"></i> Registrar Justificación
            </button>
        </div>

        <!-- MAIN TABLE CARD -->
        <div class="card card-primary card-outline shadow-sm">
            <div class="card-body">
                <table class="table table-bordered table-hover datatable text-nowrap table-sm">
                    <thead class="thead-light">
                        <tr>
                            <th>ID</th>
                            <th>Empleado</th>
                            <th>Tipo Permiso</th>
                            <th>Rango de Fechas</th>
                            <th>Motivo / Sustento</th>
                            <th>Estado</th>
                            <th>Aprobado Por</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($justificaciones as $j): ?>
                            <tr>
                                <td class="text-muted">#<?= $j['id'] ?></td>
                                <td>
                                    <div class="font-weight-bold text-dark"><?= htmlspecialchars($j['apellidos'] . ' ' . $j['nombres']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($j['departamento_nombre'] ?? 'Sin Área') ?> | DNI: <?= htmlspecialchars($j['dni']) ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-light border font-weight-bold"><?= $j['tipo'] ?></span>
                                </td>
                                <td>
                                    <span class="font-weight-bold text-dark"><?= $j['fecha_inicio'] ?></span> 
                                    <?php if ($j['fecha_inicio'] !== $j['fecha_fin']): ?>
                                        <span class="text-muted">al</span> <span class="font-weight-bold text-dark"><?= $j['fecha_fin'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="text-dark"><?= htmlspecialchars($j['motivo']) ?></div>
                                    <small class="text-muted">Registrado: <?= substr($j['creado_en'], 0, 16) ?></small>
                                </td>
                                <td>
                                    <?php if ($j['estado'] === 'APROBADO'): ?>
                                        <span class="badge badge-success px-2 py-1"><i class="fa-solid fa-check mr-1"></i> Aprobado</span>
                                    <?php elseif ($j['estado'] === 'RECHAZADO'): ?>
                                        <span class="badge badge-danger px-2 py-1"><i class="fa-solid fa-xmark mr-1"></i> Rechazado</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning text-white px-2 py-1"><i class="fa-solid fa-clock mr-1"></i> Pendiente</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted"><?= htmlspecialchars($j['aprobado_por'] ?? 'Sistema') ?></small>
                                </td>
                                <td class="text-center">
                                    <?php if ($j['estado'] === 'PENDIENTE'): ?>
                                        <div class="btn-group btn-group-sm">
                                            <form method="POST" action="?route=justificaciones&action=resolver" class="d-inline">
                                                <input type="hidden" name="id" value="<?= $j['id'] ?>">
                                                <input type="hidden" name="estado" value="APROBADO">
                                                <button type="submit" class="btn btn-outline-success btn-xs" title="Aprobar"><i class="fa-solid fa-check"></i></button>
                                            </form>
                                            <form method="POST" action="?route=justificaciones&action=resolver" class="d-inline ml-1">
                                                <input type="hidden" name="id" value="<?= $j['id'] ?>">
                                                <input type="hidden" name="estado" value="RECHAZADO">
                                                <button type="submit" class="btn btn-outline-danger btn-xs" title="Rechazar"><i class="fa-solid fa-xmark"></i></button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</section>

<!-- MODAL REGISTRAR JUSTIFICACIÓN -->
<div class="modal fade" id="modalJustificacion" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="?route=justificaciones&action=guardar" class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold"><i class="fa-solid fa-file-signature mr-2"></i> Nueva Justificación / Permiso</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="small font-weight-bold text-secondary">Empleado</label>
                    <select name="id_empleado" class="form-control form-control-sm" required>
                        <option value="">-- Seleccionar Empleado --</option>
                        <?php foreach ($empleados as $e): ?>
                            <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['apellidos'] . ' ' . $e['nombres']) ?> (DNI: <?= htmlspecialchars($e['dni']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="small font-weight-bold text-secondary">Tipo de Justificación</label>
                    <select name="tipo" class="form-control form-control-sm" required>
                        <option value="TARDANZA">Tardanza Justificada</option>
                        <option value="FALTA">Inasistencia Justificada</option>
                        <option value="PERMISO_MEDICO">Descanso Médico / Cita Médica</option>
                        <option value="COMISION_SERVICIO">Comisión de Servicio / Trabajo de Campo</option>
                        <option value="VACACIONES">Vacaciones</option>
                        <option value="LICENCIA_MATERNIDAD_PATERNIDAD">Licencia Maternidad / Paternidad</option>
                        <option value="OTRO">Otro Motivo</option>
                    </select>
                </div>

                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label class="small font-weight-bold text-secondary">Fecha Desde</label>
                            <input type="date" name="fecha_inicio" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label class="small font-weight-bold text-secondary">Fecha Hasta</label>
                            <input type="date" name="fecha_fin" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="small font-weight-bold text-secondary">Motivo / Explicación Detallada</label>
                    <textarea name="motivo" class="form-control form-control-sm" rows="3" placeholder="Detalle el sustento de la justificación..." required></textarea>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-floppy-disk mr-1"></i> Guardar y Aplicar</button>
            </div>
        </form>
    </div>
</div>

<?php require_once APP_ROOT . '/views/layout/footer.php'; ?>
