<?php require_once APP_ROOT . '/views/layout/header.php'; ?>

<!-- Content Header (Page header) -->
<div class="content-header pb-2">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-sm-6">
                <h1 class="m-0 font-weight-bold text-dark" style="font-size: 1.45rem;">
                    <i class="fa-solid fa-file-signature mr-2 text-primary"></i> Permisos y Justificaciones
                </h1>
                <div class="text-muted small mt-1">Gestión de licencias, descansos médicos, comisiones de servicio y tolerancias.</div>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right mb-0">
                    <li class="breadcrumb-item"><a href="?route=dashboard">Inicio</a></li>
                    <li class="breadcrumb-item active">Permisos y Justificaciones</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">

        <?php if (isset($_GET['msg']) || isset($_GET['error'])):
            $msgMap = [
                'guardado' => ['success', 'Justificación registrada y aprobada exitosamente.'],
                'solicitud_enviada' => ['info', 'Solicitud de justificación registrada exitosamente. Queda en estado PENDIENTE para revisión y aprobación por RRHH / Administración.'],
                'resuelto' => ['success', 'Estado de justificación actualizado correctamente.'],
                'campos_requeridos' => ['warning', 'Completa todos los campos obligatorios.'],
                'rango_invalido' => ['danger', 'La fecha de inicio no puede ser posterior a la fecha de fin.'],
                'archivo_grande' => ['danger', 'El archivo adjunto supera el tamaño máximo permitido de 5 MB.'],
                'formato_invalido' => ['danger', 'El formato del archivo adjunto no es válido (solo PDF, JPG, PNG, WEBP).'],
                'db_error' => ['danger', 'Ocurrió un error al procesar la justificación en la base de datos.'],
            ];
            $key = $_GET['msg'] ?? $_GET['error'];
            if (isset($msgMap[$key])):
                [$type, $text] = $msgMap[$key];
        ?>
            <div class="alert alert-<?= $type ?> alert-dismissible fade show mb-3 shadow-sm">
                <i class="fa-solid <?= $type === 'success' ? 'fa-circle-check' : ($type === 'info' ? 'fa-circle-info' : 'fa-triangle-exclamation') ?> mr-2"></i>
                <?= htmlspecialchars($text) ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        <?php endif; endif; ?>

        <!-- ACTIONS TOOLBAR -->
        <div class="actions-toolbar no-print">
            <div class="actions-toolbar-group">
                <h5 class="text-dark font-weight-bold mb-0" style="font-size: 1.05rem;">
                    <i class="fa-solid fa-file-signature mr-2 text-primary"></i> Solicitudes y Registros de Permiso
                </h5>
            </div>
            <div class="actions-toolbar-group">
                <?php if (in_array($userRole, ['ADMIN', 'RRHH', 'SUPERVISOR'], true)): ?>
                    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalJustificacion">
                        <i class="fa-solid fa-plus mr-1"></i> Registrar Justificación
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- MAIN TABLE CARD -->
        <div class="card">
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover datatable text-nowrap table-sm">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 70px;">N°</th>
                            <th>Empleado</th>
                            <th class="text-center">Tipo de Permiso</th>
                            <th class="text-center">Rango de Fechas</th>
                            <th>Motivo y Sustento</th>
                            <th class="text-center">Adjunto</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">Aprobado Por</th>
                            <?php if (in_array($userRole, ['ADMIN', 'RRHH'], true)): ?>
                                <th class="text-center" style="width: 90px;">Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($justificaciones as $j): ?>
                            <tr>
                                <td class="text-center text-muted font-monospace small">#<?= $j['id'] ?></td>
                                <td>
                                    <div class="font-weight-bold text-dark"><?= htmlspecialchars($j['apellidos'] . ' ' . $j['nombres']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($j['departamento_nombre'] ?? 'Sin Área') ?> &bull; DNI: <?= htmlspecialchars($j['dni']) ?></small>
                                </td>
                                <td class="text-center">
                                     <?php
                                         $tipoLabel = match($j['tipo']) {
                                             'TARDANZA' => 'Tardanza Justificada',
                                             'FALTA' => 'Inasistencia Justificada',
                                             'PERMISO_MEDICO' => 'Descanso Médico',
                                             'COMISION_SERVICIO' => 'Comisión de Servicio',
                                             'VACACIONES' => 'Vacaciones',
                                             'LICENCIA_MATERNIDAD_PATERNIDAD' => 'Licencia por Maternidad o Paternidad',
                                             default => htmlspecialchars($j['tipo'])
                                         };
                                     ?>
                                     <span class="badge-pill-custom badge-pill-neutral font-weight-bold"><?= $tipoLabel ?></span>
                                 </td>
                                <td class="text-center">
                                    <span class="font-weight-bold text-dark font-monospace small"><?= $j['fecha_inicio'] ?></span> 
                                    <?php if ($j['fecha_inicio'] !== $j['fecha_fin']): ?>
                                        <span class="text-muted small">al</span> <span class="font-weight-bold text-dark font-monospace small"><?= $j['fecha_fin'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="text-dark small font-weight-bold"><?= htmlspecialchars($j['motivo']) ?></div>
                                    <small class="text-muted font-monospace">Reg: <?= substr($j['creado_en'], 0, 16) ?></small>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($j['archivo_adjunto'])): ?>
                                        <a href="?route=justificaciones&action=ver_adjunto&id=<?= $j['id'] ?>" target="_blank" class="btn btn-outline-info btn-xs px-2" title="Ver Documento Adjunto">
                                            <i class="fa-solid fa-paperclip mr-1"></i> Ver Doc
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($j['estado'] === 'APROBADO'): ?>
                                        <span class="badge-pill-custom badge-pill-presente"><i class="fa-solid fa-check mr-1"></i> Aprobado</span>
                                    <?php elseif ($j['estado'] === 'RECHAZADO'): ?>
                                        <span class="badge-pill-custom badge-pill-falta"><i class="fa-solid fa-xmark mr-1"></i> Rechazado</span>
                                    <?php else: ?>
                                        <span class="badge-pill-custom badge-pill-tardanza"><i class="fa-solid fa-clock mr-1"></i> Pendiente</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <small class="text-muted font-weight-bold"><?= htmlspecialchars($j['aprobado_por'] ?? 'Sin aprobar') ?></small>
                                </td>
                                <?php if (in_array($userRole, ['ADMIN', 'RRHH'], true)): ?>
                                    <td class="text-center">
                                        <?php if ($j['estado'] === 'PENDIENTE'): ?>
                                            <div class="btn-group btn-group-sm" style="gap: 3px;">
                                                <form method="POST" action="?route=justificaciones&action=resolver" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="id" value="<?= $j['id'] ?>">
                                                    <input type="hidden" name="estado" value="APROBADO">
                                                    <button type="submit" class="btn btn-outline-success btn-xs px-2" title="Aprobar Solicitud"><i class="fa-solid fa-check"></i></button>
                                                </form>
                                                <form method="POST" action="?route=justificaciones&action=resolver" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="id" value="<?= $j['id'] ?>">
                                                    <input type="hidden" name="estado" value="RECHAZADO">
                                                    <button type="submit" class="btn btn-outline-danger btn-xs px-2" title="Rechazar Solicitud"><i class="fa-solid fa-xmark"></i></button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
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

<?php if (in_array($userRole, ['ADMIN', 'RRHH', 'SUPERVISOR'], true)): ?>
<!-- MODAL REGISTRAR JUSTIFICACIÓN -->
<div class="modal fade" id="modalJustificacion" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="?route=justificaciones&action=guardar" enctype="multipart/form-data" class="modal-content" onsubmit="return validateJustificacionForm(event);">
            <?= csrf_field() ?>
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold"><i class="fa-solid fa-file-signature mr-2"></i> Nueva Justificación / Permiso</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="small font-weight-bold text-secondary">Empleado</label>
                    <select name="id_empleado" class="form-control form-control-sm select2-worker" style="width: 100%;" required>
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
                        <option value="PERMISO_MEDICO">Descanso Médico</option>
                        <option value="COMISION_SERVICIO">Comisión de Servicio</option>
                        <option value="VACACIONES">Vacaciones</option>
                        <option value="LICENCIA_MATERNIDAD_PATERNIDAD">Licencia por Maternidad o Paternidad</option>
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

                <div class="form-group mb-2">
                    <label class="small font-weight-bold text-secondary">Motivo Detallado</label>
                    <textarea name="motivo" class="form-control form-control-sm" rows="3" placeholder="Ingresa el motivo o justificación..." required></textarea>
                </div>

                <div class="form-group mb-0">
                    <label class="small font-weight-bold text-secondary">Comprobante / Documento de Sustento (Opcional)</label>
                    <div class="custom-file">
                        <input type="file" name="archivo_adjunto" class="custom-file-input" id="customFileJustif" accept=".pdf,.jpg,.jpeg,.png,.webp" onchange="document.getElementById('customFileLabel').innerText = this.files[0]?.name || 'Seleccionar archivo (PDF, JPG, PNG)...'">
                        <label class="custom-file-label text-truncate small" id="customFileLabel" for="customFileJustif">Seleccionar archivo (PDF, JPG, PNG)...</label>
                    </div>
                    <small class="form-text text-muted" style="font-size: 80%;">Máximo 5 MB. Formatos permitidos: PDF, JPG, PNG, WEBP.</small>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm">
                    <?php if ($userRole === 'SUPERVISOR'): ?>
                        <i class="fa-solid fa-paper-plane mr-1"></i> Enviar Solicitud a RRHH
                    <?php else: ?>
                        <i class="fa-solid fa-floppy-disk mr-1"></i> Guardar y Aplicar
                    <?php endif; ?>
                </button>
            </div>
        </form>
    </div>
</div>
<script>
function validateJustificacionForm(e) {
    const fInicio = document.querySelector('#modalJustificacion input[name="fecha_inicio"]').value;
    const fFin = document.querySelector('#modalJustificacion input[name="fecha_fin"]').value;
    if (fInicio && fFin && fInicio > fFin) {
        if (e) e.preventDefault();
        alert('Error: La fecha de inicio (' + fInicio + ') no puede ser posterior a la fecha de fin (' + fFin + ').');
        return false;
    }
    return true;
}

$(document).ready(function() {
    if ($.fn.select2) {
        $('#modalJustificacion select[name="id_empleado"]').select2({
            theme: 'bootstrap4',
            dropdownParent: $('#modalJustificacion'),
            placeholder: '-- Seleccionar Empleado --',
            width: '100%'
        });
    }
});
</script>
<?php endif; ?>

<?php require_once APP_ROOT . '/views/layout/footer.php'; ?>
