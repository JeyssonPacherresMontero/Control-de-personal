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

        <!-- KPI SUMMARY CARDS (PANTALLA) -->
        <div class="row no-print mb-2">
            <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                <div class="kpi-card h-100">
                    <div class="kpi-card-header">
                        <div>
                            <div class="kpi-title">Total Solicitudes</div>
                            <div class="kpi-value"><?= $kpiTotal ?></div>
                            <div class="kpi-subtitle">Registros en el período</div>
                        </div>
                        <div class="kpi-icon-box">
                            <i class="fa-solid fa-file-signature"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                <div class="kpi-card h-100">
                    <div class="kpi-card-header">
                        <div>
                            <div class="kpi-title">Pendientes de Revisión</div>
                            <div class="kpi-value"><?= $kpiPendientes ?></div>
                            <div class="kpi-subtitle">Esperando resolución</div>
                        </div>
                        <div class="kpi-icon-box">
                            <i class="fa-solid fa-hourglass-half"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                <div class="kpi-card h-100">
                    <div class="kpi-card-header">
                        <div>
                            <div class="kpi-title">Aprobadas</div>
                            <div class="kpi-value"><?= $kpiAprobadas ?></div>
                            <div class="kpi-subtitle">Justificaciones validadas</div>
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
                            <div class="kpi-title">Rechazadas</div>
                            <div class="kpi-value"><?= $kpiRechazadas ?></div>
                            <div class="kpi-subtitle">Solicitudes denegadas</div>
                        </div>
                        <div class="kpi-icon-box">
                            <i class="fa-solid fa-circle-xmark"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- FILTER AND ACTIONS CARD -->
        <div class="card mb-4 no-print">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap" style="padding: 0.85rem 1.25rem;">
                <h3 class="card-title font-weight-bold text-dark mb-0 d-flex align-items-center" style="font-size: 0.92rem;">
                    <i class="fa-solid fa-filter mr-2" style="color: #1e40af;"></i> Filtros de Justificaciones y Permisos
                </h3>
                <div class="d-flex align-items-center flex-wrap" style="gap: 8px; margin-left: auto;">
                    <?php if (in_array($userRole, ['ADMIN', 'RRHH', 'SUPERVISOR'], true)): ?>
                        <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalJustificacion">
                            <i class="fa-solid fa-plus mr-1"></i> Registrar Justificación
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body p-4">
                <form method="GET" action="" class="row align-items-end">
                    <input type="hidden" name="route" value="justificaciones">

                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <label class="form-label-custom"><i class="fa-regular fa-calendar"></i> Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" class="form-control" value="<?= htmlspecialchars($fechaInicio) ?>">
                    </div>

                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <label class="form-label-custom"><i class="fa-regular fa-calendar-check"></i> Fecha Fin</label>
                        <input type="date" name="fecha_fin" class="form-control" value="<?= htmlspecialchars($fechaFin) ?>">
                    </div>

                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <label class="form-label-custom"><i class="fa-solid fa-building"></i> Área / Dpto.</label>
                        <select name="departamento_id" class="form-control">
                            <option value="">-- Todas las Áreas --</option>
                            <?php foreach ($departamentos as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= ($deptoId ?? '') == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <label class="form-label-custom"><i class="fa-solid fa-tag"></i> Estado</label>
                        <select name="estado" class="form-control">
                            <option value="TODOS" <?= ($estado ?? '') === 'TODOS' ? 'selected' : '' ?>>-- Todos los Estados --</option>
                            <option value="PENDIENTE" <?= ($estado ?? '') === 'PENDIENTE' ? 'selected' : '' ?>>Pendientes</option>
                            <option value="APROBADO" <?= ($estado ?? '') === 'APROBADO' ? 'selected' : '' ?>>Aprobados</option>
                            <option value="RECHAZADO" <?= ($estado ?? '') === 'RECHAZADO' ? 'selected' : '' ?>>Rechazados</option>
                        </select>
                    </div>

                    <div class="col-lg-3 col-md-5 col-sm-8 mb-3">
                        <label class="form-label-custom"><i class="fa-solid fa-magnifying-glass"></i> Buscar Empleado / Motivo</label>
                        <input type="text" name="search" class="form-control" placeholder="Nombre, DNI, sustento..." value="<?= htmlspecialchars($search ?? '') ?>">
                    </div>

                    <div class="col-lg-1 col-md-3 col-sm-4 mb-3 d-flex" style="gap: 6px;">
                        <button type="submit" class="btn btn-primary flex-fill" title="Filtrar resultados" style="height: 38px;">
                            <i class="fa-solid fa-filter"></i>
                        </button>
                        <a href="?route=justificaciones" class="btn btn-outline-secondary" title="Limpiar filtros" style="height: 38px; width: 38px; display: inline-flex; align-items: center; justify-content: center; padding: 0;">
                            <i class="fa-solid fa-rotate-left"></i>
                        </a>
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
                                     <?php if (!empty($j['comision_destino'])): ?>
                                         <div class="small font-weight-bold text-primary mt-1" title="Comisión de Usuarios: <?= htmlspecialchars($j['comision_destino']) ?>">
                                             <i class="fa-solid fa-map-location-dot mr-1"></i><?= htmlspecialchars(mb_strimwidth($j['comision_destino'], 0, 26, '...')) ?>
                                         </div>
                                     <?php endif; ?>
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
<div class="modal fade" id="modalJustificacion" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 620px;">
        <form method="POST" action="?route=justificaciones&action=guardar" enctype="multipart/form-data" class="modal-content shadow-lg border-0" onsubmit="return validateJustificacionForm(event);">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold"><i class="fa-solid fa-file-signature mr-2"></i> Registrar Justificación</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body p-4">
                <div class="form-group mb-3">
                    <label class="small font-weight-bold text-secondary">Empleado <span class="text-danger">*</span></label>
                    <select name="id_empleado" class="form-control select2-worker" style="width: 100%;" required>
                        <option value="">-- Seleccionar Empleado --</option>
                        <?php foreach ($empleados as $e): ?>
                            <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['apellidos'] . ' ' . $e['nombres']) ?> (DNI: <?= htmlspecialchars($e['dni']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group mb-3">
                    <label class="small font-weight-bold text-secondary">Tipo de Justificación <span class="text-danger">*</span></label>
                    <select name="tipo" id="justModal_tipo" class="form-control font-weight-bold" onchange="onJustModalTipoChanged(this.value)" required>
                        <option value="TARDANZA">Tardanza Justificada</option>
                        <option value="FALTA">Inasistencia Justificada</option>
                        <option value="PERMISO_MEDICO">Descanso Médico</option>
                        <option value="COMISION_SERVICIO">Comisión de Servicio (Otras Comisiones)</option>
                        <option value="VACACIONES">Vacaciones</option>
                        <option value="LICENCIA_MATERNIDAD_PATERNIDAD">Licencia por Maternidad o Paternidad</option>
                        <option value="OTRO">Otro Motivo</option>
                    </select>
                </div>

                <!-- SELECTOR COMISIÓN DE DESTINO (JUSHSAL) -->
                <div class="form-group mb-3 p-3 rounded" id="justModal_comision_container" style="display: none; background-color: #f8fafc; border: 1px solid #e2e8f0;">
                    <label class="small font-weight-bold text-dark mb-1">
                        <i class="fa-solid fa-map-location-dot mr-1 text-secondary"></i> Comisión de Usuarios de Destino (JUSHSAL) <span class="text-danger">*</span>
                    </label>
                    <select name="comision_destino" id="justModal_comision_destino" class="form-control font-weight-bold">
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
                    <small class="text-muted d-block mt-1">Horario oficial automático: 08:00 - 13:00 y 13:45 - 17:00 (8h 15m laboradas).</small>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-secondary">Fecha Desde <span class="text-danger">*</span></label>
                            <input type="date" name="fecha_inicio" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-secondary">Fecha Hasta <span class="text-danger">*</span></label>
                            <input type="date" name="fecha_fin" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label class="small font-weight-bold text-secondary">Motivo Detallado <span class="text-danger">*</span></label>
                    <textarea name="motivo" class="form-control" rows="3" placeholder="Ingresa el motivo o justificación..." required></textarea>
                </div>

                <div class="form-group mb-0">
                    <label class="small font-weight-bold text-secondary">Documento de Sustento (Opcional)</label>
                    <div class="custom-file">
                        <input type="file" name="archivo_adjunto" class="custom-file-input" id="customFileJustif" accept=".pdf,.jpg,.jpeg,.png,.webp" onchange="document.getElementById('customFileLabel').innerText = this.files[0]?.name || 'Seleccionar archivo (PDF, JPG, PNG)...'">
                        <label class="custom-file-label text-truncate small" id="customFileLabel" for="customFileJustif">Seleccionar archivo (PDF, JPG, PNG)...</label>
                    </div>
                    <small class="form-text text-muted" style="font-size: 80%;">Máximo 5 MB. Formatos permitidos: PDF, JPG, PNG, WEBP.</small>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm px-4 font-weight-bold">
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
function onJustModalTipoChanged(val) {
    const box = document.getElementById('justModal_comision_container');
    const sel = document.getElementById('justModal_comision_destino');
    if (val === 'COMISION_SERVICIO') {
        if (box) box.style.display = 'block';
        if (sel) sel.setAttribute('required', 'required');
    } else {
        if (box) box.style.display = 'none';
        if (sel) {
            sel.removeAttribute('required');
            sel.value = '';
        }
    }
}

function validateJustificacionForm(e) {
    const fInicio = document.querySelector('#modalJustificacion input[name="fecha_inicio"]').value;
    const fFin = document.querySelector('#modalJustificacion input[name="fecha_fin"]').value;
    if (fInicio && fFin && fInicio > fFin) {
        if (e) e.preventDefault();
        alert('Error: La fecha de inicio (' + fInicio + ') no puede ser posterior a la fecha de fin (' + fFin + ').');
        return false;
    }
    const tipo = document.getElementById('justModal_tipo')?.value;
    if (tipo === 'COMISION_SERVICIO') {
        const dest = document.getElementById('justModal_comision_destino')?.value;
        if (!dest) {
            if (e) e.preventDefault();
            alert('Por favor selecciona la Comisión de Usuarios de destino.');
            document.getElementById('justModal_comision_destino')?.focus();
            return false;
        }
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
