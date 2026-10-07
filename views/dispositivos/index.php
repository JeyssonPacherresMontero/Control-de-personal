<?php require_once APP_ROOT . '/views/layout/header.php'; ?>

<!-- Content Header (Page header) -->
<div class="content-header pb-2">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-sm-6">
                <h1 class="m-0 font-weight-bold text-dark" style="font-size: 1.45rem;">
                    <i class="fa-solid fa-network-wired mr-2 text-primary"></i> Relojes Biométricos ZKTeco
                </h1>
                <div class="text-muted small mt-1">Monitoreo de red, conectividad IP, sincronización de marcaciones y auditoría.</div>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right mb-0">
                    <li class="breadcrumb-item"><a href="?route=dashboard">Inicio</a></li>
                    <li class="breadcrumb-item active">Relojes Biométricos</li>
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
                'guardado' => ['success', 'Dispositivo guardado correctamente.'],
                'desactivado' => ['info', 'Dispositivo desactivado correctamente (soft delete para preservar historial).'],
                'desactivado_por_historial' => ['info', 'El dispositivo tiene marcaciones registradas y fue desactivado para proteger el historial.'],
                'eliminado' => ['success', 'Dispositivo eliminado correctamente.'],
                'sincronizando' => ['info', 'Sincronización en segundo plano iniciada.'],
                'memoria_liberada' => ['success', 'Memoria de marcaciones del reloj respaldada y liberada con éxito.'],
                'duplicado' => ['danger', 'Ya existe un dispositivo registrado con esa dirección IP y puerto.'],
                'campos_requeridos' => ['warning', 'Completa todos los campos obligatorios.'],
                'error_limpiar' => ['danger', 'No se pudo comunicar con el dispositivo para liberar su memoria.'],
                'error_interno' => ['danger', 'Ocurrió un error al procesar el dispositivo.'],
            ];
            if (isset($msgMap[$_GET['msg']])):
                [$type, $text] = $msgMap[$_GET['msg']];
        ?>
            <div class="alert alert-<?= $type ?> alert-dismissible fade show mb-3 shadow-sm">
                <?= htmlspecialchars($text) ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        <?php endif; endif; ?>

        <?php
            $dispTotal = count($dispositivos);
            $dispOnline = 0;
            $dispHybridOrPush = 0;
            $dispPendientes = 0;
            foreach ($dispositivos as $d) {
                if (($d['estado_conexion'] ?? '') === 'ONLINE') $dispOnline++;
                if (in_array($d['modo'] ?? '', ['HYBRID', 'PUSH'], true)) $dispHybridOrPush++;
                $dispPendientes += (int)($d['eventos_pendientes'] ?? 0);
            }
        ?>

        <!-- KPI SUMMARY CARDS (PANTALLA) -->
        <div class="row no-print mb-2">
            <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                <div class="kpi-card h-100">
                    <div class="kpi-card-header">
                        <div>
                            <div class="kpi-title">Terminales Biométricas</div>
                            <div class="kpi-value"><?= $dispTotal ?></div>
                            <div class="kpi-subtitle"><b><?= $dispOnline ?></b> en línea en red local</div>
                        </div>
                        <div class="kpi-icon-box">
                            <i class="fa-solid fa-server"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                <div class="kpi-card h-100">
                    <div class="kpi-card-header">
                        <div>
                            <div class="kpi-title">Estado de Conexión</div>
                            <div class="kpi-value"><?= $dispOnline ?> / <?= $dispTotal ?></div>
                            <div class="kpi-subtitle">Terminales respondiendo en red</div>
                        </div>
                        <div class="kpi-icon-box">
                            <i class="fa-solid fa-network-wired"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                <div class="kpi-card h-100">
                    <div class="kpi-card-header">
                        <div>
                            <div class="kpi-title">Modo Tiempo Real</div>
                            <div class="kpi-value"><?= $dispHybridOrPush ?></div>
                            <div class="kpi-subtitle">Enlace PUSH / Híbrido activo</div>
                        </div>
                        <div class="kpi-icon-box">
                            <i class="fa-solid fa-arrows-split-up-and-left"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                <div class="kpi-card h-100">
                    <div class="kpi-card-header">
                        <div>
                            <div class="kpi-title">Eventos en Cola</div>
                            <div class="kpi-value"><?= $dispPendientes ?></div>
                            <div class="kpi-subtitle">Marcaciones por consolidar</div>
                        </div>
                        <div class="kpi-icon-box">
                            <i class="fa-solid fa-hourglass-half"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ACTIONS TOOLBAR -->
        <div class="actions-toolbar no-print mb-3">
            <div class="actions-toolbar-group">
                <div>
                    <span class="font-weight-bold text-dark d-block" style="font-size: 0.95rem;">
                        <i class="fa-solid fa-server mr-2 text-primary"></i> Terminales Biométricas en Red
                    </span>
                    <div class="text-muted small" style="font-size: 0.8rem;">Gestión de dispositivos ZKTeco, conectividad IP y protocolos de comunicación.</div>
                </div>
            </div>
            <div class="actions-toolbar-group flex-wrap">
                <!-- Botón Principal: Sync Rápido Hoy -->
                <button type="button" class="btn btn-primary btn-sm" onclick="syncAllDevices('today', this)">
                    <i class="fa-solid fa-bolt mr-1"></i> Sincronizar Hoy
                </button>

                <button type="button" class="btn btn-outline-info btn-sm" onclick="openLogsModal()">
                    <i class="fa-solid fa-file-waveform mr-1"></i> Monitoreo de Logs
                </button>

                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="openAuditPurgeModal()">
                    <i class="fa-solid fa-broom mr-1"></i> Depuración Auditoría
                </button>

                <!-- Menú Desplegable de Opciones Avanzadas -->
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                        <i class="fa-solid fa-sliders mr-1"></i> Opciones
                    </button>
                    <div class="dropdown-menu dropdown-menu-right shadow border-0">
                        <a class="dropdown-item py-2" href="javascript:void(0)" onclick="syncAllDevices('full', this)">
                            <i class="fa-solid fa-database mr-2 text-primary"></i> Sincronización Histórica
                        </a>
                        <a class="dropdown-item py-2" href="javascript:void(0)" onclick="openLogsModal()">
                            <i class="fa-solid fa-file-waveform mr-2 text-info"></i> Monitoreo de Logs en Vivo
                        </a>
                        <a class="dropdown-item py-2" href="javascript:void(0)" onclick="openAuditPurgeModal()">
                            <i class="fa-solid fa-broom mr-2 text-warning"></i> Optimización Semestral
                        </a>
                        <?php if (($currentUser['rol'] ?? '') === 'ADMIN'): ?>
                            <div class="dropdown-divider"></div>
                            <h6 class="dropdown-header text-danger font-weight-bold"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Mantenimiento</h6>
                            <a class="dropdown-item py-2 text-danger" href="javascript:void(0)" onclick="openClearMemoryModal()">
                                <i class="fa-solid fa-broom mr-2"></i> Liberar Memoria del Reloj
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <button class="btn btn-primary btn-sm" onclick="openNewDeviceModal()">
                    <i class="fa-solid fa-plus mr-1"></i> Nuevo Dispositivo
                </button>
            </div>
        </div>

        <?php if (isset($_SESSION['flash_sync_output'])): ?>
            <div class="alert alert-<?= $_SESSION['flash_sync_status'] === 'success' ? 'success' : 'warning' ?> alert-dismissible fade show mb-3" role="alert">
                <h5 class="font-weight-bold mb-2"><i class="icon fas fa-info-circle mr-1"></i> Resultado de Sincronización:</h5>
                <pre class="mb-0 bg-dark text-white p-2 rounded small" style="max-height: 160px; overflow-y: auto; font-family: monospace;"><?= htmlspecialchars($_SESSION['flash_sync_output']) ?></pre>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <?php unset($_SESSION['flash_sync_output'], $_SESSION['flash_sync_status']); ?>
        <?php endif; ?>

        <!-- DEVICE CARDS -->
        <div class="row">
            <?php foreach ($dispositivos as $d): ?>
                <div class="col-md-6 col-lg-4 mb-4" id="card-col-<?= $d['id'] ?>">
                    <div class="card h-100 shadow-sm border" id="device-card-<?= $d['id'] ?>">
                        <div class="card-header d-flex justify-content-between align-items-center py-3">
                            <h3 class="card-title font-weight-bold" style="font-size: 0.95rem; color: #0f172a;">
                                <i class="fa-solid fa-network-wired mr-2" style="color: #1e40af;"></i>
                                <?= htmlspecialchars($d['nombre']) ?>
                            </h3>
                            <div class="card-tools" id="device-badge-<?= $d['id'] ?>">
                                <?php if ($d['estado_conexion'] === 'ONLINE'): ?>
                                    <span class="badge-pill-custom badge-pill-online"><i class="fa-solid fa-circle" style="font-size: 6px;"></i> En Línea</span>
                                <?php else: ?>
                                    <span class="badge-pill-custom badge-pill-offline"><i class="fa-solid fa-circle" style="font-size: 6px;"></i> Desconectado</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body p-3 d-flex flex-column justify-content-between">
                            <div class="d-flex flex-column mb-2" style="gap: 4px;">
                                <div class="d-flex justify-content-between align-items-center py-2 border-bottom" style="border-color: #f1f5f9 !important;">
                                    <span class="text-muted small font-weight-medium">
                                        <i class="fa-solid fa-ethernet mr-2" style="color: #1e40af; width: 16px;"></i> Dirección IP y Puerto
                                    </span>
                                    <span class="font-weight-bold font-monospace text-dark" style="font-size: 0.9rem;">
                                        <?= htmlspecialchars($d['ip']) ?>:<?= $d['puerto'] ?>
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center py-2 border-bottom" style="border-color: #f1f5f9 !important;">
                                    <span class="text-muted small font-weight-medium">
                                        <i class="fa-solid fa-shield-halved mr-2" style="color: #64748b; width: 16px;"></i> Protocolo / Clave
                                    </span>
                                    <span>
                                        <span class="badge-pill-custom badge-pill-neutral font-weight-bold"><?= $d['protocolo'] ?></span>
                                        <small class="text-muted ml-1 font-monospace">Clave: <?= $d['clave_comunicacion'] ?></small>
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center py-2 border-bottom" style="border-color: #f1f5f9 !important;">
                                    <span class="text-muted small font-weight-medium">
                                        <i class="fa-solid fa-arrows-split-up-and-left mr-2" style="color: #64748b; width: 16px;"></i> Modo de Sincronización
                                    </span>
                                    <span>
                                        <?php if (($d['modo'] ?? 'PULL') === 'HYBRID'): ?>
                                            <span class="badge-pill-custom badge-pill-justificado font-weight-bold" style="font-size: 0.72rem;"><i class="fa-solid fa-bolt mr-1"></i> HÍBRIDO (PUSH + PULL)</span>
                                        <?php elseif (($d['modo'] ?? 'PULL') === 'PUSH'): ?>
                                            <span class="badge-pill-custom badge-pill-presente font-weight-bold" style="font-size: 0.72rem;"><i class="fa-solid fa-cloud-arrow-up mr-1"></i> PUSH / ADMS</span>
                                        <?php else: ?>
                                            <span class="badge-pill-custom badge-pill-neutral font-weight-bold" style="font-size: 0.72rem;"><i class="fa-solid fa-download mr-1"></i> PULL (Sondeo)</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center py-2 border-bottom" style="border-color: #f1f5f9 !important;">
                                    <span class="text-muted small font-weight-medium">
                                        <i class="fa-solid fa-location-dot mr-2" style="color: #64748b; width: 16px;"></i> Ubicación / Sede
                                    </span>
                                    <span class="font-weight-semibold text-dark" style="font-size: 0.85rem;">
                                        <?= htmlspecialchars($d['ubicacion'] ?? 'Sede Principal') ?>
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center py-2 border-bottom" style="border-color: #f1f5f9 !important;">
                                    <span class="text-muted small font-weight-medium">
                                        <i class="fa-solid fa-gauge-high mr-2" style="color: #64748b; width: 16px;"></i> Latencia
                                    </span>
                                    <span class="font-monospace small font-weight-bold" id="device-latency-<?= $d['id'] ?>">
                                        <?php if (!empty($d['ultima_latencia_ms'])): ?>
                                            <?php $latClass = $d['ultima_latencia_ms'] < 200 ? 'presente' : ($d['ultima_latencia_ms'] < 1000 ? 'tardanza' : 'falta'); ?>
                                            <span class="badge-pill-custom badge-pill-<?= $latClass ?> font-weight-bold" style="font-size: 0.72rem;">
                                                <?= (int)$d['ultima_latencia_ms'] ?> ms
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center py-2 border-bottom" style="border-color: #f1f5f9 !important;">
                                    <span class="text-muted small font-weight-medium">
                                        <i class="fa-solid fa-layer-group mr-2" style="color: #64748b; width: 16px;"></i> Cola / Errores 24h
                                    </span>
                                    <span id="device-metrics-<?= $d['id'] ?>">
                                        <?php if (($d['eventos_pendientes'] ?? 0) > 0): ?>
                                            <span class="badge-pill-custom badge-pill-tardanza font-weight-bold" style="font-size: 0.72rem;"><i class="fa-solid fa-hourglass-half mr-1"></i><?= $d['eventos_pendientes'] ?> en cola</span>
                                        <?php else: ?>
                                            <span class="badge-pill-custom badge-pill-neutral" style="font-size: 0.72rem;"><i class="fa-solid fa-check text-success mr-1"></i>Al día</span>
                                        <?php endif; ?>
                                        <?php if (($d['errores_24h'] ?? 0) > 0): ?>
                                            <span class="badge-pill-custom badge-pill-falta ml-1 font-weight-bold" style="font-size: 0.72rem;" title="<?= $d['errores_24h'] ?> fallos en 24h"><i class="fa-solid fa-triangle-exclamation mr-1"></i><?= $d['errores_24h'] ?> err</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center py-2">
                                    <span class="text-muted small font-weight-medium">
                                        <i class="fa-solid fa-clock mr-2" style="color: #64748b; width: 16px;"></i> Última Sincronización
                                    </span>
                                    <span class="text-muted font-monospace small" id="device-sync-<?= $d['id'] ?>">
                                        <?= $d['ultimo_sync'] ? substr($d['ultimo_sync'], 0, 16) : 'Nunca' ?>
                                    </span>
                                </div>
                            </div>

                            <div id="device-error-<?= $d['id'] ?>">
                                <?php if (!empty($d['ultimo_error'])): ?>
                                    <div class="alert alert-danger p-2 small mb-3 rounded" style="font-size: 0.78rem;">
                                        <i class="fa-solid fa-triangle-exclamation mr-1"></i> <?= htmlspecialchars(mb_strimwidth($d['ultimo_error'], 0, 90, '...')) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-2" style="border-color: #e2e8f0 !important; gap: 6px;">
                                <button type="button" class="btn btn-outline-secondary btn-sm flex-grow-1" onclick="testConnection(<?= $d['id'] ?>, this)">
                                    <i class="fa-solid fa-plug mr-1 text-primary"></i> Probar Conexión
                                </button>
                                <button type="button" class="btn btn-primary btn-sm px-3" onclick="syncDevice(<?= $d['id'] ?>, this, 'incremental')" title="Sincronizar marcaciones de hoy">
                                    <i class="fa-solid fa-arrows-rotate"></i>
                                </button>
                                
                                <div class="btn-group">
                                    <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-toggle="dropdown" title="Más opciones">
                                        <i class="fa-solid fa-gear"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right shadow-sm border-0">
                                        <a class="dropdown-item py-2" href="javascript:void(0)" onclick="syncDevice(<?= $d['id'] ?>, null, 'full')">
                                            <i class="fa-solid fa-database mr-2 text-primary"></i> Sincronización Histórica
                                        </a>
                                        <a class="dropdown-item py-2" href="javascript:void(0)" onclick="openEditDeviceModal(<?= htmlspecialchars(json_encode($d)) ?>)">
                                            <i class="fa-solid fa-pen mr-2 text-secondary"></i> Editar Configuración
                                        </a>
                                        <?php if (($currentUser['rol'] ?? '') === 'ADMIN'): ?>
                                            <div class="dropdown-divider my-1"></div>
                                            <a class="dropdown-item py-2 text-danger font-weight-bold" href="javascript:void(0)" onclick="confirmClearDeviceMemory(<?= $d['id'] ?>, '<?= htmlspecialchars($d['nombre']) ?>')">
                                                <i class="fa-solid fa-broom mr-2"></i> Liberar Memoria
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
        <div class="card mt-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h3 class="card-title font-weight-bold">
                    <i class="fa-solid fa-clock-rotate-left mr-2 text-primary"></i> Historial de Sincronización
                </h3>
                <span class="badge-pill-custom badge-pill-neutral">Auditoría en tiempo real</span>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover datatable text-nowrap table-sm">
                    <thead>
                        <tr>
                            <th class="text-center">Fecha y Hora</th>
                            <th>Dispositivo</th>
                            <th class="text-center">Evento</th>
                            <th class="text-center">Modo</th>
                            <th class="text-center">Latencia</th>
                            <th class="text-center">Descargados</th>
                            <th class="text-center">Insertados</th>
                            <th class="text-center">Duplicados</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">Duración</th>
                            <th>Detalle y Mensaje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $l): ?>
                            <tr>
                                <td class="text-center text-secondary font-monospace small"><?= $l['fecha_hora'] ?></td>
                                <td class="font-weight-bold text-dark"><?= htmlspecialchars($l['dispositivo_nombre'] ?? 'Global') ?></td>
                                <td class="text-center">
                                    <?php
                                        $eventoLabel = match($l['tipo_evento']) {
                                            'SYNC_AUTO' => 'Sincronización Automática',
                                            'SYNC_MANUAL' => 'Sincronización Manual',
                                            'TEST_CONEXION' => 'Prueba de Conexión',
                                            'CLEAR_ATTENDANCE' => 'Limpieza de Memoria',
                                            'SYNC_USERS' => 'Sincronización de Usuarios',
                                            'ERROR' => 'Error de Conexión',
                                            default => htmlspecialchars($l['tipo_evento'])
                                        };
                                    ?>
                                    <span class="badge-pill-custom badge-pill-neutral"><?= $eventoLabel ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if (($l['modo'] ?? '') === 'HYBRID'): ?>
                                        <span class="badge-pill-custom badge-pill-justificado px-1 py-0 font-weight-bold" style="font-size: 0.72rem;">HYBRID</span>
                                    <?php elseif (($l['modo'] ?? '') === 'PUSH'): ?>
                                        <span class="badge-pill-custom badge-pill-presente px-1 py-0 font-weight-bold" style="font-size: 0.72rem;">PUSH</span>
                                    <?php else: ?>
                                        <span class="badge-pill-custom badge-pill-neutral px-1 py-0 font-weight-bold" style="font-size: 0.72rem;"><?= htmlspecialchars($l['modo'] ?? 'PULL') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center font-monospace small">
                                    <?= !empty($l['latencia_ms']) ? (int)$l['latencia_ms'] . ' ms' : '-' ?>
                                </td>
                                <td class="text-center font-weight-bold"><?= $l['total_descargados'] ?></td>
                                <td class="text-center text-success font-weight-bold">+<?= $l['total_insertados'] ?></td>
                                <td class="text-center text-muted font-monospace small"><?= $l['total_duplicados'] ?></td>
                                <td class="text-center">
                                    <?php if ($l['estado'] === 'EXITO'): ?>
                                        <span class="badge-pill-custom badge-pill-presente"><i class="fa-solid fa-check mr-1"></i> Éxito</span>
                                    <?php elseif ($l['estado'] === 'ERROR'): ?>
                                        <span class="badge-pill-custom badge-pill-falta"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Error</span>
                                    <?php else: ?>
                                        <span class="badge-pill-custom badge-pill-tardanza">Alerta</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center font-monospace small"><?= $l['duracion_segundos'] ?>s</td>
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

