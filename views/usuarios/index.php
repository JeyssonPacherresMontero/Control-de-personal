<?php require_once APP_ROOT . '/views/layout/header.php'; ?>

<!-- Content Header (Page header) -->
<div class="content-header pb-2">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-sm-6">
                <h1 class="m-0 font-weight-bold text-dark" style="font-size: 1.45rem;">
                    <i class="fa-solid fa-user-shield mr-2 text-primary"></i> Usuarios del Sistema y Control de Accesos
                </h1>
                <div class="text-muted small mt-1">Administración de cuentas, roles de seguridad y permisos de acceso a módulos.</div>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right mb-0">
                    <li class="breadcrumb-item"><a href="?route=dashboard">Inicio</a></li>
                    <li class="breadcrumb-item active">Usuarios del Sistema</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">

        <!-- ALERTAS Y NOTIFICACIONES -->
        <?php if (isset($_GET['msg'])): ?>
            <?php if ($_GET['msg'] === 'guardado'): ?>
                <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                    <i class="fa-solid fa-circle-check mr-2"></i> Usuario guardado exitosamente con sus permisos de menú.
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            <?php elseif ($_GET['msg'] === 'usuario_duplicado'): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                    <i class="fa-solid fa-triangle-exclamation mr-2"></i> Error: Ya existe un usuario registrado con ese nombre de usuario.
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            <?php elseif ($_GET['msg'] === 'estado_actualizado'): ?>
                <div class="alert alert-info alert-dismissible fade show mb-3" role="alert">
                    <i class="fa-solid fa-arrows-rotate mr-2"></i> Estado del usuario actualizado.
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            <?php elseif ($_GET['msg'] === 'eliminado'): ?>
                <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
                    <i class="fa-solid fa-trash mr-2"></i> Usuario eliminado del sistema.
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            <?php elseif ($_GET['msg'] === 'error_auto_eliminar' || $_GET['msg'] === 'error_auto_desactivar'): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                    <i class="fa-solid fa-shield-halved mr-2"></i> Acción no permitida: No puedes desactivar o eliminar tu propio usuario administrador.
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- MAIN TABLE CARD -->
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap">
                <h3 class="card-title font-weight-bold">
                    <i class="fa-solid fa-users-gear mr-2 text-primary"></i> Lista de Cuentas y Permisos de Módulos
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary btn-sm" onclick="openNewUserModal()">
                        <i class="fa-solid fa-user-plus mr-1"></i> Crear Nuevo Usuario
                    </button>
                </div>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover text-nowrap table-sm mb-0">
                    <thead>
                        <tr>
                            <th style="width: 50px;">ID</th>
                            <th>Usuario / Nombre</th>
                            <th>Rol Asignado</th>
                            <th>Módulos del Menú Autorizados</th>
                            <th>Biometría</th>
                            <th class="text-center">Estado</th>
                            <th>Último Acceso</th>
                            <th class="text-center" style="width: 140px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                            <?php 
                                $userPerms = !empty($u['permisos']) ? json_decode($u['permisos'], true) : [];
                                $isAdmin = ($u['rol'] === 'ADMIN' || in_array('*', (array)$userPerms, true));
                            ?>
                            <tr>
                                <td class="font-monospace text-muted small">#<?= $u['id'] ?></td>
                                <td>
                                    <div class="font-weight-bold text-dark">
                                        <i class="fa-solid fa-circle-user text-primary mr-1"></i>
                                        <?= htmlspecialchars($u['usuario']) ?>
                                    </div>
                                    <div class="small text-secondary font-weight-bold"><?= htmlspecialchars($u['nombre_completo']) ?></div>
                                    <?php if (!empty($u['email'])): ?>
                                        <small class="text-muted"><i class="fa-regular fa-envelope mr-1"></i><?= htmlspecialchars($u['email']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($u['rol'] === 'ADMIN'): ?>
                                        <span class="badge-pill-custom badge-pill-falta font-weight-bold">ADMINISTRADOR</span>
                                    <?php elseif ($u['rol'] === 'RRHH'): ?>
                                        <span class="badge-pill-custom badge-pill-justificado font-weight-bold">RECURSOS HUMANOS</span>
                                    <?php elseif ($u['rol'] === 'SUPERVISOR'): ?>
                                        <span class="badge-pill-custom badge-pill-tardanza font-weight-bold">SUPERVISOR</span>
                                    <?php else: ?>
                                        <span class="badge-pill-custom badge-pill-neutral font-weight-bold"><?= htmlspecialchars($u['rol']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isAdmin): ?>
                                        <span class="badge-pill-custom badge-pill-presente font-weight-bold"><i class="fa-solid fa-unlock-keyhole mr-1"></i> Acceso Total (Todos los Módulos)</span>
                                    <?php else: ?>
                                        <div class="d-flex flex-wrap" style="max-width: 480px; gap: 4px;">
                                            <?php foreach ($modulosDisponibles as $mKey => $mInfo): ?>
                                                <?php if (in_array($mKey, (array)$userPerms, true)): ?>
                                                    <span class="badge-pill-custom badge-pill-neutral" title="<?= htmlspecialchars($mInfo['description']) ?>">
                                                        <i class="<?= $mInfo['icon'] ?> text-primary mr-1"></i><?= htmlspecialchars($mInfo['name']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                            <?php if (empty($userPerms)): ?>
                                                <span class="badge-pill-custom badge-pill-tardanza">Sin módulos asignados</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ((int)$u['huellas_count'] > 0): ?>
                                        <span class="badge-pill-custom badge-pill-presente" title="Huellas respaldadas en BDD"><i class="fa-solid fa-fingerprint mr-1"></i> <?= $u['huellas_count'] ?> Huella(s)</span>
                                    <?php else: ?>
                                        <span class="badge-pill-custom badge-pill-neutral"><i class="fa-solid fa-fingerprint mr-1"></i> Sin huella</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($u['activo']): ?>
                                        <span class="badge-pill-custom badge-pill-online"><i class="fa-solid fa-circle" style="font-size: 6px;"></i> Activo</span>
                                    <?php else: ?>
                                        <span class="badge-pill-custom badge-pill-offline"><i class="fa-solid fa-circle" style="font-size: 6px;"></i> Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted font-monospace">
                                        <?= !empty($u['ultimo_login']) ? date('d/m/Y H:i', strtotime($u['ultimo_login'])) : 'Nunca' ?>
                                    </small>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-xs btn-outline-primary mr-1" onclick="openEditUserModal(<?= htmlspecialchars(json_encode($u)) ?>)" title="Editar Usuario y Permisos">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button type="button" class="btn btn-xs btn-outline-success mr-1" onclick="openBiometricModal(<?= htmlspecialchars(json_encode($u)) ?>)" title="Biometría ZKTeco (Huella / Facial)">
                                        <i class="fa-solid fa-fingerprint"></i>
                                    </button>
                                    <?php if ($u['id'] !== ($currentUser['id'] ?? 0)): ?>
                                        <a href="?route=usuarios&action=cambiar_estado&id=<?= $u['id'] ?>" class="btn btn-xs btn-outline-secondary mr-1" title="<?= $u['activo'] ? 'Desactivar Usuario' : 'Activar Usuario' ?>" onclick="return confirm('¿Deseas cambiar el estado de este usuario?')">
                                            <i class="fa-solid <?= $u['activo'] ? 'fa-user-slash text-warning' : 'fa-user-check text-success' ?>"></i>
                                        </a>
                                        <form method="POST" action="?route=usuarios&action=eliminar" style="display:inline;" onsubmit="return confirm('¿Estás seguro de eliminar este usuario definitivamente?');">
                                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="btn btn-xs btn-default border text-danger" title="Eliminar Usuario">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
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

<!-- MODAL CREAR / EDITAR USUARIO & PERMISOS DE MENÚ -->
<div class="modal fade" id="modalUsuario" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form method="POST" action="?route=usuarios&action=guardar" class="modal-content shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold" id="usrModalTitle">
                    <i class="fa-solid fa-user-gear mr-2"></i> Crear / Editar Usuario
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="usr_id">

                <!-- DATOS PRINCIPALES -->
                <div class="card card-outline card-secondary mb-3 shadow-none border">
                    <div class="card-header py-2">
                        <h6 class="card-title font-weight-bold text-secondary mb-0 small">
                            <i class="fa-solid fa-id-card mr-1"></i> DATOS DE LA CUENTA
                        </h6>
                    </div>
                    <div class="card-body py-3">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-2">
                                    <label class="small font-weight-bold text-secondary">Nombre de Usuario (Login) <span class="text-danger">*</span></label>
                                    <input type="text" name="usuario" id="usr_usuario" class="form-control form-control-sm font-monospace" placeholder="Ej: joperador, rrhh_asistencias" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-2">
                                    <label class="small font-weight-bold text-secondary">Nombre Completo <span class="text-danger">*</span></label>
                                    <input type="text" name="nombre_completo" id="usr_nombre" class="form-control form-control-sm" placeholder="Ej: Juan Pérez Morales" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group mb-2">
                                    <label class="small font-weight-bold text-secondary">Correo Electrónico</label>
                                    <input type="email" name="email" id="usr_email" class="form-control form-control-sm" placeholder="usuario@empresa.com">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-2">
                                    <label class="small font-weight-bold text-secondary">Contraseña</label>
                                    <input type="password" name="password" id="usr_password" class="form-control form-control-sm" placeholder="Dejar en blanco para mantener">
                                    <small class="text-muted" id="usr_pass_hint">Nueva clave para el usuario</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-2">
                                    <label class="small font-weight-bold text-secondary">Rol Asignado <span class="text-danger">*</span></label>
                                    <select name="rol" id="usr_rol" class="form-control form-control-sm" onchange="onRoleChange(this.value)">
                                        <option value="ADMIN">ADMIN - Administrador (Acceso Total)</option>
                                        <option value="RRHH" selected>RRHH - Recursos Humanos</option>
                                        <option value="SUPERVISOR">SUPERVISOR - Supervisor de Área</option>
                                        <option value="CONSULTA">CONSULTA - Solo Consulta / Operador</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-2">
                            <div class="col-12">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="usr_activo" name="activo" value="1" checked>
                                    <label class="custom-control-label font-weight-bold text-secondary small" for="usr_activo">Usuario Activo en el Sistema</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN DE APARTADOS DEL MENÚ (PERMISOS) -->
                <div class="card card-outline card-primary mb-0 shadow-none border" id="containerPermisos">
                    <div class="card-header py-2 d-flex align-items-center justify-content-between">
                        <h6 class="card-title font-weight-bold text-primary mb-0 small">
                            <i class="fa-solid fa-list-check mr-1"></i> CONTROL DE ACCESO A LOS APARTADOS DEL MENÚ
                        </h6>
                        <span class="badge badge-info small">Marca los apartados que el usuario verá en su menú</span>
                    </div>
                    <div class="card-body py-2">

                        <!-- BOTONES DE PLANTILLA RÁPIDA -->
                        <div class="mb-3 p-2 bg-light rounded border d-flex flex-wrap align-items-center" style="gap: 6px;">
                            <span class="small font-weight-bold text-muted mr-1"><i class="fa-solid fa-wand-magic-sparkles text-warning mr-1"></i> Accesos Rápidos:</span>
                            <button type="button" class="btn btn-xs btn-outline-success font-weight-bold" onclick="setPresetOnlyAsistencia()">
                                <i class="fa-solid fa-calendar-check mr-1"></i> Solo Asistencias Diarias
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-primary" onclick="setPresetRRHH()">
                                <i class="fa-solid fa-users mr-1"></i> Perfil RRHH Completo
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-info" onclick="setPresetSupervisor()">
                                <i class="fa-solid fa-user-tie mr-1"></i> Perfil Supervisor
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-secondary" onclick="setPresetConsulta()">
                                <i class="fa-solid fa-eye mr-1"></i> Solo Consulta
                            </button>
                            <button type="button" class="btn btn-xs btn-default border text-dark font-weight-bold" onclick="toggleAllModules(true)">
                                <i class="fa-solid fa-check-double text-success mr-1"></i> Marcar Todos
                            </button>
                            <button type="button" class="btn btn-xs btn-default border text-danger" onclick="toggleAllModules(false)">
                                <i class="fa-solid fa-xmark mr-1"></i> Desmarcar
                            </button>
                        </div>

                        <!-- MATRIZ DE CHECKBOXES DE MÓDULOS -->
                        <div class="row">
                            <?php foreach ($modulosDisponibles as $modKey => $modInfo): ?>
                                <?php if ($modKey === 'usuarios') continue; // Solo para Admin interno ?>
                                <div class="col-md-6 mb-2">
                                    <div class="p-2 border rounded module-card h-100 bg-white" style="transition: all 0.2s;">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input chk-modulo" id="chk_<?= $modKey ?>" name="permisos[]" value="<?= $modKey ?>">
                                            <label class="custom-control-label font-weight-bold text-dark d-flex align-items-center" for="chk_<?= $modKey ?>">
                                                <i class="<?= $modInfo['icon'] ?> text-primary mr-2" style="font-size: 1.1rem; width: 22px;"></i>
                                                <span><?= htmlspecialchars($modInfo['name']) ?></span>
                                            </label>
                                        </div>
                                        <div class="small text-muted pl-4 mt-1" style="font-size: 80%;">
                                            <?= htmlspecialchars($modInfo['description']) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div id="adminNotice" class="alert alert-info py-2 px-3 small mt-2 mb-0" style="display: none;">
                            <i class="fa-solid fa-circle-info mr-1"></i> Los usuarios con rol <strong>ADMIN</strong> tienen acceso a todos los módulos y opciones de configuración del sistema de forma automática.
                        </div>

                    </div>
                </div>

            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm font-weight-bold shadow-sm">
                    <i class="fa-solid fa-floppy-disk mr-1"></i> Guardar Usuario y Accesos
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DE ENROLAMIENTO BIOMÉTRICO (HUELLA / FACIAL EN RELOJ Y BDD) -->
<div class="modal fade" id="modalBiometria" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content shadow-lg">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title font-weight-bold">
                    <i class="fa-solid fa-fingerprint mr-2"></i> Gestión Biométrica ZKTeco (Huella & Rostro)
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="bio_user_id">
                <input type="hidden" id="bio_user_name">

                <div class="p-3 bg-light rounded border mb-3 text-center">
                    <div class="h5 font-weight-bold text-dark mb-1" id="bioDisplayUser">Usuario</div>
                    <span class="badge badge-primary px-2 py-1 font-monospace" id="bioDisplayCode">ID Reloj: -</span>
                </div>

                <div class="form-group">
                    <label class="small font-weight-bold text-secondary">Seleccionar Reloj Biométrico ZKTeco:</label>
                    <select id="bio_device_select" class="form-control form-control-sm">
                        <?php foreach ($dispositivos as $dev): ?>
                            <option value="<?= $dev['id'] ?>">
                                <?= htmlspecialchars($dev['nombre']) ?> (<?= $dev['ip'] ?>:<?= $dev['puerto'] ?>) - <?= $dev['estado_conexion'] ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if (empty($dispositivos)): ?>
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
                        <div id="bioStatusLoading" class="text-center text-muted small py-2">
                            <i class="fa-solid fa-spinner fa-spin mr-1"></i> Consultando plantillas biométricas...
                        </div>
                        <div id="bioStatusContent" style="display: none;">
                            <div class="d-flex justify-content-around text-center">
                                <div>
                                    <i class="fa-solid fa-fingerprint fa-2x text-primary mb-1"></i>
                                    <div class="font-weight-bold h6 mb-0" id="bioCountHuellas">0</div>
                                    <small class="text-muted">Huellas en BDD</small>
                                </div>
                                <div class="border-left"></div>
                                <div>
                                    <i class="fa-solid fa-camera fa-2x text-info mb-1"></i>
                                    <div class="font-weight-bold h6 mb-0" id="bioCountFacial">0</div>
                                    <small class="text-muted">Rostro Facial</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ACCIONES CON EL RELOJ BIOMÉTRICO -->
                <div class="form-group mb-2">
                    <label class="small font-weight-bold text-secondary">Acciones de Captura y Sincronización:</label>
                    
                    <button type="button" class="btn btn-outline-primary btn-block btn-sm mb-2 text-left" onclick="sendUserToClock()">
                        <i class="fa-solid fa-upload mr-2 text-primary"></i> <strong>1. Registrar / Enviar Usuario al Reloj</strong>
                        <div class="small text-muted pl-4">Crea el nombre y número de usuario en la memoria del reloj biométrico.</div>
                    </button>

                    <button type="button" class="btn btn-outline-success btn-block btn-sm mb-2 text-left" onclick="triggerEnrollFinger()">
                        <i class="fa-solid fa-fingerprint mr-2 text-success"></i> <strong>2. Capturar Huella Dactilar en Reloj</strong>
                        <div class="small text-muted pl-4">Activa el sensor del reloj para que el usuario coloque su dedo 3 veces.</div>
                    </button>

                    <button type="button" class="btn btn-outline-info btn-block btn-sm mb-2 text-left" onclick="syncTemplatesToDB()">
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
function openNewUserModal() {
    document.getElementById('usrModalTitle').innerHTML = '<i class="fa-solid fa-user-plus mr-2"></i> Crear Nuevo Usuario';
    document.getElementById('usr_id').value = '';
    document.getElementById('usr_usuario').value = '';
    document.getElementById('usr_usuario').readOnly = false;
    document.getElementById('usr_nombre').value = '';
    document.getElementById('usr_email').value = '';
    document.getElementById('usr_password').value = '';
    document.getElementById('usr_password').required = true;
    document.getElementById('usr_pass_hint').innerText = 'Contraseña inicial para el usuario (Obligatoria al crear)';
    document.getElementById('usr_rol').value = 'RRHH';
    document.getElementById('usr_activo').checked = true;

    // Preset inicial por defecto
    setPresetRRHH();
    $('#modalUsuario').modal('show');
}

function openEditUserModal(u) {
    document.getElementById('usrModalTitle').innerHTML = '<i class="fa-solid fa-user-pen mr-2"></i> Editar Usuario & Permisos';
    document.getElementById('usr_id').value = u.id;
    document.getElementById('usr_usuario').value = u.usuario;
    document.getElementById('usr_usuario').readOnly = false;
    document.getElementById('usr_nombre').value = u.nombre_completo;
    document.getElementById('usr_email').value = u.email || '';
    document.getElementById('usr_password').value = '';
    document.getElementById('usr_password').required = false;
    document.getElementById('usr_pass_hint').innerText = 'Dejar en blanco si deseas mantener la clave actual';
    document.getElementById('usr_rol').value = u.rol;
    document.getElementById('usr_activo').checked = (parseInt(u.activo) === 1);

    // Cargar permisos desde JSON
    toggleAllModules(false);
    var perms = [];
    try {
        perms = u.permisos ? JSON.parse(u.permisos) : [];
    } catch(e) {
        perms = [];
    }

    if (u.rol === 'ADMIN' || (Array.isArray(perms) && perms.includes('*'))) {
        toggleAllModules(true);
    } else if (Array.isArray(perms)) {
        perms.forEach(function(m) {
            var chk = document.getElementById('chk_' + m);
            if (chk) chk.checked = true;
        });
    }

    onRoleChange(u.rol);
    $('#modalUsuario').modal('show');
}

function onRoleChange(role) {
    var adminNotice = document.getElementById('adminNotice');
    if (role === 'ADMIN') {
        toggleAllModules(true);
        if (adminNotice) adminNotice.style.display = 'block';
    } else {
        if (adminNotice) adminNotice.style.display = 'none';
    }
}

function toggleAllModules(state) {
    document.querySelectorAll('.chk-modulo').forEach(function(c) {
        c.checked = state;
    });
}

function setPresetOnlyAsistencia() {
    toggleAllModules(false);
    var chk = document.getElementById('chk_asistencia');
    if (chk) chk.checked = true;
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'info',
        title: 'Asignado: Solo Asistencias Diarias',
        showConfirmButton: false,
        timer: 1800
    });
}

