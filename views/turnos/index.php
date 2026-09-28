<?php require_once APP_ROOT . '/views/layout/header.php'; ?>

<!-- Content Header (Page header) -->
<div class="content-header pb-2">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-sm-6">
                <h1 class="m-0 font-weight-bold text-dark" style="font-size: 1.45rem;">
                    <i class="fa-solid fa-business-time mr-2 text-primary"></i> Turnos y Horarios Laborales
                </h1>
                <div class="text-muted small mt-1">Definición de jornadas laborales, horarios de refrigerio y márgenes de tolerancia.</div>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right mb-0">
                    <li class="breadcrumb-item"><a href="?route=dashboard">Inicio</a></li>
                    <li class="breadcrumb-item active">Turnos y Horarios</li>
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
                'guardado' => ['success', 'Turno guardado correctamente.'],
                'eliminado' => ['success', 'Turno eliminado correctamente.'],
                'duplicado' => ['danger', 'Ya existe un turno con ese nombre.'],
                'campos_requeridos' => ['warning', 'Completa todos los campos obligatorios.'],
                'horario_invalido' => ['warning', 'La hora de entrada debe ser menor a la hora de salida para turnos regulares (no nocturnos).'],
                'error_interno' => ['danger', 'Ocurrió un error al procesar el turno. Intenta nuevamente.'],
            ];
            if (isset($msgMap[$_GET['msg']])):
                [$type, $text] = $msgMap[$_GET['msg']];
        ?>
            <div class="alert alert-<?= $type ?> alert-dismissible fade show mb-3 shadow-sm">
                <?= htmlspecialchars($text) ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        <?php endif; endif; ?>

        <!-- ACTIONS TOOLBAR -->
        <div class="actions-toolbar no-print">
            <div class="actions-toolbar-group">
                <h5 class="text-dark font-weight-bold mb-0" style="font-size: 1.05rem;">
                    <i class="fa-solid fa-business-time mr-2 text-primary"></i> Horarios Laborales Registrados
                </h5>
            </div>
            <div class="actions-toolbar-group">
                <button class="btn btn-primary btn-sm" onclick="openNewTurnoModal()">
                    <i class="fa-solid fa-plus mr-1"></i> Crear Nuevo Turno
                </button>
            </div>
        </div>

        <div class="row">
            <?php foreach ($turnos as $t): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100 shadow-sm border">
                        <div class="card-header d-flex justify-content-between align-items-center py-3">
                            <h3 class="card-title font-weight-bold" style="font-size: 0.95rem; color: #0f172a;">
                                <i class="fa-solid fa-calendar-day mr-2" style="color: #1e40af;"></i>
                                <?= htmlspecialchars($t['nombre']) ?>
                            </h3>
                            <div class="card-tools">
                                <?php if ($t['activo']): ?>
                                    <span class="badge-pill-custom badge-pill-online"><i class="fa-solid fa-circle" style="font-size: 6px;"></i> Activo</span>
                                <?php else: ?>
                                    <span class="badge-pill-custom badge-pill-offline"><i class="fa-solid fa-circle" style="font-size: 6px;"></i> Inactivo</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body p-3 d-flex flex-column justify-content-between">
                            <div class="d-flex flex-column mb-2" style="gap: 4px;">
                                <div class="d-flex justify-content-between align-items-center py-2 border-bottom" style="border-color: #f1f5f9 !important;">
                                    <span class="text-muted small font-weight-medium">
                                        <i class="fa-solid fa-calendar-week mr-2" style="color: #1e40af; width: 16px;"></i> Lunes a Viernes
                                    </span>
                                    <span class="font-weight-bold font-monospace" style="color: #0f172a; font-size: 0.92rem;">
                                        <?= substr($t['hora_entrada'], 0, 5) ?> &ndash; <?= substr($t['hora_salida'], 0, 5) ?>
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center py-2 border-bottom" style="border-color: #f1f5f9 !important;">
                                    <span class="text-muted small font-weight-medium">
                                        <i class="fa-solid fa-business-time mr-2" style="color: #64748b; width: 16px;"></i> Sábados
                                    </span>
                                    <span class="font-weight-bold font-monospace" style="color: #0f172a; font-size: 0.92rem;">
                                        <?php if (!empty($t['hora_salida_sabado'])): ?>
                                            <?= substr($t['hora_entrada_sabado'] ?? $t['hora_entrada'], 0, 5) ?> &ndash; <?= substr($t['hora_salida_sabado'], 0, 5) ?>
                                        <?php else: ?>
                                            <span class="text-muted font-weight-normal small">No laborable</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center py-2 border-bottom" style="border-color: #f1f5f9 !important;">
                                    <span class="text-muted small font-weight-medium">
                                        <i class="fa-solid fa-utensils mr-2" style="color: #64748b; width: 16px;"></i> Refrigerio (L-V)
                                    </span>
                                    <span class="font-weight-semibold" style="color: #334155; font-size: 0.85rem;">
                                        <?= $t['minutos_refrigerio'] ?> min <?= $t['hora_inicio_refrigerio'] ? '(' . substr($t['hora_inicio_refrigerio'], 0, 5) . ' &ndash; ' . substr($t['hora_fin_refrigerio'], 0, 5) . ')' : '(Flexible)' ?>
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center py-2">
                                    <span class="text-muted small font-weight-medium">
                                        <i class="fa-solid fa-stopwatch mr-2" style="color: #64748b; width: 16px;"></i> Margen Tolerancia
                                    </span>
                                    <span class="badge-pill-custom badge-pill-neutral font-weight-bold" style="font-size: 0.78rem;">
                                        <?= $t['tolerancia_minutos'] ?> min
                                    </span>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-2" style="border-color: #e2e8f0 !important;">
                                <span class="small text-muted font-weight-medium">
                                    <i class="fa-solid fa-users mr-1.5" style="color: #1e40af;"></i> <b><?= $t['total_empleados'] ?></b> colaboradores
                                </span>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-secondary btn-sm px-2.5" onclick="openEditTurnoModal(<?= htmlspecialchars(json_encode($t)) ?>)">
                                        <i class="fa-solid fa-pen mr-1 text-primary"></i> Editar
                                    </button>
                                    <?php if ($currentUser['rol'] === 'ADMIN' || $currentUser['rol'] === 'RRHH'): ?>
                                        <button type="button" class="btn btn-outline-secondary btn-sm px-2 text-danger" onclick="confirmDeleteTurno(<?= $t['id'] ?>, '<?= htmlspecialchars(addslashes($t['nombre'])) ?>', <?= (int)$t['total_empleados'] ?>)" title="Eliminar / Desactivar Turno">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
