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
            <h5 class="text-secondary font-weight-bold mb-0">Biométricos Configurados en Red</h5>
            <div>
                <a href="?route=dispositivos&action=sincronizar" class="btn btn-success btn-sm shadow-sm">
                    <i class="fa-solid fa-arrows-rotate mr-1"></i> Sincronizar Todos Ahora
                </a>
                <button class="btn btn-primary btn-sm shadow-sm ml-1" onclick="openNewDeviceModal()">
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
                <div class="col-md-6 col-lg-4">
                    <div class="card card-outline <?= $d['estado_conexion'] === 'ONLINE' ? 'card-success' : 'card-danger' ?> shadow-sm">
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold"><?= htmlspecialchars($d['nombre']) ?></h3>
                            <div class="card-tools">
                                <?php if ($d['estado_conexion'] === 'ONLINE'): ?>
                                    <span class="badge badge-success px-2 py-1"><i class="fa-solid fa-signal mr-1"></i> ONLINE</span>
                                <?php else: ?>
                                    <span class="badge badge-danger px-2 py-1"><i class="fa-solid fa-circle-xmark mr-1"></i> OFFLINE</span>
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
                                    <span><?= $d['protocolo'] ?> (ComKey: <?= $d['clave_comunicacion'] ?>)</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between py-1">
                                    <b class="text-secondary">Ubicación / Sede:</b>
                                    <span><i class="fa-solid fa-location-dot text-danger mr-1"></i><?= htmlspecialchars($d['ubicacion'] ?? 'General') ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between py-1">
                                    <b class="text-secondary">Último Sync:</b>
                                    <span class="text-muted"><?= $d['ultimo_sync'] ? substr($d['ultimo_sync'], 0, 16) : 'Nunca' ?></span>
                                </li>
                            </ul>

                            <?php if (!empty($d['ultimo_error'])): ?>
                                <div class="alert alert-danger p-2 small mb-2">
                                    <i class="fa-solid fa-triangle-exclamation mr-1"></i> <?= htmlspecialchars(mb_strimwidth($d['ultimo_error'], 0, 90, '...')) ?>
                                </div>
                            <?php endif; ?>

                            <div class="d-flex justify-content-between">
                                <button class="btn btn-outline-primary btn-sm flex-grow-1 mr-1" onclick="testConnection(<?= $d['id'] ?>, this)">
                                    <i class="fa-solid fa-plug mr-1"></i> Probar Conexión
                                </button>
                                <a href="?route=dispositivos&action=sincronizar&id=<?= $d['id'] ?>" class="btn btn-outline-success btn-sm mr-1" title="Sincronizar este dispositivo">
                                    <i class="fa-solid fa-rotate"></i>
                                </a>
                                <button class="btn btn-outline-secondary btn-sm" onclick="openEditDeviceModal(<?= htmlspecialchars(json_encode($d)) ?>)" title="Editar">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
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
                                <td><span class="badge badge-light border"><?= $l['tipo_evento'] ?></span></td>
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
                            <label class="small font-weight-bold text-secondary">Clave (ComKey)</label>
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

function testConnection(deviceId, btn) {
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Conectando...';
    btn.disabled = true;

    fetch(`?route=dispositivos&action=test&id=${deviceId}`)
        .then(res => res.json())
        .then(data => {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
            
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Conexión Exitosa con ZKTeco!',
                    html: `<pre class="text-left bg-dark text-white p-3 rounded small">${data.output}</pre>`,
                    confirmButtonText: 'Aceptar'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Fallo de Conexión',
                    html: `<pre class="text-left bg-dark text-white p-3 rounded small">${data.output}</pre>`,
                    confirmButtonText: 'Entendido'
                });
            }
        })
        .catch(err => {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
            Swal.fire('Error', 'No se pudo completar la prueba de comunicación.', 'error');
        });
}
</script>

<?php require_once APP_ROOT . '/views/layout/footer.php'; ?>
