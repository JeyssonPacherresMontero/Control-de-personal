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
                    <i class="fa-solid fa-triangle-exclamation mr-2"></i> Error: La contraseña debe tener al menos 8 caracteres, una mayúscula y un número.
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

        <?php
            $userTotal = count($usuarios);
            $userAdmin = 0;
            $userGestion = 0;
            $userActivos = 0;
            foreach ($usuarios as $u) {
                if (!empty($u['activo'])) $userActivos++;
                if (($u['rol'] ?? '') === 'ADMIN') $userAdmin++;
                if (in_array($u['rol'] ?? '', ['RRHH', 'SUPERVISOR', 'ASISTENTE'], true)) $userGestion++;
            }
            $userConsulta = $userTotal - $userAdmin - $userGestion;
        ?>

        <!-- KPI SUMMARY CARDS (PANTALLA) -->
        <div class="row no-print mb-2">
            <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                <div class="kpi-card h-100">
                    <div class="kpi-card-header">
                        <div>
                            <div class="kpi-title">Total Cuentas</div>
                            <div class="kpi-value"><?= $userTotal ?></div>
                            <div class="kpi-subtitle"><b><?= $userActivos ?></b> cuentas activas</div>
                        </div>
                        <div class="kpi-icon-box">
                            <i class="fa-solid fa-users-gear"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                <div class="kpi-card h-100">
                    <div class="kpi-card-header">
                        <div>
                            <div class="kpi-title">Administradores</div>
                            <div class="kpi-value"><?= $userAdmin ?></div>
                            <div class="kpi-subtitle">Acceso global irrestricto</div>
                        </div>
                        <div class="kpi-icon-box">
                            <i class="fa-solid fa-user-shield"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                <div class="kpi-card h-100">
                    <div class="kpi-card-header">
                        <div>
                            <div class="kpi-title">Gestión Operativa</div>
                            <div class="kpi-value"><?= $userGestion ?></div>
                            <div class="kpi-subtitle">RRHH, Supervisor y Asistente</div>
                        </div>
                        <div class="kpi-icon-box">
                            <i class="fa-solid fa-user-tie"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                <div class="kpi-card h-100">
                    <div class="kpi-card-header">
                        <div>
                            <div class="kpi-title">Usuarios / Consulta</div>
                            <div class="kpi-value"><?= $userConsulta ?></div>
                            <div class="kpi-subtitle">Acceso de visualización básica</div>
                        </div>
                        <div class="kpi-icon-box">
                            <i class="fa-solid fa-user-check"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ACTIONS TOOLBAR -->
        <div class="actions-toolbar no-print mb-3">
            <div class="actions-toolbar-group">
                <span class="font-weight-bold text-dark" style="font-size: 0.95rem;">
                    <i class="fa-solid fa-users-gear mr-2 text-primary"></i> Lista de Cuentas y Permisos de Módulos
                </span>
            </div>
            <div class="actions-toolbar-group flex-wrap">
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
                                        <span class="badge-pill-custom badge-role-admin font-weight-bold" style="font-size: 0.72rem;"><i class="fa-solid fa-shield-halved mr-1"></i> ADMIN</span>
                                    <?php elseif ($u['rol'] === 'RRHH'): ?>
                                        <span class="badge-pill-custom badge-role-rrhh font-weight-bold" style="font-size: 0.72rem;"><i class="fa-solid fa-user-tie mr-1"></i> RRHH</span>
                                    <?php elseif ($u['rol'] === 'SUPERVISOR'): ?>
                                        <span class="badge-pill-custom badge-role-supervisor font-weight-bold" style="font-size: 0.72rem;"><i class="fa-solid fa-eye mr-1"></i> SUPERVISOR</span>
                                    <?php elseif ($u['rol'] === 'ASISTENTE'): ?>
                                        <span class="badge-pill-custom badge-role-asistente font-weight-bold" style="font-size: 0.72rem;"><i class="fa-solid fa-clipboard-user mr-1"></i> ASISTENTE</span>
                                    <?php else: ?>
                                        <span class="badge-pill-custom badge-role-user font-weight-bold" style="font-size: 0.72rem;"><i class="fa-solid fa-user mr-1"></i> <?= htmlspecialchars($u['rol']) ?></span>
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
                                                    <i class="<?= $mInfo['icon'] ?> text-secondary mr-1"></i><?= htmlspecialchars($mInfo['name']) ?>
                                                </span>
                                            <?php 
                                                     endif;
                                                endforeach; 
                                                if ($activeCount === 0):
                                            ?>
                                                <span class="badge-pill-custom badge-pill-neutral text-muted" style="font-size: 0.7rem;">Sin módulos asignados</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center py-2">
                                    <?php if ((int)$u['huellas_count'] > 0): ?>
                                        <span class="badge-pill-custom badge-pill-presente" style="font-size: 0.7rem;" title="Huellas respaldadas en BDD"><i class="fa-solid fa-fingerprint mr-1"></i> <?= $u['huellas_count'] ?> Huella(s)</span>
                                    <?php else: ?>
                                        <span class="badge-pill-custom badge-pill-neutral" style="font-size: 0.7rem;"><i class="fa-solid fa-fingerprint mr-1 text-secondary"></i> Sin huella</span>
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
                                    <div class="btn-group btn-group-sm" role="group">
                                        <!-- Editar Usuario & Permisos -->
                                        <button type="button" class="btn btn-outline-secondary" onclick="openEditUserModal(<?= htmlspecialchars(json_encode($u)) ?>)" title="Editar Usuario">
                                            <i class="fa-solid fa-pen text-secondary"></i>
                                        </button>

                                        <!-- Restablecer Contraseña -->
                                        <button type="button" class="btn btn-outline-secondary" onclick="openResetPasswordModal(<?= htmlspecialchars(json_encode(['id' => $u['id'], 'usuario' => $u['usuario'], 'nombre_completo' => $u['nombre_completo']])) ?>)" title="Restablecer Contraseña">
                                            <i class="fa-solid fa-key text-secondary"></i>
                                        </button>

                                        <!-- Biometría Reloj -->
                                        <button type="button" class="btn btn-outline-secondary" onclick="openBiometricModal(<?= htmlspecialchars(json_encode($u)) ?>)" title="Biometría ZKTeco">
                                            <i class="fa-solid fa-fingerprint text-secondary"></i>
                                        </button>

                                        <?php if ($u['id'] !== ($currentUser['id'] ?? 0) && $u['rol'] !== 'ADMIN'): ?>
                                            <!-- Cambiar Estado (Activar / Desactivar) -->
                                            <button type="button" class="btn btn-outline-secondary" id="btn-toggle-status-<?= $u['id'] ?>" title="<?= $u['activo'] ? 'Desactivar Usuario' : 'Activar Usuario' ?>" onclick="toggleUserStatus(<?= $u['id'] ?>, '<?= htmlspecialchars($u['usuario'], ENT_QUOTES) ?>', <?= $u['activo'] ? 1 : 0 ?>)">
                                                <i class="fa-solid <?= $u['activo'] ? 'fa-user-slash text-muted' : 'fa-user-check text-success' ?>"></i>
                                            </button>

                                            <!-- Eliminar Usuario Definitivamente -->
                                            <button type="button" class="btn btn-outline-secondary text-danger" title="Eliminar Usuario Definitivamente" onclick="deleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['usuario'], ENT_QUOTES) ?>')">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
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
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document" style="max-width: 840px;">
        <form method="POST" action="?route=usuarios&action=guardar" class="modal-content shadow-lg border-0 rounded-lg" id="formUsuario">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold d-flex align-items-center" id="usrModalTitle">
                    <i class="fa-solid fa-user-gear mr-2" style="color: #1e40af;"></i> Gestión de Usuario
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="id" id="usr_id">

                <!-- DATOS PRINCIPALES -->
                <div class="modal-section-title">
                    <i class="fa-solid fa-id-card mr-2"></i> Datos de la Cuenta y Rol
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="modal-form-label">Nombre de Usuario (Login) <span class="text-danger">*</span></label>
                            <input type="text" name="usuario" id="usr_usuario" class="form-control font-monospace" placeholder="Ej: joperador, rrhh_asistencias" style="height: 38px;" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="modal-form-label">Nombre Completo <span class="text-danger">*</span></label>
                            <input type="text" name="nombre_completo" id="usr_nombre" class="form-control" placeholder="Ej: Juan Pérez Morales" style="height: 38px;" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="modal-form-label">Correo Electrónico</label>
                            <input type="email" name="email" id="usr_email" class="form-control" placeholder="usuario@empresa.com" style="height: 38px;">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="modal-form-label" id="usr_pass_label">Contraseña <span class="text-danger" id="usr_pass_required_star">*</span></label>
                            <div class="input-group">
                                <input type="password" name="password" id="usr_password" class="form-control" placeholder="Ingresa contraseña" style="height: 38px;">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassVisibility('usr_password', this)" title="Ver/Ocultar contraseña" style="height: 38px;">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <small class="text-muted d-block mt-1" id="usr_pass_hint" style="font-size: 0.78rem;">Obligatoria para nuevo usuario</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="modal-form-label">Rol Asignado <span class="text-danger">*</span></label>
                            <select name="rol" id="usr_rol" class="form-control font-weight-bold" onchange="onRoleChange(this.value)" style="height: 38px;">
                                <option value="RRHH" selected>RRHH - Recursos Humanos</option>
                                <option value="SUPERVISOR">SUPERVISOR - Supervisor de Área</option>
                                <option value="CONSULTA">CONSULTA - Solo Consulta u Operador</option>
                            </select>
                            <div id="usr_admin_badge" class="badge px-2.5 py-1 mt-1 font-weight-bold" style="background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; display: none; font-size: 0.8rem;">
                                <i class="fa-solid fa-lock mr-1"></i> Administrador Principal (Único)
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row" id="row_supervisor_depto" style="display: none;">
                    <div class="col-md-12">
                        <div class="form-group mb-3 p-3 rounded border" style="background: #f8fafc; border-color: #e2e8f0 !important;">
                            <label class="modal-form-label d-flex align-items-center mb-1.5" style="color: #1e40af;">
                                <i class="fa-solid fa-building-user mr-1.5"></i> Departamento / Área Asignada al Supervisor <span class="text-danger ml-1">*</span>
                            </label>
                            <select name="departamento_id" id="usr_departamento_id" class="form-control" style="height: 38px;">
                                <option value="">-- Seleccionar Área que Supervisa --</option>
                                <?php if (!empty($departamentos)): ?>
                                    <?php foreach ($departamentos as $dep): ?>
                                        <option value="<?= $dep['id'] ?>"><?= htmlspecialchars($dep['nombre']) ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <small class="text-muted d-block mt-1" style="font-size: 0.78rem;">El supervisor solo podrá ver y gestionar personal y justificaciones de este departamento.</small>
                        </div>
                    </div>
                </div>

                <div class="p-3 rounded border mt-1 mb-4" style="background: #f8fafc; border-color: #e2e8f0 !important;">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="usr_activo" name="activo" value="1" checked>
                        <label class="custom-control-label font-weight-bold" for="usr_activo" style="color: #334155; cursor: pointer; font-size: 0.88rem;">
                            Usuario Activo en el Sistema
                        </label>
                        <small class="d-block text-muted mt-1" style="font-size: 0.78rem;">
                            Los usuarios inactivos no pueden iniciar sesión ni operar en la plataforma.
                        </small>
                    </div>
                </div>

                <!-- SECCIÓN DE APARTADOS DEL MENÚ (PERMISOS) -->
                <div id="containerPermisos">
                    <div class="modal-section-title d-flex align-items-center justify-content-between flex-wrap">
                        <span><i class="fa-solid fa-shield-halved mr-2"></i> Control de Acceso a Módulos</span>
                        <span class="text-muted font-normal" style="font-size: 0.78rem; text-transform: none; letter-spacing: normal;">Habilita las vistas autorizadas para este usuario</span>
                    </div>

                    <!-- HERRAMIENTAS DE PLANTILLA RÁPIDA -->
                    <div class="mb-3 p-2 rounded border d-flex flex-wrap align-items-center" style="background: #f8fafc; border-color: #e2e8f0 !important; gap: 6px;">
                        <span class="small font-weight-bold text-muted mr-1" style="font-size: 0.78rem;"><i class="fa-solid fa-wand-magic-sparkles mr-1 text-secondary"></i> Accesos Rápidos:</span>
                        <button type="button" class="btn btn-xs btn-outline-secondary font-weight-medium" onclick="setPresetOnlyAsistencia()">
                            <i class="fa-solid fa-calendar-check mr-1 text-primary"></i> Solo Asistencias
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-secondary font-weight-medium" onclick="setPresetRRHH()">
                            <i class="fa-solid fa-users mr-1 text-primary"></i> Perfil RRHH
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-secondary font-weight-medium" onclick="setPresetSupervisor()">
                            <i class="fa-solid fa-user-tie mr-1 text-primary"></i> Supervisor
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-secondary font-weight-medium" onclick="setPresetConsulta()">
                            <i class="fa-solid fa-eye mr-1 text-primary"></i> Solo Consulta
                        </button>
                        <div class="mx-1 border-left" style="height: 18px; border-color: #cbd5e1 !important;"></div>
                        <button type="button" class="btn btn-xs btn-light border text-dark font-weight-medium" onclick="toggleAllModules(true)">
                            <i class="fa-solid fa-check-double text-primary mr-1"></i> Todos
                        </button>
                        <button type="button" class="btn btn-xs btn-light border text-muted font-weight-medium" onclick="toggleAllModules(false)">
                            <i class="fa-solid fa-xmark mr-1"></i> Ninguno
                        </button>
                    </div>

                    <!-- MATRIZ DE CHECKBOXES DE MÓDULOS -->
                    <div class="row">
                        <?php foreach ($modulosDisponibles as $modKey => $modInfo): ?>
                            <?php if ($modKey === 'usuarios') continue; // Solo para Admin interno ?>
                            <div class="col-md-6 mb-2">
                                <div class="p-2.5 px-3 border rounded module-card h-100 bg-white" style="border-color: #e2e8f0 !important; transition: all 0.2s;">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input chk-modulo" id="chk_<?= $modKey ?>" name="permisos[]" value="<?= $modKey ?>">
                                        <label class="custom-control-label font-weight-semibold text-dark d-flex align-items-center" for="chk_<?= $modKey ?>" style="cursor: pointer; font-size: 0.88rem;">
                                            <i class="<?= $modInfo['icon'] ?> mr-2" style="font-size: 1rem; width: 20px; color: #1e40af;"></i>
                                            <span><?= htmlspecialchars($modInfo['name']) ?></span>
                                        </label>
                                    </div>
                                    <div class="small text-muted pl-4 mt-1" style="font-size: 0.78rem; line-height: 1.3;">
                                        <?= htmlspecialchars($modInfo['description']) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div id="adminNotice" class="alert py-2.5 px-3 small mt-3 mb-0 rounded" style="background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; display: none;">
                        <i class="fa-solid fa-circle-info mr-1"></i> Los usuarios con rol <strong>ADMIN</strong> tienen acceso integral a todos los módulos y configuraciones del sistema automáticamente.
                    </div>
                </div>

            </div>
            <div class="modal-footer justify-content-between bg-light px-4 py-3" style="border-top: 1px solid #e2e8f0;">
                <button type="button" class="btn btn-outline-secondary px-3" data-dismiss="modal">
                    <i class="fa-solid fa-times mr-1"></i> Cancelar
                </button>
                <button type="submit" class="btn btn-primary px-4 font-weight-bold">
                    <i class="fa-solid fa-floppy-disk mr-1"></i> Guardar Usuario y Accesos
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DE ENROLAMIENTO BIOMÉTRICO (HUELLA / FACIAL EN RELOJ Y BDD) -->
<div class="modal fade" id="modalBiometria" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 540px;">
        <div class="modal-content shadow-lg border-0 rounded-lg">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold d-flex align-items-center">
                    <i class="fa-solid fa-fingerprint mr-2" style="color: #1e40af;"></i> Gestión Biométrica ZKTeco
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="bio_user_id">
                <input type="hidden" id="bio_user_name">

                <div class="p-3 rounded border mb-3 text-center" style="background: #f8fafc; border-color: #e2e8f0 !important;">
                    <div class="h5 font-weight-bold mb-1" id="bioDisplayUser" style="color: #0f172a;">Usuario</div>
                    <span class="badge border text-secondary px-2.5 py-1 font-monospace" id="bioDisplayCode" style="background: #ffffff; border-color: #cbd5e1 !important; font-size: 0.8rem;">ID Reloj: -</span>
                </div>

                <div class="form-group mb-3">
                    <label class="modal-form-label">Seleccionar Reloj Biométrico ZKTeco</label>
                    <select id="bio_device_select" class="form-control" style="height: 38px;">
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
                <div class="modal-section-title mt-3">
                    <i class="fa-solid fa-database mr-2"></i> Estado en Base de Datos Central
                </div>
                <div class="p-3 rounded border mb-3 bg-white" style="border-color: #e2e8f0 !important;">
                    <div id="bioStatusLoading" class="text-center text-muted small py-2">
                        <i class="fa-solid fa-spinner fa-spin mr-1"></i> Consultando plantillas biométricas...
                    </div>
                    <div id="bioStatusContent" style="display: none;">
                        <div class="d-flex justify-content-around text-center">
                            <div>
                                <i class="fa-solid fa-fingerprint fa-2x mb-1" style="color: #1e40af;"></i>
                                <div class="font-weight-bold h5 mb-0" id="bioCountHuellas" style="color: #0f172a;">0</div>
                                <small class="text-muted">Huellas en BDD</small>
                            </div>
                            <div class="border-left" style="border-color: #e2e8f0 !important;"></div>
                            <div>
                                <i class="fa-solid fa-camera fa-2x mb-1" style="color: #0284c7;"></i>
                                <div class="font-weight-bold h5 mb-0" id="bioCountFacial" style="color: #0f172a;">0</div>
                                <small class="text-muted">Rostro Facial</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ACCIONES CON EL RELOJ BIOMÉTRICO -->
                <div class="modal-section-title mt-3">
                    <i class="fa-solid fa-network-wired mr-2"></i> Acciones con el Dispositivo
                </div>
                <div class="d-flex flex-column" style="gap: 8px;">
                    <button type="button" class="btn btn-outline-secondary btn-block text-left p-2.5 bg-white border" style="border-color: #e2e8f0 !important; border-radius: 8px; transition: all 0.15s;" onclick="sendUserToClock()">
                        <div class="d-flex align-items-center">
                            <i class="fa-solid fa-upload mr-2.5" style="color: #1e40af; font-size: 1.1rem; width: 22px; text-align: center;"></i>
                            <div>
                                <strong class="d-block text-dark" style="font-size: 0.88rem;">1. Registrar Usuario en el Reloj</strong>
                                <span class="small text-muted" style="font-size: 0.78rem;">Crea el nombre y número de usuario en la memoria del dispositivo.</span>
                            </div>
                        </div>
                    </button>

                    <button type="button" class="btn btn-outline-secondary btn-block text-left p-2.5 bg-white border mt-0" style="border-color: #e2e8f0 !important; border-radius: 8px; transition: all 0.15s;" onclick="triggerEnrollFinger()">
                        <div class="d-flex align-items-center">
                            <i class="fa-solid fa-fingerprint mr-2.5" style="color: #1e40af; font-size: 1.1rem; width: 22px; text-align: center;"></i>
                            <div>
                                <strong class="d-block text-dark" style="font-size: 0.88rem;">2. Capturar Huella Dactilar en Reloj</strong>
                                <span class="small text-muted" style="font-size: 0.78rem;">Activa el sensor del reloj para que el usuario coloque su dedo 3 veces.</span>
                            </div>
                        </div>
                    </button>

                    <button type="button" class="btn btn-outline-secondary btn-block text-left p-2.5 bg-white border mt-0" style="border-color: #e2e8f0 !important; border-radius: 8px; transition: all 0.15s;" onclick="syncTemplatesToDB()">
                        <div class="d-flex align-items-center">
                            <i class="fa-solid fa-cloud-arrow-down mr-2.5" style="color: #1e40af; font-size: 1.1rem; width: 22px; text-align: center;"></i>
                            <div>
                                <strong class="d-block text-dark" style="font-size: 0.88rem;">3. Respaldar a Base de Datos Central</strong>
                                <span class="small text-muted" style="font-size: 0.78rem;">Descarga las plantillas del reloj y las guarda en el servidor.</span>
                            </div>
                        </div>
                    </button>
                </div>

            </div>
            <div class="modal-footer justify-content-end bg-light px-4 py-3" style="border-top: 1px solid #e2e8f0;">
                <button type="button" class="btn btn-outline-secondary px-3" data-dismiss="modal">
                    <i class="fa-solid fa-times mr-1"></i> Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL RESTABLECER CONTRASEÑA DE USUARIO (EXCLUSIVO ADMINISTRADOR) -->