</section>

<!-- Formulario oculto para eliminación segura de turnos -->
<form id="formDeleteTurno" method="POST" action="?route=turnos&action=eliminar" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="id" id="delete_turno_id">
</form>

<!-- MODAL CONFIGURACIÓN DE TURNO -->
<div class="modal fade" id="modalTurno" tabindex="-1" role="dialog" aria-labelledby="turnoModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 680px;">
        <form method="POST" action="?route=turnos&action=guardar" class="modal-content shadow-lg border-0" onsubmit="return validateTurnoForm(event)">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold" id="turnoModalTitle">
                    <i class="fa-solid fa-clock mr-2"></i> Configuración de Turno
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="id" id="tur_id">
                
                <div class="form-group mb-3">
                    <label class="small font-weight-bold">Nombre del Turno <span class="text-danger">*</span></label>
                    <input type="text" name="nombre" id="tur_nombre" class="form-control" placeholder="Ej: Turno Administrativo (08:00 - 17:00)" required>
                </div>

                <div class="modal-section-title">
                    <i class="fa-solid fa-business-time"></i> Jornada Laboral (Lunes a Viernes)
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold"><i class="fa-solid fa-arrow-right-to-bracket mr-1"></i> Hora de Ingreso <span class="text-danger">*</span></label>
                            <input type="time" name="hora_entrada" id="tur_entrada" class="form-control" required oninput="updateTolerancePreview()">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold"><i class="fa-solid fa-arrow-right-from-bracket mr-1"></i> Hora de Salida <span class="text-danger">*</span></label>
                            <input type="time" name="hora_salida" id="tur_salida" class="form-control" required>
                        </div>
                    </div>
                </div>

                <div class="modal-section-title">
                    <i class="fa-solid fa-stopwatch"></i> Tolerancias y Criterio de Puntualidad
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-2">
                            <label class="small font-weight-bold"><i class="fa-solid fa-clock-rotate-left mr-1"></i> Tolerancia Tardanza (minutos) <span class="text-danger">*</span></label>
                            <input type="number" name="tolerancia_minutos" id="tur_tolerancia" class="form-control" value="10" min="0" required oninput="updateTolerancePreview()">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-2">
                            <label class="small font-weight-bold"><i class="fa-solid fa-ban mr-1"></i> Límite para Falta Injustificada (minutos) <span class="text-danger">*</span></label>
                            <input type="number" name="tolerancia_falta_minutos" id="tur_tolfalta" class="form-control" value="60" min="0" required>
                        </div>
                    </div>
                </div>

                <div class="small text-muted mb-3" style="line-height: 1.45;">
                    <i class="fa-solid fa-circle-info text-secondary mr-1"></i>
                    <span id="toleranceHelpText">Ingreso oficial hasta las <strong class="text-dark" id="previewGraceTime">08:10</strong> se registra como puntual. Posterior a este margen, se computará como tardanza oficial.</span>
                </div>

                <div class="modal-section-title">
                    <i class="fa-solid fa-utensils"></i> Refrigerio y Horario Especial Sábados
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold">Inicio Refrigerio (Lun-Vie)</label>
                            <input type="time" name="hora_inicio_refrigerio" id="tur_ref_ini" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold">Fin Refrigerio (Lun-Vie)</label>
                            <input type="time" name="hora_fin_refrigerio" id="tur_ref_fin" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold">Duración Refrigerio (min)</label>
                            <input type="number" name="minutos_refrigerio" id="tur_ref_min" class="form-control" value="45" min="0">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold"><i class="fa-solid fa-calendar-day mr-1"></i> Ingreso Sábado</label>
                            <input type="time" name="hora_entrada_sabado" id="tur_ent_sab" class="form-control" value="08:00">
                            <small class="text-muted d-block mt-1">Sábados sin descuento de refrigerio</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold"><i class="fa-solid fa-calendar-day mr-1"></i> Salida Sábado</label>
                            <input type="time" name="hora_salida_sabado" id="tur_sal_sab" class="form-control" value="13:00">
                            <small class="text-muted d-block mt-1">Jornada reducida (2 marcaciones)</small>
                        </div>
                    </div>
                </div>

                <div class="modal-section-title">
                    <i class="fa-solid fa-calendar-week"></i> Días Laborables y Estado del Turno
                </div>

                <div class="form-group mb-3">
                    <label class="small font-weight-bold d-block mb-2">Seleccione los días de cumplimiento de este turno:</label>
                    <div class="d-flex flex-wrap" style="gap: 8px;">
                        <label class="btn btn-outline-secondary btn-sm mb-0 flex-fill text-center d-flex align-items-center justify-content-center py-2" style="border-radius: 6px; font-weight: 500;">
                            <input type="checkbox" name="dias_laborables[]" value="1" id="dia_1" class="mr-1.5"> Lun
                        </label>
                        <label class="btn btn-outline-secondary btn-sm mb-0 flex-fill text-center d-flex align-items-center justify-content-center py-2" style="border-radius: 6px; font-weight: 500;">
                            <input type="checkbox" name="dias_laborables[]" value="2" id="dia_2" class="mr-1.5"> Mar
                        </label>
                        <label class="btn btn-outline-secondary btn-sm mb-0 flex-fill text-center d-flex align-items-center justify-content-center py-2" style="border-radius: 6px; font-weight: 500;">
                            <input type="checkbox" name="dias_laborables[]" value="3" id="dia_3" class="mr-1.5"> Mié
                        </label>
                        <label class="btn btn-outline-secondary btn-sm mb-0 flex-fill text-center d-flex align-items-center justify-content-center py-2" style="border-radius: 6px; font-weight: 500;">
                            <input type="checkbox" name="dias_laborables[]" value="4" id="dia_4" class="mr-1.5"> Jue
                        </label>
                        <label class="btn btn-outline-secondary btn-sm mb-0 flex-fill text-center d-flex align-items-center justify-content-center py-2" style="border-radius: 6px; font-weight: 500;">
                            <input type="checkbox" name="dias_laborables[]" value="5" id="dia_5" class="mr-1.5"> Vie
                        </label>
                        <label class="btn btn-outline-secondary btn-sm mb-0 flex-fill text-center d-flex align-items-center justify-content-center py-2" style="border-radius: 6px; font-weight: 500;">
                            <input type="checkbox" name="dias_laborables[]" value="6" id="dia_6" class="mr-1.5"> Sáb
                        </label>
                        <label class="btn btn-outline-secondary btn-sm mb-0 flex-fill text-center d-flex align-items-center justify-content-center py-2" style="border-radius: 6px; font-weight: 500;">
                            <input type="checkbox" name="dias_laborables[]" value="7" id="dia_7" class="mr-1.5"> Dom
                        </label>
                    </div>
                </div>

                <div class="row pt-2">
                    <div class="col-md-6">
                        <div class="custom-control custom-checkbox mb-2">
                            <input class="custom-control-input" type="checkbox" name="es_nocturno" id="tur_nocturno" value="1">
                            <label class="custom-control-label small font-weight-bold" for="tur_nocturno">Turno Nocturno (cruza medianoche)</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="custom-control custom-checkbox mb-2">
                            <input class="custom-control-input" type="checkbox" name="activo" id="tur_activo" value="1" checked>
                            <label class="custom-control-label small font-weight-bold" for="tur_activo">Turno Activo para Asignaciones</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm px-4 font-weight-bold"><i class="fa-solid fa-floppy-disk mr-1"></i> Guardar Turno</button>
            </div>
        </form>
    </div>
