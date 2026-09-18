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
            <?php elseif ($_GET['msg'] === 'pass_restablecido'): ?>
                <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                    <i class="fa-solid fa-circle-check mr-2"></i> Contraseña del usuario restablecida exitosamente.
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            <?php elseif ($_GET['msg'] === 'error_password_requerida'): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                    <i class="fa-solid fa-triangle-exclamation mr-2"></i> Error: Al crear un nuevo usuario es obligatorio definir una contraseña.
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            <?php elseif ($_GET['msg'] === 'error_pass_corta'): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                    <i class="fa-solid fa-triangle-exclamation mr-2"></i> Error: La contraseña debe contener al menos 5 caracteres.
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
                <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                    <i class="fa-solid fa-circle-check mr-2"></i> Usuario eliminado definitivamente del sistema.
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            <?php elseif ($_GET['msg'] === 'error_admin_protegido'): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                    <i class="fa-solid fa-shield-halved mr-2"></i> Acción denegada: La cuenta de Administrador principal está protegida y no puede ser eliminada ni desactivada.
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            <?php elseif ($_GET['msg'] === 'error_auto_desactivar'): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                    <i class="fa-solid fa-triangle-exclamation mr-2"></i> Acción no permitida: No puedes desactivar tu propia cuenta mientras estás en sesión activa.
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            <?php elseif ($_GET['msg'] === 'error_auto_eliminar'): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                    <i class="fa-solid fa-triangle-exclamation mr-2"></i> Acción no permitida: No puedes eliminar tu propia cuenta mientras estás en sesión activa.
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            <?php elseif ($_GET['msg'] === 'error_db'): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                    <i class="fa-solid fa-triangle-exclamation mr-2"></i> Ocurrió un error en la base de datos al procesar la solicitud.
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- ACTIONS TOOLBAR -->
        <div class="actions-toolbar no-print">
            <div class="actions-toolbar-group">
                <h5 class="text-dark font-weight-bold mb-0" style="font-size: 1.05rem;">
                    <i class="fa-solid fa-users-gear mr-2 text-primary"></i> Lista de Cuentas y Permisos de Módulos
                </h5>
            </div>
            <div class="actions-toolbar-group">
                <button type="button" class="btn btn-primary btn-sm font-weight-bold" onclick="openNewUserModal()">
                    <i class="fa-solid fa-user-plus mr-1"></i> Crear Nuevo Usuario
                </button>
            </div>
        </div>

        <!-- MAIN TABLE CARD -->
        <div class="card">
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover datatable text-nowrap table-sm mb-0">
                    <thead>
                        <tr>
                            <th style="width: 45px;" class="text-center">ID</th>
                            <th style="min-width: 170px;">Usuario y Nombre</th>
                            <th style="width: 120px;" class="text-center">Rol Asignado</th>
                            <th style="min-width: 200px;">Módulos Autorizados</th>
                            <th class="text-center" style="width: 95px;">Biometría</th>
                            <th class="text-center" style="width: 80px;">Estado</th>
                            <th class="text-center" style="width: 110px;">Último Acceso</th>
                            <th class="text-center" style="width: 140px; white-space: nowrap;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                            <?php 
                                $userPerms = !empty($u['permisos']) ? json_decode($u['permisos'], true) : [];
                                $isAdmin = ($u['rol'] === 'ADMIN' || in_array('*', (array)$userPerms, true));
                            ?>
                            <tr id="row-user-<?= $u['id'] ?>">
                                <td class="font-monospace text-muted small text-center py-2">#<?= $u['id'] ?></td>
                                <td class="py-2">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center mr-2 border flex-shrink-0" style="width: 32px; height: 32px;">
                                            <i class="fa-solid fa-circle-user text-primary" style="font-size: 1rem;"></i>
                                        </div>
                                        <div>
                                            <div class="font-weight-bold text-dark" style="font-size: 0.85rem; line-height: 1.2;">
                                                <?= htmlspecialchars($u['usuario']) ?>
                                            </div>
                                            <div class="small text-secondary font-weight-bold" style="font-size: 0.76rem;"><?= htmlspecialchars($u['nombre_completo']) ?></div>
                                            <?php if (!empty($u['email'])): ?>
                                                <small class="text-muted d-block" style="font-size: 0.72rem;"><i class="fa-regular fa-envelope mr-1"></i><?= htmlspecialchars($u['email']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-2 text-center">
                                    <?php if ($u['rol'] === 'ADMIN'): ?>
                                        <span class="badge-pill-custom badge-pill-falta font-weight-bold" style="font-size: 0.7rem;">ADMINISTRADOR</span>
                                    <?php elseif ($u['rol'] === 'RRHH'): ?>
                                        <span class="badge-pill-custom badge-pill-justificado font-weight-bold" style="font-size: 0.7rem;">RECURSOS HUMANOS</span>
                                    <?php elseif ($u['rol'] === 'SUPERVISOR'): ?>
                                        <span class="badge-pill-custom badge-pill-tardanza font-weight-bold" style="font-size: 0.7rem;">SUPERVISOR</span>
                                    <?php else: ?>
                                        <span class="badge-pill-custom badge-pill-neutral font-weight-bold" style="font-size: 0.7rem;"><?= htmlspecialchars($u['rol']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2">
                                    <?php if ($isAdmin): ?>
                                        <span class="badge-pill-custom badge-pill-presente font-weight-bold" style="font-size: 0.72rem;"><i class="fa-solid fa-unlock-keyhole mr-1"></i> Acceso Total</span>
                                    <?php else: ?>
                                        <div class="d-flex flex-wrap" style="gap: 3px; max-width: 320px;">
                                            <?php 
                                                $activeCount = 0;
                                                foreach ($modulosDisponibles as $mKey => $mInfo): 
                                                     if (in_array($mKey, (array)$userPerms, true)):
                                                        $activeCount++;
                                            ?>
                                                <span class="badge-pill-custom badge-pill-neutral" style="font-size: 0.69rem; padding: 2px 6px;" title="<?= htmlspecialchars($mInfo['description']) ?>">
                                                    <i class="<?= $mInfo['icon'] ?> text-primary mr-1"></i><?= htmlspecialchars($mInfo['name']) ?>
                                                </span>
                                            <?php 
                                                    endif;
                                                endforeach; 
                                                if ($activeCount === 0):
                                            ?>
                                                <span class="badge-pill-custom badge-pill-tardanza" style="font-size: 0.7rem;">Sin módulos asignados</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center py-2">
                                    <?php if ((int)$u['huellas_count'] > 0): ?>
                                        <span class="badge-pill-custom badge-pill-presente" style="font-size: 0.7rem;" title="Huellas respaldadas en BDD"><i class="fa-solid fa-fingerprint mr-1"></i> <?= $u['huellas_count'] ?> Huella(s)</span>
                                    <?php else: ?>
                                        <span class="badge-pill-custom badge-pill-neutral" style="font-size: 0.7rem;"><i class="fa-solid fa-fingerprint mr-1"></i> Sin huella</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center py-2" id="user-status-badge-<?= $u['id'] ?>">
                                    <?php if ($u['activo']): ?>
                                        <span class="badge-pill-custom badge-pill-online" style="font-size: 0.7rem;"><i class="fa-solid fa-circle" style="font-size: 5px;"></i> Activo</span>
                                    <?php else: ?>
                                        <span class="badge-pill-custom badge-pill-offline" style="font-size: 0.7rem;"><i class="fa-solid fa-circle" style="font-size: 5px;"></i> Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2 text-center">
                                    <small class="text-muted font-monospace d-block" style="font-size: 0.74rem;">
                                        <?= !empty($u['ultimo_login']) ? date('d/m/Y H:i', strtotime($u['ultimo_login'])) : 'Nunca' ?>
                                    </small>
                                </td>
                                <td class="text-center py-2" style="white-space: nowrap;">
                                    <!-- Editar Usuario & Permisos -->
                                    <button type="button" class="btn btn-xs btn-outline-primary mr-1" onclick="openEditUserModal(<?= htmlspecialchars(json_encode($u)) ?>)" title="Editar Usuario">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>

                                    <!-- Restablecer Contraseña -->
                                    <button type="button" class="btn btn-xs btn-outline-warning mr-1" onclick="openResetPasswordModal(<?= htmlspecialchars(json_encode(['id' => $u['id'], 'usuario' => $u['usuario'], 'nombre_completo' => $u['nombre_completo']])) ?>)" title="Restablecer Contraseña">
                                        <i class="fa-solid fa-key"></i>
                                    </button>

                                    <!-- Biometría Reloj -->
                                    <button type="button" class="btn btn-xs btn-outline-success mr-1" onclick="openBiometricModal(<?= htmlspecialchars(json_encode($u)) ?>)" title="Biometría ZKTeco">
                                        <i class="fa-solid fa-fingerprint"></i>
                                    </button>

                                    <?php if ($u['id'] !== ($currentUser['id'] ?? 0) && $u['rol'] !== 'ADMIN'): ?>
                                        <!-- Cambiar Estado (Activar / Desactivar) -->
                                        <button type="button" class="btn btn-xs <?= $u['activo'] ? 'btn-outline-warning' : 'btn-outline-success' ?> mr-1" id="btn-toggle-status-<?= $u['id'] ?>" title="<?= $u['activo'] ? 'Desactivar Usuario' : 'Activar Usuario' ?>" onclick="toggleUserStatus(<?= $u['id'] ?>, '<?= htmlspecialchars($u['usuario'], ENT_QUOTES) ?>', <?= $u['activo'] ? 1 : 0 ?>)">
                                            <i class="fa-solid <?= $u['activo'] ? 'fa-user-slash' : 'fa-user-check' ?>"></i>
                                        </button>

                                        <!-- Eliminar Usuario Definitivamente -->
                                        <button type="button" class="btn btn-xs btn-outline-danger" title="Eliminar Usuario Definitivamente" onclick="deleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['usuario'], ENT_QUOTES) ?>')">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
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

<!-- MODAL GESTIÓN DE USUARIO -->
<div class="modal fade" id="modalUsuario" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form method="POST" action="?route=usuarios&action=guardar" class="modal-content shadow-lg" id="formUsuario">
            <?= csrf_field() ?>
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold" id="usrModalTitle">
                    <i class="fa-solid fa-user-gear mr-2"></i> Gestión de Usuario
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
                                    <label class="small font-weight-bold text-secondary" id="usr_pass_label">Contraseña <span class="text-danger" id="usr_pass_required_star">*</span></label>
                                    <div class="input-group input-group-sm">
                                        <input type="password" name="password" id="usr_password" class="form-control" placeholder="Ingresa contraseña">
                                        <div class="input-group-append">
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassVisibility('usr_password', this)" title="Ver/Ocultar contraseña">
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <small class="text-muted" id="usr_pass_hint">Contraseña obligatoria para el nuevo usuario</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-2">
                                    <label class="small font-weight-bold text-secondary">Rol Asignado <span class="text-danger">*</span></label>
                                    <select name="rol" id="usr_rol" class="form-control form-control-sm font-weight-bold" onchange="onRoleChange(this.value)">
                                        <option value="RRHH" selected>RRHH - Recursos Humanos</option>
                                        <option value="SUPERVISOR">SUPERVISOR - Supervisor de Área</option>
                                        <option value="CONSULTA">CONSULTA - Solo Consulta u Operador</option>
                                    </select>
                                    <div id="usr_admin_badge" class="badge badge-danger px-2 py-1 mt-1 font-weight-bold" style="display: none;">
                                        <i class="fa-solid fa-lock mr-1"></i> Administrador Principal (Único)
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row" id="row_supervisor_depto" style="display: none;">
                            <div class="col-md-12">
                                <div class="form-group mb-2 p-2 bg-light rounded border">
                                    <label class="small font-weight-bold text-primary"><i class="fa-solid fa-building-user mr-1"></i> Departamento / Área Asignada al Supervisor <span class="text-danger">*</span></label>
                                    <select name="departamento_id" id="usr_departamento_id" class="form-control form-control-sm">
                                        <option value="">-- Seleccionar Área que Supervisa --</option>
                                        <?php if (!empty($departamentos)): ?>
                                            <?php foreach ($departamentos as $dep): ?>
                                                <option value="<?= $dep['id'] ?>"><?= htmlspecialchars($dep['nombre']) ?></option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                    <small class="text-muted">El supervisor solo podrá ver y gestionar personal y justificaciones de este departamento.</small>
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
                        <i class="fa-solid fa-upload mr-2 text-primary"></i> <strong>1. Registrar y Enviar Usuario al Reloj</strong>
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

<!-- MODAL RESTABLECER CONTRASEÑA DE USUARIO (EXCLUSIVO ADMINISTRADOR) -->
<div class="modal fade" id="modalRestablecerPass" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 480px;">
        <div class="modal-content shadow-lg border-0" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-warning py-3 px-4 d-flex align-items-center justify-content-between">
                <h5 class="modal-title font-weight-bold text-dark mb-0 d-flex align-items-center" style="font-size: 1.05rem;">
                    <i class="fa-solid fa-key mr-2"></i> Restablecer Contraseña de Usuario
                </h5>
                <button type="button" class="close text-dark" data-dismiss="modal" aria-label="Close" style="outline: none;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4 bg-light">
                <input type="hidden" id="reset_user_id">

                <!-- DATOS DEL USUARIO SELECCIONADO -->
                <div class="card mb-3 border bg-white shadow-none" style="border-radius: 8px;">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center">
                            <div class="bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center mr-3 font-weight-bold" style="width: 42px; height: 42px; font-size: 1.1rem; flex-shrink: 0;">
                                <i class="fa-solid fa-user-lock"></i>
                            </div>
                            <div class="overflow-hidden">
                                <h6 class="font-weight-bold text-dark mb-0 text-truncate" id="reset_display_nombre">Nombre del Usuario</h6>
                                <div class="text-muted small font-monospace" id="reset_display_usuario">usuario: -</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FORMULARIO DE RESTABLECIMIENTO -->
                <div class="card border bg-white shadow-none mb-0" style="border-radius: 8px;">
                    <div class="card-body p-3">
                        <div class="form-group mb-2">
                            <label class="small font-weight-bold text-secondary mb-1">Nueva Contraseña para el Usuario <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm mb-2">
                                <input type="password" id="reset_new_pass" class="form-control font-monospace" placeholder="Mínimo 8 caracteres (mayúscula y número)" minlength="8" required>
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassVisibility('reset_new_pass', this)" title="Ver/Ocultar contraseña">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <small class="text-muted d-block mb-2">Debe tener al menos 8 caracteres, 1 mayúscula y 1 número. O usa el generador seguro:</small>

                            <!-- BOTÓN GENERAR CONTRASEÑA ALEATORIA -->
                            <button type="button" class="btn btn-outline-primary btn-xs btn-block py-1 mb-2" onclick="generateRandomPass()">
                                <i class="fa-solid fa-wand-magic-sparkles mr-1 text-warning"></i> Generar Contraseña Aleatoria Segura (12 car.)
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white d-flex justify-content-between py-2 px-4 border-top">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                <button type="button" id="btnConfirmResetPass" class="btn btn-warning btn-sm font-weight-bold" onclick="submitResetPassword()">
                    <i class="fa-solid fa-check mr-1"></i> Guardar Nueva Contraseña
                </button>
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
    document.getElementById('usr_password').placeholder = 'Contraseña requerida (min. 8 caracteres, mayúscula y número)';
    document.getElementById('usr_pass_hint').innerText = 'Mínimo 8 caracteres, con al menos una mayúscula y un número.';
    document.getElementById('usr_pass_required_star').style.display = 'inline';
    
    // Configuración de Rol: Exclusivo no-admin
    const rolSelect = document.getElementById('usr_rol');
    rolSelect.style.display = 'block';
    rolSelect.disabled = false;
    rolSelect.value = 'RRHH';
    document.getElementById('usr_admin_badge').style.display = 'none';
    if (document.getElementById('usr_departamento_id')) {
        document.getElementById('usr_departamento_id').value = '';
    }

    document.getElementById('usr_activo').checked = true;

    // Preset inicial por defecto
    setPresetRRHH();
    onRoleChange('RRHH');
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
    document.getElementById('usr_password').placeholder = 'Dejar en blanco para conservar actual';
    document.getElementById('usr_pass_hint').innerText = 'Dejar en blanco si deseas mantener la clave actual';
    document.getElementById('usr_pass_required_star').style.display = 'none';
    
    const rolSelect = document.getElementById('usr_rol');
    const adminBadge = document.getElementById('usr_admin_badge');

    if (u.rol === 'ADMIN') {
        rolSelect.style.display = 'none';
        adminBadge.style.display = 'inline-block';
    } else {
        rolSelect.style.display = 'block';
        rolSelect.disabled = false;
        rolSelect.value = inArray(u.rol, ['RRHH', 'SUPERVISOR', 'CONSULTA']) ? u.rol : 'RRHH';
        adminBadge.style.display = 'none';
    }

    if (document.getElementById('usr_departamento_id')) {
        document.getElementById('usr_departamento_id').value = u.departamento_id || '';
    }

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

function inArray(needle, haystack) {
    return Array.isArray(haystack) && haystack.indexOf(needle) !== -1;
}

// RESTABLECER CONTRASEÑA DE CUALQUIER USUARIO
function openResetPasswordModal(user) {
    document.getElementById('reset_user_id').value = user.id;
    document.getElementById('reset_display_nombre').innerText = user.nombre_completo || user.usuario;
    document.getElementById('reset_display_usuario').innerText = 'Cuenta: @' + user.usuario;
    document.getElementById('reset_new_pass').value = '';
    document.getElementById('reset_new_pass').type = 'password';
    $('#modalRestablecerPass').modal('show');
}

function generateRandomPass() {
    const upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    const lower = 'abcdefghijkmnopqrstuvwxyz';
    const digits = '23456789';
    const symbols = '!@#$%&*';
    const all = upper + lower + digits + symbols;

    // Garantizar que contenga al menos mayúsculas, minúsculas, números y símbolos
    let pass = [
        upper.charAt(Math.floor(Math.random() * upper.length)),
        upper.charAt(Math.floor(Math.random() * upper.length)),
        lower.charAt(Math.floor(Math.random() * lower.length)),
        lower.charAt(Math.floor(Math.random() * lower.length)),
        digits.charAt(Math.floor(Math.random() * digits.length)),
        digits.charAt(Math.floor(Math.random() * digits.length)),
        symbols.charAt(Math.floor(Math.random() * symbols.length))
    ];
    for (let i = 0; i < 5; i++) {
        pass.push(all.charAt(Math.floor(Math.random() * all.length)));
    }
    pass = pass.sort(() => Math.random() - 0.5).join('');

    const input = document.getElementById('reset_new_pass');
    input.value = pass;
    input.type = 'text'; // Mostrar para que el admin la pueda leer y copiar

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(pass).then(function() {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'info',
                title: 'Contraseña generada y copiada al portapapeles',
                showConfirmButton: false,
                timer: 2000
            });
        }).catch(function(){});
    }
}