<!-- MODAL CREAR Y EDITAR DISPOSITIVO -->
<div class="modal fade" id="modalDispositivo" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 580px;">
        <form method="POST" action="?route=dispositivos&action=guardar" class="modal-content shadow-lg border-0 rounded-lg">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold d-flex align-items-center">
                    <i class="fa-solid fa-network-wired mr-2" style="color: #1e40af;"></i>
                    <span id="deviceModalTitle">Configurar Reloj Biométrico</span>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="id" id="dev_id">
                
                <div class="modal-section-title">
                    <i class="fa-solid fa-server mr-2"></i> Identificación y Conexión de Red
                </div>

                <div class="form-group mb-3">
                    <label class="modal-form-label">Nombre Descriptivo <span class="text-danger">*</span></label>
                    <input type="text" name="nombre" id="dev_nombre" class="form-control" placeholder="Ej: Reloj Principal Recepción" style="height: 38px;" required>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <div class="form-group mb-3">
                            <label class="modal-form-label">Dirección IP Fija <span class="text-danger">*</span></label>
                            <input type="text" name="ip" id="dev_ip" class="form-control font-monospace" placeholder="192.168.1.201" style="height: 38px;" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="modal-form-label">Puerto <span class="text-danger">*</span></label>
                            <input type="number" name="puerto" id="dev_puerto" class="form-control font-monospace" value="4370" style="height: 38px;" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="modal-form-label">Protocolo de Red</label>
                            <select name="protocolo" id="dev_protocolo" class="form-control" style="height: 38px;">
                                <option value="TCP">TCP (Estándar)</option>
                                <option value="UDP">UDP</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="modal-form-label">Clave de Comunicación (ComKey)</label>
                            <input type="number" name="clave_comunicacion" id="dev_clave" class="form-control" value="0" style="height: 38px;">
                        </div>
                    </div>
                </div>

                <div class="modal-section-title mt-3">
                    <i class="fa-solid fa-location-dot mr-2"></i> Ubicación y Parámetros
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="modal-form-label">Ubicación o Sede</label>
                            <input type="text" name="ubicacion" id="dev_ubicacion" class="form-control" placeholder="Ej: Puerta Principal, Sede Central" style="height: 38px;">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="modal-form-label">Modelo del Dispositivo</label>
                            <input type="text" name="modelo" id="dev_modelo" class="form-control" placeholder="Ej: ZKTeco MB20, SilkBio..." style="height: 38px;">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="modal-form-label">
                                <i class="fa-solid fa-arrows-split-up-and-left mr-1 text-primary"></i> Modo de Sincronización
                            </label>
                            <select name="modo" id="dev_modo" class="form-control" style="height: 38px;">
                                <option value="HYBRID">HÍBRIDO (PUSH tiempo real + PULL respaldo)</option>
                                <option value="PULL" selected>PULL (Sondeo por IP / pyzk)</option>
                                <option value="PUSH">PUSH (Servidor Cloud ADMS exclusivo)</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="modal-form-label">
                                <i class="fa-solid fa-key mr-1 text-muted"></i> Token API (Autenticación PUSH)
                            </label>
                            <input type="text" name="api_token" id="dev_api_token" class="form-control font-monospace" placeholder="Automático si se deja vacío" style="height: 38px;">
                        </div>
                    </div>
                </div>

                <div class="p-3 rounded border mt-2" style="background: #f8fafc; border-color: #e2e8f0 !important;">
                    <div class="custom-control custom-switch">
                        <input class="custom-control-input" type="checkbox" name="activo" id="dev_activo" value="1" checked>
                        <label class="custom-control-label font-weight-bold" for="dev_activo" style="color: #334155; cursor: pointer; font-size: 0.88rem;">
                            Dispositivo Activo para Sincronización
                        </label>
                        <small class="d-block text-muted mt-1" style="font-size: 0.78rem;">
                            Si está deshabilitado, el sistema omitirá los sondeos automáticos de presencia y marcaciones de este reloj.
                        </small>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between bg-light px-4 py-3" style="border-top: 1px solid #e2e8f0;">
                <button type="button" class="btn btn-outline-secondary px-3" data-dismiss="modal">
                    <i class="fa-solid fa-times mr-1"></i> Cancelar
                </button>
                <button type="submit" class="btn btn-primary px-4 font-weight-bold">
                    <i class="fa-solid fa-floppy-disk mr-1"></i> Guardar Dispositivo
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DE MONITOREO DE LOGS Y DIAGNÓSTICO ZKTECO -->
<div class="modal fade" id="modalLogsMonitor" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width: 1100px;">
        <div class="modal-content shadow-lg border-0 rounded-lg">
            <div class="modal-header d-flex align-items-center justify-content-between py-2 px-3 bg-dark text-white">
                <h5 class="modal-title font-weight-bold d-flex align-items-center text-white" style="font-size: 1.05rem;">
                    <i class="fa-solid fa-file-waveform mr-2 text-info"></i> Centro de Monitoreo de Logs & Diagnóstico ZKTeco
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body p-3 bg-light">
                <!-- KPI BANNERS DE SALUD -->
                <div class="row mb-3">
                    <div class="col-md-6 mb-2">
                        <div class="p-3 bg-white rounded border shadow-sm h-100">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="font-weight-bold text-dark small text-uppercase">Salud de Red Biometría ZKTeco</span>
                                <span id="logZkBadge" class="badge-pill-custom badge-pill-<?= $zkHealth['badge_class'] ?? 'secondary' ?> font-weight-bold">
                                    <?= htmlspecialchars($zkHealth['status_label'] ?? 'Consultando...') ?>
                                </span>
                            </div>
                            <div class="small text-muted" id="logZkDetails">
                                Último éxito: <b><?= htmlspecialchars($zkHealth['last_success_time'] ?? 'N/D') ?></b> | Latencia: <b><?= $zkHealth['last_latency_ms'] ? $zkHealth['last_latency_ms'] . ' ms' : 'N/D' ?></b>
                                <?php if (!empty($zkHealth['last_error_message'])): ?>
                                    <div class="text-danger mt-1 small text-truncate" title="<?= htmlspecialchars($zkHealth['last_error_message']) ?>">
                                        <i class="fa-solid fa-triangle-exclamation mr-1"></i> <?= htmlspecialchars(mb_strimwidth($zkHealth['last_error_message'], 0, 75, '...')) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="p-3 bg-white rounded border shadow-sm h-100">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="font-weight-bold text-dark small text-uppercase">Salud Servidor PHP & Base de Datos</span>
                                <span id="logPhpBadge" class="badge-pill-custom badge-pill-<?= $phpHealth['badge_class'] ?? 'secondary' ?> font-weight-bold">
                                    <?= htmlspecialchars($phpHealth['status_label'] ?? 'Consultando...') ?>
                                </span>
                            </div>
                            <div class="small text-muted" id="logPhpDetails">
                                Errores fatales: <b><?= $phpHealth['fatal_count'] ?? 0 ?></b> | Conexión BD: <b><?= ($phpHealth['db_connection_errors'] ?? 0) === 0 ? 'Conectado OK' : 'Fallo de Red' ?></b>
                                <?php if (!empty($phpHealth['last_issue'])): ?>
                                    <div class="text-warning mt-1 small text-truncate" title="<?= htmlspecialchars($phpHealth['last_issue']) ?>">
                                        <i class="fa-solid fa-circle-exclamation mr-1"></i> <?= htmlspecialchars(mb_strimwidth($phpHealth['last_issue'], 0, 75, '...')) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SELECTOR DE LOG Y CONTROLES -->
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2 p-2 bg-white rounded border">
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-primary active font-weight-bold" id="btnTabLogSync" onclick="selectLogTab('sync')">
                            <i class="fa-solid fa-network-wired mr-1"></i> sync_current.log
                        </button>
                        <button type="button" class="btn btn-outline-secondary font-weight-bold" id="btnTabLogPhp" onclick="selectLogTab('php')">
                            <i class="fa-brands fa-php mr-1"></i> php_error.log
                        </button>
                        <button type="button" class="btn btn-outline-secondary font-weight-bold" id="btnTabLogAudit" onclick="selectLogTab('audit')">
                            <i class="fa-solid fa-broom mr-1"></i> audit_purge.log
                        </button>
                    </div>

                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <input type="text" id="logFilterInput" class="form-control form-control-sm" placeholder="Filtrar texto..." style="width: 170px;" oninput="applyLogFilter()">
                        <select id="logLinesSelect" class="form-control form-control-sm" style="width: 110px;" onchange="reloadActiveLog()">
                            <option value="50">50 líneas</option>
                            <option value="100" selected>100 líneas</option>
                            <option value="200">200 líneas</option>
                        </select>
                        <div class="custom-control custom-switch custom-control-inline ml-1" title="Actualiza el visor automáticamente cada 5 segundos">
                            <input type="checkbox" class="custom-control-input" id="logAutoRefreshSwitch" onchange="toggleAutoRefresh(this.checked)">
                            <label class="custom-control-label small text-muted" for="logAutoRefreshSwitch">Auto 5s</label>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="reloadActiveLog()" title="Recargar log">
                            <i class="fa-solid fa-arrows-rotate"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="downloadActiveLog()" title="Descargar log crudo">
                            <i class="fa-solid fa-download mr-1"></i> Descargar
                        </button>
                        <?php if (($currentUser['rol'] ?? '') === 'ADMIN'): ?>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmClearActiveLog()" title="Vaciar y generar copia .bak">
                                <i class="fa-solid fa-trash-can mr-1"></i> Vaciar (.bak)
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- TERMINAL VIEW -->
                <div class="position-relative">
                    <pre id="logTerminalContent" style="background: #0f172a; color: #f8fafc; font-family: 'Consolas', 'Courier New', monospace; font-size: 0.81rem; line-height: 1.45; padding: 14px; border-radius: 8px; height: 420px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; border: 1px solid #1e293b; margin-bottom: 0;">Cargando registros del log...</pre>
                </div>
            </div>
            <div class="modal-footer py-2 px-3 bg-white justify-content-between">
                <span class="small text-muted" id="logTerminalFooter">Líneas cargadas: 0 | Tamaño: -</span>
                <button type="button" class="btn btn-sm btn-outline-secondary px-3" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DE DEPURACIÓN Y ARCHIVADO DE AUDITORÍA -->
