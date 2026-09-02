<?php require_once APP_ROOT . '/views/layout/header.php'; ?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-sm-6">
                <h1 class="m-0 font-weight-bold"><i class="fa-solid fa-network-wired mr-2 text-primary"></i> Relojes Biométricos ZKTeco</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="?route=dashboard">Inicio</a></li>
                    <li class="breadcrumb-item active">Dispositivos</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">

        <!-- ACTIONS ROW -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="text-dark font-weight-bold mb-0">
                    <i class="fa-solid fa-server mr-1 text-primary"></i> Biométricos Configurados en Red
                </h5>
                <small class="text-muted">Gestión de terminales ZKTeco, conectividad IP y protocolos</small>
            </div>
            <div class="d-flex align-items-center">
                <!-- Botón Principal: Sync Rápido Hoy -->
                <button type="button" class="btn btn-success btn-sm shadow-sm" onclick="syncAllDevices('today', this)">
                    <i class="fa-solid fa-bolt mr-1"></i> Sincronizar Hoy (Rápido)
                </button>

                <!-- Menú Desplegable de Opciones Avanzadas -->
                <div class="btn-group ml-1">
                    <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle shadow-sm bg-white" data-toggle="dropdown" aria-expanded="false">
                        <i class="fa-solid fa-sliders mr-1"></i> Opciones
                    </button>
                    <div class="dropdown-menu dropdown-menu-right shadow border-0">
                        <a class="dropdown-item py-2" href="javascript:void(0)" onclick="syncAllDevices('full', this)">
                            <i class="fa-solid fa-database mr-2 text-primary"></i> Sincronización Histórica Completa
                        </a>
                        <?php if (($currentUser['rol'] ?? '') === 'ADMIN'): ?>
                            <div class="dropdown-divider"></div>
                            <h6 class="dropdown-header text-danger font-weight-bold"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Mantenimiento de Hardware</h6>
                            <a class="dropdown-item py-2 text-danger" href="javascript:void(0)" onclick="openClearMemoryModal()">
                                <i class="fa-solid fa-broom mr-2"></i> Respaldar y Liberar Memoria del Reloj
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <button class="btn btn-primary btn-sm shadow-sm ml-2" onclick="openNewDeviceModal()">
                    <i class="fa-solid fa-plus mr-1"></i> Nuevo Dispositivo
                </button>
            </div>
        </div>

        <?php if (isset($_SESSION['flash_sync_output'])): ?>
            <div class="alert alert-<?= $_SESSION['flash_sync_status'] === 'success' ? 'success' : 'warning' ?> alert-dismissible fade show" role="alert">
                <h5 class="font-weight-bold"><i class="icon fas fa-info-circle"></i> Resultado del Ciclo de Sincronización:</h5>
                <pre class="mb-0 bg-dark text-white p-2 rounded small" style="max-height: 160px; overflow-y: auto;"><?= htmlspecialchars($_SESSION['flash_sync_output']) ?></pre>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <?php unset($_SESSION['flash_sync_output'], $_SESSION['flash_sync_status']); ?>
        <?php endif; ?>

        <!-- DEVICE CARDS -->
        <div class="row">
            <?php foreach ($dispositivos as $d): ?>
                <div class="col-md-6 col-lg-4" id="card-col-<?= $d['id'] ?>">
                    <div class="card card-outline <?= $d['estado_conexion'] === 'ONLINE' ? 'card-success' : 'card-danger' ?> shadow-sm" id="device-card-<?= $d['id'] ?>">
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold text-dark"><?= htmlspecialchars($d['nombre']) ?></h3>
                            <div class="card-tools" id="device-badge-<?= $d['id'] ?>">
                                <?php if ($d['estado_conexion'] === 'ONLINE'): ?>
                                    <span class="badge badge-success px-2 py-1"><i class="fa-solid fa-signal mr-1"></i> EN LÍNEA</span>
                                <?php else: ?>
                                    <span class="badge badge-danger px-2 py-1"><i class="fa-solid fa-circle-xmark mr-1"></i> DESCONECTADO</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body py-2">
                            <ul class="list-group list-group-unbordered mb-3 small">
                                <li class="list-group-item d-flex justify-content-between py-1">
                                    <b class="text-secondary">Dirección IP:</b>
                                    <span class="font-weight-bold font-monospace"><?= htmlspecialchars($d['ip']) ?>:<?= $d['puerto'] ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between py-1">
                                    <b class="text-secondary">Protocolo / Clave:</b>
                                    <span>Protocolo <?= $d['protocolo'] ?> (Clave: <?= $d['clave_comunicacion'] ?>)</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between py-1">
                                    <b class="text-secondary">Ubicación / Sede:</b>
                                    <span><i class="fa-solid fa-location-dot text-danger mr-1"></i><?= htmlspecialchars($d['ubicacion'] ?? 'General') ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between py-1">
                                    <b class="text-secondary">Última Sincronización:</b>
                                    <span class="text-muted" id="device-sync-<?= $d['id'] ?>"><?= $d['ultimo_sync'] ? substr($d['ultimo_sync'], 0, 16) : 'Nunca' ?></span>
                                </li>
                            </ul>

                            <div id="device-error-<?= $d['id'] ?>">
                                <?php if (!empty($d['ultimo_error'])): ?>
                                    <div class="alert alert-danger p-2 small mb-2">
                                        <i class="fa-solid fa-triangle-exclamation mr-1"></i> <?= htmlspecialchars(mb_strimwidth($d['ultimo_error'], 0, 90, '...')) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <button type="button" class="btn btn-outline-primary btn-sm flex-grow-1 mr-1" onclick="testConnection(<?= $d['id'] ?>, this)">
                                    <i class="fa-solid fa-plug mr-1"></i> Probar Conexión
                                </button>
                                <button type="button" class="btn btn-success btn-sm mr-1" onclick="syncDevice(<?= $d['id'] ?>, this, 'today')" title="Sincronizar hoy (rápido)">
                                    <i class="fa-solid fa-bolt"></i>
                                </button>
                                
                                <div class="btn-group">
                                    <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-toggle="dropdown">
                                        <i class="fa-solid fa-gear"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right shadow border-0">
                                        <a class="dropdown-item small" href="javascript:void(0)" onclick="syncDevice(<?= $d['id'] ?>, null, 'full')">
                                            <i class="fa-solid fa-database mr-2 text-primary"></i> Sincronización Histórica
                                        </a>
                                        <a class="dropdown-item small" href="javascript:void(0)" onclick="openEditDeviceModal(<?= htmlspecialchars(json_encode($d)) ?>)">
                                            <i class="fa-solid fa-pen mr-2 text-secondary"></i> Editar Configuración
                                        </a>
                                        <?php if (($currentUser['rol'] ?? '') === 'ADMIN'): ?>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item small text-danger" href="javascript:void(0)" onclick="confirmClearDeviceMemory(<?= $d['id'] ?>, '<?= htmlspecialchars($d['nombre']) ?>')">
                                                <i class="fa-solid fa-broom mr-2"></i> Liberar Memoria del Reloj
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- LOGS TABLE CARD -->
        <div class="card card-primary card-outline shadow-sm mt-3">
            <div class="card-header">
                <h3 class="card-title font-weight-bold">
                    <i class="fa-solid fa-list-check mr-1 text-primary"></i>
                    Historial de Sincronización y Auditoría
                </h3>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-hover datatable text-nowrap table-sm">
                    <thead class="thead-light">
                        <tr>
                            <th>Fecha y Hora</th>
                            <th>Dispositivo</th>
                            <th>Evento</th>
                            <th>Descargados</th>
                            <th>Insertados</th>
                            <th>Duplicados</th>
                            <th>Estado</th>
                            <th>Duración</th>
                            <th>Mensaje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $l): ?>
                            <tr>
                                <td class="text-secondary"><?= $l['fecha_hora'] ?></td>
                                <td class="font-weight-bold text-dark"><?= htmlspecialchars($l['dispositivo_nombre'] ?? 'Global') ?></td>
                                <td>
                                    <?php
                                        $eventoLabel = match($l['tipo_evento']) {
                                            'SYNC_AUTO' => 'Sincronización Automática',
                                            'SYNC_MANUAL' => 'Sincronización Manual',
                                            'TEST_CONEXION' => 'Prueba de Conexión',
                                            'CLEAR_ATTENDANCE' => 'Limpieza de Memoria',
                                            'SYNC_USERS' => 'Sincronización de Usuarios',
                                            'ERROR' => 'Error',
                                            default => htmlspecialchars($l['tipo_evento'])
                                        };
                                    ?>
                                    <span class="badge badge-light border"><?= $eventoLabel ?></span>
                                </td>
                                <td class="text-center font-weight-bold"><?= $l['total_descargados'] ?></td>
                                <td class="text-center text-success font-weight-bold">+<?= $l['total_insertados'] ?></td>
                                <td class="text-center text-muted"><?= $l['total_duplicados'] ?></td>
                                <td>
                                    <?php if ($l['estado'] === 'EXITO'): ?>
                                        <span class="badge badge-success px-2 py-1">Éxito</span>
                                    <?php elseif ($l['estado'] === 'ERROR'): ?>
                                        <span class="badge badge-danger px-2 py-1">Error</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning text-white px-2 py-1">Alerta</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $l['duracion_segundos'] ?>s</td>
                                <td class="small text-muted" title="<?= htmlspecialchars($l['mensaje'] ?? '') ?>">
                                    <?= htmlspecialchars(mb_strimwidth($l['mensaje'] ?? '', 0, 50, '...')) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</section>

