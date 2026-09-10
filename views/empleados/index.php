<?php require_once APP_ROOT . '/views/layout/header.php'; ?>

<!-- Content Header (Page header) -->
<div class="content-header pb-2">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-sm-6">
                <h1 class="m-0 font-weight-bold text-dark" style="font-size: 1.45rem;">
                    <i class="fa-solid fa-users mr-2 text-primary"></i> Directorio de Personal
                </h1>
                <div class="text-muted small mt-1">Padrón de trabajadores, asignación de turnos y sincronización de huellas biométricas.</div>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right mb-0">
                    <li class="breadcrumb-item"><a href="?route=dashboard">Inicio</a></li>
                    <li class="breadcrumb-item active">Directorio de Personal</li>
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
                'guardado' => ['success', 'Empleado guardado correctamente.'],
                'eliminado' => ['success', 'Empleado eliminado correctamente.'],
                'duplicado' => ['danger', 'Ya existe un empleado con ese DNI o código de reloj.'],
                'dni_invalido' => ['warning', 'El DNI debe tener 8 dígitos numéricos.'],
                'campos_requeridos' => ['warning', 'Completa todos los campos obligatorios.'],
                'error_interno' => ['danger', 'Ocurrió un error al guardar. Intenta nuevamente.'],
            ];
            if (isset($msgMap[$_GET['msg']])):
                [$type, $text] = $msgMap[$_GET['msg']];
        ?>
            <div class="alert alert-<?= $type ?> alert-dismissible fade show mb-3 shadow-sm">
                <?= htmlspecialchars($text) ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        <?php endif; endif; ?>

        <!-- FILTER AND ACTIONS CARD -->
        <div class="card mb-3 no-print">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap py-2 px-3">
                <h3 class="card-title font-weight-bold text-dark mb-0 d-flex align-items-center" style="font-size: 0.92rem;">
                    <i class="fa-solid fa-filter mr-2 text-primary"></i> Filtros de Búsqueda de Personal
                </h3>
                <?php if (in_array($userRole, ['ADMIN', 'RRHH'], true)): ?>
                    <div class="card-tools my-1">
                        <button class="btn btn-primary btn-sm" onclick="openNewEmpleadoModal()">
                            <i class="fa-solid fa-user-plus mr-1"></i> Registrar Empleado
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-body py-3 px-3">
                <form method="GET" action="" class="row align-items-end">
                    <input type="hidden" name="route" value="empleados">

                    <div class="col-md-4 col-sm-6 mb-2">
                        <label class="form-label-custom"><i class="fa-solid fa-building mr-1"></i> Departamento</label>
                        <select name="departamento_id" class="form-control form-control-sm">
                            <option value="">-- Todos los Departamentos --</option>
                            <?php foreach ($departamentos as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= $deptoId == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6 col-sm-6 mb-2">
                        <label class="form-label-custom"><i class="fa-solid fa-magnifying-glass mr-1"></i> Buscar por Nombre, DNI o ID</label>
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Ej: Perez, 70112233, 101..." value="<?= htmlspecialchars($search ?? '') ?>">
                    </div>

                    <div class="col-md-2 col-sm-12 mb-2">
                        <button type="submit" class="btn btn-primary btn-sm btn-block" style="height: 34px;">
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
                            <th class="text-center" style="width: 110px;">ID Reloj</th>
                            <th class="text-center" style="width: 100px;">DNI</th>
                            <th>Apellidos y Nombres</th>
                            <th>Departamento y Cargo</th>
                            <th class="text-center">Turno</th>
                            <th class="text-center">Biometría</th>
                            <th>Contacto</th>
                            <th class="text-center" style="width: 90px;">Estado</th>
                            <?php if (in_array($userRole, ['ADMIN', 'RRHH'], true)): ?>
                                <th class="text-center" style="width: 95px;">Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($empleados as $e): ?>
                            <tr>
                                <td class="text-center">
                                    <span class="badge-pill-custom badge-pill-neutral font-monospace font-weight-bold">
                                        ID: <?= htmlspecialchars($e['codigo_reloj']) ?>
                                    </span>
                                </td>
                                <td class="text-center font-monospace font-weight-bold text-dark"><?= htmlspecialchars($e['dni']) ?></td>
                                <td>
                                    <div class="font-weight-bold text-dark"><?= htmlspecialchars($e['apellidos'] . ' ' . $e['nombres']) ?></div>
                                    <small class="text-muted">Ingreso: <?= !empty($e['fecha_ingreso']) ? $e['fecha_ingreso'] : 'No registrado' ?></small>
                                </td>
                                <td>
                                    <div class="text-dark font-weight-bold"><?= htmlspecialchars($e['departamento_nombre'] ?? 'Sin Departamento') ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($e['cargo_nombre'] ?? 'Sin Cargo') ?></small>
                                </td>
                                <td class="text-center">
                                    <span class="badge-pill-custom badge-pill-neutral">
                                        <i class="far fa-clock mr-1 text-primary"></i><?= htmlspecialchars($e['turno_nombre'] ?? 'Sin Turno') ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ((int)($e['huellas_count'] ?? 0) > 0): ?>
                                        <span class="badge-pill-custom badge-pill-presente" title="Huellas respaldadas en BDD"><i class="fa-solid fa-fingerprint mr-1"></i> <?= $e['huellas_count'] ?> Huella(s)</span>
                                    <?php else: ?>
                                        <span class="badge-pill-custom badge-pill-neutral"><i class="fa-solid fa-fingerprint mr-1"></i> Sin huella</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small text-muted"><?= htmlspecialchars($e['email'] ?? '-') ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($e['telefono'] ?? '') ?></div>
                                </td>
                                <td class="text-center">
                                    <?php if ($e['activo']): ?>
                                        <span class="badge-pill-custom badge-pill-online"><i class="fa-solid fa-circle" style="font-size: 6px;"></i> Activo</span>
                                    <?php else: ?>
                                        <span class="badge-pill-custom badge-pill-offline"><i class="fa-solid fa-circle" style="font-size: 6px;"></i> Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <?php if (in_array($userRole, ['ADMIN', 'RRHH'], true)): ?>
                                    <td class="text-center">
                                        <button class="btn btn-xs btn-outline-primary mr-1" onclick="openEditEmpleadoModal(<?= htmlspecialchars(json_encode($e)) ?>)" title="Editar">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        <button class="btn btn-xs btn-outline-success" onclick="openEmpBiometricModal(<?= htmlspecialchars(json_encode($e)) ?>)" title="Biometría ZKTeco">
                                            <i class="fa-solid fa-fingerprint"></i>
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
<!-- MODAL GESTIÓN DE EMPLEADO -->
<div class="modal fade" id="modalEmpleado" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="?route=empleados&action=guardar" class="modal-content">
            <?= csrf_field() ?>
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold" id="empModalTitle">Ficha de Empleado</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="emp_id">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="small font-weight-bold text-secondary mb-0">ID de Usuario en Reloj ZKTeco <span class="text-danger">*</span></label>
                                <button type="button" class="btn btn-xs btn-outline-primary" onclick="document.getElementById('emp_codigo_reloj').value = '<?= $siguienteCodigo ?>'" title="Genera el siguiente número correlativo disponible">
                                    <i class="fa-solid fa-wand-magic-sparkles mr-1"></i> Sugerir ID (<?= $siguienteCodigo ?>)
                                </button>
                            </div>
                            <input type="text" name="codigo_reloj" id="emp_codigo_reloj" class="form-control form-control-sm font-monospace font-weight-bold" placeholder="Ej: 1, 2, 101..." required>
                            <small class="text-muted">Número correlativo registrado en el reloj</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="small font-weight-bold text-secondary">Número de DNI <span class="text-danger">*</span></label>
                            <input type="text" name="dni" id="emp_dni" class="form-control form-control-sm font-monospace" placeholder="8 dígitos" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="small font-weight-bold text-secondary">Nombres <span class="text-danger">*</span></label>
                            <input type="text" name="nombres" id="emp_nombres" class="form-control form-control-sm" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="small font-weight-bold text-secondary">Apellidos <span class="text-danger">*</span></label>
                            <input type="text" name="apellidos" id="emp_apellidos" class="form-control form-control-sm" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="small font-weight-bold text-secondary">Correo Electrónico</label>
                            <input type="email" name="email" id="emp_email" class="form-control form-control-sm" placeholder="usuario@empresa.com">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="small font-weight-bold text-secondary">Teléfono</label>
                            <input type="text" name="telefono" id="emp_telefono" class="form-control form-control-sm" placeholder="+51 987 654 321">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="small font-weight-bold text-secondary">Departamento</label>
                            <select name="departamento_id" id="emp_departamento_id" class="form-control form-control-sm">
                                <option value="">-- Sin Asignar --</option>
                                <?php foreach ($departamentos as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="small font-weight-bold text-secondary">Cargo o Puesto</label>
                            <select name="cargo_id" id="emp_cargo_id" class="form-control form-control-sm">
                                <option value="">-- Sin Asignar --</option>
                                <?php foreach ($cargos as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="small font-weight-bold text-secondary">Turno Laboral Asignado <span class="text-danger">*</span></label>
                            <select name="turno_id" id="emp_turno_id" class="form-control form-control-sm" required>
                                <?php foreach ($turnos as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="small font-weight-bold text-secondary">Fecha de Ingreso</label>
                            <input type="date" name="fecha_ingreso" id="emp_fecha_ingreso" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="col-md-6 d-flex align-items-center">
                        <div class="custom-control custom-switch mt-3">
                            <input type="checkbox" class="custom-control-input" id="emp_activo" name="activo" value="1" checked>
                            <label class="custom-control-label font-weight-bold text-secondary" for="emp_activo">Empleado Activo</label>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN INTEGRADA: BIOMETRÍA & RELOJ ZKTECO -->
                <div class="card card-outline card-success mt-3 mb-0 shadow-none border">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center bg-light">
                        <h6 class="card-title font-weight-bold text-success mb-0 small">
                            <i class="fa-solid fa-fingerprint mr-1"></i> BIOMETRÍA & CAPTURA DE HUELLA EN RELOJ ZKTECO
                        </h6>
                        <span class="badge badge-success px-2 py-1" id="emp_inline_bio_badge">
                            <i class="fa-solid fa-fingerprint mr-1"></i> <span id="emp_inline_bio_text">0 Huellas en BDD</span>
                        </span>
                    </div>
                    <div class="card-body py-3">
                        <div class="row align-items-end">
                            <div class="col-md-5 mb-2">
                                <label class="small font-weight-bold text-secondary mb-1">Reloj Biométrico de Captura:</label>
                                <select id="emp_inline_device_id" class="form-control form-control-sm">
                                    <?php if (!empty($dispositivos)): ?>
                                        <?php foreach ($dispositivos as $dev): ?>
                                            <option value="<?= $dev['id'] ?>">
                                                <?= htmlspecialchars($dev['nombre']) ?> (<?= $dev['ip'] ?>) - <?= $dev['estado_conexion'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="1">Reloj Principal (192.168.1.201)</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-7 mb-2">
                                <div class="d-flex gap-2" style="gap: 8px;">
                                    <button type="button" class="btn btn-sm btn-outline-primary flex-fill" onclick="quickRegisterInClock()" title="1. Registra el ID y Nombre del empleado en el reloj">
                                        <i class="fa-solid fa-upload mr-1"></i> 1. Enviar a Reloj
                                    </button>
                                    <button type="button" class="btn btn-sm btn-success font-weight-bold flex-fill shadow-sm" onclick="quickEnrollFingerInClock()" title="2. Activa el sensor para colocar la huella 3 veces y respalda en MySQL">
                                        <i class="fa-solid fa-fingerprint mr-1"></i> 2. Capturar Huella
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="small text-muted mt-1" style="font-size: 82%;">
                            <i class="fa-solid fa-circle-info text-info mr-1"></i> <strong>Instrucciones:</strong> Primero ingresa el <em>ID de Reloj</em> y los <em>Nombres</em>, luego presiona <strong>"Capturar Huella"</strong>. El reloj ZKTeco pitará para que el empleado coloque su dedo 3 veces en el sensor y la huella quedará guardada en el reloj y respaldada en la base de datos.
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-floppy-disk mr-1"></i> Guardar Empleado</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DE ENROLAMIENTO BIOMÉTRICO (EMPLEADOS) -->
<div class="modal fade" id="modalBiometriaEmp" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content shadow-lg">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title font-weight-bold">
                    <i class="fa-solid fa-fingerprint mr-2"></i> Gestión Biométrica ZKTeco (Huella & Rostro)
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="emp_bio_user_id">
                <input type="hidden" id="emp_bio_user_name">

                <div class="p-3 bg-light rounded border mb-3 text-center">
                    <div class="h5 font-weight-bold text-dark mb-1" id="empBioDisplayUser">Empleado</div>
                    <span class="badge badge-primary px-2 py-1 font-monospace" id="empBioDisplayCode">ID Reloj: -</span>
                </div>

                <div class="form-group">
                    <label class="small font-weight-bold text-secondary">Seleccionar Reloj Biométrico ZKTeco:</label>
                    <select id="emp_bio_device_select" class="form-control form-control-sm">
                        <?php if (!empty($dispositivos)): ?>
                            <?php foreach ($dispositivos as $dev): ?>
                                <option value="<?= $dev['id'] ?>">
                                    <?= htmlspecialchars($dev['nombre']) ?> (<?= $dev['ip'] ?>:<?= $dev['puerto'] ?>) - <?= $dev['estado_conexion'] ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="1">Reloj Principal (192.168.1.201)</option>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- ESTADO EN BDD -->
                <div class="card card-outline card-secondary mb-3 shadow-none border">
                    <div class="card-header py-1">
                        <span class="small font-weight-bold text-secondary"><i class="fa-solid fa-database mr-1"></i> Estado en Base de Datos (MySQL):</span>
                    </div>
                    <div class="card-body py-2">
                        <div id="empBioStatusLoading" class="text-center text-muted small py-2">
                            <i class="fa-solid fa-spinner fa-spin mr-1"></i> Consultando plantillas biométricas...
                        </div>
                        <div id="empBioStatusContent" style="display: none;">
                            <div class="d-flex justify-content-around text-center">
                                <div>
                                    <i class="fa-solid fa-fingerprint fa-2x text-primary mb-1"></i>
                                    <div class="font-weight-bold h6 mb-0" id="empBioCountHuellas">0</div>
                                    <small class="text-muted">Huellas en BDD</small>
                                </div>
                                <div class="border-left"></div>
                                <div>
                                    <i class="fa-solid fa-camera fa-2x text-info mb-1"></i>
                                    <div class="font-weight-bold h6 mb-0" id="empBioCountFacial">0</div>
                                    <small class="text-muted">Rostro Facial</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ACCIONES CON EL RELOJ BIOMÉTRICO -->
                <div class="form-group mb-2">
                    <label class="small font-weight-bold text-secondary">Acciones de Captura y Sincronización:</label>
                    
                    <button type="button" class="btn btn-outline-primary btn-block btn-sm mb-2 text-left" onclick="sendEmpToClock()">
                        <i class="fa-solid fa-upload mr-2 text-primary"></i> <strong>1. Registrar y Enviar Empleado al Reloj</strong>
                        <div class="small text-muted pl-4">Crea el ID y nombre del empleado en la memoria del reloj biométrico.</div>
                    </button>

                    <button type="button" class="btn btn-outline-success btn-block btn-sm mb-2 text-left" onclick="triggerEnrollEmpFinger()">
                        <i class="fa-solid fa-fingerprint mr-2 text-success"></i> <strong>2. Capturar Huella Dactilar en Reloj</strong>
                        <div class="small text-muted pl-4">Activa el sensor del reloj para que el empleado coloque su dedo 3 veces.</div>
                    </button>

                    <button type="button" class="btn btn-outline-info btn-block btn-sm mb-2 text-left" onclick="syncEmpTemplatesToDB()">
                        <i class="fa-solid fa-cloud-arrow-down mr-2 text-info"></i> <strong>3. Respaldar Huellas y Rostro a la BDD (MySQL)</strong>
                        <div class="small text-muted pl-4">Descarga las plantillas del reloj y las guarda en la tabla `plantillas_biometricas`.</div>
                    </button>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
function openNewEmpleadoModal() {
    document.getElementById('empModalTitle').innerText = 'Registrar Nuevo Empleado';
    document.getElementById('emp_id').value = '';
    document.getElementById('emp_codigo_reloj').value = '<?= $siguienteCodigo ?>';
    document.getElementById('emp_dni').value = '';
    document.getElementById('emp_nombres').value = '';
    document.getElementById('emp_apellidos').value = '';
    document.getElementById('emp_email').value = '';
    document.getElementById('emp_telefono').value = '';
    document.getElementById('emp_departamento_id').value = '';
    document.getElementById('emp_cargo_id').value = '';
    document.getElementById('emp_turno_id').value = '1';
    document.getElementById('emp_fecha_ingreso').value = '<?= date('Y-m-d') ?>';
    document.getElementById('emp_activo').checked = true;

    // Reset inline bio
    document.getElementById('emp_inline_bio_text').innerText = '0 Huellas en BDD';
    $('#modalEmpleado').modal('show');
}

function openEditEmpleadoModal(e) {
    document.getElementById('empModalTitle').innerText = 'Editar Datos de Empleado';
    document.getElementById('emp_id').value = e.id;
    document.getElementById('emp_codigo_reloj').value = e.codigo_reloj;
    document.getElementById('emp_dni').value = e.dni;
    document.getElementById('emp_nombres').value = e.nombres;
    document.getElementById('emp_apellidos').value = e.apellidos;
    document.getElementById('emp_email').value = e.email || '';
    document.getElementById('emp_telefono').value = e.telefono || '';
    document.getElementById('emp_departamento_id').value = e.departamento_id || '';
    document.getElementById('emp_cargo_id').value = e.cargo_id || '';
    document.getElementById('emp_turno_id').value = e.turno_id || '';
    document.getElementById('emp_fecha_ingreso').value = e.fecha_ingreso || '';
    document.getElementById('emp_activo').checked = (parseInt(e.activo) === 1);

    var count = parseInt(e.huellas_count || 0);
    document.getElementById('emp_inline_bio_text').innerText = count + ' Huella(s) en BDD';

    $('#modalEmpleado').modal('show');
}

// CAPTURA RÁPIDA DIRECTA DENTRO DEL FORMULARIO DE EMPLEADO
function quickRegisterInClock() {
    var devId = document.getElementById('emp_inline_device_id').value;
    var userId = document.getElementById('emp_codigo_reloj').value.trim();
    var nombres = document.getElementById('emp_nombres').value.trim();
    var apellidos = document.getElementById('emp_apellidos').value.trim();
    var fullName = (nombres + ' ' + apellidos).trim();

    if (!userId) {
        Swal.fire('Atención', 'Por favor ingresa primero el <strong>ID de Usuario en Reloj ZKTeco</strong>.', 'warning');
        document.getElementById('emp_codigo_reloj').focus();
        return;
    }
    if (!fullName) {
        Swal.fire('Atención', 'Por favor ingresa los <strong>Nombres y Apellidos</strong> del empleado.', 'warning');
        document.getElementById('emp_nombres').focus();
        return;
    }

    Swal.fire({
        title: 'Registrando en Reloj Biométrico...',
        text: 'Enviando ID: ' + userId + ' (' + fullName + ') al dispositivo.',
        allowOutsideClick: false,
        didOpen: function() { Swal.showLoading(); }
    });

    var fd = new FormData();
    fd.append('device_id', devId);
    fd.append('user_id', userId);
    fd.append('name', fullName);
    fd.append('privilege', '0');

    fetch('?route=dispositivos&action=enviar_usuario_reloj', {
        method: 'POST',
        body: fd
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) {
            Swal.fire('¡Registrado!', res.message || 'Empleado guardado en la memoria del reloj con éxito.', 'success');
        } else {
            Swal.fire('Aviso', res.error || 'No se pudo comunicar con el reloj.', 'error');
        }
    })
    .catch(function() {
        Swal.fire('Error', 'Fallo de comunicación con el servidor.', 'error');
    });
}

function quickEnrollFingerInClock() {
    var devId = document.getElementById('emp_inline_device_id').value;
    var userId = document.getElementById('emp_codigo_reloj').value.trim();
    var nombres = document.getElementById('emp_nombres').value.trim();
    var apellidos = document.getElementById('emp_apellidos').value.trim();
    var fullName = (nombres + ' ' + apellidos).trim();

    if (!userId) {
        Swal.fire('Atención', 'Por favor ingresa primero el <strong>ID de Usuario en Reloj ZKTeco</strong>.', 'warning');
        document.getElementById('emp_codigo_reloj').focus();
        return;
    }

    // 1. Enviar usuario primero si tiene nombre
    if (fullName) {
        var fdUser = new FormData();
        fdUser.append('device_id', devId);
        fdUser.append('user_id', userId);
        fdUser.append('name', fullName);
        fdUser.append('privilege', '0');
        fetch('?route=dispositivos&action=enviar_usuario_reloj', { method: 'POST', body: fdUser });
    }

    // 2. Activar modo captura en el reloj
    var fdEnroll = new FormData();
    fdEnroll.append('device_id', devId);
    fdEnroll.append('user_id', userId);
    fdEnroll.append('temp_id', '0');
    fetch('?route=dispositivos&action=enrolar_huella', { method: 'POST', body: fdEnroll });

    // 3. Mostrar modal interactivo al operador
    Swal.fire({
        title: '🖐️ ¡Coloca el dedo en el sensor del reloj!',
        html: '<div class="text-left">' +
              '<p>Se ha activado el sensor óptico en el reloj biométrico para el usuario <strong>ID ' + userId + '</strong>.</p>' +
              '<div class="alert alert-info py-2 px-3 small mb-2">' +
              '<i class="fa-solid fa-hand-point-right mr-1"></i> <strong>Instrucciones:</strong> Solicita al empleado que coloque su dedo en el lector óptico del reloj <strong>3 veces consecutivas</strong> hasta escuchar el pitido de confirmación del reloj.' +
              '</div>' +
              '</div>',
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-cloud-arrow-down mr-1"></i> Ya colocó la huella (Respaldar en BDD)',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#28a745'
    }).then(function(result) {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Descargando y Respaldando Huella...',
                text: 'Guardando plantilla biométrica en MySQL...',
                allowOutsideClick: false,
                didOpen: function() { Swal.showLoading(); }
            });

            var fdSync = new FormData();
            fdSync.append('device_id', devId);
            fdSync.append('user_id', userId);

            fetch('?route=dispositivos&action=sincronizar_biometria', {
                method: 'POST',
                body: fdSync
            })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    document.getElementById('emp_inline_bio_text').innerText = '1 Huella(s) en BDD';
                    Swal.fire('¡Huella Registrada!', 'La huella dactilar quedó guardada en el reloj ZKTeco y respaldada en la base de datos MySQL.', 'success');
                } else {
                    Swal.fire('Aviso', res.error || 'No se pudo descargar la huella.', 'warning');
                }
            })
            .catch(function() {
                Swal.fire('Error', 'Fallo al sincronizar con la base de datos.', 'error');
            });
        }
    });
}