function setPresetRRHH() {
    toggleAllModules(false);
    ['dashboard', 'asistencia', 'marcaciones', 'empleados', 'turnos', 'justificaciones'].forEach(function(k) {
        var chk = document.getElementById('chk_' + k);
        if (chk) chk.checked = true;
    });
}

function setPresetSupervisor() {
    toggleAllModules(false);
    ['dashboard', 'asistencia', 'marcaciones', 'empleados', 'justificaciones'].forEach(function(k) {
        var chk = document.getElementById('chk_' + k);
        if (chk) chk.checked = true;
    });
}

function setPresetConsulta() {
    toggleAllModules(false);
    ['dashboard', 'asistencia', 'marcaciones'].forEach(function(k) {
        var chk = document.getElementById('chk_' + k);
        if (chk) chk.checked = true;
    });
}

// GESTIÓN BIOMÉTRICA
function openBiometricModal(u) {
    document.getElementById('bio_user_id').value = u.usuario;
    document.getElementById('bio_user_name').value = u.nombre_completo;
    document.getElementById('bioDisplayUser').innerText = u.nombre_completo + ' (' + u.usuario + ')';
    document.getElementById('bioDisplayCode').innerText = 'Código en Reloj: ' + u.usuario;

    loadBiometricStatus(u.usuario);
    $('#modalBiometria').modal('show');
}