<!-- MODAL CREAR / EDITAR DISPOSITIVO -->
<div class="modal fade" id="modalDispositivo" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="?route=dispositivos&action=guardar" class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold" id="deviceModalTitle">Configurar Reloj Biométrico</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="dev_id">
                
                <div class="form-group">
                    <label class="small font-weight-bold text-secondary">Nombre Descriptivo</label>
                    <input type="text" name="nombre" id="dev_nombre" class="form-control form-control-sm" placeholder="Ej: Reloj Principal Recepción" required>
                </div>

                <div class="row">
                    <div class="col-8">
                        <div class="form-group">
                            <label class="small font-weight-bold text-secondary">Dirección IP Fija</label>
                            <input type="text" name="ip" id="dev_ip" class="form-control form-control-sm font-monospace" placeholder="192.168.1.201" required>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="form-group">
                            <label class="small font-weight-bold text-secondary">Puerto</label>
                            <input type="number" name="puerto" id="dev_puerto" class="form-control form-control-sm font-monospace" value="4370" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label class="small font-weight-bold text-secondary">Protocolo</label>
                            <select name="protocolo" id="dev_protocolo" class="form-control form-control-sm">
                                <option value="TCP">TCP (Estándar)</option>
                                <option value="UDP">UDP</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label class="small font-weight-bold text-secondary">Clave de Comunicación (ComKey)</label>
                            <input type="number" name="clave_comunicacion" id="dev_clave" class="form-control form-control-sm" value="0">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="small font-weight-bold text-secondary">Ubicación / Sede</label>
                    <input type="text" name="ubicacion" id="dev_ubicacion" class="form-control form-control-sm" placeholder="Ej: Puerta Principal, Almacén...">
                </div>

                <div class="form-group">
                    <label class="small font-weight-bold text-secondary">Modelo</label>
                    <input type="text" name="modelo" id="dev_modelo" class="form-control form-control-sm" placeholder="Ej: ZKTeco MB20 / K40 / SilkBio">
                </div>

                <div class="form-group custom-control custom-checkbox">
                    <input class="custom-control-input" type="checkbox" name="activo" id="dev_activo" value="1" checked>
                    <label class="custom-control-label small font-weight-bold" for="dev_activo">Dispositivo Activo para Sincronización</label>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-floppy-disk mr-1"></i> Guardar Dispositivo</button>
            </div>
        </form>
    </div>
