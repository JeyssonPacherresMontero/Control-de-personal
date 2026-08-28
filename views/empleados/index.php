<?php require_once APP_ROOT . '/views/layout/header.php'; ?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-sm-6">
                <h1 class="m-0 font-weight-bold"><i class="fa-solid fa-users mr-2 text-primary"></i> Directorio de Empleados</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="?route=dashboard">Inicio</a></li>
                    <li class="breadcrumb-item active">Empleados</li>
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
                <h3 class="card-title font-weight-bold"><i class="fa-solid fa-filter mr-1 text-secondary"></i> Filtros de Personal</h3>
                <div class="card-tools">
                    <button class="btn btn-primary btn-sm shadow-sm" onclick="openNewEmpleadoModal()">
                        <i class="fa-solid fa-user-plus mr-1"></i> Registrar Empleado
                    </button>
                </div>
            </div>
            <div class="card-body py-3">
                <form method="GET" action="" class="row align-items-end">
                    <input type="hidden" name="route" value="empleados">

                    <div class="col-md-4 mb-2">
                        <label class="small font-weight-bold text-secondary mb-1">Departamento / Área</label>
                        <select name="departamento_id" class="form-control form-control-sm">
                            <option value="">-- Todos los Departamentos --</option>
                            <?php foreach ($departamentos as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= $deptoId == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6 mb-2">
                        <label class="small font-weight-bold text-secondary mb-1">Buscar por Nombre, DNI o ID Biométrico</label>
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Ej: Perez, 70112233, 101..." value="<?= htmlspecialchars($search ?? '') ?>">
                    </div>

                    <div class="col-md-2 mb-2">
                        <button type="submit" class="btn btn-secondary btn-sm btn-block"><i class="fa-solid fa-magnifying-glass mr-1"></i> Filtrar</button>
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
                            <th>ID Reloj ZK</th>
                            <th>DNI</th>
                            <th>Apellidos y Nombres</th>
                            <th>Departamento / Cargo</th>
                            <th>Turno Asignado</th>
                            <th>Contacto</th>
                            <th>Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($empleados as $e): ?>
                            <tr>
                                <td>
                                    <span class="badge badge-primary px-2 py-1 font-monospace">
                                        ID: <?= htmlspecialchars($e['codigo_reloj']) ?>
                                    </span>
                                </td>
                                <td class="font-monospace font-weight-bold"><?= htmlspecialchars($e['dni']) ?></td>
                                <td>
                                    <div class="font-weight-bold text-dark"><?= htmlspecialchars($e['apellidos'] . ' ' . $e['nombres']) ?></div>
                                    <small class="text-muted">Ingreso: <?= $e['fecha_ingreso'] ?? 'N/A' ?></small>
                                </td>
                                <td>
                                    <div class="text-dark font-weight-bold"><?= htmlspecialchars($e['departamento_nombre'] ?? 'Sin Departamento') ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($e['cargo_nombre'] ?? 'Sin Cargo') ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-light border">
                                        <i class="far fa-clock mr-1"></i><?= htmlspecialchars($e['turno_nombre'] ?? 'Sin Turno') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="small text-muted"><?= htmlspecialchars($e['email'] ?? '-') ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($e['telefono'] ?? '') ?></div>
                                </td>
                                <td>
                                    <?php if ($e['activo']): ?>
                                        <span class="badge badge-success px-2 py-1">Activo</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary px-2 py-1">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-xs btn-default border" onclick="openEditEmpleadoModal(<?= htmlspecialchars(json_encode($e)) ?>)" title="Editar">
                                        <i class="fa-solid fa-pen text-primary"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</section>

<!-- MODAL CREAR / EDITAR EMPLEADO -->
<div class="modal fade" id="modalEmpleado" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="?route=empleados&action=guardar" class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold" id="empModalTitle">Ficha de Empleado</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="emp_id">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="small font-weight-bold text-secondary">Código / ID en Reloj ZKTeco <span class="text-danger">*</span></label>
                            <input type="text" name="codigo_reloj" id="emp_codigo_reloj" class="form-control form-control-sm font-monospace" placeholder="Ej: 1, 101, 1005" required>
                            <small class="text-muted">Debe ser el User ID en el menú del biométrico.</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="small font-weight-bold text-secondary">DNI / Documento <span class="text-danger">*</span></label>
                            <input type="text" name="dni" id="emp_dni" class="form-control form-control-sm font-monospace" placeholder="70112233" required>
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
                            <input type="email" name="email" id="emp_email" class="form-control form-control-sm" placeholder="empleado@empresa.com">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="small font-weight-bold text-secondary">Teléfono / Celular</label>
                            <input type="text" name="telefono" id="emp_telefono" class="form-control form-control-sm" placeholder="987654321">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="small font-weight-bold text-secondary">Departamento</label>
                            <select name="departamento_id" id="emp_departamento_id" class="form-control form-control-sm">
                                <option value="">-- Seleccionar --</option>
                                <?php foreach ($departamentos as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="small font-weight-bold text-secondary">Cargo</label>
                            <select name="cargo_id" id="emp_cargo_id" class="form-control form-control-sm">
                                <option value="">-- Seleccionar --</option>
                                <?php foreach ($cargos as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="small font-weight-bold text-secondary">Turno Asignado <span class="text-danger">*</span></label>
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
                    <div class="col-md-6">
                        <div class="form-group custom-control custom-checkbox mt-4 pt-2">
                            <input class="custom-control-input" type="checkbox" name="activo" id="emp_activo" value="1" checked>
                            <label class="custom-control-label small font-weight-bold" for="emp_activo">Empleado Activo</label>
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

<script>
function openNewEmpleadoModal() {
    document.getElementById('empModalTitle').innerText = 'Registrar Nuevo Empleado';
    document.getElementById('emp_id').value = '';
    document.getElementById('emp_codigo_reloj').value = '';
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
    $('#modalEmpleado').modal('show');
}
</script>

<?php require_once APP_ROOT . '/views/layout/footer.php'; ?>