<div class="modal fade" id="modalAuditPurge" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" style="max-width: 820px;">
        <div class="modal-content shadow-lg border-0 rounded-lg">
            <div class="modal-header d-flex align-items-center justify-content-between py-2 px-3 bg-dark text-white">
                <h5 class="modal-title font-weight-bold d-flex align-items-center text-white" style="font-size: 1.05rem;">
                    <i class="fa-solid fa-broom mr-2 text-warning"></i> Depuración y Archivado Semestral de Auditoría
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body p-4 bg-light">
                <!-- INTRODUCTORY BANNER -->
                <div class="alert alert-info border shadow-sm mb-3">
                    <div class="d-flex align-items-start">
                        <i class="fa-solid fa-circle-info fa-lg mr-2 mt-1 text-info"></i>
                        <div>
                            <strong>Optimización del Event Store y Colas:</strong>
                            <p class="mb-0 small">
                                Este proceso traslada registros antiguos de eventos y colas finalizadas hacia tablas históricas de respaldo comprimidas (<code>eventos_asistencia_historico</code>), liberando espacio en el motor de base de datos y acelerando la generación de reportes y consolidados anuales.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- MÉTRICAS EN VIVO DE LAS TABLAS -->
                <div class="row mb-3">
                    <div class="col-md-4 mb-2">
                        <div class="p-3 bg-white rounded border shadow-sm text-center">
                            <span class="text-muted small text-uppercase d-block mb-1">Eventos Activos</span>
                            <h4 class="font-weight-bold text-dark mb-0" id="ap_stat_eventos"><?= number_format($auditStats['eventos']['total'] ?? 0) ?></h4>
                            <small class="text-muted" id="ap_stat_eventos_mb"><?= $auditStats['eventos']['size_mb'] ?? 0 ?> MB en disco</small>
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="p-3 bg-white rounded border shadow-sm text-center">
                            <span class="text-muted small text-uppercase d-block mb-1">Cola Finalizada</span>
                            <h4 class="font-weight-bold text-dark mb-0" id="ap_stat_cola"><?= number_format($auditStats['cola']['total'] ?? 0) ?></h4>
                            <small class="text-muted" id="ap_stat_cola_mb"><?= $auditStats['cola']['size_mb'] ?? 0 ?> MB en disco</small>
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="p-3 bg-white rounded border shadow-sm text-center">
                            <span class="text-muted small text-uppercase d-block mb-1">Archivados Históricos</span>
                            <h4 class="font-weight-bold text-success mb-0" id="ap_stat_hist"><?= number_format($auditStats['historico']['eventos_total'] ?? 0) ?></h4>
                            <small class="text-muted" id="ap_stat_hist_mb"><?= $auditStats['historico']['eventos_size_mb'] ?? 0 ?> MB archivados</small>
                        </div>
                    </div>
                </div>

                <!-- FORMULARIO DE DEPURACIÓN -->
                <div class="card bg-white border shadow-sm mb-3">
                    <div class="card-header bg-white font-weight-bold py-2 px-3 border-bottom small text-uppercase">
                        <i class="fa-solid fa-gears mr-1 text-primary"></i> Parámetros de Retención
                    </div>
                    <div class="card-body p-3">
                        <div class="row align-items-center mb-3">
                            <div class="col-md-6">
                                <label class="small font-weight-bold text-dark mb-1">Periodo de Conservación de Registros</label>
                                <select id="ap_months_select" class="form-control form-control-sm" onchange="actualizarElegiblesAuditoria()">
                                    <option value="6" selected>6 Meses (Semestral - Recomendado Oficial)</option>
                                    <option value="3">3 Meses (Trimestral - Para alta concurrencia)</option>
                                    <option value="12">12 Meses (Anual - Máximo histórico activo)</option>
                                </select>
                                <small class="text-muted d-block mt-1">Registros con fecha anterior al corte serán procesados.</small>
                            </div>
                            <div class="col-md-6">
                                <div class="p-2 rounded bg-light border">
                                    <span class="small text-muted d-block">Registros elegibles calculados:</span>
                                    <span class="font-weight-bold text-primary h6 mb-0" id="ap_elegibles_badge">
                                        <?= number_format($auditStats['eventos']['depurables'] ?? 0) ?> eventos detectados
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="custom-control custom-checkbox mb-3">
                            <input type="checkbox" class="custom-control-input" id="ap_archive_checkbox" checked>
                            <label class="custom-control-label small font-weight-bold text-dark" for="ap_archive_checkbox">
                                Archivar en tablas históricas antes de eliminar (Recomendado: Preserva trazabilidad inmutable)
                            </label>
                        </div>

                        <div class="form-group mb-0">
                            <label class="small font-weight-bold text-dark mb-1">Notas u Observaciones del Mantenimiento (Opcional)</label>
                            <input type="text" id="ap_notes_input" class="form-control form-control-sm" placeholder="Ej: Depuración semestral programada de segundo semestre">
                        </div>
                    </div>
                </div>

                <!-- ÚLTIMOS MANTENIMIENTOS REALIZADOS -->
                <div class="card bg-white border shadow-sm mb-0">
                    <div class="card-header bg-white font-weight-bold py-2 px-3 border-bottom small text-uppercase">
                        <i class="fa-solid fa-timeline mr-1 text-secondary"></i> Historial Reciente de Mantenimientos
                    </div>
                    <div class="card-body p-0 table-responsive" style="max-height: 160px;">
                        <table class="table table-sm table-hover mb-0 text-nowrap small">
                            <thead class="bg-light">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Operador</th>
                                    <th>Modo</th>
                                    <th class="text-center">Eventos</th>
                                    <th class="text-center">Cola</th>
                                    <th class="text-center">Tiempo</th>
                                </tr>
                            </thead>
                            <tbody id="ap_history_tbody">
                                <?php if (!empty($auditStats['historial_mantenimientos'])): ?>
                                    <?php foreach ($auditStats['historial_mantenimientos'] as $hm): ?>
                                        <tr>
                                            <td class="font-monospace text-muted"><?= htmlspecialchars($hm['fecha_ejecucion']) ?></td>
                                            <td class="font-weight-bold"><?= htmlspecialchars($hm['usuario']) ?></td>
                                            <td><span class="badge badge-light border"><?= htmlspecialchars($hm['modo']) ?></span></td>
                                            <td class="text-center text-success font-weight-bold"><?= number_format($hm['eventos_eliminados']) ?></td>
                                            <td class="text-center text-info font-weight-bold"><?= number_format($hm['cola_eliminada']) ?></td>
                                            <td class="text-center font-monospace"><?= $hm['duracion_ms'] ?> ms</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-2">No se han registrado ejecuciones previas de depuración.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="modal-footer py-2 px-3 bg-white justify-content-between">
                <button type="button" class="btn btn-sm btn-outline-secondary px-3" data-dismiss="modal">Cancelar</button>
                <?php if (($currentUser['rol'] ?? '') === 'ADMIN'): ?>
                    <button type="button" class="btn btn-sm btn-primary px-4 font-weight-bold" onclick="ejecutarDepuracionAuditoria()">
                        <i class="fa-solid fa-broom mr-1"></i> Ejecutar Depuración y Archivado
                    </button>
                <?php else: ?>
                    <span class="small text-muted font-italic"><i class="fa-solid fa-lock mr-1"></i> Acción reservada exclusivamente para Administradores</span>
                <?php endif; ?>
            </div>
        </div>
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
    document.getElementById('dev_modo').value = 'PULL';
    document.getElementById('dev_clave').value = '0';
    document.getElementById('dev_api_token').value = '';
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
    document.getElementById('dev_modo').value = d.modo || 'PULL';
    document.getElementById('dev_clave').value = d.clave_comunicacion || 0;
    document.getElementById('dev_api_token').value = d.api_token || '';
    document.getElementById('dev_ubicacion').value = d.ubicacion || '';
    document.getElementById('dev_modelo').value = d.modelo || '';
    document.getElementById('dev_activo').checked = (parseInt(d.activo) === 1);
    $('#modalDispositivo').modal('show');
}