</div>

<script>
function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
}

function openNewDeviceModal() {
    document.getElementById('deviceModalTitle').innerText = 'Nuevo Reloj Biométrico';
    document.getElementById('dev_id').value = '';
    document.getElementById('dev_nombre').value = '';
    document.getElementById('dev_ip').value = '';
    document.getElementById('dev_puerto').value = '4370';
    document.getElementById('dev_protocolo').value = 'TCP';
    document.getElementById('dev_clave').value = '0';
    document.getElementById('dev_ubicacion').value = '';
    document.getElementById('dev_modelo').value = '';
    document.getElementById('dev_activo').checked = true;
    $('#modalDispositivo').modal('show');
}

function openEditDeviceModal(d) {
    document.getElementById('deviceModalTitle').innerText = 'Editar Reloj Biométrico';
    document.getElementById('dev_id').value = d.id;
    document.getElementById('dev_nombre').value = d.nombre;
    document.getElementById('dev_ip').value = d.ip;
    document.getElementById('dev_puerto').value = d.puerto || 4370;
    document.getElementById('dev_protocolo').value = d.protocolo || 'TCP';
    document.getElementById('dev_clave').value = d.clave_comunicacion || 0;
    document.getElementById('dev_ubicacion').value = d.ubicacion || '';
    document.getElementById('dev_modelo').value = d.modelo || '';
    document.getElementById('dev_activo').checked = (parseInt(d.activo) === 1);
    $('#modalDispositivo').modal('show');
}

