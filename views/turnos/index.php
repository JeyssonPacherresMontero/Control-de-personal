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
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title font-weight-bold">
                                <i class="fa-solid fa-calendar-day mr-2 text-primary"></i>
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
                        <div class="card-body py-3 d-flex flex-column justify-content-between">
                            <ul class="list-group list-group-flush mb-3 small">
                                <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-0 border-0 border-bottom">
                                    <span class="text-secondary"><i class="fa-solid fa-arrow-right-to-bracket text-success mr-2"></i> Hora de Entrada:</span>
                                    <span class="font-weight-bold font-monospace text-success" style="font-size: 0.95rem;"><?= substr($t['hora_entrada'], 0, 5) ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-0 border-0 border-bottom">
                                    <span class="text-secondary"><i class="fa-solid fa-arrow-right-from-bracket text-primary mr-2"></i> Hora de Salida:</span>
                                    <span class="font-weight-bold font-monospace text-primary" style="font-size: 0.95rem;"><?= substr($t['hora_salida'], 0, 5) ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-0 border-0 border-bottom">
                                     <span class="text-secondary"><i class="fa-solid fa-stopwatch text-warning mr-2"></i> Tolerancia Entrada:</span>
                                     <span class="badge-pill-custom badge-pill-tardanza"><?= $t['tolerancia_minutos'] ?> min</span>
                                 </li>
                                 <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-0 border-0">
                                     <span class="text-secondary"><i class="fa-solid fa-utensils text-secondary mr-2"></i> Refrigerio:</span>
                                     <span class="font-weight-bold text-dark"><?= $t['minutos_refrigerio'] ?> min <?= $t['hora_inicio_refrigerio'] ? '(' . substr($t['hora_inicio_refrigerio'], 0, 5) . ')' : '(Flexible)' ?></span>
                                 </li>
                            </ul>

                            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                <span class="small text-muted"><i class="fa-solid fa-users mr-1 text-primary"></i> <b><?= $t['total_empleados'] ?></b> trabajadores</span>
                                <button class="btn btn-outline-primary btn-sm" onclick="openEditTurnoModal(<?= htmlspecialchars(json_encode($t)) ?>)">
                                     <i class="fa-solid fa-pen mr-1"></i> Editar Horario
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
</section>

<!-- MODAL CONFIGURACIÓN DE TURNO -->
<div class="modal fade" id="modalTurno" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="?route=turnos&action=guardar" class="modal-content">
            <?= csrf_field() ?>
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold" id="turnoModalTitle">Configuración de Turno</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="tur_id">
                
                <div class="form-group">
                    <label class="small font-weight-bold text-secondary">Nombre del Turno</label>
                    <input type="text" name="nombre" id="tur_nombre" class="form-control form-control-sm" placeholder="Ej: Turno Administrativo (08:00 - 17:00)" required>
                </div>

                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label class="small font-weight-bold text-success"><i class="fa-solid fa-arrow-right-to-bracket mr-1"></i> Hora de Ingreso</label>
                            <input type="time" name="hora_entrada" id="tur_entrada" class="form-control form-control-sm" required oninput="updateTolerancePreview()">
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label class="small font-weight-bold text-primary"><i class="fa-solid fa-arrow-right-from-bracket mr-1"></i> Hora de Salida</label>
                            <input type="time" name="hora_salida" id="tur_salida" class="form-control form-control-sm" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label class="small font-weight-bold text-warning"><i class="fa-solid fa-stopwatch mr-1"></i> Tolerancia Tardanza (min)</label>
                            <input type="number" name="tolerancia_minutos" id="tur_tolerancia" class="form-control form-control-sm" value="10" min="0" required oninput="updateTolerancePreview()">
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label class="small font-weight-bold text-danger"><i class="fa-solid fa-ban mr-1"></i> Límite para Falta (min)</label>
                            <input type="number" name="tolerancia_falta_minutos" id="tur_tolfalta" class="form-control form-control-sm" value="60" min="0" required>
                        </div>
                    </div>
                </div>

                <!-- CALLOUT EXPLICATIVO DE REGLA DE ASISTENCIA -->
                <div class="alert alert-info py-2 px-3 mb-3 small" style="border-left: 4px solid #17a2b8;">
                    <div class="font-weight-bold mb-1"><i class="fa-solid fa-circle-info mr-1"></i> Regla de Evaluación de Tardanza:</div>
                    <div id="toleranceHelpText">
                        El personal que ingrese hasta <strong id="previewGraceTime">08:10</strong> se marcará como <strong>Presente</strong>. Pasando este límite, el sistema lo marcará como <strong>Tardanza</strong>.
                    </div>
                </div>

                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label class="small font-weight-bold text-secondary"><i class="fa-solid fa-utensils mr-1"></i> Inicio de Refrigerio</label>
                            <input type="time" name="hora_inicio_refrigerio" id="tur_ref_ini" class="form-control form-control-sm">
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label class="small font-weight-bold text-secondary"><i class="fa-solid fa-utensils mr-1"></i> Fin de Refrigerio</label>
                            <input type="time" name="hora_fin_refrigerio" id="tur_ref_fin" class="form-control form-control-sm">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="small font-weight-bold text-secondary">Tiempo de Refrigerio a Descontar (minutos)</label>
                    <input type="number" name="minutos_refrigerio" id="tur_ref_min" class="form-control form-control-sm" value="60" min="0">
                </div>

                <div class="form-group">
                    <label class="small font-weight-bold text-secondary d-block">Días Laborables</label>
                    <div class="btn-group btn-group-toggle d-flex flex-wrap" data-toggle="buttons">
                        <label class="btn btn-outline-secondary btn-sm"><input type="checkbox" name="dias_laborables[]" value="1" id="dia_1"> Lun</label>
                        <label class="btn btn-outline-secondary btn-sm"><input type="checkbox" name="dias_laborables[]" value="2" id="dia_2"> Mar</label>
                        <label class="btn btn-outline-secondary btn-sm"><input type="checkbox" name="dias_laborables[]" value="3" id="dia_3"> Mié</label>
                        <label class="btn btn-outline-secondary btn-sm"><input type="checkbox" name="dias_laborables[]" value="4" id="dia_4"> Jue</label>
                        <label class="btn btn-outline-secondary btn-sm"><input type="checkbox" name="dias_laborables[]" value="5" id="dia_5"> Vie</label>
                        <label class="btn btn-outline-secondary btn-sm"><input type="checkbox" name="dias_laborables[]" value="6" id="dia_6"> Sáb</label>
                        <label class="btn btn-outline-secondary btn-sm"><input type="checkbox" name="dias_laborables[]" value="7" id="dia_7"> Dom</label>
                    </div>
                </div>

                <div class="row">
                    <div class="col-6">
                        <div class="form-group custom-control custom-checkbox">
                            <input class="custom-control-input" type="checkbox" name="es_nocturno" id="tur_nocturno" value="1">
                            <label class="custom-control-label small font-weight-bold" for="tur_nocturno">Turno Nocturno</label>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group custom-control custom-checkbox">
                            <input class="custom-control-input" type="checkbox" name="activo" id="tur_activo" value="1" checked>
                            <label class="custom-control-label small font-weight-bold" for="tur_activo">Activo</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-floppy-disk mr-1"></i> Guardar Turno</button>
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
            helpEl.innerHTML = `El personal que ingrese hasta las <strong class="text-success">${limitStr}</strong> (Entrada: ${entrada} + ${tol} min) se registrará como <span class="badge badge-success">Presente</span>. A partir de las <strong class="text-danger">${limitStr} con 1 segundo</strong>, el sistema lo marcará como <span class="badge badge-warning text-white">Tardanza</span>.`;
        }
    }
}