</div>

<script>
function updateTolerancePreview() {
    const entrada = document.getElementById('tur_entrada').value;
    const tol = parseInt(document.getElementById('tur_tolerancia').value) || 0;
    
    if (entrada) {
        const parts = entrada.split(':');
        const h = parseInt(parts[0], 10);
        const m = parseInt(parts[1], 10);
        
        let totalMin = h * 60 + m + tol;
        let newH = Math.floor(totalMin / 60) % 24;
        let newM = totalMin % 60;
        
        const limitStr = String(newH).padStart(2, '0') + ':' + String(newM).padStart(2, '0');
        const previewEl = document.getElementById('previewGraceTime');
        if (previewEl) previewEl.innerText = limitStr;
        
        const helpEl = document.getElementById('toleranceHelpText');
        if (helpEl) {
            helpEl.innerHTML = `Ingreso oficial hasta las <strong class="text-dark">${limitStr}</strong> (Entrada ${entrada} + ${tol}m de tolerancia) se registra como puntual. Posterior a este margen, se computará como tardanza sujeta a descuento.`;
        }
    }
}

function openNewTurnoModal() {
    document.getElementById('turnoModalTitle').innerText = 'Crear Nuevo Turno';
    document.getElementById('tur_id').value = '';
    document.getElementById('tur_nombre').value = '';
    document.getElementById('tur_entrada').value = '08:00';
    document.getElementById('tur_salida').value = '17:00';
    document.getElementById('tur_ent_sab').value = '08:00';
    document.getElementById('tur_sal_sab').value = '13:00';
    document.getElementById('tur_tolerancia').value = '10';
    document.getElementById('tur_tolfalta').value = '60';
    document.getElementById('tur_ref_ini').value = '13:00';
    document.getElementById('tur_ref_fin').value = '13:45';
    document.getElementById('tur_ref_min').value = '45';
    document.getElementById('tur_nocturno').checked = false;
    document.getElementById('tur_activo').checked = true;
    
    for (let i=1; i<=6; i++) document.getElementById('dia_' + i).checked = true;
    document.getElementById('dia_7').checked = false;

    updateTolerancePreview();
    $('#modalTurno').modal('show');
}

