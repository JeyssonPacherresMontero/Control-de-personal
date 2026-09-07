<?php require_once APP_ROOT . '/views/layout/header.php'; ?>

<!-- Content Header (Page header) -->
<div class="content-header pb-2">
    <div class="container-fluid">
        <!-- Banner Institucional JUSHSAL -->
        <div class="banner-jushsal">
            <div class="d-flex align-items-center">
                <img src="<?= jushsal_logo_data_uri('icon') ?: asset('img/logo_icon.png') ?>" alt="JUSHSAL" class="mr-3" style="width: 44px; height: 44px; object-fit: contain;">
                <div>
                    <div class="font-weight-bold text-dark" style="font-size: 1.05rem; letter-spacing: 0.3px;">
                        JUSHSAL <span class="font-weight-normal text-muted small d-none d-md-inline">&bull; Junta de Usuarios del Sector Hidráulico Menor San Lorenzo</span>
                    </div>
                    <div class="text-secondary small">
                        Sistema Integral de Control de Personal y Asistencia Laboral
                    </div>
                </div>
            </div>
            <div class="d-none d-lg-flex align-items-center text-muted small">
                <span class="badge-pill-custom badge-pill-neutral mr-2"><i class="fa-solid fa-droplet text-primary mr-1"></i> Sector Hidráulico San Lorenzo</span>
                <span class="badge-pill-custom badge-pill-neutral"><i class="fa-regular fa-clock mr-1 text-primary"></i> <?= date('d/m/Y') ?></span>
            </div>
        </div>

        <div class="row mb-3 align-items-center">
            <div class="col-md-7 col-sm-12 mb-2 mb-md-0">
                <h1 class="m-0 font-weight-bold text-dark" style="font-size: 1.45rem;">
                    <?php if ($activeRoleView === 'ADMIN'): ?>
                        <i class="fa-solid fa-server mr-2 text-primary"></i> Tablero de Administración TI y Biometría
                    <?php elseif ($activeRoleView === 'RRHH'): ?>
                        <i class="fa-solid fa-users-gear mr-2 text-primary"></i> Tablero de Gestión de Recursos Humanos
                    <?php elseif ($activeRoleView === 'SUPERVISOR'): ?>
                        <i class="fa-solid fa-user-tie mr-2 text-primary"></i> Tablero de Supervisión Operativa
                    <?php else: ?>
                        <i class="fa-solid fa-chart-pie mr-2 text-primary"></i> Tablero Principal de Control
                    <?php endif; ?>
                </h1>
                <div class="text-muted small mt-1">
                    <?php if ($activeRoleView === 'ADMIN'): ?>
                        Monitoreo de infraestructura biométrica ZKTeco, conectividad de red, auditoría de sincronización y estado del sistema.
                    <?php elseif ($activeRoleView === 'RRHH'): ?>
                        Analítica de puntualidad, ausentismo, balance de horas extras acumuladas, ranking de incidencias y justificaciones.
                    <?php elseif ($activeRoleView === 'SUPERVISOR'): ?>
                        Control en tiempo real del personal en turno, llegadas tarde de la jornada y monitoreo operativo.
                    <?php else: ?>
                        Resumen general de asistencia y métricas consolidadas del personal.
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-5 col-sm-12 text-md-right">
                <!-- Botones de Acción Rápida Específicos por Rol -->
                <?php if ($activeRoleView === 'ADMIN'): ?>
                    <a href="?route=dispositivos" class="btn btn-sm btn-outline-secondary mr-1">
                        <i class="fa-solid fa-network-wired mr-1"></i> Relojes Biométricos
                    </a>
                    <button type="button" class="btn btn-sm btn-success" onclick="openSyncModal()">
                        <i class="fa-solid fa-arrows-rotate mr-1"></i> Sincronizar Biométricos
                    </button>
                <?php elseif ($activeRoleView === 'RRHH'): ?>
                    <a href="?route=justificaciones" class="btn btn-sm btn-outline-primary mr-1">
                        <i class="fa-solid fa-file-signature mr-1"></i> Justificaciones (<?= $justificacionesPendientes ?>)
                    </a>
                    <button type="button" class="btn btn-sm btn-success" onclick="openSyncModal()">
                        <i class="fa-solid fa-bolt mr-1"></i> Sincronizar Hoy
                    </button>
                <?php elseif ($activeRoleView === 'SUPERVISOR'): ?>
                    <a href="?route=asistencia&fecha_inicio=<?= $today ?>&fecha_fin=<?= $today ?>" class="btn btn-sm btn-primary">
                        <i class="fa-solid fa-list-check mr-1"></i> Asistencia de Hoy
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">

        <?php if ($activeRoleView === 'ADMIN'): ?>
            <!-- =================================================================== -->
            <!-- VISTA: ADMINISTRADOR (TI, RED & BIOMETRÍA)                         -->
            <!-- =================================================================== -->
            
            <!-- TARJETAS KPIS TI -->
            <div class="row">
                <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                    <div class="kpi-card h-100">
                        <div class="kpi-card-header">
                            <div>
                                <div class="kpi-title">Biométricos En Línea</div>
                                <div class="kpi-value"><?= $dispositivosOnline ?> <span style="font-size: 1.05rem; color: #64748b; font-weight: 600;">/ <?= count($dispositivos) ?></span></div>
                                <div class="kpi-subtitle"><?= $dispositivosOnline === count($dispositivos) ? '100% de terminales operativas' : 'Revisar terminales inactivas' ?></div>
                            </div>
                            <div class="kpi-icon-box <?= $dispositivosOnline === count($dispositivos) ? 'kpi-icon-emerald' : 'kpi-icon-amber' ?>">
                                <i class="fa-solid fa-network-wired"></i>
                            </div>
                        </div>
                        <div>
                            <a href="?route=dispositivos" class="kpi-footer-link">
                                Gestionar biométricos <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                    <div class="kpi-card h-100">
                        <div class="kpi-card-header">
                            <div>
                                <div class="kpi-title">Efectividad de Sincronización</div>
                                <div class="kpi-value"><?= $tasaExitoSync ?>%</div>
                                <div class="kpi-subtitle"><?= (int)($syncStats['exitos'] ?? 0) ?> exitosos de <?= (int)($syncStats['total_syncs'] ?? 0) ?> ciclos (7 días)</div>
                            </div>
                            <div class="kpi-icon-box kpi-icon-blue">
                                <i class="fa-solid fa-circle-check"></i>
                            </div>
                        </div>
                        <div>
                            <a href="?route=dispositivos" class="kpi-footer-link">
                                Ver auditoría de sincronización <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                    <div class="kpi-card h-100">
                        <div class="kpi-card-header">
                            <div>
                                <div class="kpi-title">Marcaciones de Hoy</div>
                                <div class="kpi-value"><?= $totalMarcacionesHoy ?></div>
                                <div class="kpi-subtitle">Descargadas en base de datos</div>
                            </div>
                            <div class="kpi-icon-box kpi-icon-indigo">
                                <i class="fa-solid fa-fingerprint"></i>
                            </div>
                        </div>
                        <div>
                            <a href="?route=marcaciones&fecha=<?= $today ?>" class="kpi-footer-link">
                                Ver marcaciones crudas <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                    <div class="kpi-card h-100">
                        <div class="kpi-card-header">
                            <div>
                                <div class="kpi-title">Latencia Promedio Sync</div>
                                <div class="kpi-value"><?= round((float)($syncStats['promedio_duracion'] ?? 0), 1) ?>s</div>
                                <div class="kpi-subtitle"><?= $totalUsuarios ?> cuentas de usuario activas</div>
                            </div>
                            <div class="kpi-icon-box kpi-icon-slate">
                                <i class="fa-solid fa-stopwatch"></i>
                            </div>
                        </div>
                        <div>
                            <a href="?route=dispositivos" class="kpi-footer-link">
                                Probar conectividad TCP <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FILA 2 TI: ESTADO DETALLADO DE RELOJES Y AUDITORÍA DE LOGS -->
            <div class="row">
                <!-- Relojes Biométricos -->
                <div class="col-lg-6 mb-3">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title font-weight-bold">
                                <i class="fa-solid fa-server mr-2 text-primary"></i> Terminales Biométricas en Red
                            </h3>
                            <div class="card-tools">
                                <a href="?route=dispositivos" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-gear mr-1"></i> Administrar
                                </a>
                            </div>
                        </div>
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th>Dispositivo</th>
                                        <th>Dirección IP / Puerto</th>
                                        <th>Ubicación</th>
                                        <th class="text-center">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($dispositivos)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted">No hay biométricos registrados en el sistema.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($dispositivos as $d): ?>
                                            <tr>
                                                <td class="font-weight-bold text-dark">
                                                    <i class="fa-solid fa-fingerprint text-primary mr-1"></i>
                                                    <?= htmlspecialchars($d['nombre']) ?>
                                                </td>
                                                <td class="font-monospace small">
                                                    <?= htmlspecialchars($d['ip']) ?>:<?= $d['puerto'] ?>
                                                    <span class="badge-pill-custom badge-pill-neutral ml-1"><?= htmlspecialchars($d['protocolo']) ?></span>
                                                </td>
                                                <td class="small text-muted">
                                                    <?= htmlspecialchars($d['ubicacion'] ?? 'Sede Principal') ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($d['estado_conexion'] === 'ONLINE'): ?>
                                                        <span class="badge-pill-custom badge-pill-online"><i class="fa-solid fa-circle" style="font-size: 6px;"></i> En Línea</span>
                                                    <?php else: ?>
                                                        <span class="badge-pill-custom badge-pill-offline"><i class="fa-solid fa-circle" style="font-size: 6px;"></i> Desconectado</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Auditoría de Logs de Sincronización Recientes -->
                <div class="col-lg-6 mb-3">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title font-weight-bold">
                                <i class="fa-solid fa-clock-rotate-left mr-2 text-primary"></i> Historial Reciente de Sincronización
                            </h3>
                            <span class="badge-pill-custom badge-pill-neutral">Últimos ciclos</span>
                        </div>
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th>Fecha y Hora</th>
                                        <th>Dispositivo</th>
                                        <th class="text-center">Nuevos</th>
                                        <th class="text-center">Duración</th>
                                        <th class="text-center">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($ultimosLogsSync)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">Sin registros de sincronización recientes.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($ultimosLogsSync as $log): ?>
                                            <tr>
                                                <td class="font-monospace small text-secondary">
                                                    <?= substr($log['fecha_hora'], 0, 16) ?>
                                                </td>
                                                <td class="small font-weight-bold text-dark">
                                                    <?= htmlspecialchars($log['dispositivo_nombre'] ?? 'Reloj') ?>
                                                </td>
                                                <td class="text-center font-weight-bold text-success">
                                                    +<?= (int)$log['total_insertados'] ?>
                                                </td>
                                                <td class="text-center font-monospace small text-muted">
                                                    <?= round((float)$log['duracion_segundos'], 1) ?>s
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($log['estado'] === 'EXITO'): ?>
                                                        <span class="badge-pill-custom badge-pill-presente"><i class="fa-solid fa-check mr-1"></i> Éxito</span>
                                                    <?php else: ?>
                                                        <span class="badge-pill-custom badge-pill-falta" title="<?= htmlspecialchars($log['mensaje_error'] ?? '') ?>"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Error</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FILA 3 TI: FEED DE MARCACIONES CRUDAS Y RESUMEN GENERAL -->
            <div class="row">
                <!-- Feed en Vivo -->
                <div class="col-lg-8 mb-3">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title font-weight-bold">
                                <span class="badge-pill-custom badge-pill-online mr-2">
                                    <i class="fa-solid fa-circle" style="font-size: 6px;"></i> EN VIVO
                                </span>
                                Últimas Marcaciones Recibidas
                            </h3>
                            <div class="card-tools">
                                <a href="?route=marcaciones&fecha=<?= $today ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-list mr-1"></i> Ver todas
                                </a>
                            </div>
                        </div>
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th>Hora</th>
                                        <th>Empleado / ID</th>
                                        <th>Punto de Control</th>
                                        <th>Tipo Marcación</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($ultimasMarcaciones)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted">
                                                <i class="fa-regular fa-clock fa-2x mb-2 d-block text-secondary"></i>
                                                Aún no hay marcaciones registradas para el día de hoy.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($ultimasMarcaciones as $m): ?>
                                            <tr>
                                                <td class="font-weight-bold font-monospace text-secondary">
                                                    <i class="far fa-clock mr-1 text-muted"></i>
                                                    <?= substr($m['fecha_hora'], 11, 8) ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($m['nombres'])): ?>
                                                        <div class="font-weight-bold text-dark"><?= htmlspecialchars($m['apellidos'] . ' ' . $m['nombres']) ?></div>
                                                        <small class="text-muted">ID Reloj: <?= htmlspecialchars($m['codigo_reloj']) ?></small>
                                                    <?php else: ?>
                                                        <span class="badge-pill-custom badge-pill-neutral">ID Reloj: <?= htmlspecialchars($m['codigo_reloj']) ?> (Sin vincular)</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="text-muted small">
                                                        <?php
                                                            $v = strtolower($m['tipo_verificacion'] ?? '');
                                                            if (str_contains($v, 'facial') || str_contains($v, 'face')) {
                                                                echo '<i class="fa-solid fa-camera text-info mr-1" title="Reconocimiento Facial"></i>';
                                                            } elseif (str_contains($v, 'tarjeta') || str_contains($v, 'card')) {
                                                                echo '<i class="fa-solid fa-id-card text-success mr-1" title="Tarjeta RFID"></i>';
                                                            } elseif (str_contains($v, 'clave') || str_contains($v, 'pin')) {
                                                                echo '<i class="fa-solid fa-key text-warning mr-1" title="Contraseña / PIN"></i>';
                                                            } else {
                                                                echo '<i class="fa-solid fa-fingerprint text-primary mr-1" title="Huella Dactilar"></i>';
                                                            }
                                                        ?>
                                                        <?= htmlspecialchars($m['dispositivo_nombre'] ?? 'Reloj') ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                        $tipo = strtolower($m['tipo'] ?? '');
                                                        if ($tipo === 'entrada') echo '<span class="badge-pill-custom badge-pill-presente"><i class="fa-solid fa-arrow-right-to-bracket mr-1"></i> Entrada</span>';
                                                        elseif ($tipo === 'salida') echo '<span class="badge-pill-custom badge-pill-justificado"><i class="fa-solid fa-arrow-right-from-bracket mr-1"></i> Salida</span>';
                                                        elseif (str_contains($tipo, 'refrigerio')) echo '<span class="badge-pill-custom badge-pill-neutral"><i class="fa-solid fa-utensils mr-1"></i> Refrigerio</span>';
                                                        else echo '<span class="badge-pill-custom badge-pill-neutral">Marcación</span>';
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Resumen de Asistencia de Hoy TI -->
                <div class="col-lg-4 mb-3">
                    <div class="card h-100">
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold">
                                <i class="fa-solid fa-chart-pie mr-2 text-primary"></i> Resumen de Asistencia Hoy
                            </h3>
                        </div>
                        <div class="card-body d-flex flex-column justify-content-between">
                            <div style="height: 200px; position: relative;">
                                <canvas id="distribucionChart"></canvas>
                            </div>
                            <div class="d-flex justify-content-around text-center mt-3 small pt-2 border-top">
                                <div><i class="fas fa-circle text-success mr-1"></i> Presentes: <b><?= $presentesHoy ?></b></div>
                                <div><i class="fas fa-circle text-warning mr-1"></i> Tardanzas: <b><?= $tardanzasHoy ?></b></div>
                                <div><i class="fas fa-circle text-danger mr-1"></i> Faltas: <b><?= (int)($statsHoy['faltas'] ?? 0) ?></b></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($activeRoleView === 'RRHH'): ?>
            <!-- =================================================================== -->
            <!-- VISTA: RECURSOS HUMANOS (ANALÍTICA, NÓMINA & CUMPLIMIENTO)          -->
            <!-- =================================================================== -->

            <!-- TARJETAS KPIS RRHH -->
            <div class="row">
                <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                    <div class="kpi-card h-100">
                        <div class="kpi-card-header">
                            <div>
                                <div class="kpi-title">Tasa de Puntualidad Hoy</div>
                                <div class="kpi-value text-success"><?= $puntualidadHoyPorc ?>%</div>
                                <div class="kpi-subtitle"><?= $presentesHoy ?> de <?= $totalEmpleados ?> trabajadores a tiempo</div>
                            </div>
                            <div class="kpi-icon-box kpi-icon-emerald">
                                <i class="fa-solid fa-user-check"></i>
                            </div>
                        </div>
                        <div>
                            <a href="?route=asistencia&fecha_inicio=<?= $today ?>&fecha_fin=<?= $today ?>&estado=PRESENTE" class="kpi-footer-link">
                                Ver presentes <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                    <div class="kpi-card h-100">
                        <div class="kpi-card-header">
                            <div>
                                <div class="kpi-title">Tardanzas de Hoy</div>
                                <div class="kpi-value text-warning"><?= (int)($statsHoy['tardanzas'] ?? 0) ?></div>
                                <div class="kpi-subtitle"><?= (int)($statsHoy['total_minutos_tardanza'] ?? 0) ?> min acumulados hoy</div>
                            </div>
                            <div class="kpi-icon-box kpi-icon-amber">
                                <i class="fa-solid fa-clock-rotate-left"></i>
                            </div>
                        </div>
                        <div>
                            <a href="?route=asistencia&fecha_inicio=<?= $today ?>&fecha_fin=<?= $today ?>&estado=TARDANZA" class="kpi-footer-link">
                                Ver detalle de tardanzas <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                    <div class="kpi-card h-100">
                        <div class="kpi-card-header">
                            <div>
                                <div class="kpi-title">Inasistencias Hoy</div>
                                <div class="kpi-value text-danger"><?= (int)($statsHoy['faltas'] ?? 0) ?></div>
                                <div class="kpi-subtitle"><?= $justificacionesPendientes ?> justificaciones pendientes</div>
                            </div>
                            <div class="kpi-icon-box kpi-icon-rose">
                                <i class="fa-solid fa-user-xmark"></i>
                            </div>
                        </div>
                        <div>
                            <a href="?route=asistencia&fecha_inicio=<?= $today ?>&fecha_fin=<?= $today ?>&estado=FALTA" class="kpi-footer-link">
                                Ver inasistencias <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                    <div class="kpi-card h-100">
                        <div class="kpi-card-header">
                            <div>
                                <div class="kpi-title">Horas Extras del Mes</div>
                                <div class="kpi-value text-primary"><?= $horasExtraMes ?> <span style="font-size: 1rem; color: #64748b; font-weight: 600;">hrs</span></div>
                                <div class="kpi-subtitle"><?= (int)($statsMes['total_minutos_extra_mes'] ?? 0) ?> min acumulados</div>
                            </div>
                            <div class="kpi-icon-box kpi-icon-blue">
                                <i class="fa-solid fa-business-time"></i>
                            </div>
                        </div>
                        <div>
                            <a href="?route=asistencia&fecha_inicio=<?= $monthStart ?>&fecha_fin=<?= $today ?>" class="kpi-footer-link">
                                Revisar acumulado del mes <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- GRÁFICOS ANALÍTICOS RRHH -->
            <div class="row">
                <div class="col-lg-8 mb-3">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title font-weight-bold">
                                <i class="fa-solid fa-chart-line mr-2 text-primary"></i> Tendencia de Asistencia (Últimos 7 Días)
                            </h3>
                            <span class="badge-pill-custom badge-pill-neutral">Últimos 7 días</span>
                        </div>
                        <div class="card-body">
                            <div style="height: 250px; position: relative;">
                                <canvas id="tendenciaChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 mb-3">
                    <div class="card h-100">
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold">
                                <i class="fa-solid fa-chart-pie mr-2 text-primary"></i> Distribución de Asistencia Hoy
                            </h3>
                        </div>
                        <div class="card-body d-flex flex-column justify-content-between">
                            <div style="height: 200px; position: relative;">
                                <canvas id="distribucionChart"></canvas>
                            </div>
                            <div class="d-flex justify-content-around text-center mt-3 small pt-2 border-top">
                                <div><i class="fas fa-circle text-success mr-1"></i> Presentes: <b><?= $presentesHoy ?></b></div>
                                <div><i class="fas fa-circle text-warning mr-1"></i> Tardanzas: <b><?= $tardanzasHoy ?></b></div>
                                <div><i class="fas fa-circle text-danger mr-1"></i> Faltas: <b><?= (int)($statsHoy['faltas'] ?? 0) ?></b></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CUMPLIMIENTO POR ÁREAS Y RANKING DE IMPUNTUALIDAD -->
            <div class="row">
                <div class="col-lg-7 mb-3">
                    <div class="card h-100">
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold">
                                <i class="fa-solid fa-building-user mr-2 text-primary"></i> Cumplimiento por Departamentos (Hoy)
                            </h3>
                        </div>
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th>Departamento / Área</th>
                                        <th class="text-center">Personal</th>
                                        <th class="text-center">Presentes</th>
                                        <th class="text-center">Tardanzas</th>
                                        <th class="text-center">Faltas</th>
                                        <th>% Cumplimiento</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($deptosStats as $ds): ?>
                                        <?php 
                                            $tot = (int)$ds['total_empleados'];
                                            $pres = (int)$ds['presentes'];
                                            $porc = $tot > 0 ? round(($pres / $tot) * 100, 0) : 0;
                                            $barColor = $porc >= 90 ? 'bg-success' : ($porc >= 70 ? 'bg-warning' : 'bg-danger');
                                        ?>
                                        <tr>
                                            <td class="font-weight-bold text-dark"><?= htmlspecialchars($ds['depto_nombre'] ?? 'General') ?></td>
                                            <td class="text-center font-weight-bold"><?= $tot ?></td>
                                            <td class="text-center text-success font-weight-bold"><?= $pres ?></td>
                                            <td class="text-center text-warning font-weight-bold"><?= (int)$ds['tardanzas'] ?></td>
                                            <td class="text-center text-danger font-weight-bold"><?= (int)$ds['faltas'] ?></td>
                                            <td style="min-width: 140px;">
                                                <div class="progress mb-1" style="height: 6px; border-radius: 9999px;">
                                                    <div class="progress-bar <?= $barColor ?>" style="width: <?= $porc ?>%; border-radius: 9999px;"></div>
                                                </div>
                                                <small class="font-weight-bold text-muted"><?= $porc ?>%</small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5 mb-3">
                    <!-- Top Tardanzas Recurrentes del Mes -->
                    <div class="card h-100">
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold">
                                <i class="fa-solid fa-chart-simple mr-2 text-primary"></i> Ranking de Impuntualidad (Mes Actual)
                            </h3>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($topTardanzasMes)): ?>
                                <div class="text-center py-4 text-muted small">
                                    <i class="fa-solid fa-circle-check text-success fa-2x mb-2 d-block"></i>
                                    ¡Excelente! No hay tardanzas acumuladas en el mes.
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($topTardanzasMes as $tm): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 border-0 border-bottom">
                                            <div>
                                                <div class="font-weight-bold text-dark small"><?= htmlspecialchars($tm['apellidos'] . ' ' . $tm['nombres']) ?></div>
                                                <div class="text-muted small">
                                                    <?= htmlspecialchars($tm['depto_nombre'] ?? 'Área') ?> &bull; <?= $tm['veces_tarde'] ?> incidencias
                                                </div>
                                            </div>
                                            <div>
                                                <span class="badge-pill-custom badge-pill-tardanza"><?= $tm['total_minutos'] ?> min</span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($activeRoleView === 'SUPERVISOR'): ?>
            <!-- =================================================================== -->
            <!-- VISTA: SUPERVISIÓN OPERATIVA (MONITOREO DE PLANTA Y TURNO)          -->
            <!-- =================================================================== -->

            <!-- TARJETAS KPIS SUPERVISIÓN -->
            <div class="row">
                <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                    <div class="kpi-card h-100">
                        <div class="kpi-card-header">
                            <div>
                                <div class="kpi-title">Personal en Turno Hoy</div>
                                <div class="kpi-value text-success"><?= $presentesHoy ?></div>
                                <div class="kpi-subtitle">Presentes con ingreso marcado</div>
                            </div>
                            <div class="kpi-icon-box kpi-icon-emerald">
                                <i class="fa-solid fa-users-viewfinder"></i>
                            </div>
                        </div>
                        <div>
                            <a href="?route=asistencia&fecha_inicio=<?= $today ?>&fecha_fin=<?= $today ?>&estado=PRESENTE" class="kpi-footer-link">
                                Listar personal en turno <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                    <div class="kpi-card h-100">
                        <div class="kpi-card-header">
                            <div>
                                <div class="kpi-title">Llegadas Tarde Hoy</div>
                                <div class="kpi-value text-warning"><?= $tardanzasHoy ?></div>
                                <div class="kpi-subtitle"><?= (int)($statsHoy['total_minutos_tardanza'] ?? 0) ?> min acumulados</div>
                            </div>
                            <div class="kpi-icon-box kpi-icon-amber">
                                <i class="fa-solid fa-person-walking-arrow-right"></i>
                            </div>
                        </div>
                        <div>
                            <a href="?route=asistencia&fecha_inicio=<?= $today ?>&fecha_fin=<?= $today ?>&estado=TARDANZA" class="kpi-footer-link">
                                Ver tardanzas <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                    <div class="kpi-card h-100">
                        <div class="kpi-card-header">
                            <div>
                                <div class="kpi-title">Inasistencias del Turno</div>
                                <div class="kpi-value text-danger"><?= (int)($statsHoy['faltas'] ?? 0) ?></div>
                                <div class="kpi-subtitle">Sin registro de entrada</div>
                            </div>
                            <div class="kpi-icon-box kpi-icon-rose">
                                <i class="fa-solid fa-user-slash"></i>
                            </div>
                        </div>
                        <div>
                            <a href="?route=asistencia&fecha_inicio=<?= $today ?>&fecha_fin=<?= $today ?>&estado=FALTA" class="kpi-footer-link">
                                Ver ausentes <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                    <div class="kpi-card h-100">
                        <div class="kpi-card-header">
                            <div>
                                <div class="kpi-title">Sin Salida Registrada</div>
                                <div class="kpi-value text-primary"><?= (int)($statsHoy['sin_salida'] ?? 0) ?></div>
                                <div class="kpi-subtitle">Jornada en curso / pendiente</div>
                            </div>
                            <div class="kpi-icon-box kpi-icon-indigo">
                                <i class="fa-solid fa-clock"></i>
                            </div>
                        </div>
                        <div>
                            <a href="?route=asistencia&fecha_inicio=<?= $today ?>&fecha_fin=<?= $today ?>&estado=SALIDA_SIN_MARCAR" class="kpi-footer-link">
                                Ver pendientes <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FEED EN VIVO Y CUMPLIMIENTO OPERATIVO -->
            <div class="row">
                <!-- Feed en Vivo -->
                <div class="col-lg-7 mb-3">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title font-weight-bold">
                                <span class="badge-pill-custom badge-pill-online mr-2">
                                    <i class="fa-solid fa-circle" style="font-size: 6px;"></i> EN VIVO
                                </span>
                                Marcaciones en Tiempo Real del Personal
                            </h3>
                            <div class="card-tools">
                                <a href="?route=marcaciones&fecha=<?= $today ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-list mr-1"></i> Ver todas
                                </a>
                            </div>
                        </div>
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th>Hora</th>
                                        <th>Empleado</th>
                                        <th>Punto de Control</th>
                                        <th>Marcación</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($ultimasMarcaciones)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted">
                                                Aún no hay marcaciones para el día de hoy.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($ultimasMarcaciones as $m): ?>
                                            <tr>
                                                <td class="font-weight-bold font-monospace text-secondary">
                                                    <?= substr($m['fecha_hora'], 11, 8) ?>
                                                </td>
                                                <td class="font-weight-bold text-dark">
                                                    <?= htmlspecialchars($m['apellidos'] . ' ' . $m['nombres']) ?>
                                                </td>
                                                <td class="small text-muted">
                                                    <?php
                                                        $v = strtolower($m['tipo_verificacion'] ?? '');
                                                        if (str_contains($v, 'facial') || str_contains($v, 'face')) {
                                                            echo '<i class="fa-solid fa-camera text-info mr-1" title="Reconocimiento Facial"></i>';
                                                        } elseif (str_contains($v, 'tarjeta') || str_contains($v, 'card')) {
                                                            echo '<i class="fa-solid fa-id-card text-success mr-1" title="Tarjeta RFID"></i>';
                                                        } elseif (str_contains($v, 'clave') || str_contains($v, 'pin')) {
                                                            echo '<i class="fa-solid fa-key text-warning mr-1" title="Contraseña / PIN"></i>';
                                                        } else {
                                                            echo '<i class="fa-solid fa-fingerprint text-primary mr-1" title="Huella Dactilar"></i>';
                                                        }
                                                    ?>
                                                    <?= htmlspecialchars($m['dispositivo_nombre'] ?? 'Reloj') ?>
                                                </td>
                                                <td>
                                                    <?php
                                                        $tipo = strtolower($m['tipo'] ?? '');
                                                        if ($tipo === 'entrada') echo '<span class="badge-pill-custom badge-pill-presente"><i class="fa-solid fa-arrow-right-to-bracket mr-1"></i> Entrada</span>';
                                                        elseif ($tipo === 'salida') echo '<span class="badge-pill-custom badge-pill-justificado"><i class="fa-solid fa-arrow-right-from-bracket mr-1"></i> Salida</span>';
                                                        elseif (str_contains($tipo, 'refrigerio')) echo '<span class="badge-pill-custom badge-pill-neutral"><i class="fa-solid fa-utensils mr-1"></i> Refrigerio</span>';
                                                        else echo '<span class="badge-pill-custom badge-pill-neutral">Marcación</span>';
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Mayores Tardanzas de Hoy -->
                <div class="col-lg-5 mb-3">
                    <div class="card h-100">
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold">
                                <i class="fa-solid fa-triangle-exclamation mr-2 text-warning"></i> Mayores Tardanzas de Hoy
                            </h3>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($topTardanzasHoy)): ?>
                                <div class="text-center py-4 text-muted small">
                                    <i class="fa-solid fa-circle-check text-success fa-2x mb-2 d-block"></i>
                                    ¡Cero tardanzas registradas el día de hoy!
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($topTardanzasHoy as $t): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 border-0 border-bottom">
                                            <div>
                                                <div class="font-weight-bold text-dark small"><?= htmlspecialchars($t['apellidos'] . ' ' . $t['nombres']) ?></div>
                                                <div class="text-muted small">
                                                    <?= htmlspecialchars($t['depto_nombre'] ?? 'Área General') ?> &bull; Ingreso: <?= substr($t['hora_entrada_real'], 11, 5) ?>
                                                </div>
                                            </div>
                                            <span class="badge-pill-custom badge-pill-tardanza">+<?= $t['minutos_tardanza'] ?> min</span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- =================================================================== -->
            <!-- VISTA: SOLO CONSULTA / GENERAL                                     -->
            <!-- =================================================================== -->
            <div class="row">
                <div class="col-lg-4 col-12 mb-3">
                    <div class="kpi-card h-100">
                        <div class="kpi-card-header">
                            <div>
                                <div class="kpi-title">Total Presentes Hoy</div>
                                <div class="kpi-value text-success"><?= $presentesHoy ?></div>
                                <div class="kpi-subtitle">Personal con ingreso registrado</div>
                            </div>
                            <div class="kpi-icon-box kpi-icon-emerald">
                                <i class="fa-solid fa-user-check"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-12 mb-3">
                    <div class="kpi-card h-100">
                        <div class="kpi-card-header">
                            <div>
                                <div class="kpi-title">Tardanzas Hoy</div>
                                <div class="kpi-value text-warning"><?= $tardanzasHoy ?></div>
                                <div class="kpi-subtitle">Ingresos fuera de tolerancia</div>
                            </div>
                            <div class="kpi-icon-box kpi-icon-amber">
                                <i class="fa-solid fa-clock-rotate-left"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-12 mb-3">
                    <div class="kpi-card h-100">
                        <div class="kpi-card-header">
                            <div>
                                <div class="kpi-title">Inasistencias Registradas</div>
                                <div class="kpi-value text-danger"><?= (int)($statsHoy['faltas'] ?? 0) ?></div>
                                <div class="kpi-subtitle">Sin registro de entrada</div>
                            </div>
                            <div class="kpi-icon-box kpi-icon-rose">
                                <i class="fa-solid fa-user-slash"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-8 mb-3">
                    <div class="card h-100">
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold"><i class="fa-solid fa-chart-line mr-2 text-primary"></i> Tendencia de Asistencia (Últimos 7 Días)</h3>
                        </div>
                        <div class="card-body">
                            <div style="height: 250px; position: relative;">
                                <canvas id="tendenciaChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 mb-3">
                    <div class="card h-100">
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold"><i class="fa-solid fa-chart-pie mr-2 text-primary"></i> Distribución de Hoy</h3>
                        </div>
                        <div class="card-body">
                            <div style="height: 210px; position: relative;">
                                <canvas id="distribucionChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>