<div class="modal fade" id="modalRestablecerPass" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 480px;">
        <div class="modal-content shadow-lg border-0 rounded-lg">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold d-flex align-items-center">
                    <i class="fa-solid fa-key mr-2" style="color: #1e40af;"></i> Restablecer Contraseña
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body p-4 bg-white">
                <input type="hidden" id="reset_user_id">

                <!-- DATOS DEL USUARIO SELECCIONADO -->
                <div class="p-3 rounded border mb-3 d-flex align-items-center" style="background: #f8fafc; border-color: #e2e8f0 !important;">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mr-3 font-weight-bold" style="width: 44px; height: 44px; font-size: 1.1rem; background: #e0e7ff; color: #1e40af; flex-shrink: 0;">
                        <i class="fa-solid fa-user-lock"></i>
                    </div>
                    <div class="overflow-hidden">
                        <h6 class="font-weight-bold mb-0 text-truncate" id="reset_display_nombre" style="color: #0f172a; font-size: 0.95rem;">Nombre del Usuario</h6>
                        <div class="text-muted small font-monospace" id="reset_display_usuario">usuario: -</div>
                    </div>
                </div>

                <!-- FORMULARIO DE RESTABLECIMIENTO -->
                <div class="form-group mb-3">
                    <label class="modal-form-label">Nueva Contraseña para el Usuario <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" id="reset_new_pass" class="form-control font-monospace" placeholder="Mínimo 8 caracteres" minlength="8" style="height: 38px;" required>
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassVisibility('reset_new_pass', this)" title="Ver/Ocultar contraseña" style="height: 38px;">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <small class="text-muted d-block mt-1" style="font-size: 0.78rem;">Debe tener al menos 8 caracteres, 1 mayúscula y 1 número.</small>
                </div>

                <!-- BOTÓN GENERAR CONTRASEÑA ALEATORIA -->
                <button type="button" class="btn btn-outline-secondary btn-block py-2 mb-2 bg-white text-dark font-weight-medium" onclick="generateRandomPass()" style="border-color: #cbd5e1; font-size: 0.85rem;">
                    <i class="fa-solid fa-key mr-2" style="color: #1e40af;"></i> Generar Contraseña Segura (12 car.)
                </button>
            </div>
            <div class="modal-footer d-flex justify-content-between bg-light px-4 py-3" style="border-top: 1px solid #e2e8f0;">
                <button type="button" class="btn btn-outline-secondary px-3" data-dismiss="modal">
                    <i class="fa-solid fa-times mr-1"></i> Cancelar
                </button>
                <button type="button" id="btnConfirmResetPass" class="btn btn-primary px-4 font-weight-bold" onclick="submitResetPassword()">
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