function submitResetPassword() {
    const userId = document.getElementById('reset_user_id').value;
    const newPass = document.getElementById('reset_new_pass').value.trim();

    if (!newPass || newPass.length < 8 || !/[A-Z]/.test(newPass) || !/[0-9]/.test(newPass)) {
        Swal.fire({
            icon: 'warning',
            title: 'Contraseña no válida',
            text: 'La nueva contraseña debe tener al menos 8 caracteres, contener al menos una letra mayúscula y al menos un número.',
            confirmButtonColor: '#1d4ed8'
        });
        return;
    }

    const btn = document.getElementById('btnConfirmResetPass');
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Guardando...';

    const fd = new FormData();
    fd.append('id', userId);
    fd.append('new_password', newPass);

    fetch('?route=usuarios&action=restablecer_password', {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        btn.disabled = false;
        btn.innerHTML = origHtml;

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Contraseña Actualizada!',
                html: `
                    <p class="mb-2">${data.message || 'La contraseña se ha actualizado correctamente.'}</p>
                    <div class="alert alert-secondary p-2 font-monospace small mb-0">
                        <strong>Nueva clave:</strong> ${newPass}
                    </div>
                `,
                confirmButtonColor: '#1d4ed8'
            }).then(function() {
                $('#modalRestablecerPass').modal('hide');
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.error || 'No se pudo restablecer la contraseña.',
                confirmButtonColor: '#1d4ed8'
            });
        }
    })
    .catch(function(err) {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        Swal.fire('Error', 'Fallo al comunicarse con el servidor.', 'error');
    });
}