</section>

<!-- MODAL DE SELECCIÓN DE SINCRONIZACIÓN -->
<div class="modal fade" id="modalSincronizacion" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold"><i class="fa-solid fa-arrows-rotate mr-2"></i> Sincronización de Biométricos</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body py-4">
                <p class="text-secondary small mb-3">Selecciona el modo de sincronización deseado para los relojes ZKTeco:</p>
                
                <div class="list-group">
                    <!-- Opción 1: Rápido Hoy -->
                    <a href="javascript:void(0)" onclick="executeSyncMode('today')" class="list-group-item list-group-item-action d-flex align-items-center p-3 mb-2 border rounded">
                        <div class="mr-3 text-success"><i class="fa-solid fa-bolt fa-2x"></i></div>
                        <div>
                            <div class="font-weight-bold text-dark">Sincronizar Solo Hoy (Ultra Rápido) <span class="badge badge-success ml-1">Recomendado</span></div>
                            <small class="text-muted">Procesa solo las marcaciones de hoy. No satura memoria con registros históricos (Toma ~1 segundo).</small>
                        </div>
                    </a>

                    <!-- Opción 2: Histórico Completo -->
                    <a href="javascript:void(0)" onclick="executeSyncMode('full')" class="list-group-item list-group-item-action d-flex align-items-center p-3 border rounded">
                        <div class="mr-3 text-primary"><i class="fa-solid fa-database fa-2x"></i></div>
                        <div>
                            <div class="font-weight-bold text-dark">Sincronización Histórica Completa</div>
                            <small class="text-muted">Descarga todos los registros almacenados en el reloj. Ideal para auditorías iniciales.</small>
                        </div>
                    </a>
                </div>
            </div>
            <div class="modal-footer justify-content-end bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes pulse {
    0% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.7; transform: scale(1.03); }
    100% { opacity: 1; transform: scale(1); }
}
</style>

