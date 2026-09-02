<?php require_once APP_ROOT . '/views/layout/header.php'; ?>

<!-- Content Header (Page header) -->
<div class="content-header pb-2">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-md-7 col-sm-12 mb-2 mb-md-0">
                <h1 class="m-0 font-weight-bold text-dark">
                    <?php if ($activeRoleView === 'ADMIN'): ?>
                        <i class="fa-solid fa-server mr-2 text-danger"></i> Panel de Control TI & Biometría
                    <?php elseif ($activeRoleView === 'RRHH'): ?>
                        <i class="fa-solid fa-users-gear mr-2 text-primary"></i> Panel de Control de Recursos Humanos
                    <?php elseif ($activeRoleView === 'SUPERVISOR'): ?>
                        <i class="fa-solid fa-user-tie mr-2 text-info"></i> Panel de Supervisión Operativa
                    <?php else: ?>
                        <i class="fa-solid fa-gauge-high mr-2 text-secondary"></i> Panel de Control y Consulta
                    <?php endif; ?>
                </h1>
                <small class="text-muted">
                    <?php if ($activeRoleView === 'ADMIN'): ?>
                        Monitoreo de infraestructura biométrica ZKTeco, conectividad IP, logs de sincronización y estado del sistema
                    <?php elseif ($activeRoleView === 'RRHH'): ?>
                        Analítica de puntualidad, ausentismo, horas extras acumuladas, ranking de tardanzas y justificaciones
                    <?php elseif ($activeRoleView === 'SUPERVISOR'): ?>
                        Control en tiempo real del personal en turno, llegadas tarde del día y monitoreo de piso
                    <?php else: ?>
                        Resumen general de asistencia y métricas consolidadas de la empresa
                    <?php endif; ?>
                </small>
            </div>
            <div class="col-md-5 col-sm-12 text-md-right">
                <!-- Botones de Acción Rápida Específicos por Rol -->
                <?php if ($activeRoleView === 'ADMIN'): ?>
                    <a href="?route=dispositivos" class="btn btn-sm btn-outline-danger shadow-sm mr-1">
                        <i class="fa-solid fa-network-wired mr-1"></i> Gestionar Relojes
                    </a>
                    <button type="button" class="btn btn-sm btn-success shadow-sm" onclick="openSyncModal()">
                        <i class="fa-solid fa-arrows-rotate mr-1"></i> Sincronizar Biométricos
                    </button>
                <?php elseif ($activeRoleView === 'RRHH'): ?>
                    <a href="?route=justificaciones" class="btn btn-sm btn-outline-primary shadow-sm mr-1">
                        <i class="fa-solid fa-file-signature mr-1"></i> Justificaciones (<?= $justificacionesPendientes ?>)
                    </a>
                    <button type="button" class="btn btn-sm btn-success shadow-sm" onclick="openSyncModal()">
                        <i class="fa-solid fa-bolt mr-1"></i> Sincronizar Hoy
                    </button>
                <?php elseif ($activeRoleView === 'SUPERVISOR'): ?>
                    <a href="?route=asistencia&fecha_inicio=<?= $today ?>&fecha_fin=<?= $today ?>" class="btn btn-sm btn-info shadow-sm">
                        <i class="fa-solid fa-list-check mr-1"></i> Asistencias de Hoy
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
            <!-- VISTA EXCLUSIVA: ADMINISTRADOR (TI, HARDWARE & BIOMETRÍA)            -->
            <!-- =================================================================== -->
            
            <!-- TARJETAS KPIS TI -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-gradient-danger shadow-sm">
                        <div class="inner">
                            <h3><?= $dispositivosOnline ?> <sup style="font-size: 18px">/ <?= count($dispositivos) ?></sup></h3>
                            <p class="font-weight-bold mb-0">Biométricos En Línea</p>
                            <small><?= $dispositivosOnline === count($dispositivos) ? '100% de relojes conectados' : 'Atención en conectividad' ?></small>
                        </div>
                        <div class="icon"><i class="fa-solid fa-network-wired"></i></div>
                        <a href="?route=dispositivos" class="small-box-footer">Gestionar biométricos <i class="fas fa-arrow-circle-right ml-1"></i></a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-gradient-success shadow-sm">
                        <div class="inner">
                            <h3><?= $tasaExitoSync ?>%</h3>
                            <p class="font-weight-bold mb-0">Efectividad de Sincronización</p>
                            <small><?= (int)($syncStats['exitos'] ?? 0) ?> exitosos / <?= (int)($syncStats['total_syncs'] ?? 0) ?> ciclos (7d)</small>
                        </div>
                        <div class="icon"><i class="fa-solid fa-circle-check"></i></div>
                        <a href="?route=dispositivos" class="small-box-footer">Ver auditoría de sync <i class="fas fa-arrow-circle-right ml-1"></i></a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-gradient-primary shadow-sm">
                        <div class="inner">
                            <h3><?= $totalMarcacionesHoy ?></h3>
                            <p class="font-weight-bold mb-0">Marcaciones de Hoy</p>
                            <small>Descargadas en base de datos</small>
                        </div>
                        <div class="icon"><i class="fa-solid fa-fingerprint"></i></div>
                        <a href="?route=marcaciones&fecha=<?= $today ?>" class="small-box-footer">Ver marcaciones crudas <i class="fas fa-arrow-circle-right ml-1"></i></a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-gradient-secondary shadow-sm">
                        <div class="inner">
                            <h3><?= round((float)($syncStats['promedio_duracion'] ?? 0), 1) ?>s</h3>
                            <p class="font-weight-bold mb-0">Latencia Promedio Sync</p>
                            <small><?= $totalUsuarios ?> usuarios del sistema activos</small>
                        </div>
                        <div class="icon"><i class="fa-solid fa-stopwatch"></i></div>
                        <a href="?route=dispositivos" class="small-box-footer">Probar conexión TCP <i class="fas fa-arrow-circle-right ml-1"></i></a>
                    </div>
                </div>
            </div>

            <!-- FILA 2 TI: ESTADO DETALLADO DE RELOJES Y AUDITORÍA DE LOGS -->
            <div class="row">
                <!-- Relojes Biométricos -->
                <div class="col-lg-6">
                    <div class="card card-outline card-danger shadow-sm mb-3">
                        <div class="card-header border-0 d-flex justify-content-between align-items-center">
                            <h3 class="card-title font-weight-bold text-dark">
                                <i class="fa-solid fa-server mr-2 text-danger"></i> Infraestructura de Relojes ZKTeco
                            </h3>
                            <div class="card-tools">
                                <a href="?route=dispositivos" class="btn btn-xs btn-outline-danger font-weight-bold">
                                    <i class="fa-solid fa-gear mr-1"></i> Administrar Dispositivos
                                </a>
                            </div>
                        </div>
                        <div class="card-body table-responsive p-0">
                            <table class="table table-hover mb-0 table-sm">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Dispositivo</th>
                                        <th>Dirección IP / Puerto</th>
                                        <th>Modelo / Ubicación</th>
                                        <th class="text-center">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($dispositivos)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-3 text-muted">No hay biométricos registrados en el sistema.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($dispositivos as $d): ?>
                                            <tr>
                                                <td class="font-weight-bold text-dark">
                                                    <i class="fa-solid fa-fingerprint text-muted mr-1"></i>
                                                    <?= htmlspecialchars($d['nombre']) ?>
                                                </td>
                                                <td class="font-monospace small">
                                                    <?= htmlspecialchars($d['ip']) ?>:<?= $d['puerto'] ?>
                                                    <span class="badge badge-light border ml-1"><?= htmlspecialchars($d['protocolo']) ?></span>
                                                </td>
                                                <td class="small text-muted">
                                                    <?= htmlspecialchars($d['modelo'] ?? 'ZKTeco') ?> &bull; <?= htmlspecialchars($d['ubicacion'] ?? 'Sede') ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($d['estado_conexion'] === 'ONLINE'): ?>
                                                        <span class="badge badge-success px-2 py-1"><i class="fa-solid fa-circle-check mr-1"></i> ONLINE</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-danger px-2 py-1"><i class="fa-solid fa-circle-xmark mr-1"></i> OFFLINE</span>
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
                <div class="col-lg-6">
                    <div class="card card-outline card-secondary shadow-sm mb-3">
                        <div class="card-header border-0 d-flex justify-content-between align-items-center">
                            <h3 class="card-title font-weight-bold text-dark">
                                <i class="fa-solid fa-clock-rotate-left mr-2 text-secondary"></i> Historial Reciente de Sincronización
                            </h3>
                            <span class="badge badge-light border">Últimos ciclos</span>
                        </div>
                        <div class="card-body table-responsive p-0">
                            <table class="table table-hover table-striped mb-0 table-sm">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Fecha / Hora</th>
                                        <th>Dispositivo</th>
                                        <th class="text-center">Nuevos</th>
                                        <th class="text-center">Duración</th>
                                        <th class="text-center">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($ultimosLogsSync)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-3 text-muted">Sin registros de sincronización recientes.</td>
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
                                                        <span class="badge badge-success px-2 py-1"><i class="fa-solid fa-check mr-1"></i> ÉXITO</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-danger px-2 py-1" title="<?= htmlspecialchars($log['mensaje_error'] ?? '') ?>"><i class="fa-solid fa-triangle-exclamation mr-1"></i> ERROR</span>
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
                <div class="col-lg-8">
                    <div class="card card-outline card-primary shadow-sm mb-3">
                        <div class="card-header border-0 d-flex justify-content-between align-items-center">
                            <h3 class="card-title font-weight-bold text-dark">
                                <span class="badge badge-success px-2 py-1 mr-2" style="animation: pulse 1.5s infinite;">
                                    <i class="fa-solid fa-satellite-dish mr-1"></i> EN VIVO
                                </span>
                                Últimas Marcaciones Crudas Recibidas
                            </h3>
                            <div class="card-tools">
                                <a href="?route=marcaciones&fecha=<?= $today ?>" class="btn btn-tool btn-sm">
                                    <i class="fas fa-list mr-1"></i> Ver todas
                                </a>
                            </div>
                        </div>
                        <div class="card-body table-responsive p-0">
                            <table class="table table-hover table-striped mb-0 table-sm">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Hora</th>
                                        <th>Empleado / ID</th>
                                        <th>Dispositivo Origen</th>
                                        <th>Tipo Marcación</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($ultimasMarcaciones)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted">
                                                <i class="fa-regular fa-clock fa-2x mb-2 d-block"></i>
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
                                                        <span class="badge badge-secondary">ID Reloj: <?= htmlspecialchars($m['codigo_reloj']) ?> (Sin vincular)</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="text-muted small">
                                                        <i class="fa-solid fa-fingerprint mr-1 text-primary"></i>
                                                        <?= htmlspecialchars($m['dispositivo_nombre'] ?? 'Reloj') ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                        $tipo = strtolower($m['tipo'] ?? '');
                                                        if ($tipo === 'entrada') echo '<span class="badge badge-success px-2 py-1"><i class="fa-solid fa-arrow-right-to-bracket mr-1"></i>Entrada</span>';
                                                        elseif ($tipo === 'salida') echo '<span class="badge badge-primary px-2 py-1"><i class="fa-solid fa-arrow-right-from-bracket mr-1"></i>Salida</span>';
                                                        elseif (str_contains($tipo, 'refrigerio')) echo '<span class="badge badge-info px-2 py-1"><i class="fa-solid fa-utensils mr-1"></i>Refrigerio</span>';
                                                        else echo '<span class="badge badge-secondary px-2 py-1">Marcación</span>';
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
                <div class="col-lg-4">
                    <div class="card card-outline card-info shadow-sm mb-3">
                        <div class="card-header border-0">
                            <h3 class="card-title font-weight-bold text-dark">
                                <i class="fa-solid fa-chart-pie mr-1 text-info"></i> Resumen de Asistencia Hoy
                            </h3>
                        </div>
                        <div class="card-body pt-0">
                            <div style="height: 210px; position: relative;">
                                <canvas id="distribucionChart"></canvas>
                            </div>
                            <div class="d-flex justify-content-around text-center mt-2 small">
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
            <!-- VISTA EXCLUSIVA: RECURSOS HUMANOS (ANALÍTICA, NÓMINA & CUMPLIMIENTO)-->
            <!-- =================================================================== -->

            <!-- TARJETAS KPIS RRHH -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-gradient-success shadow-sm">
                        <div class="inner">
                            <h3><?= $puntualidadHoyPorc ?>%</h3>
                            <p class="font-weight-bold mb-0">Tasa de Puntualidad Hoy</p>
                            <small><?= $presentesHoy ?> de <?= $totalEmpleados ?> empleados a tiempo</small>
                        </div>
                        <div class="icon"><i class="fa-solid fa-user-check"></i></div>
                        <a href="?route=asistencia&fecha_inicio=<?= $today ?>&fecha_fin=<?= $today ?>&estado=PRESENTE" class="small-box-footer">
                            Ver presentes <i class="fas fa-arrow-circle-right ml-1"></i>
                        </a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-gradient-warning shadow-sm">
                        <div class="inner">
                            <h3 class="text-white"><?= (int)($statsHoy['tardanzas'] ?? 0) ?></h3>
                            <p class="font-weight-bold text-white mb-0">Tardanzas de Hoy</p>
                            <small class="text-white"><?= (int)($statsHoy['total_minutos_tardanza'] ?? 0) ?> min acumulados hoy</small>
                        </div>
                        <div class="icon"><i class="fa-solid fa-clock-rotate-left text-white"></i></div>
                        <a href="?route=asistencia&fecha_inicio=<?= $today ?>&fecha_fin=<?= $today ?>&estado=TARDANZA" class="small-box-footer text-white">
                            Ver detalle <i class="fas fa-arrow-circle-right ml-1"></i>
                        </a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-gradient-danger shadow-sm">
                        <div class="inner">
                            <h3><?= (int)($statsHoy['faltas'] ?? 0) ?></h3>
                            <p class="font-weight-bold mb-0">Inasistencias Hoy</p>
                            <small><?= $justificacionesPendientes ?> justificaciones pendientes</small>
                        </div>
                        <div class="icon"><i class="fa-solid fa-user-xmark"></i></div>
                        <a href="?route=asistencia&fecha_inicio=<?= $today ?>&fecha_fin=<?= $today ?>&estado=FALTA" class="small-box-footer">
                            Ver inasistencias <i class="fas fa-arrow-circle-right ml-1"></i>
                        </a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-gradient-info shadow-sm">
                        <div class="inner">
                            <h3><?= $horasExtraMes ?> <sup style="font-size: 18px">hrs</sup></h3>
                            <p class="font-weight-bold mb-0">Horas Extras del Mes</p>
                            <small><?= (int)($statsMes['total_minutos_extra_mes'] ?? 0) ?> min acumulados</small>
                        </div>
                        <div class="icon"><i class="fa-solid fa-business-time"></i></div>
                        <a href="?route=asistencia&fecha_inicio=<?= $monthStart ?>&fecha_fin=<?= $today ?>" class="small-box-footer">
                            Revisar mes <i class="fas fa-arrow-circle-right ml-1"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- GRÁFICOS ANALÍTICOS RRHH -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card card-outline card-primary shadow-sm mb-3">
                        <div class="card-header border-0 d-flex justify-content-between align-items-center">
                            <h3 class="card-title font-weight-bold text-dark">
                                <i class="fa-solid fa-chart-line mr-1 text-primary"></i> Tendencia de Asistencia (Últimos 7 Días)
                            </h3>
                            <div class="card-tools">
                                <span class="badge badge-light border">Últimos 7 días</span>
                            </div>
                        </div>
                        <div class="card-body pt-0">
                            <div style="height: 250px; position: relative;">
                                <canvas id="tendenciaChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card card-outline card-info shadow-sm mb-3">
                        <div class="card-header border-0">
                            <h3 class="card-title font-weight-bold text-dark">
                                <i class="fa-solid fa-chart-pie mr-1 text-info"></i> Distribución de Asistencia Hoy
                            </h3>
                        </div>
                        <div class="card-body pt-0">
                            <div style="height: 210px; position: relative;">
                                <canvas id="distribucionChart"></canvas>
                            </div>
                            <div class="d-flex justify-content-around text-center mt-2 small">
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
                <div class="col-lg-7">
                    <div class="card card-outline card-secondary shadow-sm mb-3">
                        <div class="card-header border-0">
                            <h3 class="card-title font-weight-bold text-dark">
                                <i class="fa-solid fa-building-user mr-1 text-secondary"></i> Cumplimiento por Departamentos (Hoy)
                            </h3>
                        </div>
                        <div class="card-body table-responsive p-0">
                            <table class="table table-hover mb-0 table-sm">
                                <thead class="thead-light">
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
                                                <div class="progress progress-xs mb-1" style="height: 6px;">
                                                    <div class="progress-bar <?= $barColor ?>" style="width: <?= $porc ?>%"></div>
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

                <div class="col-lg-5">
                    <!-- Top Tardanzas Recurrentes del Mes -->
                    <div class="card card-outline card-danger shadow-sm mb-3">
                        <div class="card-header border-0">
                            <h3 class="card-title font-weight-bold text-dark">
                                <i class="fa-solid fa-chart-simple mr-1 text-danger"></i> Ranking de Impuntualidad (Mes Actual)
                            </h3>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($topTardanzasMes)): ?>
                                <div class="text-center py-4 text-muted small">
                                    <i class="fa-solid fa-circle-check text-success fa-2x mb-2 d-block"></i>
                                    ¡Excelente! No hay tardanzas acumuladas en el mes.
                                </div>
                            <?php else: ?>
                                <ul class="products-list product-list-in-card pl-2 pr-2">
                                    <?php foreach ($topTardanzasMes as $tm): ?>
                                        <li class="item d-flex justify-content-between align-items-center p-2 border-bottom">
                                            <div>
                                                <span class="font-weight-bold text-dark"><?= htmlspecialchars($tm['apellidos'] . ' ' . $tm['nombres']) ?></span>
                                                <span class="product-description text-muted small">
                                                    <?= htmlspecialchars($tm['depto_nombre'] ?? 'Área') ?> &bull; <?= $tm['veces_tarde'] ?> incidencias
                                                </span>
                                            </div>
                                            <div class="text-right">
                                                <span class="badge badge-danger font-weight-bold px-2 py-1"><?= $tm['total_minutos'] ?> min</span>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($activeRoleView === 'SUPERVISOR'): ?>
            <!-- =================================================================== -->
            <!-- VISTA EXCLUSIVA: SUPERVISIÓN OPERATIVA (MONITOREO DE PLANTA Y TURNO)-->
            <!-- =================================================================== -->

            <!-- TARJETAS KPIS SUPERVISIÓN -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-gradient-success shadow-sm">
                        <div class="inner">
                            <h3><?= $presentesHoy ?></h3>
                            <p class="font-weight-bold mb-0">Personal en Turno Hoy</p>
                            <small>Presentes con ingreso marcado</small>
                        </div>
                        <div class="icon"><i class="fa-solid fa-users-viewfinder"></i></div>
                        <a href="?route=asistencia&fecha_inicio=<?= $today ?>&fecha_fin=<?= $today ?>&estado=PRESENTE" class="small-box-footer">Listar personal <i class="fas fa-arrow-circle-right ml-1"></i></a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-gradient-warning shadow-sm">
                        <div class="inner">
                            <h3 class="text-white"><?= $tardanzasHoy ?></h3>
                            <p class="font-weight-bold text-white mb-0">Llegadas Tarde Hoy</p>
                            <small class="text-white"><?= (int)($statsHoy['total_minutos_tardanza'] ?? 0) ?> min acumulados</small>
                        </div>
                        <div class="icon"><i class="fa-solid fa-person-walking-arrow-right text-white"></i></div>
                        <a href="?route=asistencia&fecha_inicio=<?= $today ?>&fecha_fin=<?= $today ?>&estado=TARDANZA" class="small-box-footer text-white">Ver tardanzas <i class="fas fa-arrow-circle-right ml-1"></i></a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-gradient-danger shadow-sm">
                        <div class="inner">
                            <h3><?= (int)($statsHoy['faltas'] ?? 0) ?></h3>
                            <p class="font-weight-bold mb-0">Inasistencias del Turno</p>
                            <small>Sin registro de entrada</small>
                        </div>
                        <div class="icon"><i class="fa-solid fa-user-slash"></i></div>
                        <a href="?route=asistencia&fecha_inicio=<?= $today ?>&fecha_fin=<?= $today ?>&estado=FALTA" class="small-box-footer">Ver ausentes <i class="fas fa-arrow-circle-right ml-1"></i></a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-gradient-info shadow-sm">
                        <div class="inner">
                            <h3><?= (int)($statsHoy['sin_salida'] ?? 0) ?></h3>
                            <p class="font-weight-bold mb-0">Sin Salida Registrada</p>
                            <small>Jornada en curso / pendiente</small>
                        </div>
                        <div class="icon"><i class="fa-solid fa-clock"></i></div>
                        <a href="?route=asistencia&fecha_inicio=<?= $today ?>&fecha_fin=<?= $today ?>&estado=SALIDA_SIN_MARCAR" class="small-box-footer">Ver pendientes <i class="fas fa-arrow-circle-right ml-1"></i></a>
                    </div>
                </div>
            </div>

            <!-- FEED EN VIVO Y CUMPLIMIENTO OPERATIVO -->
            <div class="row">
                <!-- Feed en Vivo -->
                <div class="col-lg-7">
                    <div class="card card-outline card-success shadow-sm mb-3">
                        <div class="card-header border-0 d-flex justify-content-between align-items-center">
                            <h3 class="card-title font-weight-bold text-dark">
                                <span class="badge badge-success px-2 py-1 mr-2" style="animation: pulse 1.5s infinite;">
                                    <i class="fa-solid fa-satellite-dish mr-1"></i> EN VIVO
                                </span>
                                Marcaciones en Vivo del Personal
                            </h3>
                            <div class="card-tools">
                                <a href="?route=marcaciones&fecha=<?= $today ?>" class="btn btn-tool btn-sm">
                                    <i class="fas fa-list mr-1"></i> Ver todas
                                </a>
                            </div>
                        </div>
                        <div class="card-body table-responsive p-0">
                            <table class="table table-hover table-striped mb-0 table-sm">
                                <thead class="thead-light">
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
                                                    <?= htmlspecialchars($m['dispositivo_nombre'] ?? 'Reloj') ?>
                                                </td>
                                                <td>
                                                    <?php
                                                        $tipo = strtolower($m['tipo'] ?? '');
                                                        if ($tipo === 'entrada') echo '<span class="badge badge-success px-2 py-1"><i class="fa-solid fa-arrow-right-to-bracket mr-1"></i>Entrada</span>';
                                                        elseif ($tipo === 'salida') echo '<span class="badge badge-primary px-2 py-1"><i class="fa-solid fa-arrow-right-from-bracket mr-1"></i>Salida</span>';
                                                        elseif (str_contains($tipo, 'refrigerio')) echo '<span class="badge badge-info px-2 py-1"><i class="fa-solid fa-utensils mr-1"></i>Refrigerio</span>';
                                                        else echo '<span class="badge badge-secondary px-2 py-1">Marcación</span>';
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
                <div class="col-lg-5">
                    <div class="card card-outline card-warning shadow-sm mb-3">
                        <div class="card-header border-0">
                            <h3 class="card-title font-weight-bold text-dark">
                                <i class="fa-solid fa-triangle-exclamation mr-1 text-warning"></i> Mayores Tardanzas de Hoy
                            </h3>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($topTardanzasHoy)): ?>
                                <div class="text-center py-3 text-muted small">
                                    <i class="fa-solid fa-circle-check text-success fa-2x mb-1 d-block"></i>
                                    ¡Cero tardanzas registradas el día de hoy!
                                </div>
                            <?php else: ?>
                                <ul class="products-list product-list-in-card pl-2 pr-2">
                                    <?php foreach ($topTardanzasHoy as $t): ?>
                                        <li class="item d-flex justify-content-between align-items-center p-2 border-bottom">
                                            <div>
                                                <span class="font-weight-bold text-dark"><?= htmlspecialchars($t['apellidos'] . ' ' . $t['nombres']) ?></span>
                                                <span class="product-description text-muted small">
                                                    <?= htmlspecialchars($t['depto_nombre'] ?? 'Área General') ?> &bull; Ingreso: <?= substr($t['hora_entrada_real'], 11, 5) ?>
                                                </span>
                                            </div>
                                            <span class="badge badge-warning font-weight-bold text-white px-2 py-1">+<?= $t['minutos_tardanza'] ?> min</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- =================================================================== -->
            <!-- VISTA EXCLUSIVA: SOLO CONSULTA / AUDITORÍA                           -->
            <!-- =================================================================== -->
            <div class="row">
                <div class="col-lg-4 col-6">
                    <div class="small-box bg-gradient-success shadow-sm">
                        <div class="inner">
                            <h3><?= $presentesHoy ?></h3>
                            <p class="font-weight-bold mb-0">Total Presentes Hoy</p>
                        </div>
                        <div class="icon"><i class="fa-solid fa-user-check"></i></div>
                    </div>
                </div>
                <div class="col-lg-4 col-6">
                    <div class="small-box bg-gradient-warning shadow-sm">
                        <div class="inner">
                            <h3 class="text-white"><?= $tardanzasHoy ?></h3>
                            <p class="font-weight-bold text-white mb-0">Tardanzas Hoy</p>
                        </div>
                        <div class="icon"><i class="fa-solid fa-clock-rotate-left text-white"></i></div>
                    </div>
                </div>
                <div class="col-lg-4 col-12">
                    <div class="small-box bg-gradient-danger shadow-sm">
                        <div class="inner">
                            <h3><?= (int)($statsHoy['faltas'] ?? 0) ?></h3>
                            <p class="font-weight-bold mb-0">Faltas Registradas</p>
                        </div>
                        <div class="icon"><i class="fa-solid fa-user-slash"></i></div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-8">
                    <div class="card card-outline card-primary shadow-sm mb-3">
                        <div class="card-header border-0">
                            <h3 class="card-title font-weight-bold text-dark"><i class="fa-solid fa-chart-line mr-1 text-primary"></i> Tendencia de Asistencia (Últimos 7 Días)</h3>
                        </div>
                        <div class="card-body pt-0">
                            <div style="height: 250px; position: relative;">
                                <canvas id="tendenciaChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card card-outline card-info shadow-sm mb-3">
                        <div class="card-header border-0">
                            <h3 class="card-title font-weight-bold text-dark"><i class="fa-solid fa-chart-pie mr-1 text-info"></i> Distribución de Hoy</h3>
                        </div>
                        <div class="card-body pt-0">
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
    const ctxTendencia = document.getElementById('tendenciaChart').getContext('2d');
    new Chart(ctxTendencia, {
        type: 'line',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [
                {
                    label: 'Presentes',
                    data: <?= json_encode($chartPresentes) ?>,
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.15)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3
                },
                {
                    label: 'Tardanzas',
                    data: <?= json_encode($chartTardanzas) ?>,
                    borderColor: '#ffc107',
                    backgroundColor: 'rgba(255, 193, 7, 0.15)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3
                },
                {
                    label: 'Faltas',
                    data: <?= json_encode($chartFaltas) ?>,
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.15)',
                    borderWidth: 2,
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
                    labels: { boxWidth: 12, font: { size: 12 } }
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

    // 2. Gráfico de Dona: Distribución Hoy
    const ctxDist = document.getElementById('distribucionChart').getContext('2d');
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
                backgroundColor: ['#28a745', '#ffc107', '#dc3545', '#007bff', '#6c757d'],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            cutout: '70%'
        }
    });
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