function updateDeviceCardUI(deviceId, status, lastSync, lastError) {
    const card = document.getElementById(`device-card-${deviceId}`);
    const badge = document.getElementById(`device-badge-${deviceId}`);
    const syncEl = document.getElementById(`device-sync-${deviceId}`);
    const errorEl = document.getElementById(`device-error-${deviceId}`);

    if (card) {
        if (status === 'ONLINE') {
            card.classList.remove('card-danger');
            card.classList.add('card-success');
        } else {
            card.classList.remove('card-success');
            card.classList.add('card-danger');
        }
    }

    if (badge) {
        if (status === 'ONLINE') {
            badge.innerHTML = '<span class="badge badge-success px-2 py-1"><i class="fa-solid fa-signal mr-1"></i> EN LÍNEA</span>';
        } else {
            badge.innerHTML = '<span class="badge badge-danger px-2 py-1"><i class="fa-solid fa-circle-xmark mr-1"></i> DESCONECTADO</span>';
        }
    }

    if (syncEl && lastSync) {
        syncEl.innerText = lastSync;
    }

    if (errorEl) {
        if (status === 'ONLINE' || !lastError) {
            errorEl.innerHTML = '';
        } else {
            const shortError = lastError.length > 90 ? lastError.substring(0, 90) + '...' : lastError;
            errorEl.innerHTML = `
                <div class="alert alert-danger p-2 small mb-2">
                    <i class="fa-solid fa-triangle-exclamation mr-1"></i> ${escapeHtml(shortError)}
                </div>
            `;
        }
    }
}

