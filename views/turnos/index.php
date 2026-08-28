<?php require_once APP_ROOT . '/views/layout/header.php'; ?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-sm-6">
                <h1 class="m-0 font-weight-bold"><i class="fa-solid fa-business-time mr-2 text-primary"></i> Turnos y Horarios Laborales</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="?route=dashboard">Inicio</a></li>
                    <li class="breadcrumb-item active">Turnos</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="text-secondary font-weight-bold mb-0">Horarios Configurados</h5>
            <button class="btn btn-primary btn-sm shadow-sm" onclick="openNewTurnoModal()">
                <i class="fa-solid fa-plus mr-1"></i> Crear Nuevo Turno
            </button>
        </div>

        <div class="row">
            <?php foreach ($turnos as $t): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card card-outline card-info shadow-sm">
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold"><?= htmlspecialchars($t['nombre']) ?></h3>
                            <div class="card-tools">
                                <?php if ($t['activo']): ?>
                                    <span class="badge badge-success px-2 py-1">Activo</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary px-2 py-1">Inactivo</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body py-2">
                            <ul class="list-group list-group-unbordered mb-3 small">
                                <li class="list-group-item d-flex justify-content-between py-1">
                                    <b class="text-secondary"><i class="fa-solid fa-arrow-right-to-bracket text-success mr-1"></i> Entrada:</b>
                                    <span class="font-weight-bold fs-6 text-success"><?= substr($t['hora_entrada'], 0, 5) ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between py-1">
                                    <b class="text-secondary"><i class="fa-solid fa-arrow-right-from-bracket text-primary mr-1"></i> Salida:</b>
                                    <span class="font-weight-bold fs-6 text-primary"><?= substr($t['hora_salida'], 0, 5) ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between py-1">
                                    <b class="text-secondary"><i class="fa-solid fa-stopwatch text-warning mr-1"></i> Tolerancia de Gracia:</b>
                                    <span class="badge badge-warning text-white font-weight-bold"><?= $t['tolerancia_minutos'] ?> min</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between py-1">
                                    <b class="text-secondary"><i class="fa-solid fa-utensils text-secondary mr-1"></i> Refrigerio:</b>
                                    <span class="font-weight-bold"><?= $t['minutos_refrigerio'] ?> min (<?= $t['hora_inicio_refrigerio'] ? substr($t['hora_inicio_refrigerio'], 0, 5) : 'Flexible' ?>)</span>
                                </li>
                            </ul>

                            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                <span class="small text-muted"><i class="fa-solid fa-users mr-1"></i> <?= $t['total_empleados'] ?> empleados</span>
                                <button class="btn btn-outline-secondary btn-sm" onclick="openEditTurnoModal(<?= htmlspecialchars(json_encode($t)) ?>)">
                                    <i class="fa-solid fa-pen mr-1"></i> Editar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
</section>

<!-- MODAL CREAR / EDITAR TURNO -->
<div class="modal fade" id="modalTurno" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="?route=turnos&action=guardar" class="modal-content">
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
                            <label class="small font-weight-bold text-secondary">Hora de Entrada</label>
                            <input type="time" name="hora_entrada" id="tur_entrada" class="form-control form-control-sm" required>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label class="small font-weight-bold text-secondary">Hora de Salida</label>
                            <input type="time" name="hora_salida" id="tur_salida" class="form-control form-control-sm" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label class="small font-weight-bold text-secondary">Tolerancia Tardanza (Min)</label>
                            <input type="number" name="tolerancia_minutos" id="tur_tolerancia" class="form-control form-control-sm" value="10" min="0" required>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label class="small font-weight-bold text-secondary">Límite para Falta (Min)</label>
                            <input type="number" name="tolerancia_falta_minutos" id="tur_tolfalta" class="form-control form-control-sm" value="60" min="0" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label class="small font-weight-bold text-secondary">Inicio Refrigerio</label>
                            <input type="time" name="hora_inicio_refrigerio" id="tur_ref_ini" class="form-control form-control-sm">
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label class="small font-weight-bold text-secondary">Fin Refrigerio</label>
                            <input type="time" name="hora_fin_refrigerio" id="tur_ref_fin" class="form-control form-control-sm">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="small font-weight-bold text-secondary">Minutos de Refrigerio a descontar</label>
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

    $('#modalTurno').modal('show');
}
</script>

<?php require_once APP_ROOT . '/views/layout/footer.php'; ?>
