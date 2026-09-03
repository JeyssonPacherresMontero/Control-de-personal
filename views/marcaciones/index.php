<?php require_once APP_ROOT . '/views/layout/header.php'; ?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-sm-6">
                <h1 class="m-0 font-weight-bold"><i class="fa-solid fa-clock-rotate-left mr-2 text-primary"></i> Registro de Marcaciones de Relojes Biométricos</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="?route=dashboard">Inicio</a></li>
                    <li class="breadcrumb-item active">Marcaciones</li>
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
                <h3 class="card-title font-weight-bold"><i class="fa-solid fa-filter mr-1 text-secondary"></i> Filtros de Auditoría</h3>
                <div class="card-tools d-flex align-items-center flex-wrap">
                    <?php if (in_array($userRole, ['ADMIN', 'RRHH'], true)): ?>
                        <button class="btn btn-primary btn-sm shadow-sm mr-1" data-toggle="modal" data-target="#modalNuevaMarcacion">
                            <i class="fa-solid fa-plus mr-1"></i> Registrar Marcación
                        </button>
                    <?php endif; ?>
                    <a href="?route=marcaciones&fecha=<?= $fecha ?>&dispositivo_id=<?= $dispositivoId ?>&search=<?= urlencode($search ?? '') ?>&export=excel" class="btn btn-success btn-sm shadow-sm mr-1" title="Exportar marcaciones a Excel">
                        <i class="fa-solid fa-file-excel mr-1"></i> Excel
                    </a>
                    <a href="?route=marcaciones&fecha=<?= $fecha ?>&dispositivo_id=<?= $dispositivoId ?>&search=<?= urlencode($search ?? '') ?>&export=csv" class="btn btn-outline-secondary btn-sm shadow-sm mr-1" title="Exportar a CSV">
                        <i class="fa-solid fa-file-csv mr-1"></i> CSV
                    </a>
                    <button type="button" class="btn btn-outline-dark btn-sm shadow-sm" onclick="window.print()" title="Imprimir o PDF">
                        <i class="fa-solid fa-print"></i>
                    </button>
                </div>
            </div>
            <div class="card-body py-3">
                <form method="GET" action="" class="row align-items-end">
                    <input type="hidden" name="route" value="marcaciones">

                    <div class="col-md-3 mb-2">
                        <label class="small font-weight-bold text-secondary mb-1">Fecha</label>
                        <input type="date" name="fecha" class="form-control form-control-sm" value="<?= htmlspecialchars($fecha) ?>">
                    </div>

                    <div class="col-md-4 mb-2">
                        <label class="small font-weight-bold text-secondary mb-1">Dispositivo Biométrico</label>
                        <select name="dispositivo_id" class="form-control form-control-sm">
                            <option value="">-- Todos los Relojes --</option>
                            <?php foreach ($dispositivos as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= $dispositivoId == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['nombre']) ?> (<?= htmlspecialchars($d['ip']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4 mb-2">
                        <label class="small font-weight-bold text-secondary mb-1">Buscar Empleado / ID Reloj</label>
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Nombre, DNI o ID..." value="<?= htmlspecialchars($search ?? '') ?>">
                    </div>

                    <div class="col-md-1 mb-2">
                        <button type="submit" class="btn btn-secondary btn-sm btn-block"><i class="fa-solid fa-magnifying-glass"></i></button>
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
                            <th>N° Registro</th>
                            <th>Fecha y Hora</th>
                            <th>ID en Reloj</th>
                            <th>Empleado Identificado</th>
                            <th>Reloj Biométrico</th>
                            <th>Tipo de Marcación</th>
                            <th>Método de Verificación</th>
                            <th class="text-center">Estado de Procesamiento</th>
                            <th class="text-center">Trazabilidad Event Sourcing</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($marcaciones as $m): ?>
                            <tr>
                                <td class="text-muted font-monospace">#<?= $m['id'] ?></td>
                                <td class="font-weight-bold text-dark"><?= $m['fecha_hora'] ?></td>
                                <td><span class="badge badge-light border">ID: <?= htmlspecialchars($m['codigo_reloj']) ?></span></td>
                                <td>
                                    <?php if (!empty($m['nombres'])): ?>
                                        <div class="font-weight-bold text-dark"><?= htmlspecialchars($m['apellidos'] . ' ' . $m['nombres']) ?></div>
                                        <small class="text-muted">DNI: <?= htmlspecialchars($m['dni']) ?></small>
                                    <?php else: ?>
                                        <span class="badge badge-warning"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Sin vincular a empleado</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="text-dark font-weight-bold"><?= htmlspecialchars($m['dispositivo_nombre'] ?? 'Desconocido') ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($m['dispositivo_ip'] ?? '') ?></small>
                                </td>
                                <td>
                                    <?php
                                        $t = strtolower($m['tipo']);
                                        if ($t === 'entrada') echo '<span class="badge badge-success px-2 py-1"><i class="fa-solid fa-arrow-right-to-bracket mr-1"></i>Entrada</span>';
                                        elseif ($t === 'salida') echo '<span class="badge badge-primary px-2 py-1"><i class="fa-solid fa-arrow-right-from-bracket mr-1"></i>Salida</span>';
                                        elseif (str_contains($t, 'refrigerio')) echo '<span class="badge badge-info px-2 py-1"><i class="fa-solid fa-utensils mr-1"></i>Refrigerio</span>';
                                        else echo '<span class="badge badge-secondary px-2 py-1">Marcación</span>';
                                    ?>
                                </td>
                                <td>
                                    <?php
                                        $verif = strtolower($m['tipo_verificacion'] ?? '');
                                        if (str_contains($verif, 'huella') || $verif === 'fingerprint') {
                                             echo '<i class="fa-solid fa-fingerprint text-primary mr-1"></i> Huella Dactilar';
                                        } elseif (str_contains($verif, 'facial') || str_contains($verif, 'face')) {
                                            echo '<i class="fa-solid fa-camera text-info mr-1"></i> Rostro / Facial';
                                        } elseif (str_contains($verif, 'tarjeta') || str_contains($verif, 'card') || str_contains($verif, 'rfid')) {
                                            echo '<i class="fa-solid fa-id-card text-success mr-1"></i> Tarjeta RFID';
                                        } elseif (str_contains($verif, 'manual')) {
                                            echo '<i class="fa-solid fa-keyboard text-secondary mr-1"></i> Registro Manual RRHH';
                                        } elseif (str_contains($verif, 'clave') || str_contains($verif, 'pin') || str_contains($verif, 'password')) {
                                            echo '<i class="fa-solid fa-key text-warning mr-1"></i> Contraseña / PIN';
                                        } else {
                                            echo '<i class="fa-solid fa-check text-muted mr-1"></i> ' . htmlspecialchars($m['tipo_verificacion'] ?: 'Biométrico');
                                        }
                                    ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($m['procesado']): ?>
                                        <span class="badge badge-success px-2 py-1"><i class="fa-solid fa-circle-check mr-1"></i> Procesado</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning text-white px-2 py-1"><i class="fa-solid fa-clock mr-1"></i> Pendiente</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($m['id_empleado'])): ?>
                                        <button class="btn btn-xs btn-outline-info" onclick="openTimelineModal(<?= $m['id_empleado'] ?>, '<?= substr($m['fecha_hora'], 0, 10) ?>', '<?= htmlspecialchars(addslashes(($m['apellidos'] ?? '') . ' ' . ($m['nombres'] ?? ''))) ?>')" title="Ver flujo completo de eventos inmutables">
                                            <i class="fa-solid fa-timeline mr-1"></i> Auditoría
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted small">N/A</span>
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

<?php if (in_array($userRole, ['ADMIN', 'RRHH'], true)): ?>
<!-- MODAL NUEVA MARCACION MANUAL -->
<div class="modal fade" id="modalNuevaMarcacion" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="?route=marcaciones&action=guardar_manual" class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold"><i class="fa-solid fa-plus-circle mr-2"></i> Registrar Marcación Manual</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="small font-weight-bold text-secondary">Empleado</label>
                    <select name="id_empleado" class="form-control form-control-sm" required>
                        <option value="">-- Seleccionar Empleado --</option>
                        <?php foreach ($empleados as $e): ?>
                            <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['apellidos'] . ' ' . $e['nombres']) ?> (ID Reloj: <?= htmlspecialchars($e['codigo_reloj']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="small font-weight-bold text-secondary">Fecha y Hora</label>
                    <input type="datetime-local" name="fecha_hora" class="form-control form-control-sm" value="<?= date('Y-m-d\TH:i') ?>" required>
                </div>

                <div class="form-group">
                    <label class="small font-weight-bold text-secondary">Tipo de Marcación</label>
                    <select name="tipo" class="form-control form-control-sm">
                        <option value="entrada">Entrada</option>
                        <option value="salida">Salida</option>
                        <option value="refrigerio_salida">Salida a Refrigerio</option>
                        <option value="refrigerio_entrada">Regreso de Refrigerio</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="small font-weight-bold text-secondary">Dispositivo Asociado</label>
                    <select name="id_dispositivo" class="form-control form-control-sm">
                        <?php foreach ($dispositivos as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nombre']) ?> (<?= htmlspecialchars($d['ip']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-floppy-disk mr-1"></i> Guardar Marcación</button>
            </div>
        </form>
    </div>
</div>
<!-- MODAL EVENT SOURCING: LÍNEA DE TIEMPO DE AUDITORÍA Y TRAZABILIDAD -->
<div class="modal fade" id="modalTimelineEventos" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title font-weight-bold">
                    <i class="fa-solid fa-timeline text-info mr-2"></i> Trazabilidad y Event Sourcing
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="d-flex justify-content-between align-items-center bg-white p-3 rounded shadow-sm mb-4 border">
                    <div>
                        <h6 class="font-weight-bold mb-1 text-primary" id="timelineEmpleadoNombre">Cargando empleado...</h6>
                        <small class="text-muted"><i class="fa-solid fa-id-card mr-1"></i> DNI: <span id="timelineEmpleadoDni">--</span> | ID Reloj: <span id="timelineEmpleadoReloj">--</span></small>
                    </div>
                    <div class="text-right">
                        <span class="badge badge-light border px-2 py-1 font-weight-bold text-secondary" id="timelineFecha">--</span>
                        <div class="small text-muted mt-1" id="timelineTotalEventos">-- eventos registrados</div>
                    </div>
                </div>

                <div id="timelineLoading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="small text-muted mt-2 font-weight-bold">Recuperando flujo de eventos inmutables desde el Event Store...</div>
                </div>

                <div id="timelineContent" class="timeline" style="display: none;"></div>

                <div id="timelineEmpty" class="alert alert-secondary text-center py-4" style="display: none;">
                    <i class="fa-solid fa-inbox fa-2x mb-2 text-muted"></i>
                    <div>No se encontraron eventos registrados para este día.</div>
                </div>
            </div>
            <div class="modal-footer justify-content-between bg-white">
                <span class="small text-muted"><i class="fa-solid fa-shield-halved mr-1"></i> Log inmutable respaldado por arquitectura Event Sourcing</span>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
function openTimelineModal(empId, fecha, nombreEmp) {
    document.getElementById('timelineEmpleadoNombre').innerText = nombreEmp;
    document.getElementById('timelineFecha').innerText = fecha;
    document.getElementById('timelineEmpleadoDni').innerText = '...';
    document.getElementById('timelineEmpleadoReloj').innerText = '...';
    document.getElementById('timelineTotalEventos').innerText = 'Cargando...';
    
    document.getElementById('timelineLoading').style.display = 'block';
    document.getElementById('timelineContent').style.display = 'none';
    document.getElementById('timelineEmpty').style.display = 'none';

    $('#modalTimelineEventos').modal('show');

    fetch(`?route=asistencia&action=historial_eventos&id_empleado=${empId}&fecha=${fecha}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('timelineLoading').style.display = 'none';

            if (!data.success || !data.events || data.events.length === 0) {
                document.getElementById('timelineEmpty').style.display = 'block';
                document.getElementById('timelineTotalEventos').innerText = '0 eventos';
                return;
            }

            if (data.empleado) {
                document.getElementById('timelineEmpleadoNombre').innerText = `${data.empleado.apellidos} ${data.empleado.nombres}`;
                document.getElementById('timelineEmpleadoDni').innerText = data.empleado.dni || '--';
                document.getElementById('timelineEmpleadoReloj').innerText = data.empleado.codigo_reloj || '--';
            }

            document.getElementById('timelineTotalEventos').innerText = `${data.total} evento(s) inmutable(s)`;

            const container = document.getElementById('timelineContent');
            container.innerHTML = '';

            let timelineHtml = `
                <div class="time-label">
                    <span class="bg-primary text-white font-weight-bold px-3 py-1 rounded shadow-sm">${data.fecha}</span>
                </div>
            `;

            data.events.forEach((ev, idx) => {
                const disp = ev.display || {};
                const hora = ev.created_at ? ev.created_at.substr(11, 8) : '--:--';
                const version = ev.version ? `<span class="badge badge-light border ml-1">v${ev.version}</span>` : '';
                
                let detailsHtml = '';
                if (disp.details && Object.keys(disp.details).length > 0) {
                    detailsHtml = '<div class="row mt-2 pt-2 border-top small text-muted">';
                    for (const [k, v] of Object.entries(disp.details)) {
                        detailsHtml += `<div class="col-sm-6 mb-1"><strong>${k}:</strong> <span class="text-dark">${v}</span></div>`;
                    }
                    detailsHtml += '</div>';
                }

                timelineHtml += `
                    <div>
                        <i class="fa-solid ${disp.icon || 'fa-circle'} bg-info"></i>
                        <div class="timeline-item shadow-sm">
                            <span class="time font-weight-bold text-secondary"><i class="fas fa-clock mr-1"></i>${hora} ${version}</span>
                            <h3 class="timeline-header">
                                <span class="badge ${disp.badgeClass || 'badge-secondary'} mr-2">${ev.event_type}</span>
                                <strong>${disp.title || ev.event_type}</strong>
                            </h3>
                            <div class="timeline-body">
                                <p class="mb-0 text-dark">${disp.description || ''}</p>
                                ${detailsHtml}
                            </div>
                            <div class="timeline-footer py-1 px-3 bg-light d-flex justify-content-between align-items-center">
                                <small class="text-muted"><i class="fa-solid fa-user-shield mr-1"></i> Originado por: <strong>${ev.created_by || 'SYSTEM'}</strong></small>
                                <small class="text-muted"><i class="fa-solid fa-network-wired mr-1"></i> IP: ${ev.ip_address || '127.0.0.1'}</small>
                            </div>
                        </div>
                    </div>
                `;
            });

            timelineHtml += `
                <div>
                    <i class="fas fa-clock bg-gray"></i>
                </div>
            `;

            container.innerHTML = timelineHtml;
            container.style.display = 'block';
        })
        .catch(err => {
            console.error(err);
            document.getElementById('timelineLoading').style.display = 'none';
            document.getElementById('timelineEmpty').innerText = 'Error al cargar los eventos de auditoría.';
            document.getElementById('timelineEmpty').style.display = 'block';
        });
}
</script>
<?php endif; ?>

<?php require_once APP_ROOT . '/views/layout/footer.php'; ?>