function openEmpBiometricModal(e) {
    var fullName = (e.nombres || '') + ' ' + (e.apellidos || '');
    document.getElementById('emp_bio_user_id').value = e.codigo_reloj;
    document.getElementById('emp_bio_user_name').value = fullName.trim();
    document.getElementById('empBioDisplayUser').innerText = fullName.trim();
    document.getElementById('empBioDisplayCode').innerText = 'Código en Reloj: ' + e.codigo_reloj;

    loadEmpBiometricStatus(e.codigo_reloj);
    $('#modalBiometriaEmp').modal('show');
}

function loadEmpBiometricStatus(userId) {
    document.getElementById('empBioStatusLoading').style.display = 'block';
    document.getElementById('empBioStatusContent').style.display = 'none';

    fetch('?route=dispositivos&action=obtener_biometria_usuario&user_id=' + encodeURIComponent(userId))
        .then(function(res) { return res.json(); })
        .then(function(data) {
            document.getElementById('empBioStatusLoading').style.display = 'none';
            document.getElementById('empBioStatusContent').style.display = 'block';
            if (data.success) {
                document.getElementById('empBioCountHuellas').innerText = data.huellas_count || 0;
                document.getElementById('empBioCountFacial').innerText = (data.facial_count > 0 ? 'Registrado' : 'No');
            }
        })
        .catch(function() {
            document.getElementById('empBioStatusLoading').style.display = 'none';
            document.getElementById('empBioStatusContent').style.display = 'block';
            document.getElementById('empBioCountHuellas').innerText = '-';
            document.getElementById('empBioCountFacial').innerText = '-';
        });
}