// CAMBIAR ESTADO DE USUARIO (ACTIVAR / DESACTIVAR) VÍA AJAX SEGURO
function toggleUserStatus(userId, username, currentStatus) {
    const actionText = currentStatus ? 'desactivar' : 'activar';
    const confirmBtnColor = currentStatus ? '#f59e0b' : '#16a34a';

    Swal.fire({
        title: `¿Deseas ${actionText} a ${username}?`,
        text: currentStatus ? 'El usuario no podrá iniciar sesión en el sistema mientras esté inactivo.' : 'El usuario volverá a tener acceso con sus credenciales y permisos habituales.',
        icon: currentStatus ? 'warning' : 'question',
        showCancelButton: true,
        confirmButtonColor: confirmBtnColor,
        cancelButtonColor: '#64748b',
        confirmButtonText: `Sí, ${actionText}`,
        cancelButtonText: 'Cancelar'
    }).then(function(result) {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Actualizando estado...',
                allowOutsideClick: false,
                didOpen: function() { Swal.showLoading(); }
            });

            const token = window._csrfToken || '<?= csrf_token() ?>';
            const fd = new FormData();
            fd.append('id', userId);
            fd.append('_csrf', token);
            fd.append('_csrf_token', token);

            fetch('?route=usuarios&action=cambiar_estado', {
                method: 'POST',
                body: fd,
                headers: { 
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': token
                }
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Estado Actualizado!',
                        text: data.message || 'Estado del usuario modificado con éxito.',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(function() {
                        window.location.reload();
                    });
                } else {
                    Swal.fire('Error', data.error || 'No se pudo cambiar el estado.', 'error');
                }
            })
            .catch(function() {
                Swal.fire('Error', 'Fallo de red al intentar cambiar el estado.', 'error');
            });
        }
    });
}