function loadBiometricStatus(userId) {
    document.getElementById('bioStatusLoading').style.display = 'block';
    document.getElementById('bioStatusContent').style.display = 'none';

    fetch('?route=dispositivos&action=obtener_biometria_usuario&user_id=' + encodeURIComponent(userId))
        .then(function(res) { return res.json(); })
        .then(function(data) {
            document.getElementById('bioStatusLoading').style.display = 'none';
            document.getElementById('bioStatusContent').style.display = 'block';
            if (data.success) {
                document.getElementById('bioCountHuellas').innerText = data.huellas_count || 0;
                document.getElementById('bioCountFacial').innerText = (data.facial_count > 0 ? 'Registrado' : 'No');
            }
        })
        .catch(function() {
            document.getElementById('bioStatusLoading').style.display = 'none';
            document.getElementById('bioStatusContent').style.display = 'block';
            document.getElementById('bioCountHuellas').innerText = '-';
            document.getElementById('bioCountFacial').innerText = '-';
        });
}

function sendUserToClock() {
    var devId = document.getElementById('bio_device_select').value;
    var userId = document.getElementById('bio_user_id').value;
    var name = document.getElementById('bio_user_name').value;

    Swal.fire({
        title: 'Enviando al Reloj Biométrico...',
        text: 'Registrando usuario en la memoria del dispositivo.',
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
            Swal.fire('¡Registrado!', res.message || 'Usuario creado en el reloj con éxito.', 'success');
        } else {
            Swal.fire('Error en el Reloj', res.error || 'No se pudo registrar.', 'error');
        }
    })
    .catch(function(err) {
        Swal.fire('Error', 'Fallo de comunicación con el servidor.', 'error');
    });
}