function sendEmpToClock() {
    var devId = document.getElementById('emp_bio_device_select').value;
    var userId = document.getElementById('emp_bio_user_id').value;
    var name = document.getElementById('emp_bio_user_name').value;

    Swal.fire({
        title: 'Enviando al Reloj Biométrico...',
        text: 'Registrando empleado en la memoria del dispositivo.',
        allowOutsideClick: false,
        didOpen: function() { Swal.showLoading(); }
    });

    var fd = new FormData();
    fd.append('device_id', devId);
    fd.append('user_id', userId);
    fd.append('name', name);
    fd.append('privilege', '0');

    fetch('?route=dispositivos&action=enviar_usuario_reloj', {
        method: 'POST',
        body: fd
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) {
            Swal.fire('¡Registrado!', res.message || 'Empleado creado en el reloj con éxito.', 'success');
        } else {
            Swal.fire('Error en el Reloj', res.error || 'No se pudo registrar.', 'error');
        }
    })
    .catch(function() {
        Swal.fire('Error', 'Fallo de comunicación con el servidor.', 'error');
    });
}

function triggerEnrollEmpFinger() {
    var devId = document.getElementById('emp_bio_device_select').value;
    var userId = document.getElementById('emp_bio_user_id').value;

    Swal.fire({
        title: '¡Coloca el dedo en el sensor!',
        html: 'Se ha activado el modo de enrolamiento en el reloj.<br><strong>El empleado debe colocar su dedo en el sensor óptico del reloj biométrico 3 veces consecutivas.</strong>',
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-check mr-1"></i> Ya colocó la huella',
        cancelButtonText: 'Cancelar'
    }).then(function(result) {
        if (result.isConfirmed) {
            syncEmpTemplatesToDB();
        }
    });

    var fd = new FormData();
    fd.append('device_id', devId);
    fd.append('user_id', userId);
    fd.append('temp_id', '0');

    fetch('?route=dispositivos&action=enrolar_huella', {
        method: 'POST',
        body: fd
    });
}

function syncEmpTemplatesToDB() {
    var devId = document.getElementById('emp_bio_device_select').value;
    var userId = document.getElementById('emp_bio_user_id').value;

    Swal.fire({
        title: 'Descargando y Respaldando Biometría...',
        text: 'Guardando huellas y rostros del reloj en la base de datos MySQL.',
        allowOutsideClick: false,
        didOpen: function() { Swal.showLoading(); }
    });

    var fd = new FormData();
    fd.append('device_id', devId);
    fd.append('user_id', userId);

    fetch('?route=dispositivos&action=sincronizar_biometria', {
        method: 'POST',
        body: fd
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) {
            loadEmpBiometricStatus(userId);
            Swal.fire('¡Respaldado!', res.message || 'Plantillas biométricas guardadas en MySQL exitosamente.', 'success');
        } else {
            Swal.fire('Aviso', res.error || 'No se pudieron descargar las plantillas.', 'warning');
        }
    })
    .catch(function() {
        Swal.fire('Error', 'Fallo al procesar sincronización biométrica.', 'error');
    });
}
</script>
<?php endif; ?>

<?php require_once APP_ROOT . '/views/layout/footer.php'; ?>