function updateDeviceCardUI(deviceId, status, lastSync, lastError, latency = null, pendingEvents = null, errors24h = null) {
    const card = document.getElementById(`device-card-${deviceId}`);
    const badge = document.getElementById(`device-badge-${deviceId}`);
    const syncEl = document.getElementById(`device-sync-${deviceId}`);
    const errorEl = document.getElementById(`device-error-${deviceId}`);
    const latencyEl = document.getElementById(`device-latency-${deviceId}`);
    const metricsEl = document.getElementById(`device-metrics-${deviceId}`);

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
            badge.innerHTML = '<span class="badge-pill-custom badge-pill-online"><i class="fa-solid fa-circle" style="font-size: 6px;"></i> En Línea</span>';
        } else {
            badge.innerHTML = '<span class="badge-pill-custom badge-pill-offline"><i class="fa-solid fa-circle" style="font-size: 6px;"></i> Desconectado</span>';
        }
    }

    if (syncEl && lastSync) {
        syncEl.innerText = lastSync;
    }

    if (latencyEl && latency !== null) {
        if (latency > 0) {
            const latClass = latency < 200 ? 'presente' : (latency < 1000 ? 'tardanza' : 'falta');
            latencyEl.innerHTML = `<span class="badge-pill-custom badge-pill-${latClass} font-weight-bold" style="font-size: 0.72rem;">${parseInt(latency)} ms</span>`;
        } else {
            latencyEl.innerHTML = '<span class="text-muted">-</span>';
        }
    }

    if (metricsEl && pendingEvents !== null) {
        let html = '';
        if (pendingEvents > 0) {
            html += `<span class="badge-pill-custom badge-pill-tardanza font-weight-bold" style="font-size: 0.72rem;"><i class="fa-solid fa-hourglass-half mr-1"></i>${pendingEvents} en cola</span>`;
        } else {
            html += `<span class="badge-pill-custom badge-pill-neutral" style="font-size: 0.72rem;"><i class="fa-solid fa-check text-success mr-1"></i>Al día</span>`;
        }
        if (errors24h > 0) {
            html += `<span class="badge-pill-custom badge-pill-falta ml-1 font-weight-bold" style="font-size: 0.72rem;" title="${errors24h} fallos en 24h"><i class="fa-solid fa-triangle-exclamation mr-1"></i>${errors24h} err</span>`;
        }
        metricsEl.innerHTML = html;
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

    const fd = new FormData();
    fd.append('id', deviceId);
    if (window._csrfToken) {
        fd.append('_csrf', window._csrfToken);
    }

    fetch('?route=dispositivos&action=test', {
        method: 'POST',
        body: fd,
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
    let consecutiveErrors = 0;
    
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
        
        // Timeout de seguridad en cliente tras 120 segundos
        if (secondsElapsed > 120) {
            clearInterval(timerInterval);
            clearInterval(pollInterval);
            if (btn) {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            }
            Swal.fire({
                icon: 'info',
                title: 'Sincronización en curso',
                text: 'El proceso continúa ejecutándose en el servidor. La pantalla se actualizará automáticamente.',
                confirmButtonText: 'Aceptar'
            }).then(() => {
                location.href = '?route=dispositivos';
            });
        }
    }, 1000);

    const pollInterval = setInterval(() => {
        fetch('?route=dispositivos&action=sync_status', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => {
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            return res.json();
        })
        .then(data => {
            consecutiveErrors = 0;
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

                const outMsg = data.output || (data.success ? 'Sincronización completada exitosamente.' : 'Sincronización finalizada.');
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Sincronización Completada!',
                        html: `<pre class="text-left bg-dark text-white p-3 rounded small" style="max-height: 250px; overflow-y: auto;">${escapeHtml(outMsg)}</pre>`,
                        confirmButtonText: 'Aceptar',
                        confirmButtonColor: '#28a745'
                    }).then(() => {
                        location.href = '?route=dispositivos';
                    });
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Resultado de Sincronización',
                        html: `<pre class="text-left bg-dark text-white p-3 rounded small" style="max-height: 250px; overflow-y: auto;">${escapeHtml(outMsg)}</pre>`,
                        confirmButtonText: 'Entendido'
                    }).then(() => {
                        location.href = '?route=dispositivos';
                    });
                }
            }
        })
        .catch(err => {
            console.error("Polling error:", err);
            consecutiveErrors++;
            if (consecutiveErrors >= 5) {
                clearInterval(pollInterval);
                clearInterval(timerInterval);
                if (btn) {
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                }
                Swal.fire({
                    icon: 'warning',
                    title: 'Verificando estado...',
                    text: 'El proceso se inició en segundo plano. Recargando para verificar los nuevos registros.',
                    confirmButtonText: 'Recargar'
                }).then(() => {
                    location.href = '?route=dispositivos';
                });
            }
        });
    }, 2500);
}