function triggerEnrollFinger() {
    var devId = document.getElementById('bio_device_select').value;
    var userId = document.getElementById('bio_user_id').value;

    Swal.fire({
        title: '¡Coloca el dedo en el sensor!',
        html: 'Se ha activado el modo de enrolamiento en el reloj.<br><strong>Coloca tu dedo en el sensor óptico del reloj biométrico 3 veces consecutivas.</strong>',
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-check mr-1"></i> Ya coloqué la huella',
        cancelButtonText: 'Cancelar'
    }).then(function(result) {
        if (result.isConfirmed) {
            syncTemplatesToDB();
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

function syncTemplatesToDB() {
    var devId = document.getElementById('bio_device_select').value;
    var userId = document.getElementById('bio_user_id').value;

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
            loadBiometricStatus(userId);
            Swal.fire('¡Respaldado!', res.message || 'Plantillas biométricas guardadas en MySQL exitosamente.', 'success');
        } else {
            Swal.fire('Aviso', res.error || 'No se pudieron descargar las plantillas.', 'warning');
        }
    })
    .catch(function(err) {
        Swal.fire('Error', 'Fallo al procesar sincronización biométrica.', 'error');
    });
}
</script>

<?php require_once APP_ROOT . '/views/layout/footer.php'; ?>