<!-- SCRIPTS DE CHART.JS -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Gráfico de Tendencia 7 Días
    const ctxTendencia = document.getElementById('tendenciaChart')?.getContext('2d');
    if (ctxTendencia) {
        new Chart(ctxTendencia, {
            type: 'line',
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [
                    {
                        label: 'Presentes',
                        data: <?= json_encode($chartPresentes) ?>,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.12)',
                        borderWidth: 2,
                        pointBackgroundColor: '#10b981',
                        fill: true,
                        tension: 0.3
                    },
                    {
                        label: 'Tardanzas',
                        data: <?= json_encode($chartTardanzas) ?>,
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245, 158, 11, 0.12)',
                        borderWidth: 2,
                        pointBackgroundColor: '#f59e0b',
                        fill: true,
                        tension: 0.3
                    },
                    {
                        label: 'Faltas',
                        data: <?= json_encode($chartFaltas) ?>,
                        borderColor: '#f43f5e',
                        backgroundColor: 'rgba(244, 63, 94, 0.12)',
                        borderWidth: 2,
                        pointBackgroundColor: '#f43f5e',
                        fill: true,
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { boxWidth: 12, font: { size: 12, family: "'Plus Jakarta Sans', sans-serif" } }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 }
                    }
                }
            }
        });
    }

    // 2. Gráfico de Dona: Distribución Hoy
    const ctxDist = document.getElementById('distribucionChart')?.getContext('2d');
    if (ctxDist) {
        new Chart(ctxDist, {
            type: 'doughnut',
            data: {
                labels: ['Presentes', 'Tardanzas', 'Faltas', 'Justificados', 'Sin Salida'],
                datasets: [{
                    data: [
                        <?= (int)($statsHoy['presentes'] ?? 0) ?>,
                        <?= (int)($statsHoy['tardanzas'] ?? 0) ?>,
                        <?= (int)($statsHoy['faltas'] ?? 0) ?>,
                        <?= (int)($statsHoy['justificados'] ?? 0) ?>,
                        <?= (int)($statsHoy['sin_salida'] ?? 0) ?>
                    ],
                    backgroundColor: ['#10b981', '#f59e0b', '#f43f5e', '#3b82f6', '#94a3b8'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                cutout: '72%'
            }
        });
    }
});

function openSyncModal() {
    $('#modalSincronizacion').modal('show');
}

function executeSyncMode(mode) {
    $('#modalSincronizacion').modal('hide');
    
    Swal.fire({
        title: mode === 'today' ? 'Sincronizando Marcaciones de Hoy...' : 'Sincronizando Histórico Completo...',
        html: `
            <div class="text-center py-2">
                <i class="fa-solid fa-arrows-rotate fa-spin fa-3x text-success mb-3"></i>
                <p class="mb-1 font-weight-bold text-dark">Conectando con biométricos ZKTeco...</p>
                <div class="badge badge-light border px-2 py-1 text-muted" id="swal-timer">Tiempo transcurrido: 0s</div>
            </div>
        `,
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    let seconds = 0;
    const timer = setInterval(() => {
        seconds++;
        const timerEl = document.getElementById('swal-timer');
        if (timerEl) timerEl.innerText = `Tiempo transcurrido: ${seconds}s`;
    }, 1000);

    fetch(`?route=dispositivos&action=sincronizar&mode=${mode}&ajax=1`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        // Polling de estado
        const poll = setInterval(() => {
            fetch('?route=dispositivos&action=sync_status', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(statusData => {
                if (!statusData.running) {
                    clearInterval(poll);
                    clearInterval(timer);

                    if (statusData.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Sincronización Exitosa!',
                            text: statusData.output || 'Marcaciones descargadas y procesadas correctamente.',
                            confirmButtonText: 'Actualizar Panel',
                            confirmButtonColor: '#28a745'
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'info',
                            title: 'Resultado de Sincronización',
                            text: statusData.output || 'Ciclo de sincronización completado.',
                            confirmButtonText: 'Aceptar'
                        }).then(() => {
                            window.location.reload();
                        });
                    }
                }
            });
        }, 2000);
    })
    .catch(err => {
        clearInterval(timer);
        Swal.fire('Error', 'No se pudo comunicar con el servidor: ' + err.message, 'error');
    });
}
</script>

<?php require_once APP_ROOT . '/views/layout/footer.php'; ?>