function testConnection(deviceId, btn) {
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Probando...';
    btn.disabled = true;

    fetch(`?route=dispositivos&action=test&id=${deviceId}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(async res => {
        const text = await res.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            throw new Error(text || 'Respuesta vacía o formato inválido del servidor.');
        }
    })
    .then(data => {
        btn.innerHTML = originalHtml;
        btn.disabled = false;

        updateDeviceCardUI(deviceId, data.estado_conexion, data.ultimo_sync, data.ultimo_error);

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Conexión Exitosa con ZKTeco!',
                html: `<pre class="text-left bg-dark text-white p-3 rounded small" style="max-height: 250px; overflow-y: auto;">${escapeHtml(data.output)}</pre>`,
                confirmButtonText: 'Aceptar',
                confirmButtonColor: '#28a745'
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Fallo de Conexión',
                html: `<pre class="text-left bg-dark text-white p-3 rounded small" style="max-height: 250px; overflow-y: auto;">${escapeHtml(data.output)}</pre>`,
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#dc3545'
            });
        }
    })
    .catch(err => {
        btn.innerHTML = originalHtml;
        btn.disabled = false;
        Swal.fire('Error', 'No se pudo completar la prueba de comunicación: ' + err.message, 'error');
    });
}

function monitorSyncProgress(btn, originalHtml, customTitle) {
    let secondsElapsed = 0;
    
    Swal.fire({
        title: customTitle || 'Sincronizando Relojes Biométricos...',
        html: `
            <div class="text-center py-2">
                <i class="fa-solid fa-arrows-rotate fa-spin fa-3x text-success mb-3"></i>
                <p class="mb-1 font-weight-bold text-dark" id="swal-sync-msg">Descargando marcaciones y usuarios desde ZKTeco...</p>
                <div class="badge badge-light border px-2 py-1 text-muted mb-2" id="swal-sync-timer">Tiempo transcurrido: 0s</div>
                <pre class="text-left bg-dark text-white p-2 rounded small" id="swal-sync-log" style="max-height: 120px; overflow-y: auto; font-size: 11px; display: none;"></pre>
            </div>
        `,
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    const timerInterval = setInterval(() => {
        secondsElapsed++;
        const timerEl = document.getElementById('swal-sync-timer');
        if (timerEl) {
            timerEl.innerText = `Tiempo transcurrido: ${secondsElapsed}s`;
        }
    }, 1000);

    const pollInterval = setInterval(() => {
        fetch('?route=dispositivos&action=sync_status', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            const logEl = document.getElementById('swal-sync-log');
            if (logEl && data.log_tail) {
                logEl.style.display = 'block';
                logEl.innerText = data.log_tail;
                logEl.scrollTop = logEl.scrollHeight;
            }

            if (!data.running) {
                clearInterval(pollInterval);
                clearInterval(timerInterval);

                if (btn) {
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                }

                if (data.dispositivos) {
                    data.dispositivos.forEach(d => {
                        updateDeviceCardUI(d.id, d.estado_conexion, d.ultimo_sync ? d.ultimo_sync.substring(0, 16) : 'Nunca', d.ultimo_error);
                    });
                }

                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Sincronización Completada!',
                        html: `<pre class="text-left bg-dark text-white p-3 rounded small" style="max-height: 250px; overflow-y: auto;">${escapeHtml(data.output)}</pre>`,
                        confirmButtonText: 'Aceptar',
                        confirmButtonColor: '#28a745'
                    }).then(() => {
                        location.href = '?route=dispositivos';
                    });
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Resultado de Sincronización',
                        html: `<pre class="text-left bg-dark text-white p-3 rounded small" style="max-height: 250px; overflow-y: auto;">${escapeHtml(data.output)}</pre>`,
                        confirmButtonText: 'Entendido'
                    }).then(() => {
                        location.href = '?route=dispositivos';
                    });
                }
            }
        })
        .catch(err => {
            console.error("Polling error:", err);
        });
    }, 2500);
}

function syncDevice(deviceId, btn, mode = 'today') {
    const originalHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        btn.disabled = true;
    }

    const title = (mode === 'today') ? 'Sincronización Rápida de Hoy...' : 'Sincronización Histórica Completa...';

    fetch(`?route=dispositivos&action=sincronizar&id=${deviceId}&mode=${mode}&ajax=1`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        monitorSyncProgress(btn, originalHtml, title);
    })
    .catch(err => {
        if (btn) {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        }
        Swal.fire('Error', 'No se pudo iniciar la sincronización: ' + err.message, 'error');
    });
}

function syncAllDevices(mode = 'today', btn = null) {
    const originalHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Sincronizando...';
        btn.disabled = true;
    }

    const title = (mode === 'today') ? 'Sincronizando Todos los Relojes (Solo Hoy)...' : 'Sincronizando Todo el Histórico...';

    fetch(`?route=dispositivos&action=sincronizar&mode=${mode}&ajax=1`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        monitorSyncProgress(btn, originalHtml, title);
    })
    .catch(err => {
        if (btn) {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        }
        Swal.fire('Error', 'No se pudo iniciar la sincronización masiva: ' + err.message, 'error');
    });
}

function confirmClearDeviceMemory(deviceId, deviceName) {
    Swal.fire({
        title: '¿Liberar memoria del reloj?',
        html: `
            <div class="text-left small text-secondary">
                <p>Estás a punto de vaciar el búfer de marcaciones del biométrico <b>${escapeHtml(deviceName)}</b>.</p>
                <div class="alert alert-warning p-2">
                    <i class="fa-solid fa-triangle-exclamation mr-1"></i> 
                    <b>Asegúrate de haber sincronizado primero</b> para que todos los registros históricos estén respaldados en la base de datos MySQL.
                </div>
                <p class="mb-0">Al liberar la memoria, las futuras sincronizaciones tomarán <b>menos de 0.5 segundos</b> en lugar de procesar miles de registros antiguos.</p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fa-solid fa-broom mr-1"></i> Sí, liberar memoria',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Conectando con el biométrico...',
                text: 'Enviando comando de limpieza de registros...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const formData = new FormData();
            formData.append('id', deviceId);

            fetch('?route=dispositivos&action=limpiar_memoria', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Memoria Liberada!',
                        text: data.output || 'Se ha vaciado la memoria del reloj. Las siguientes sincronizaciones serán ultrarrápidas.',
                        confirmButtonColor: '#28a745'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'No se pudo liberar memoria',
                        text: data.output || 'Ocurrió un error al comunicarse con el reloj.'
                    });
                }
            })
            .catch(err => {
                Swal.fire('Error', 'Fallo de red: ' + err.message, 'error');
            });
        }
    });
}

function openClearMemoryModal() {
    // Si hay más de un dispositivo, consultar cuál
    const devices = <?= json_encode(array_map(function($d) { return ['id' => $d['id'], 'nombre' => $d['nombre']]; }, $dispositivos)) ?>;
    if (devices.length === 1) {
        confirmClearDeviceMemory(devices[0].id, devices[0].nombre);
    } else {
        let inputOptions = {};
        devices.forEach(d => {
            inputOptions[d.id] = d.nombre;
        });

        Swal.fire({
            title: 'Selecciona el Reloj a Limpiar',
            input: 'select',
            inputOptions: inputOptions,
            inputPlaceholder: '-- Seleccionar dispositivo --',
            showCancelButton: true,
            confirmButtonText: 'Continuar',
            cancelButtonText: 'Cancelar',
            inputValidator: (value) => {
                if (!value) return 'Debes seleccionar un reloj biométrico';
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const selectedDev = devices.find(d => d.id == result.value);
                if (selectedDev) {
                    confirmClearDeviceMemory(selectedDev.id, selectedDev.nombre);
                }
            }
        });
    }
}
</script>

<?php require_once APP_ROOT . '/views/layout/footer.php'; ?>