// ELIMINAR USUARIO DEFINITIVAMENTE VÍA AJAX SEGURO
function deleteUser(userId, username) {
    Swal.fire({
        title: `¿Eliminar a ${username}?`,
        html: `¿Estás seguro de que deseas <b>eliminar definitivamente</b> al usuario <b>${username}</b> del sistema?<br><br><span class="text-danger small font-weight-bold"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Esta acción es irreversible y borrará la cuenta por completo de la base de datos.</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        confirmButtonText: '<i class="fa-solid fa-trash mr-1"></i> Sí, eliminar definitivamente',
        cancelButtonText: 'Cancelar'
    }).then(function(result) {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Eliminando usuario...',
                text: 'Procesando eliminación física en el servidor',
                allowOutsideClick: false,
                didOpen: function() { Swal.showLoading(); }
            });

            const token = window._csrfToken || '<?= csrf_token() ?>';
            const fd = new FormData();
            fd.append('id', userId);
            fd.append('_csrf', token);
            fd.append('_csrf_token', token);

            fetch('?route=usuarios&action=eliminar', {
                method: 'POST',
                body: fd,
                headers: { 
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': token
                }
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Usuario Eliminado!',
                        text: data.message || 'El usuario ha sido eliminado definitivamente.',
                        timer: 1600,
                        showConfirmButton: false
                    }).then(function() {
                        window.location.reload();
                    });
                } else {
                    Swal.fire('No se pudo eliminar', data.error || 'Ocurrió un error al eliminar.', 'error');
                }
            })
            .catch(function() {
                Swal.fire('Error', 'Fallo de red al intentar eliminar el usuario.', 'error');
            });
        }
    });
}

function onRoleChange(role) {
    var adminNotice = document.getElementById('adminNotice');
    var rowSupervisor = document.getElementById('row_supervisor_depto');
    if (role === 'ADMIN') {
        toggleAllModules(true);
        if (adminNotice) adminNotice.style.display = 'block';
    } else {
        if (adminNotice) adminNotice.style.display = 'none';
    }

    if (rowSupervisor) {
        if (role === 'SUPERVISOR') {
            rowSupervisor.style.display = 'block';
        } else {
            rowSupervisor.style.display = 'none';
            var deptoSelect = document.getElementById('usr_departamento_id');
            if (deptoSelect && role !== 'SUPERVISOR') {
                deptoSelect.value = '';
            }
        }
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