function syncDevice(deviceId, btn, mode = 'incremental') {
    const originalHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        btn.disabled = true;
    }

    const title = (mode === 'today') ? 'Sincronización Rápida de Hoy...' : ((mode === 'full') ? 'Sincronización Histórica Completa...' : 'Sincronización Inteligente de Marcaciones...');

    const formData = new FormData();
    formData.append('id', deviceId);
    formData.append('mode', mode);
    formData.append('_csrf_token', window._csrfToken || '');

    fetch(`?route=dispositivos&action=sincronizar&ajax=1`, {
        method: 'POST',
        headers: { 
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': window._csrfToken || ''
        },
        body: formData
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

function syncAllDevices(mode = 'incremental', btn = null) {
    const originalHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Sincronizando...';
        btn.disabled = true;
    }

    const title = (mode === 'today') ? 'Sincronizando Relojes (Solo Hoy)...' : ((mode === 'full') ? 'Sincronizando Todo el Histórico...' : 'Sincronizando Marcaciones Pendientes...');

    const formData = new FormData();
    formData.append('mode', mode);
    formData.append('_csrf_token', window._csrfToken || '');

    fetch(`?route=dispositivos&action=sincronizar&ajax=1`, {
        method: 'POST',
        headers: { 
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': window._csrfToken || ''
        },
        body: formData
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

// Telemetría en vivo: sondeo de estado de red y colas cada 20 segundos
function pollDeviceHealth() {
    fetch('?route=dispositivos&action=health_check', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.ok ? res.json() : null)
    .then(data => {
        if (data && data.success && Array.isArray(data.dispositivos)) {
            data.dispositivos.forEach(d => {
                updateDeviceCardUI(
                    d.id,
                    d.estado_conexion,
                    d.ultimo_sync ? d.ultimo_sync.substring(0, 16) : 'Nunca',
                    d.ultimo_error,
                    d.ultima_latencia_ms,
                    d.eventos_pendientes,
                    d.errores_24h
                );
            });
        }
    })
    .catch(() => {});
}

// Iniciar sondeo automático tras cargar la página
document.addEventListener('DOMContentLoaded', () => {
    setInterval(pollDeviceHealth, 20000);
});

/* ==========================================================
   CENTRO DE MONITOREO DE LOGS Y DIAGNÓSTICO ZKTECO
   ========================================================== */
let activeLogTab = 'sync';
let rawLogLines = [];
let logAutoRefreshInterval = null;

function openLogsModal() {
    $('#modalLogsMonitor').modal('show');
    reloadActiveLog();
}

function selectLogTab(tab) {
    activeLogTab = tab;
    $('#btnTabLogSync').toggleClass('btn-primary active', tab === 'sync').toggleClass('btn-outline-secondary', tab !== 'sync');
    $('#btnTabLogPhp').toggleClass('btn-primary active', tab === 'php').toggleClass('btn-outline-secondary', tab !== 'php');
    $('#btnTabLogAudit').toggleClass('btn-primary active', tab === 'audit').toggleClass('btn-outline-secondary', tab !== 'audit');
    $('#logFilterInput').val('');
    reloadActiveLog();
}

function formatLogLine(line) {
    const escaped = escapeHtml(line);
    if (!escaped) return '';

    // Detección de errores y excepciones
    if (/error|fatal|exception|failed|falló|refused|timed out|critical/i.test(escaped)) {
        return `<span style="color: #f87171; font-weight: 600;">${escaped}</span>`;
    }
    // Detección de advertencias y alertas
    if (/warning|deprecated|alerta|retry|intento/i.test(escaped)) {
        return `<span style="color: #fbbf24;">${escaped}</span>`;
    }
    // Detección de éxitos y confirmaciones
    if (/success|éxito|exitosa|sincronizados|finalizado exitosamente/i.test(escaped)) {
        return `<span style="color: #34d399;">${escaped}</span>`;
    }
    // Detección de timestamps
    if (/^\d{4}-\d{2}-\d{2}|\[\d{2}-\w{3}-\d{4}/.test(escaped)) {
        return `<span style="color: #38bdf8;">${escaped}</span>`;
    }
    return `<span style="color: #cbd5e1;">${escaped}</span>`;
}

function renderLogLines(lines) {
    const term = document.getElementById('logTerminalContent');
    if (!lines || lines.length === 0) {
        term.innerHTML = '<span class="text-muted font-italic">No hay registros en este archivo de log.</span>';
        document.getElementById('logTerminalFooter').innerText = '0 líneas';
        return;
    }

    const filterVal = (document.getElementById('logFilterInput').value || '').toLowerCase().trim();
    const filtered = filterVal ? lines.filter(l => l.toLowerCase().includes(filterVal)) : lines;

    const html = filtered.map(l => formatLogLine(l)).join('\n');
    term.innerHTML = html;
    term.scrollTop = term.scrollHeight;

    document.getElementById('logTerminalFooter').innerText = `Mostrando ${filtered.length} de ${lines.length} líneas cargadas`;
}

function applyLogFilter() {
    renderLogLines(rawLogLines);
}

function reloadActiveLog() {
    const lines = document.getElementById('logLinesSelect').value || 100;
    const term = document.getElementById('logTerminalContent');

    fetch(`?route=dispositivos&action=logs_stream&type=${activeLogTab}&lines=${lines}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && Array.isArray(data.lines)) {
            rawLogLines = data.lines;
            renderLogLines(rawLogLines);
        } else {
            term.innerHTML = `<span class="text-danger">${escapeHtml(data.error || 'Error al leer el archivo de log')}</span>`;
        }
    })
    .catch(err => {
        term.innerHTML = `<span class="text-danger">Error de comunicación: ${escapeHtml(err.message)}</span>`;
    });

    // Actualizar también diagnóstico de salud
    fetch('?route=dispositivos&action=logs_diagnostico', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if (data.zk_health) {
                const zk = data.zk_health;
                const zkBadge = document.getElementById('logZkBadge');
                if (zkBadge) {
                    zkBadge.className = `badge-pill-custom badge-pill-${zk.badge_class} font-weight-bold`;
                    zkBadge.innerText = zk.status_label;
                }
            }
            if (data.php_health) {
                const php = data.php_health;
                const phpBadge = document.getElementById('logPhpBadge');
                if (phpBadge) {
                    phpBadge.className = `badge-pill-custom badge-pill-${php.badge_class} font-weight-bold`;
                    phpBadge.innerText = php.status_label;
                }
            }
        }
    })
    .catch(() => {});
}

function toggleAutoRefresh(enable) {
    if (logAutoRefreshInterval) {
        clearInterval(logAutoRefreshInterval);
        logAutoRefreshInterval = null;
    }
    if (enable) {
        logAutoRefreshInterval = setInterval(reloadActiveLog, 5000);
    }
}

function downloadActiveLog() {
    window.location.href = `?route=dispositivos&action=download_log&type=${activeLogTab}`;
}

function confirmClearActiveLog() {
    const fileLabels = {
        'sync': 'sync_current.log (Relojes ZKTeco)',
        'php': 'php_error.log (Servidor PHP)',
        'audit': 'audit_purge.log (Auditoría)'
    };
    const logDesc = fileLabels[activeLogTab] || activeLogTab;

    Swal.fire({
        title: '¿Vaciar este archivo de log?',
        html: `Se creará automáticamente una copia de respaldo <b>.bak</b> antes de vaciar <code>${logDesc}</code>.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, vaciar y respaldar',
        cancelButtonText: 'Cancelar'
    }).then(result => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('type', activeLogTab);

            fetch('?route=dispositivos&action=clear_log', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('¡Log Vaciado!', data.message || 'Se ha creado la copia .bak y vaciado el archivo.', 'success');
                    reloadActiveLog();
                } else {
                    Swal.fire('Error', data.error || 'No se pudo vaciar el log.', 'error');
                }
            })
            .catch(err => {
                Swal.fire('Error', 'Fallo de red: ' + err.message, 'error');
            });
        }
    });
}