function openNewTurnoModal() {
    document.getElementById('turnoModalTitle').innerText = 'Crear Nuevo Turno';
    document.getElementById('tur_id').value = '';
    document.getElementById('tur_nombre').value = '';
    document.getElementById('tur_entrada').value = '08:00';
    document.getElementById('tur_salida').value = '17:00';
    document.getElementById('tur_tolerancia').value = '10';
    document.getElementById('tur_tolfalta').value = '60';
    document.getElementById('tur_ref_ini').value = '13:00';
    document.getElementById('tur_ref_fin').value = '14:00';
    document.getElementById('tur_ref_min').value = '60';
    document.getElementById('tur_nocturno').checked = false;
    document.getElementById('tur_activo').checked = true;
    
    for (let i=1; i<=5; i++) document.getElementById('dia_' + i).checked = true;
    for (let i=6; i<=7; i++) document.getElementById('dia_' + i).checked = false;

    updateTolerancePreview();
    $('#modalTurno').modal('show');
}

function openEditTurnoModal(t) {
    document.getElementById('turnoModalTitle').innerText = 'Editar Turno Laboral';
    document.getElementById('tur_id').value = t.id;
    document.getElementById('tur_nombre').value = t.nombre;
    document.getElementById('tur_entrada').value = t.hora_entrada.substr(0, 5);
    document.getElementById('tur_salida').value = t.hora_salida.substr(0, 5);
    document.getElementById('tur_tolerancia').value = t.tolerancia_minutos;
    document.getElementById('tur_tolfalta').value = t.tolerancia_falta_minutos || 60;
    document.getElementById('tur_ref_ini').value = t.hora_inicio_refrigerio ? t.hora_inicio_refrigerio.substr(0, 5) : '';
    document.getElementById('tur_ref_fin').value = t.hora_fin_refrigerio ? t.hora_fin_refrigerio.substr(0, 5) : '';
    document.getElementById('tur_ref_min').value = t.minutos_refrigerio || 60;
    document.getElementById('tur_nocturno').checked = (parseInt(t.es_nocturno) === 1);
    document.getElementById('tur_activo').checked = (parseInt(t.activo) === 1);

    const dias = (t.dias_laborables || '').split(',');
    for (let i=1; i<=7; i++) {
        document.getElementById('dia_' + i).checked = dias.includes(i.toString());
    }

    updateTolerancePreview();
    $('#modalTurno').modal('show');
}
</script>

<?php require_once APP_ROOT . '/views/layout/footer.php'; ?>