function openEditTurnoModal(t) {
    document.getElementById('turnoModalTitle').innerText = 'Editar Turno Laboral';
    document.getElementById('tur_id').value = t.id;
    document.getElementById('tur_nombre').value = t.nombre;
    document.getElementById('tur_entrada').value = t.hora_entrada ? t.hora_entrada.substr(0, 5) : '08:00';
    document.getElementById('tur_salida').value = t.hora_salida ? t.hora_salida.substr(0, 5) : '17:00';
    document.getElementById('tur_ent_sab').value = t.hora_entrada_sabado ? t.hora_entrada_sabado.substr(0, 5) : (t.hora_entrada ? t.hora_entrada.substr(0, 5) : '08:00');
    document.getElementById('tur_sal_sab').value = t.hora_salida_sabado ? t.hora_salida_sabado.substr(0, 5) : '13:00';
    document.getElementById('tur_tolerancia').value = t.tolerancia_minutos;
    document.getElementById('tur_tolfalta').value = t.tolerancia_falta_minutos || 60;
    document.getElementById('tur_ref_ini').value = t.hora_inicio_refrigerio ? t.hora_inicio_refrigerio.substr(0, 5) : '';
    document.getElementById('tur_ref_fin').value = t.hora_fin_refrigerio ? t.hora_fin_refrigerio.substr(0, 5) : '';
    document.getElementById('tur_ref_min').value = t.minutos_refrigerio || 45;
    document.getElementById('tur_nocturno').checked = (parseInt(t.es_nocturno) === 1);
    document.getElementById('tur_activo').checked = (parseInt(t.activo) === 1);

    const dias = (t.dias_laborables || '').split(',');
    for (let i=1; i<=7; i++) {
        document.getElementById('dia_' + i).checked = dias.includes(i.toString());
    }

    updateTolerancePreview();
    $('#modalTurno').modal('show');
}

function validateTurnoForm(e) {

    const entrada = document.getElementById('tur_entrada').value;
    const salida = document.getElementById('tur_salida').value;
    const esNocturno = document.getElementById('tur_nocturno').checked;

    if (!esNocturno && entrada && salida) {
        if (entrada >= salida) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Horario Inválido',
                text: 'En turnos regulares (no nocturnos), la hora de entrada debe ser menor a la hora de salida.',
                confirmButtonColor: '#1d4ed8'
            });
            return false;
        }
    }
    return true;
}

function confirmDeleteTurno(id, nombre, totalEmpleados) {
    const msg = totalEmpleados > 0
        ? `El turno "${nombre}" tiene ${totalEmpleados} empleado(s) asignado(s). Será desactivado para proteger la consistencia de los contratos y registros.`
        : `¿Estás seguro de eliminar el turno "${nombre}"?`;

    Swal.fire({
        title: 'Confirmar Acción',
        text: msg,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, continuar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('delete_turno_id').value = id;
            document.getElementById('formDeleteTurno').submit();
        }
    });
}
</script>


<?php require_once APP_ROOT . '/views/layout/footer.php'; ?>