// Al cerrar modal de logs, limpiar intervalo automático
$('#modalLogsMonitor').on('hidden.bs.modal', function () {
    toggleAutoRefresh(false);
    const sw = document.getElementById('logAutoRefreshSwitch');
    if (sw) sw.checked = false;
});

/* ==========================================================
   DEPURACIÓN Y ARCHIVADO SEMESTRAL DE AUDITORÍA
   ========================================================== */
function openAuditPurgeModal() {
    $('#modalAuditPurge').modal('show');
    actualizarElegiblesAuditoria();
}

function actualizarElegiblesAuditoria() {
    const months = document.getElementById('ap_months_select').value || 6;
    const badge = document.getElementById('ap_elegibles_badge');
    badge.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Calculando...';

    fetch(`?route=dispositivos&action=audit_stats&months=${months}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.stats) {
            const s = data.stats;
            badge.innerText = `${parseInt(s.eventos.depurables).toLocaleString()} eventos detectados (< ${s.cutoff_date.substring(0, 10)})`;
            document.getElementById('ap_stat_eventos').innerText = parseInt(s.eventos.total).toLocaleString();
            document.getElementById('ap_stat_eventos_mb').innerText = `${s.eventos.size_mb} MB en disco`;
            document.getElementById('ap_stat_cola').innerText = parseInt(s.cola.total).toLocaleString();
            document.getElementById('ap_stat_cola_mb').innerText = `${s.cola.size_mb} MB en disco`;
            document.getElementById('ap_stat_hist').innerText = parseInt(s.historico.eventos_total).toLocaleString();
            document.getElementById('ap_stat_hist_mb').innerText = `${s.historico.eventos_size_mb} MB archivados`;
        } else {
            badge.innerText = 'No se pudo calcular';
        }
    })
    .catch(() => {
        badge.innerText = 'Error al consultar';
    });
}

function ejecutarDepuracionAuditoria() {
    const months = document.getElementById('ap_months_select').value || 6;
    const archive = document.getElementById('ap_archive_checkbox').checked ? 1 : 0;
    const notes = document.getElementById('ap_notes_input').value.trim();

    Swal.fire({
        title: '¿Confirmar Mantenimiento de Auditoría?',
        html: `Se depurarán los registros con más de <b>${months} meses</b> de antigüedad.<br>${archive ? 'Los registros serán respaldados en <code>eventos_asistencia_historico</code>.' : '<span class="text-danger font-weight-bold">Atención: No se creará copia histórica.</span>'}`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#1d4ed8',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, ejecutar mantenimiento',
        cancelButtonText: 'Cancelar'
    }).then(result => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Ejecutando depuración y archivado...',
                text: 'Optimizando tablas de auditoría en MySQL...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const formData = new FormData();
            formData.append('months', months);
            formData.append('archive', archive);
            formData.append('notes', notes);

            fetch('?route=dispositivos&action=audit_purge', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Depuración Completada!',
                        html: `
                            <p class="mb-2">${escapeHtml(data.message)}</p>
                            <div class="small text-muted text-left p-2 bg-light rounded border">
                                • Eventos archivados: <b>${parseInt(data.eventos_archivados || 0).toLocaleString()}</b><br>
                                • Eventos eliminados de tabla activa: <b>${parseInt(data.eventos_eliminados || 0).toLocaleString()}</b><br>
                                • Cola archivada: <b>${parseInt(data.cola_archivada || 0).toLocaleString()}</b><br>
                                • Cola procesada liberada: <b>${parseInt(data.cola_eliminada || 0).toLocaleString()}</b><br>
                                • Duración de la operación: <b>${data.duracion_ms} ms</b>
                            </div>
                        `,
                        confirmButtonColor: '#1d4ed8'
                    }).then(() => {
                        actualizarElegiblesAuditoria();
                    });
                } else {
                    Swal.fire('Error', data.error || 'Ocurrió un error al depurar.', 'error');
                }
            })
            .catch(err => {
                Swal.fire('Error', 'Fallo de red: ' + err.message, 'error');
            });
        }
    });
}
</script>

<?php require_once APP_ROOT . '/views/layout/footer.php'; ?>

