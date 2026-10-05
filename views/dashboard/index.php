<?php require_once APP_ROOT . '/views/layout/header.php'; ?>

<!-- Content Header (Page header) -->
<div class="content-header pb-1 pt-2">
    <div class="container-fluid">
        <!-- Banner Institucional JUSHSAL Compacto -->
        <div class="banner-jushsal">
            <div class="d-flex align-items-center">
                <img src="<?= jushsal_logo_data_uri('icon') ?: asset('img/logo_icon.png') ?>" alt="JUSHSAL" class="mr-2" style="width: 34px; height: 34px; object-fit: contain;">
                <div>
                    <div class="font-weight-bold text-dark" style="font-size: 0.95rem; letter-spacing: 0.2px;">
                        JUSHSAL <span class="font-weight-normal text-muted small d-none d-md-inline">&bull; Junta de Usuarios del Sector Hidráulico Menor San Lorenzo</span>
                    </div>
                    <div class="text-secondary small" style="font-size: 0.76rem; line-height: 1.1;">
                        Sistema Integral de Control de Personal y Asistencia Laboral
                    </div>
                </div>
            </div>
            <div class="d-none d-lg-flex align-items-center text-muted small">
                <span class="badge-pill-custom badge-pill-neutral mr-2" style="font-size: 0.72rem;"><i class="fa-solid fa-droplet text-primary mr-1"></i> San Lorenzo</span>
                <span class="badge-pill-custom badge-pill-neutral" style="font-size: 0.72rem;"><i class="fa-regular fa-clock mr-1 text-primary"></i> <?= date('d/m/Y') ?></span>
            </div>
        </div>

        <div class="row mb-2 align-items-center">
            <div class="col-md-7 col-sm-12 mb-1 mb-md-0">
                <h1 class="m-0 font-weight-bold text-dark" style="font-size: 1.25rem;">
                    <?php if ($activeRoleView === 'ADMIN'): ?>
                        <i class="fa-solid fa-server mr-2 text-primary"></i> Panel de Administración
                    <?php elseif ($activeRoleView === 'RRHH'): ?>
                        <i class="fa-solid fa-users-gear mr-2 text-primary"></i> Panel de Recursos Humanos
                    <?php elseif ($activeRoleView === 'SUPERVISOR'): ?>
                        <i class="fa-solid fa-user-tie mr-2 text-primary"></i> Panel de Supervisión
                    <?php elseif ($activeRoleView === 'ASISTENTE'): ?>
                        <i class="fa-solid fa-clipboard-user mr-2 text-primary"></i> Panel de Asistente
                    <?php elseif (in_array($activeRoleView, ['USER', 'USUARIO'], true)): ?>
                        <i class="fa-solid fa-chart-pie mr-2 text-primary"></i> Panel de Usuario
                    <?php else: ?>
                        <i class="fa-solid fa-chart-pie mr-2 text-primary"></i> Panel Principal
                    <?php endif; ?>
                </h1>
                <div class="text-muted small" style="font-size: 0.78rem;">
                    <?php if ($activeRoleView === 'ADMIN'): ?>
                        Monitoreo biométrico, sincronización y estado del sistema.
                    <?php elseif ($activeRoleView === 'RRHH'): ?>
                        Puntualidad, ausentismo y horas trabajadas del personal.
                    <?php elseif ($activeRoleView === 'SUPERVISOR'): ?>
                        Control en tiempo real del personal en turno.
                    <?php elseif ($activeRoleView === 'ASISTENTE'): ?>
                        Control de asistencia diaria, permisos y justificaciones del personal.
                    <?php elseif (in_array($activeRoleView, ['USER', 'USUARIO'], true)): ?>
                        Consulta general de asistencias, puntualidad y marcaciones.
                    <?php else: ?>
                        Resumen general de asistencia y métricas del personal.
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-5 col-sm-12 text-md-right mt-1 mt-md-0">
                <!-- Botones de Acción Rápida Específicos por Rol -->
                <?php if ($activeRoleView === 'ADMIN'): ?>
                    <a href="?route=dispositivos" class="btn btn-xs btn-outline-secondary mr-1">
                        <i class="fa-solid fa-network-wired mr-1"></i> Biométricos
                    </a>
                    <button type="button" class="btn btn-xs btn-primary" onclick="openSyncModal()">
                        <i class="fa-solid fa-arrows-rotate mr-1"></i> Sincronizar
                    </button>
                <?php elseif ($activeRoleView === 'RRHH'): ?>
                    <a href="?route=justificaciones" class="btn btn-xs btn-outline-primary mr-1">
                        <i class="fa-solid fa-file-signature mr-1"></i> Justificaciones (<?= $justificacionesPendientes ?>)
                    </a>
                    <button type="button" class="btn btn-xs btn-primary" onclick="openSyncModal()">
                        <i class="fa-solid fa-bolt mr-1"></i> Sincronizar
                    </button>
                <?php elseif ($activeRoleView === 'SUPERVISOR'): ?>
                    <a href="?route=asistencia&fecha_inicio=<?= $today ?>&fecha_fin=<?= $today ?>" class="btn btn-xs btn-primary">
                        <i class="fa-solid fa-list-check mr-1"></i> Asistencia de Hoy
                    </a>
                <?php elseif ($activeRoleView === 'ASISTENTE'): ?>
                    <a href="?route=asistencia&fecha_inicio=<?= $today ?>&fecha_fin=<?= $today ?>" class="btn btn-xs btn-primary mr-1">
                        <i class="fa-solid fa-calendar-check mr-1"></i> Asistencia de Hoy
                    </a>
                    <a href="?route=justificaciones" class="btn btn-xs btn-outline-primary">
                        <i class="fa-solid fa-file-signature mr-1"></i> Justificaciones
                    </a>
                <?php else: ?>
                    <a href="?route=asistencia&fecha_inicio=<?= $today ?>&fecha_fin=<?= $today ?>" class="btn btn-xs btn-primary">
                        <i class="fa-solid fa-calendar-check mr-1"></i> Ver Asistencia de Hoy
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">

        <?php 
            // Métricas calculadas para porcentajes del resumen de hoy
            $totalHoyConteo = $presentesHoy + $tardanzasHoy + (int)($statsHoy['faltas'] ?? 0) + (int)($statsHoy['justificados'] ?? 0) + (int)($statsHoy['sin_salida'] ?? 0);
            $baseTotal = $totalHoyConteo > 0 ? $totalHoyConteo : ($totalEmpleados > 0 ? $totalEmpleados : 1);
            $porcPresentes = round(($presentesHoy / $baseTotal) * 100, 1);
            $porcTardanzas = round(($tardanzasHoy / $baseTotal) * 100, 1);
            $porcFaltas = round(((int)($statsHoy['faltas'] ?? 0) / $baseTotal) * 100, 1);
            $porcJustificados = round(((int)($statsHoy['justificados'] ?? 0) / $baseTotal) * 100, 1);
            $porcSinSalida = round(((int)($statsHoy['sin_salida'] ?? 0) / $baseTotal) * 100, 1);
            $tasaAsistenciaHoy = round((($presentesHoy + $tardanzasHoy) / $baseTotal) * 100);
        ?>

        <?php if ($activeRoleView === 'ADMIN'): ?>
            <!-- =================================================================== -->
            <!-- VISTA: ADMINISTRADOR                                                -->
            <!-- =================================================================== -->
            
            <!-- TARJETAS KPIS TI (Compactas en una sola fila) -->
            <div class="row">
                <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-2">
                    <div class="kpi-card h-100">
                        <div class="kpi-card-header">
                            <div>
                                <div class="kpi-title">Biométricos En Línea</div>
                                <div class="kpi-value"><?= $dispositivosOnline ?> <span style="font-size: 0.95rem; color: #64748b; font-weight: 600;">de <?= count($dispositivos) ?></span></div>
                                <div class="kpi-subtitle"><?= $dispositivosOnline === count($dispositivos) ? 'Todas las terminales operativas' : 'Revisar terminales inactivas' ?></div>
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

                <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-2">
                    <div class="kpi-card h-100">
                        <div class="kpi-card-header">
                            <div>
                                <div class="kpi-title">Efectividad Sync</div>
                                <div class="kpi-value"><?= $tasaExitoSync ?>%</div>
                                <div class="kpi-subtitle"><?= (int)($syncStats['exitos'] ?? 0) ?> exitosos de <?= (int)($syncStats['total_syncs'] ?? 0) ?> ciclos</div>
                            </div>
                            <div class="kpi-icon-box kpi-icon-blue">
                                <i class="fa-solid fa-circle-check"></i>
                            </div>
                        </div>
                        <div>
                            <a href="?route=dispositivos" class="kpi-footer-link">
                                Ver auditoría <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-2">
                    <div class="kpi-card h-100">
                        <div class="kpi-card-header">
                            <div>
                                <div class="kpi-title">Marcaciones de Hoy</div>
                                <div class="kpi-value"><?= $totalMarcacionesHoy ?></div>
                                <div class="kpi-subtitle">Registros descargados en BDD</div>
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

                <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-2">
                    <div class="kpi-card h-100">
                        <div class="kpi-card-header">
                            <div>
                                <div class="kpi-title">Latencia Promedio</div>
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

            <!-- FILA 2 TI: GRID BALANCEADO EN 2 COLUMNAS (TODOS LOS ELEMENTOS EN UNA PANTALLA) -->
            <div class="row">
                <!-- COLUMNA IZQUIERDA (7 COLS): Terminales Biométricas & Feed de Marcaciones en Vivo -->
                <div class="col-lg-7 mb-2">
                    <!-- Relojes Biométricos -->
                    <div class="card mb-2">
                        <div class="card-header d-flex justify-content-between align-items-center py-2 px-3">
                            <h3 class="card-title font-weight-bold" style="font-size: 0.88rem;">
                                <i class="fa-solid fa-server mr-2 text-primary"></i> Terminales Biométricas en Red
                            </h3>
                            <div class="card-tools">
                                <a href="?route=dispositivos" class="btn btn-xs btn-outline-secondary">
                                    <i class="fa-solid fa-gear mr-1"></i> Administrar
                                </a>
                            </div>
                        </div>
                        <div class="card-body p-0" style="max-height: 200px; overflow-y: auto; overflow-x: hidden;">
                            <table class="table table-hover table-sm mb-0" style="width: 100%; table-layout: fixed;">
                                <thead>
                                    <tr>
                                        <th style="width: 32%;">Dispositivo</th>
                                        <th style="width: 30%;">IP y Puerto</th>
                                        <th style="width: 20%;">Ubicación</th>
                                        <th class="text-center" style="width: 18%;">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($dispositivos)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-3 text-muted small">No hay biométricos registrados en el sistema.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($dispositivos as $d): ?>
                                            <tr>
                                                <td class="font-weight-bold text-dark py-1 text-truncate" title="<?= htmlspecialchars($d['nombre']) ?>">
                                                    <i class="fa-solid fa-fingerprint text-primary mr-1"></i>
                                                    <?= htmlspecialchars($d['nombre']) ?>
                                                </td>
                                                <td class="font-monospace small py-1 text-truncate">
                                                    <?= htmlspecialchars($d['ip']) ?>:<?= $d['puerto'] ?>
                                                    <span class="badge-pill-custom badge-pill-neutral ml-1" style="font-size: 0.68rem;"><?= htmlspecialchars($d['protocolo']) ?></span>
                                                </td>
                                                <td class="small text-muted py-1 text-truncate" title="<?= htmlspecialchars($d['ubicacion'] ?? 'Sede Principal') ?>">
                                                    <?= htmlspecialchars($d['ubicacion'] ?? 'Sede Principal') ?>
                                                </td>
                                                <td class="text-center py-1">
                                                    <?php if ($d['estado_conexion'] === 'ONLINE'): ?>
                                                        <span class="badge-pill-custom badge-pill-online" style="font-size: 0.7rem;"><i class="fa-solid fa-circle" style="font-size: 5px;"></i> En Línea</span>
                                                    <?php else: ?>
                                                        <span class="badge-pill-custom badge-pill-offline" style="font-size: 0.7rem;"><i class="fa-solid fa-circle" style="font-size: 5px;"></i> Desconectado</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Feed en Vivo de Marcaciones -->
                    <div class="card mb-2">
                        <div class="card-header d-flex justify-content-between align-items-center py-2 px-3">
                            <h3 class="card-title font-weight-bold" style="font-size: 0.88rem;">
                                <span class="badge-pill-custom badge-pill-online mr-2" style="font-size: 0.68rem;">
                                    <i class="fa-solid fa-circle" style="font-size: 5px;"></i> EN VIVO
                                </span>
                                Últimas Marcaciones Recibidas
                            </h3>
                            <div class="card-tools">
                                <a href="?route=marcaciones&fecha=<?= $today ?>" class="btn btn-xs btn-outline-secondary">
                                    <i class="fas fa-list mr-1"></i> Ver todas
                                </a>
                            </div>
                        </div>
                        <div class="card-body p-0" style="max-height: 220px; overflow-y: auto; overflow-x: hidden;">
                            <table class="table table-hover table-sm mb-0" style="width: 100%; table-layout: fixed;">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="width: 18%;">Hora</th>
                                        <th style="width: 38%;">Empleado e ID</th>
                                        <th style="width: 24%;">Punto de Control</th>
                                        <th class="text-center" style="width: 20%;">Tipo Marcación</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($ultimasMarcaciones)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-3 text-muted small">
                                                <i class="fa-regular fa-clock mr-1 text-secondary"></i>
                                                Aún no hay marcaciones registradas para el día de hoy.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($ultimasMarcaciones as $m): ?>
                                            <tr>
                                                <td class="text-center font-weight-bold font-monospace text-secondary py-1" style="font-size: 0.78rem;">
                                                    <i class="far fa-clock mr-1 text-muted"></i>
                                                    <?= substr($m['fecha_hora'], 11, 8) ?>
                                                </td>
                                                <td class="py-1 text-truncate">
                                                    <?php if (!empty($m['nombres'])): ?>
                                                        <div class="font-weight-bold text-dark text-truncate" style="font-size: 0.82rem;" title="<?= htmlspecialchars($m['apellidos'] . ' ' . $m['nombres']) ?>"><?= htmlspecialchars($m['apellidos'] . ' ' . $m['nombres']) ?></div>
                                                        <small class="text-muted" style="font-size: 0.72rem;">ID Reloj: <?= htmlspecialchars($m['codigo_reloj']) ?></small>
                                                    <?php else: ?>
                                                        <span class="badge-pill-custom badge-pill-neutral" style="font-size: 0.7rem;">ID Reloj: <?= htmlspecialchars($m['codigo_reloj']) ?> (Sin vincular)</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="py-1 text-truncate" title="<?= htmlspecialchars($m['dispositivo_nombre'] ?? 'Reloj') ?>">
                                                    <span class="text-muted small" style="font-size: 0.78rem;">
                                                        <?php
                                                            $v = strtolower($m['tipo_verificacion'] ?? '');
                                                            if (str_contains($v, 'facial') || str_contains($v, 'face')) {
                                                                echo '<i class="fa-solid fa-camera text-info mr-1" title="Reconocimiento Facial"></i>';
                                                            } elseif (str_contains($v, 'tarjeta') || str_contains($v, 'card')) {
                                                                echo '<i class="fa-solid fa-id-card text-success mr-1" title="Tarjeta RFID"></i>';
                                                            } elseif (str_contains($v, 'clave') || str_contains($v, 'pin')) {
                                                                echo '<i class="fa-solid fa-key text-warning mr-1" title="Contraseña o PIN"></i>';
                                                            } else {
                                                                echo '<i class="fa-solid fa-fingerprint text-primary mr-1" title="Huella Dactilar"></i>';
                                                            }
                                                        ?>
                                                        <?= htmlspecialchars($m['dispositivo_nombre'] ?? 'Reloj') ?>
                                                    </span>
                                                </td>
                                                <td class="text-center py-1">
                                                    <?php
                                                        $tipo = strtolower($m['tipo'] ?? '');
                                                        if ($tipo === 'entrada') echo '<span class="badge-pill-custom badge-pill-presente" style="font-size: 0.7rem;"><i class="fa-solid fa-arrow-right-to-bracket mr-1"></i> Entrada</span>';
                                                        elseif ($tipo === 'salida') echo '<span class="badge-pill-custom badge-pill-justificado" style="font-size: 0.7rem;"><i class="fa-solid fa-arrow-right-from-bracket mr-1"></i> Salida</span>';
                                                        elseif (str_contains($tipo, 'refrigerio')) echo '<span class="badge-pill-custom badge-pill-neutral" style="font-size: 0.7rem;"><i class="fa-solid fa-utensils mr-1"></i> Refrigerio</span>';
                                                        else echo '<span class="badge-pill-custom badge-pill-neutral" style="font-size: 0.7rem;">Marcación</span>';
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

                <!-- COLUMNA DERECHA (5 COLS): Resumen Asistencia Hoy (Gráfico Enriquecido) & Auditoría Sync -->
                <div class="col-lg-5 mb-2">
                    <!-- Resumen de Asistencia Hoy (GRÁFICO POTENCIADO) -->
                    <div class="card mb-2">
                        <div class="card-header d-flex justify-content-between align-items-center py-2 px-3">
                            <h3 class="card-title font-weight-bold" style="font-size: 0.88rem;">
                                <i class="fa-solid fa-chart-pie mr-2 text-primary"></i> Resumen de Asistencia Hoy
                            </h3>
                            <span class="badge-pill-custom badge-pill-neutral" style="font-size: 0.7rem;">
                                <i class="fa-solid fa-users mr-1 text-primary"></i> <?= $totalHoyConteo ?> marcados
                            </span>
                        </div>
                        <div class="card-body p-3">
                            <!-- Canvas con donut chart -->
                            <div style="height: 165px; position: relative;" class="mb-2">
                                <canvas id="distribucionChart"></canvas>
                            </div>

                            <!-- Desglose de Métricas Claro y Detallado con Porcentajes -->
                            <div class="pt-2 border-top">
                                <div class="d-flex justify-content-between align-items-center py-1 border-bottom" style="font-size: 0.79rem;">
                                    <span class="text-dark"><i class="fa-solid fa-circle text-success mr-1" style="font-size: 8px;"></i> <strong>Presentes</strong> (A tiempo)</span>
                                    <span class="font-weight-bold text-dark"><?= $presentesHoy ?> <span class="badge-pill-custom badge-pill-presente ml-1" style="font-size: 0.7rem;"><?= $porcPresentes ?>%</span></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center py-1 border-bottom" style="font-size: 0.79rem;">
                                    <span class="text-dark"><i class="fa-solid fa-circle text-warning mr-1" style="font-size: 8px;"></i> <strong>Tardanzas</strong></span>
                                    <span class="font-weight-bold text-dark"><?= $tardanzasHoy ?> <span class="badge-pill-custom badge-pill-tardanza ml-1" style="font-size: 0.7rem;"><?= $porcTardanzas ?>%</span></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center py-1 border-bottom" style="font-size: 0.79rem;">
                                    <span class="text-dark"><i class="fa-solid fa-circle text-danger mr-1" style="font-size: 8px;"></i> <strong>Inasistencias</strong> (Faltas)</span>
                                    <span class="font-weight-bold text-dark"><?= (int)($statsHoy['faltas'] ?? 0) ?> <span class="badge-pill-custom badge-pill-falta ml-1" style="font-size: 0.7rem;"><?= $porcFaltas ?>%</span></span>
                                </div>
                                <?php if (((int)($statsHoy['justificados'] ?? 0) > 0) || ((int)($statsHoy['sin_salida'] ?? 0) > 0)): ?>
                                    <div class="d-flex justify-content-between align-items-center py-1" style="font-size: 0.79rem;">
                                        <span class="text-secondary"><i class="fa-solid fa-circle text-primary mr-1" style="font-size: 8px;"></i> Justificados o En Curso</span>
                                        <span class="font-weight-bold text-dark"><?= (int)($statsHoy['justificados'] ?? 0) + (int)($statsHoy['sin_salida'] ?? 0) ?> <span class="badge-pill-custom badge-pill-neutral ml-1" style="font-size: 0.7rem;"><?= round($porcJustificados + $porcSinSalida, 1) ?>%</span></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Historial Reciente de Sincronización -->
                    <div class="card mb-2">
                        <div class="card-header d-flex justify-content-between align-items-center py-2 px-3">
                            <h3 class="card-title font-weight-bold" style="font-size: 0.88rem;">
                                <i class="fa-solid fa-clock-rotate-left mr-2 text-primary"></i> Auditoría de Sincronización
                            </h3>
                            <span class="badge-pill-custom badge-pill-neutral" style="font-size: 0.68rem;">Últimos 5 ciclos</span>
                        </div>
                        <div class="card-body p-0" style="overflow: hidden;">
                            <table class="table table-hover table-sm mb-0" style="width: 100%; table-layout: fixed;">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="width: 18%; padding: 6px 4px;">Hora</th>
                                        <th style="width: 32%; padding: 6px 4px;">Dispositivo</th>
                                        <th class="text-center" style="width: 16%; padding: 6px 4px;">Nuevos</th>
                                        <th class="text-center" style="width: 16%; padding: 6px 4px;">Duración</th>
                                        <th class="text-center" style="width: 18%; padding: 6px 4px;">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($ultimosLogsSync)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-3 text-muted small">Sin registros de sincronización recientes.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($ultimosLogsSync as $log): ?>
                                            <tr>
                                                <td class="text-center font-monospace small text-secondary py-1" style="font-size: 0.74rem; padding: 5px 4px;">
                                                    <?= substr($log['fecha_hora'], 11, 5) ?>
                                                </td>
                                                <td class="small font-weight-bold text-dark py-1 text-truncate" style="padding: 5px 4px;" title="<?= htmlspecialchars($log['dispositivo_nombre'] ?? 'Reloj') ?>">
                                                    <?= htmlspecialchars($log['dispositivo_nombre'] ?? 'Reloj') ?>
                                                </td>
                                                <td class="text-center font-weight-bold text-success py-1" style="font-size: 0.76rem; padding: 5px 4px;">
                                                    +<?= (int)$log['total_insertados'] ?>
                                                </td>
                                                <td class="text-center font-monospace small text-muted py-1" style="font-size: 0.74rem; padding: 5px 4px;">
                                                    <?= round((float)$log['duracion_segundos'], 1) ?>s
                                                </td>
                                                <td class="text-center py-1" style="padding: 5px 4px;">
                                                    <?php if ($log['estado'] === 'EXITO'): ?>
                                                        <span class="badge-pill-custom badge-pill-presente" style="font-size: 0.67rem; padding: 2px 5px;"><i class="fa-solid fa-check mr-1"></i>Éxito</span>
                                                    <?php else: ?>
                                                        <span class="badge-pill-custom badge-pill-falta" style="font-size: 0.67rem; padding: 2px 5px;" title="<?= htmlspecialchars($log['mensaje_error'] ?? '') ?>"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Error</span>
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

        <?php elseif ($activeRoleView === 'RRHH'): ?>
            <!-- =================================================================== -->
            <!-- VISTA: RECURSOS HUMANOS (ANALÍTICA, NÓMINA & CUMPLIMIENTO)          -->
            <!-- =================================================================== -->

            <!-- TARJETAS KPIS RRHH -->
            <div class="row">
                <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-2">
                    <div class="kpi-card h-100">
                        <div class="kpi-card-header">
                            <div>
                                <div class="kpi-title">Tasa de Puntualidad Hoy</div>
                                <div class="kpi-value"><?= $puntualidadHoyPorc ?>%</div>
                                <div class="kpi-subtitle"><?= $presentesHoy ?> de <?= $totalEmpleados ?> trabajadores a tiempo</div>
                            </div>
                            <div class="kpi-icon-box">
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

                <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-2">
                    <div class="kpi-card h-100">
                        <div class="kpi-card-header">
                            <div>
                                <div class="kpi-title">Tardanzas de Hoy</div>
                                <div class="kpi-value"><?= (int)($statsHoy['tardanzas'] ?? 0) ?></div>
                                <div class="kpi-subtitle"><?= (int)($statsHoy['total_minutos_tardanza'] ?? 0) ?> min acumulados hoy</div>
                            </div>
                            <div class="kpi-icon-box">
                                <i class="fa-solid fa-clock-rotate-left"></i>
                            </div>
                        </div>
                        <div>
                            <a href="?route=asistencia&fecha_inicio=<?= $today ?>&fecha_fin=<?= $today ?>&estado=TARDANZA" class="kpi-footer-link">
                                Ver tardanzas <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-2">
                    <div class="kpi-card h-100">
                        <div class="kpi-card-header">
                            <div>
                                <div class="kpi-title">Inasistencias Hoy</div>
                                <div class="kpi-value"><?= (int)($statsHoy['faltas'] ?? 0) ?></div>
                                <div class="kpi-subtitle"><?= $justificacionesPendientes ?> justificaciones pendientes</div>
                            </div>
                            <div class="kpi-icon-box">
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

                <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-2">
                    <div class="kpi-card h-100">
                        <div class="kpi-card-header">
                            <div>
                                <div class="kpi-title">Personal Activo</div>
                                <div class="kpi-value"><?= $totalEmpleados ?></div>
                                <div class="kpi-subtitle">Trabajadores en padrón activo</div>
                            </div>
                            <div class="kpi-icon-box">
                                <i class="fa-solid fa-users"></i>
                            </div>
                        </div>
                        <div>
                            <a href="?route=empleados" class="kpi-footer-link">
                                Ver directorio <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- GRÁFICOS Y ANÁLISIS RRHH (2 COLUMNAS BALANCEADAS) -->
            <div class="row">
                <div class="col-lg-7 mb-2">
                    <!-- Tendencia de Asistencia con Filtro y Leyenda de Datos -->
                    <div class="card mb-2 shadow-sm">
                        <div class="card-header d-flex flex-wrap justify-content-between align-items-center py-2 px-3 gap-2">
                            <div class="d-flex align-items-center">
                                <h3 class="card-title font-weight-bold mb-0" style="font-size: 0.88rem;">
                                    <i class="fa-solid fa-chart-line mr-2 text-primary"></i> Tendencia de Asistencia
                                </h3>
                            </div>

                            <!-- CONTROLES DE FILTRO -->
                            <div class="d-flex align-items-center flex-wrap" style="gap: 6px;">
                                <!-- Filtro Departamento -->
                                <div class="input-group input-group-sm" style="width: auto;">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light text-muted border-right-0 py-0" style="font-size: 0.72rem; padding: 2px 6px;">
                                            <i class="fa-solid fa-building text-primary"></i>
                                        </span>
                                    </div>
                                    <select id="filtro-tendencia-depto" class="form-control form-control-sm border-left-0 py-0" style="font-size: 0.75rem; height: 27px;" onchange="filtrarTendencia()">
                                        <option value="all">Todas las Áreas</option>
                                        <?php if (!empty($listaDepartamentos)): ?>
                                            <?php foreach ($listaDepartamentos as $dep): ?>
                                                <option value="<?= $dep['id'] ?>"><?= htmlspecialchars($dep['nombre']) ?></option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <!-- Filtro Rango de Tiempo -->
                                <div class="input-group input-group-sm" style="width: auto;">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light text-muted border-right-0 py-0" style="font-size: 0.72rem; padding: 2px 6px;">
                                            <i class="fa-regular fa-calendar text-primary"></i>
                                        </span>
                                    </div>
                                    <select id="filtro-tendencia-periodo" class="form-control form-control-sm border-left-0 py-0 font-weight-bold" style="font-size: 0.75rem; height: 27px;" onchange="filtrarTendencia()">
                                        <option value="7d" selected>Últimos 7 días</option>
                                        <option value="15d">Últimos 15 días</option>
                                        <option value="30d">Últimos 30 días</option>
                                        <option value="mes_actual">Este Mes</option>
                                        <option value="mes_anterior">Mes Anterior</option>
                                    </select>
                                </div>

                                <!-- Spinner AJAX -->
                                <div id="tendencia-loading" class="spinner-border spinner-border-sm text-primary ml-1" role="status" style="display: none; width: 0.9rem; height: 0.9rem;">
                                    <span class="sr-only">Cargando...</span>
                                </div>
                            </div>
                        </div>

                        <!-- ÚNICA LEYENDA DINÁMICA E INTERACTIVA (CLICKABLE PARA OCULTAR/MOSTRAR SERIES) -->
                        <div class="px-3 py-1.5 bg-light border-bottom d-flex flex-wrap justify-content-between align-items-center" style="font-size: 0.76rem; background-color: #f8fafc !important;">
                            <div class="d-flex flex-wrap align-items-center" style="gap: 14px;">
                                <span class="d-inline-flex align-items-center legend-item-btn" id="legend-item-presentes" onclick="toggleTendenciaDataset(0)" title="Clic para ocultar o mostrar 'Presentes' en el gráfico" style="cursor: pointer; user-select: none; transition: all 0.2s ease;">
                                    <span style="width: 10px; height: 10px; border-radius: 2px; display: inline-block; background-color: #10b981; margin-right: 5px;"></span>
                                    <span class="text-muted mr-1">Presentes:</span>
                                    <strong class="text-success" id="leyenda-total-presentes"><?= $totalesTendencia['presentes'] ?? 0 ?></strong>
                                    <span class="badge badge-light border text-success ml-1 font-weight-bold" id="leyenda-porc-presentes" style="font-size: 0.68rem;"><?= $totalesTendencia['porc_presentes'] ?? 0 ?>%</span>
                                </span>

                                <span class="d-inline-flex align-items-center legend-item-btn" id="legend-item-tardanzas" onclick="toggleTendenciaDataset(1)" title="Clic para ocultar o mostrar 'Tardanzas' en el gráfico" style="cursor: pointer; user-select: none; transition: all 0.2s ease;">
                                    <span style="width: 10px; height: 10px; border-radius: 2px; display: inline-block; background-color: #f59e0b; margin-right: 5px;"></span>
                                    <span class="text-muted mr-1">Tardanzas:</span>
                                    <strong class="text-warning" id="leyenda-total-tardanzas"><?= $totalesTendencia['tardanzas'] ?? 0 ?></strong>
                                    <span class="badge badge-light border text-warning ml-1 font-weight-bold" id="leyenda-porc-tardanzas" style="font-size: 0.68rem;"><?= $totalesTendencia['porc_tardanzas'] ?? 0 ?>%</span>
                                </span>

                                <span class="d-inline-flex align-items-center legend-item-btn" id="legend-item-faltas" onclick="toggleTendenciaDataset(2)" title="Clic para ocultar o mostrar 'Faltas' en el gráfico" style="cursor: pointer; user-select: none; transition: all 0.2s ease;">
                                    <span style="width: 10px; height: 10px; border-radius: 2px; display: inline-block; background-color: #f43f5e; margin-right: 5px;"></span>
                                    <span class="text-muted mr-1">Faltas:</span>
                                    <strong class="text-danger" id="leyenda-total-faltas"><?= $totalesTendencia['faltas'] ?? 0 ?></strong>
                                    <span class="badge badge-light border text-danger ml-1 font-weight-bold" id="leyenda-porc-faltas" style="font-size: 0.68rem;"><?= $totalesTendencia['porc_faltas'] ?? 0 ?>%</span>
                                </span>
                            </div>

                            <div class="text-secondary small">
                                Total: <strong class="text-dark font-weight-bold" id="leyenda-total-general"><?= $totalesTendencia['total'] ?? 0 ?></strong>
                            </div>
                        </div>

                        <div class="card-body p-3">
                            <div style="height: 195px; position: relative;">
                                <canvas id="tendenciaChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Cumplimiento por Áreas -->
                    <div class="card mb-2">
                        <div class="card-header py-2 px-3">
                            <h3 class="card-title font-weight-bold" style="font-size: 0.88rem;">
                                <i class="fa-solid fa-building-user mr-2 text-primary"></i> Cumplimiento por Departamentos (Hoy)
                            </h3>
                        </div>
                        <div class="card-body p-0" style="max-height: 200px; overflow-y: auto; overflow-x: hidden;">
                            <table class="table table-hover table-sm mb-0" style="width: 100%; table-layout: fixed;">
                                <thead>
                                    <tr>
                                        <th style="width: 32%;">Departamento o Área</th>
                                        <th class="text-center" style="width: 13%;">Personal</th>
                                        <th class="text-center" style="width: 13%;">Presentes</th>
                                        <th class="text-center" style="width: 13%;">Tardanzas</th>
                                        <th class="text-center" style="width: 13%;">Faltas</th>
                                        <th class="text-center" style="width: 16%;">% Cumplimiento</th>
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
                                            <td class="font-weight-bold text-dark py-1 text-truncate" style="font-size: 0.82rem;" title="<?= htmlspecialchars($ds['depto_nombre'] ?? 'General') ?>"><?= htmlspecialchars($ds['depto_nombre'] ?? 'General') ?></td>
                                            <td class="text-center font-weight-bold py-1"><?= $tot ?></td>
                                            <td class="text-center text-success font-weight-bold py-1"><?= $pres ?></td>
                                            <td class="text-center text-warning font-weight-bold py-1"><?= (int)$ds['tardanzas'] ?></td>
                                            <td class="text-center text-danger font-weight-bold py-1"><?= (int)$ds['faltas'] ?></td>
                                            <td class="text-center py-1">
                                                <div class="progress mb-1" style="height: 5px; border-radius: 9999px;">
                                                    <div class="progress-bar <?= $barColor ?>" style="width: <?= $porc ?>%; border-radius: 9999px;"></div>
                                                </div>
                                                <small class="font-weight-bold text-muted" style="font-size: 0.72rem;"><?= $porc ?>%</small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5 mb-2">
                    <!-- Resumen de Asistencia Hoy RRHH (GRÁFICO POTENCIADO) -->
                    <div class="card mb-2">
                        <div class="card-header d-flex justify-content-between align-items-center py-2 px-3">
                            <h3 class="card-title font-weight-bold" style="font-size: 0.88rem;">
                                <i class="fa-solid fa-chart-pie mr-2 text-primary"></i> Distribución de Asistencia Hoy
                            </h3>
                            <span class="badge-pill-custom badge-pill-neutral" style="font-size: 0.7rem;">
                                <i class="fa-solid fa-users mr-1 text-primary"></i> <?= $totalHoyConteo ?> marcados
                            </span>
                        </div>
                        <div class="card-body p-3">
                            <div style="height: 165px; position: relative;" class="mb-2">
                                <canvas id="distribucionChart"></canvas>
                            </div>
                            <div class="pt-2 border-top">
                                <div class="d-flex justify-content-between align-items-center py-1 border-bottom" style="font-size: 0.79rem;">
                                    <span class="text-dark"><i class="fa-solid fa-circle text-success mr-1" style="font-size: 8px;"></i> <strong>Presentes</strong></span>
                                    <span class="font-weight-bold text-dark"><?= $presentesHoy ?> <span class="badge-pill-custom badge-pill-presente ml-1" style="font-size: 0.7rem;"><?= $porcPresentes ?>%</span></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center py-1 border-bottom" style="font-size: 0.79rem;">
                                    <span class="text-dark"><i class="fa-solid fa-circle text-warning mr-1" style="font-size: 8px;"></i> <strong>Tardanzas</strong></span>
                                    <span class="font-weight-bold text-dark"><?= $tardanzasHoy ?> <span class="badge-pill-custom badge-pill-tardanza ml-1" style="font-size: 0.7rem;"><?= $porcTardanzas ?>%</span></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center py-1 border-bottom" style="font-size: 0.79rem;">
                                    <span class="text-dark"><i class="fa-solid fa-circle text-danger mr-1" style="font-size: 8px;"></i> <strong>Faltas</strong></span>
                                    <span class="font-weight-bold text-dark"><?= (int)($statsHoy['faltas'] ?? 0) ?> <span class="badge-pill-custom badge-pill-falta ml-1" style="font-size: 0.7rem;"><?= $porcFaltas ?>%</span></span>
                                </div>
                                <?php if (((int)($statsHoy['justificados'] ?? 0) > 0) || ((int)($statsHoy['sin_salida'] ?? 0) > 0)): ?>
                                    <div class="d-flex justify-content-between align-items-center py-1" style="font-size: 0.79rem;">
                                        <span class="text-secondary"><i class="fa-solid fa-circle text-primary mr-1" style="font-size: 8px;"></i> Justificados o En Turno</span>
                                        <span class="font-weight-bold text-dark"><?= (int)($statsHoy['justificados'] ?? 0) + (int)($statsHoy['sin_salida'] ?? 0) ?> <span class="badge-pill-custom badge-pill-neutral ml-1" style="font-size: 0.7rem;"><?= round($porcJustificados + $porcSinSalida, 1) ?>%</span></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Ranking de Impuntualidad -->
                    <div class="card mb-2">
                        <div class="card-header py-2 px-3">
                            <h3 class="card-title font-weight-bold" style="font-size: 0.88rem;">
                                <i class="fa-solid fa-chart-simple mr-2 text-primary"></i> Ranking de Impuntualidad (Mes Actual)
                            </h3>
                        </div>
                        <div class="card-body p-0" style="max-height: 200px; overflow-y: auto;">
                            <?php if (empty($topTardanzasMes)): ?>
                                <div class="text-center py-3 text-muted small">
                                    <i class="fa-solid fa-circle-check text-success mr-1"></i>
                                    ¡Excelente! No hay tardanzas acumuladas en el mes.
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($topTardanzasMes as $tm): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 border-0 border-bottom">
                                            <div>
                                                <div class="font-weight-bold text-dark small"><?= htmlspecialchars($tm['apellidos'] . ' ' . $tm['nombres']) ?></div>
                                                <div class="text-muted small" style="font-size: 0.74rem;">
                                                    <?= htmlspecialchars($tm['depto_nombre'] ?? 'Área') ?> &bull; <?= $tm['veces_tarde'] ?> incidencias
                                                </div>
                                            </div>
                                            <div>
                                                <span class="badge-pill-custom badge-pill-tardanza" style="font-size: 0.7rem;"><?= $tm['total_minutos'] ?> min</span>
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
                <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-2">
                    <div class="kpi-card h-100">
                        <div class="kpi-card-header">
                            <div>
                                <div class="kpi-title">Personal en Turno Hoy</div>
                                <div class="kpi-value"><?= $presentesHoy ?></div>
                                <div class="kpi-subtitle">Presentes con ingreso marcado</div>
                            </div>
                            <div class="kpi-icon-box">
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

                <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-2">
                    <div class="kpi-card h-100">
                        <div class="kpi-card-header">
                            <div>
                                <div class="kpi-title">Llegadas Tarde Hoy</div>
                                <div class="kpi-value"><?= $tardanzasHoy ?></div>
                                <div class="kpi-subtitle"><?= (int)($statsHoy['total_minutos_tardanza'] ?? 0) ?> min acumulados</div>
                            </div>
                            <div class="kpi-icon-box">
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

                <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-2">
                    <div class="kpi-card h-100">
                        <div class="kpi-card-header">
                            <div>
                                <div class="kpi-title">Inasistencias del Turno</div>
                                <div class="kpi-value"><?= (int)($statsHoy['faltas'] ?? 0) ?></div>
                                <div class="kpi-subtitle">Sin registro de entrada</div>
                            </div>
                            <div class="kpi-icon-box">
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

                <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-2">
                    <div class="kpi-card h-100">
                        <div class="kpi-card-header">
                            <div>
                                <div class="kpi-title">Sin Salida Registrada</div>
                                <div class="kpi-value"><?= (int)($statsHoy['sin_salida'] ?? 0) ?></div>
                                <div class="kpi-subtitle">Jornada en curso o pendiente</div>
                            </div>
                            <div class="kpi-icon-box">
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
                <div class="col-lg-7 mb-2">
                    <div class="card mb-2">
                        <div class="card-header d-flex justify-content-between align-items-center py-2 px-3">
                            <h3 class="card-title font-weight-bold" style="font-size: 0.88rem;">
                                <span class="badge-pill-custom badge-pill-online mr-2" style="font-size: 0.68rem;">
                                    <i class="fa-solid fa-circle" style="font-size: 5px;"></i> EN VIVO
                                </span>
                                Marcaciones en Tiempo Real del Personal
                            </h3>
                            <div class="card-tools">
                                <a href="?route=marcaciones&fecha=<?= $today ?>" class="btn btn-xs btn-outline-secondary">
                                    <i class="fas fa-list mr-1"></i> Ver todas
                                </a>
                            </div>
                        </div>
                        <div class="card-body p-0" style="max-height: 380px; overflow-y: auto; overflow-x: hidden;">
                            <table class="table table-hover table-sm mb-0" style="width: 100%; table-layout: fixed;">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="width: 18%;">Hora</th>
                                        <th style="width: 38%;">Empleado</th>
                                        <th style="width: 24%;">Punto de Control</th>
                                        <th class="text-center" style="width: 20%;">Marcación</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($ultimasMarcaciones)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-3 text-muted small">
                                                Aún no hay marcaciones para el día de hoy.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($ultimasMarcaciones as $m): ?>
                                            <tr>
                                                <td class="text-center font-weight-bold font-monospace text-secondary py-1" style="font-size: 0.78rem;">
                                                    <?= substr($m['fecha_hora'], 11, 8) ?>
                                                </td>
                                                <td class="font-weight-bold text-dark py-1 text-truncate" style="font-size: 0.82rem;" title="<?= htmlspecialchars($m['apellidos'] . ' ' . $m['nombres']) ?>">
                                                    <?= htmlspecialchars($m['apellidos'] . ' ' . $m['nombres']) ?>
                                                </td>
                                                <td class="small text-muted py-1 text-truncate" style="font-size: 0.78rem;" title="<?= htmlspecialchars($m['dispositivo_nombre'] ?? 'Reloj') ?>">
                                                    <?= htmlspecialchars($m['dispositivo_nombre'] ?? 'Reloj') ?>
                                                </td>
                                                <td class="text-center py-1">
                                                    <?php
                                                        $tipo = strtolower($m['tipo'] ?? '');
                                                        if ($tipo === 'entrada') echo '<span class="badge-pill-custom badge-pill-presente" style="font-size: 0.7rem;"><i class="fa-solid fa-arrow-right-to-bracket mr-1"></i> Entrada</span>';
                                                        elseif ($tipo === 'salida') echo '<span class="badge-pill-custom badge-pill-justificado" style="font-size: 0.7rem;"><i class="fa-solid fa-arrow-right-from-bracket mr-1"></i> Salida</span>';
                                                        elseif (str_contains($tipo, 'refrigerio')) echo '<span class="badge-pill-custom badge-pill-neutral" style="font-size: 0.7rem;"><i class="fa-solid fa-utensils mr-1"></i> Refrigerio</span>';
                                                        else echo '<span class="badge-pill-custom badge-pill-neutral" style="font-size: 0.7rem;">Marcación</span>';
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

                <!-- Resumen Asistencia & Mayores Tardanzas -->
                <div class="col-lg-5 mb-2">
                    <div class="card mb-2">
                        <div class="card-header d-flex justify-content-between align-items-center py-2 px-3">
                            <h3 class="card-title font-weight-bold" style="font-size: 0.88rem;">
                                <i class="fa-solid fa-chart-pie mr-2 text-primary"></i> Resumen de Asistencia Hoy
                            </h3>
                            <span class="badge-pill-custom badge-pill-neutral" style="font-size: 0.7rem;">
                                <?= $totalHoyConteo ?> marcados
                            </span>
                        </div>
                        <div class="card-body p-3">
                            <div style="height: 165px; position: relative;" class="mb-2">
                                <canvas id="distribucionChart"></canvas>
                            </div>
                            <div class="pt-2 border-top">
                                <div class="d-flex justify-content-between align-items-center py-1 border-bottom" style="font-size: 0.79rem;">
                                    <span class="text-dark"><i class="fa-solid fa-circle text-success mr-1" style="font-size: 8px;"></i> <strong>Presentes</strong></span>
                                    <span class="font-weight-bold text-dark"><?= $presentesHoy ?> <span class="badge-pill-custom badge-pill-presente ml-1" style="font-size: 0.7rem;"><?= $porcPresentes ?>%</span></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center py-1 border-bottom" style="font-size: 0.79rem;">
                                    <span class="text-dark"><i class="fa-solid fa-circle text-warning mr-1" style="font-size: 8px;"></i> <strong>Tardanzas</strong></span>
                                    <span class="font-weight-bold text-dark"><?= $tardanzasHoy ?> <span class="badge-pill-custom badge-pill-tardanza ml-1" style="font-size: 0.7rem;"><?= $porcTardanzas ?>%</span></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center py-1" style="font-size: 0.79rem;">
                                    <span class="text-dark"><i class="fa-solid fa-circle text-danger mr-1" style="font-size: 8px;"></i> <strong>Faltas</strong></span>
                                    <span class="font-weight-bold text-dark"><?= (int)($statsHoy['faltas'] ?? 0) ?> <span class="badge-pill-custom badge-pill-falta ml-1" style="font-size: 0.7rem;"><?= $porcFaltas ?>%</span></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Mayores Tardanzas de Hoy -->
                    <div class="card mb-2">
                        <div class="card-header py-2 px-3">
                            <h3 class="card-title font-weight-bold" style="font-size: 0.88rem;">
                                <i class="fa-solid fa-triangle-exclamation mr-2 text-warning"></i> Mayores Tardanzas de Hoy
                            </h3>
                        </div>
                        <div class="card-body p-0" style="max-height: 180px; overflow-y: auto;">
                            <?php if (empty($topTardanzasHoy)): ?>
                                <div class="text-center py-3 text-muted small">
                                    <i class="fa-solid fa-circle-check text-success mr-1"></i>
                                    ¡Cero tardanzas registradas el día de hoy!
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($topTardanzasHoy as $t): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 border-0 border-bottom">
                                            <div>
                                                <div class="font-weight-bold text-dark small"><?= htmlspecialchars($t['apellidos'] . ' ' . $t['nombres']) ?></div>
                                                <div class="text-muted small" style="font-size: 0.74rem;">
                                                    <?= htmlspecialchars($t['depto_nombre'] ?? 'Área General') ?> &bull; Ingreso: <?= substr($t['hora_entrada_real'], 11, 5) ?>
                                                </div>
                                            </div>
                                            <span class="badge-pill-custom badge-pill-tardanza" style="font-size: 0.7rem;">+<?= $t['minutos_tardanza'] ?> min</span>
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
            <!-- VISTA: SOLO CONSULTA / GENERAL / USUARIO / ASISTENTE               -->
            <!-- =================================================================== -->
            <div class="row">
                <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-2">
                    <div class="kpi-card h-100">
                        <div class="kpi-card-header">
                            <div>
                                <div class="kpi-title">Total Presentes Hoy</div>
                                <div class="kpi-value"><?= $presentesHoy ?></div>
                                <div class="kpi-subtitle">Personal con ingreso puntual registrado</div>
                            </div>
                            <div class="kpi-icon-box">
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
                <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-2">
                    <div class="kpi-card h-100">
                        <div class="kpi-card-header">
                            <div>
                                <div class="kpi-title">Tardanzas Hoy</div>
                                <div class="kpi-value"><?= $tardanzasHoy ?></div>
                                <div class="kpi-subtitle"><?= (int)($statsHoy['total_minutos_tardanza'] ?? 0) ?> min acumulados hoy</div>
                            </div>
                            <div class="kpi-icon-box">
                                <i class="fa-solid fa-clock-rotate-left"></i>
                            </div>
                        </div>
                        <div>
                            <a href="?route=asistencia&fecha_inicio=<?= $today ?>&fecha_fin=<?= $today ?>&estado=TARDANZA" class="kpi-footer-link">
                                Ver tardanzas <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-2">
                    <div class="kpi-card h-100">
                        <div class="kpi-card-header">
                            <div>
                                <div class="kpi-title">Inasistencias Registradas</div>
                                <div class="kpi-value"><?= (int)($statsHoy['faltas'] ?? 0) ?></div>
                                <div class="kpi-subtitle">Sin registro de entrada</div>
                            </div>
                            <div class="kpi-icon-box">
                                <i class="fa-solid fa-user-slash"></i>
                            </div>
                        </div>
                        <div>
                            <a href="?route=asistencia&fecha_inicio=<?= $today ?>&fecha_fin=<?= $today ?>&estado=FALTA" class="kpi-footer-link">
                                Ver inasistencias <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-2">
                    <div class="kpi-card h-100">
                        <div class="kpi-card-header">
                            <div>
                                <div class="kpi-title">Tasa de Asistencia Hoy</div>
                                <div class="kpi-value"><?= $tasaAsistenciaHoy ?>%</div>
                                <div class="kpi-subtitle"><?= $totalHoyConteo ?> marcaciones procesadas</div>
                            </div>
                            <div class="kpi-icon-box">
                                <i class="fa-solid fa-chart-pie"></i>
                            </div>
                        </div>
                        <div>
                            <a href="?route=asistencia&fecha_inicio=<?= $today ?>&fecha_fin=<?= $today ?>" class="kpi-footer-link">
                                Ver reporte completo <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-7 mb-2">
                    <div class="card mb-2 shadow-sm">
                        <div class="card-header d-flex flex-wrap justify-content-between align-items-center py-2 px-3 gap-2">
                            <div class="d-flex align-items-center flex-wrap">
                                <h3 class="card-title font-weight-bold mb-0" style="font-size: 0.88rem;">
                                    <i class="fa-solid fa-chart-line mr-2 text-primary"></i> Tendencia de Asistencia
                                </h3>
                            </div>

                            <!-- CONTROLES DE FILTRO -->
                            <div class="d-flex align-items-center flex-wrap" style="gap: 6px;">
                                <!-- Filtro Rango de Tiempo -->
                                <div class="input-group input-group-sm" style="width: auto;">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light text-muted border-right-0 py-0" style="font-size: 0.72rem; padding: 2px 6px;">
                                            <i class="fa-regular fa-calendar text-primary"></i>
                                        </span>
                                    </div>
                                    <select id="filtro-tendencia-periodo-consulta" class="form-control form-control-sm border-left-0 py-0 font-weight-bold" style="font-size: 0.75rem; height: 27px;" onchange="filtrarTendencia('consulta')">
                                        <option value="7d" selected>Últimos 7 días</option>
                                        <option value="15d">Últimos 15 días</option>
                                        <option value="30d">Últimos 30 días</option>
                                        <option value="mes_actual">Este Mes</option>
                                        <option value="mes_anterior">Mes Anterior</option>
                                    </select>
                                </div>

                                <!-- Spinner AJAX -->
                                <div id="tendencia-loading-consulta" class="spinner-border spinner-border-sm text-primary ml-1" role="status" style="display: none; width: 0.9rem; height: 0.9rem;">
                                    <span class="sr-only">Cargando...</span>
                                </div>
                            </div>
                        </div>

                        <!-- ÚNICA LEYENDA DINÁMICA E INTERACTIVA (CLICKABLE PARA OCULTAR/MOSTRAR SERIES) -->
                        <div class="px-3 py-1.5 bg-light border-bottom d-flex flex-wrap justify-content-between align-items-center" style="font-size: 0.76rem; background-color: #f8fafc !important;">
                            <div class="d-flex flex-wrap align-items-center" style="gap: 14px;">
                                <span class="d-inline-flex align-items-center legend-item-btn" id="legend-item-presentes-consulta" onclick="toggleTendenciaDataset(0, 'consulta')" title="Clic para ocultar o mostrar 'Presentes' en el gráfico" style="cursor: pointer; user-select: none; transition: all 0.2s ease;">
                                    <span style="width: 10px; height: 10px; border-radius: 2px; display: inline-block; background-color: #10b981; margin-right: 5px;"></span>
                                    <span class="text-muted mr-1">Presentes:</span>
                                    <strong class="text-success" id="leyenda-total-presentes-consulta"><?= $totalesTendencia['presentes'] ?? 0 ?></strong>
                                    <span class="badge-pill-custom badge-pill-presente ml-1" id="leyenda-porc-presentes-consulta" style="font-size: 0.68rem;"><?= $totalesTendencia['porc_presentes'] ?? 0 ?>%</span>
                                </span>

                                <span class="d-inline-flex align-items-center legend-item-btn" id="legend-item-tardanzas-consulta" onclick="toggleTendenciaDataset(1, 'consulta')" title="Clic para ocultar o mostrar 'Tardanzas' en el gráfico" style="cursor: pointer; user-select: none; transition: all 0.2s ease;">
                                    <span style="width: 10px; height: 10px; border-radius: 2px; display: inline-block; background-color: #f59e0b; margin-right: 5px;"></span>
                                    <span class="text-muted mr-1">Tardanzas:</span>
                                    <strong class="text-warning" id="leyenda-total-tardanzas-consulta"><?= $totalesTendencia['tardanzas'] ?? 0 ?></strong>
                                    <span class="badge-pill-custom badge-pill-tardanza ml-1" id="leyenda-porc-tardanzas-consulta" style="font-size: 0.68rem;"><?= $totalesTendencia['porc_tardanzas'] ?? 0 ?>%</span>
                                </span>

                                <span class="d-inline-flex align-items-center legend-item-btn" id="legend-item-faltas-consulta" onclick="toggleTendenciaDataset(2, 'consulta')" title="Clic para ocultar o mostrar 'Faltas' en el gráfico" style="cursor: pointer; user-select: none; transition: all 0.2s ease;">
                                    <span style="width: 10px; height: 10px; border-radius: 2px; display: inline-block; background-color: #f43f5e; margin-right: 5px;"></span>
                                    <span class="text-muted mr-1">Faltas:</span>
                                    <strong class="text-danger" id="leyenda-total-faltas-consulta"><?= $totalesTendencia['faltas'] ?? 0 ?></strong>
                                    <span class="badge-pill-custom badge-pill-falta ml-1" id="leyenda-porc-faltas-consulta" style="font-size: 0.68rem;"><?= $totalesTendencia['porc_faltas'] ?? 0 ?>%</span>
                                </span>
                            </div>

                            <div class="text-secondary small">
                                Total: <strong class="text-dark font-weight-bold" id="leyenda-total-general-consulta"><?= $totalesTendencia['total'] ?? 0 ?></strong>
                            </div>
                        </div>

                        <div class="card-body p-3">
                            <div style="height: 195px; position: relative;">
                                <canvas id="tendenciaChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5 mb-2">
                    <div class="card mb-2">
                        <div class="card-header d-flex justify-content-between align-items-center py-2 px-3">
                            <h3 class="card-title font-weight-bold" style="font-size: 0.88rem;"><i class="fa-solid fa-chart-pie mr-2 text-primary"></i> Distribución de Asistencia Hoy</h3>
                            <span class="badge-pill-custom badge-pill-neutral" style="font-size: 0.7rem;"><?= $totalHoyConteo ?> marcados</span>
                        </div>
                        <div class="card-body p-3">
                            <div style="height: 165px; position: relative;" class="mb-2">
                                <canvas id="distribucionChart"></canvas>
                            </div>
                            <div class="pt-2 border-top">
                                <div class="d-flex justify-content-between align-items-center py-1 border-bottom" style="font-size: 0.79rem;">
                                    <span class="text-dark"><i class="fa-solid fa-circle text-success mr-1" style="font-size: 8px;"></i> <strong>Presentes</strong> (A tiempo)</span>
                                    <span class="font-weight-bold text-dark"><?= $presentesHoy ?> <span class="badge-pill-custom badge-pill-presente ml-1" style="font-size: 0.7rem;"><?= $porcPresentes ?>%</span></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center py-1 border-bottom" style="font-size: 0.79rem;">
                                    <span class="text-dark"><i class="fa-solid fa-circle text-warning mr-1" style="font-size: 8px;"></i> <strong>Tardanzas</strong></span>
                                    <span class="font-weight-bold text-dark"><?= $tardanzasHoy ?> <span class="badge-pill-custom badge-pill-tardanza ml-1" style="font-size: 0.7rem;"><?= $porcTardanzas ?>%</span></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center py-1 border-bottom" style="font-size: 0.79rem;">
                                    <span class="text-dark"><i class="fa-solid fa-circle text-danger mr-1" style="font-size: 8px;"></i> <strong>Inasistencias</strong> (Faltas)</span>
                                    <span class="font-weight-bold text-dark"><?= (int)($statsHoy['faltas'] ?? 0) ?> <span class="badge-pill-custom badge-pill-falta ml-1" style="font-size: 0.7rem;"><?= $porcFaltas ?>%</span></span>
                                </div>
                                <?php if (((int)($statsHoy['justificados'] ?? 0) > 0) || ((int)($statsHoy['sin_salida'] ?? 0) > 0)): ?>
                                    <div class="d-flex justify-content-between align-items-center py-1" style="font-size: 0.79rem;">
                                        <span class="text-secondary"><i class="fa-solid fa-circle text-primary mr-1" style="font-size: 8px;"></i> Justificados o En Curso</span>
                                        <span class="font-weight-bold text-dark"><?= (int)($statsHoy['justificados'] ?? 0) + (int)($statsHoy['sin_salida'] ?? 0) ?> <span class="badge-pill-custom badge-pill-neutral ml-1" style="font-size: 0.7rem;"><?= round($porcJustificados + $porcSinSalida, 1) ?>%</span></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>
</section>

<!-- MODAL DE SELECCIÓN DE SINCRONIZACIÓN -->
<div class="modal fade" id="modalSincronizacion" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 560px;">
        <div class="modal-content shadow-lg border-0 rounded-lg">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold d-flex align-items-center">
                    <i class="fa-solid fa-arrows-rotate mr-2" style="color: #1e40af;"></i> Sincronización de Relojes Biométricos
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body p-4">
                <p class="mb-3" style="color: #475569; font-size: 0.88rem;">
                    Selecciona el método de extracción de marcaciones para los relojes ZKTeco registrados:
                </p>
                
                <div class="d-flex flex-column" style="gap: 10px;">
                    <!-- Opción 1: Incremental Inteligente (Recomendado) -->
                    <a href="javascript:void(0)" onclick="executeSyncMode('incremental')" class="list-group-item-action d-flex align-items-start p-3 border rounded bg-white" style="border-color: #cbd5e1 !important; border-radius: 8px; text-decoration: none; transition: all 0.15s ease;">
                        <div class="mr-3 mt-1" style="color: #1e40af;">
                            <i class="fa-solid fa-arrows-rotate fa-2x"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="font-weight-bold d-flex align-items-center justify-content-between" style="color: #0f172a; font-size: 0.95rem;">
                                <span>Sincronización Inteligente</span>
                                <span class="badge font-weight-bold" style="background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; font-size: 0.72rem;">Recomendado</span>
                            </div>
                            <small class="text-muted d-block mt-1" style="font-size: 0.8rem; line-height: 1.35;">
                                Descarga únicamente las marcaciones nuevas desde la última fecha registrada hasta el momento actual sin omitir días pendientes.
                            </small>
                        </div>
                    </a>

                    <!-- Opción 2: Rápido Hoy -->
                    <a href="javascript:void(0)" onclick="executeSyncMode('today')" class="list-group-item-action d-flex align-items-start p-3 border rounded bg-white" style="border-color: #e2e8f0 !important; border-radius: 8px; text-decoration: none; transition: all 0.15s ease;">
                        <div class="mr-3 mt-1" style="color: #475569;">
                            <i class="fa-solid fa-bolt fa-2x"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="font-weight-bold" style="color: #0f172a; font-size: 0.95rem;">
                                Sincronizar Solo Hoy (Rápido)
                            </div>
                            <small class="text-muted d-block mt-1" style="font-size: 0.8rem; line-height: 1.35;">
                                Procesa de forma ultrarrápida exclusivamente los fichajes capturados durante la jornada de hoy.
                            </small>
                        </div>
                    </a>

                    <!-- Opción 3: Histórico Completo -->
                    <a href="javascript:void(0)" onclick="executeSyncMode('full')" class="list-group-item-action d-flex align-items-start p-3 border rounded bg-white" style="border-color: #e2e8f0 !important; border-radius: 8px; text-decoration: none; transition: all 0.15s ease;">
                        <div class="mr-3 mt-1" style="color: #64748b;">
                            <i class="fa-solid fa-database fa-2x"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="font-weight-bold" style="color: #0f172a; font-size: 0.95rem;">
                                Sincronización Histórica Completa
                            </div>
                            <small class="text-muted d-block mt-1" style="font-size: 0.8rem; line-height: 1.35;">
                                Extrae todo el historial de eventos almacenado en la memoria de los dispositivos biométricos.
                            </small>
                        </div>
                    </a>
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
    // Plugin personalizado para dibujar el porcentaje y texto central dentro del anillo del gráfico
    const centerDoughnutPlugin = {
        id: 'centerDoughnutPlugin',
        afterDraw(chart) {
            if (chart.config.type !== 'doughnut') return;
            const { ctx, chartArea: { top, bottom, left, right } } = chart;
            ctx.save();
            const centerX = (left + right) / 2;
            const centerY = (top + bottom) / 2;
            
            const rate = <?= (int)$tasaAsistenciaHoy ?>;
            ctx.font = "bold 22px 'Plus Jakarta Sans', sans-serif";
            ctx.fillStyle = '#0f172a';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(rate + '%', centerX, centerY - 7);
            
            ctx.font = "700 9px 'Plus Jakarta Sans', sans-serif";
            ctx.fillStyle = '#64748b';
            ctx.fillText('ASISTENCIA', centerX, centerY + 13);
            ctx.restore();
        }
    };

    // 1. Gráfico de Tendencia (Con datos dinámicos en Leyenda y Filtros AJAX)
    const ctxTendencia = document.getElementById('tendenciaChart')?.getContext('2d');
    if (ctxTendencia) {
        window.tendenciaChartInstance = new Chart(ctxTendencia, {
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
                        pointRadius: 3,
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
                        pointRadius: 3,
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
                        pointRadius: 3,
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
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleFont: { size: 12, family: "'Plus Jakarta Sans', sans-serif" },
                        bodyFont: { size: 11, family: "'Plus Jakarta Sans', sans-serif" },
                        padding: 8,
                        cornerRadius: 6
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, font: { size: 10 } }
                    },
                    x: {
                        ticks: { font: { size: 10 } }
                    }
                }
            }
        });
    }

    // Función para alternar visibilidad de una serie desde la leyenda interactiva
    window.toggleTendenciaDataset = function(index, tipo) {
        if (!window.tendenciaChartInstance) return;
        const chart = window.tendenciaChartInstance;
        const isVisible = chart.isDatasetVisible(index);
        
        if (isVisible) {
            chart.hide(index);
        } else {
            chart.show(index);
        }
        
        const itemIds = ['legend-item-presentes', 'legend-item-tardanzas', 'legend-item-faltas'];
        const sufijo = tipo === 'consulta' ? '-consulta' : '';
        const el = document.getElementById(itemIds[index] + sufijo) || document.getElementById(itemIds[index]);
        if (el) {
            el.style.opacity = isVisible ? '0.38' : '1';
            el.style.textDecoration = isVisible ? 'line-through' : 'none';
        }
    };

    // Función global para filtrar dinámicamente el gráfico de tendencia
    window.filtrarTendencia = function(tipo) {
        const isConsulta = tipo === 'consulta';
        const sufijo = isConsulta ? '-consulta' : '';
        
        const periodoSelect = document.getElementById('filtro-tendencia-periodo' + sufijo) 
                           || document.getElementById('filtro-tendencia-periodo');
        const deptoSelect = document.getElementById('filtro-tendencia-depto' + sufijo) 
                         || document.getElementById('filtro-tendencia-depto');
        const spinner = document.getElementById('tendencia-loading' + sufijo) 
                     || document.getElementById('tendencia-loading');
        const badge = document.getElementById('tendencia-badge-periodo' + sufijo) 
                   || document.getElementById('tendencia-badge-periodo');

        const periodo = periodoSelect ? periodoSelect.value : '7d';
        const depto = deptoSelect ? deptoSelect.value : 'all';

        if (spinner) spinner.style.display = 'inline-block';

        fetch(`?route=dashboard&action=tendencia_datos&periodo=${encodeURIComponent(periodo)}&departamento_id=${encodeURIComponent(depto)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (spinner) spinner.style.display = 'none';
            if (!data.success) {
                console.error('Error al cargar datos de tendencia:', data.error);
                return;
            }

            if (badge && data.labelPeriodo) {
                badge.textContent = data.labelPeriodo;
            }

            // Actualizar datos del gráfico de forma animada
            if (window.tendenciaChartInstance) {
                window.tendenciaChartInstance.data.labels = data.labels;
                window.tendenciaChartInstance.data.datasets[0].data = data.datasets.presentes;
                window.tendenciaChartInstance.data.datasets[1].data = data.datasets.tardanzas;
                window.tendenciaChartInstance.data.datasets[2].data = data.datasets.faltas;
                window.tendenciaChartInstance.update();
            }

            // Actualizar las cifras numéricas de la barra de leyenda
            if (data.totales) {
                const ids = isConsulta ? '-consulta' : '';
                const elPres = document.getElementById('leyenda-total-presentes' + ids);
                const elTard = document.getElementById('leyenda-total-tardanzas' + ids);
                const elFalt = document.getElementById('leyenda-total-faltas' + ids);
                const elTot = document.getElementById('leyenda-total-general' + ids);
                const elPorcPres = document.getElementById('leyenda-porc-presentes' + ids);
                const elPorcTard = document.getElementById('leyenda-porc-tardanzas' + ids);
                const elPorcFalt = document.getElementById('leyenda-porc-faltas' + ids);

                if (elPres) elPres.textContent = data.totales.presentes.toLocaleString();
                if (elTard) elTard.textContent = data.totales.tardanzas.toLocaleString();
                if (elFalt) elFalt.textContent = data.totales.faltas.toLocaleString();
                if (elTot) elTot.textContent = data.totales.total_general.toLocaleString();
                if (elPorcPres) elPorcPres.textContent = data.totales.porc_presentes + '%';
                if (elPorcTard) elPorcTard.textContent = data.totales.porc_tardanzas + '%';
                if (elPorcFalt) elPorcFalt.textContent = data.totales.porc_faltas + '%';
            }
        })
        .catch(err => {
            if (spinner) spinner.style.display = 'none';
            console.error('Fallo en petición de tendencia:', err);
        });
    };

    // 2. Gráfico de Dona: Distribución Hoy con porcentaje y tooltip enriquecido
    const ctxDist = document.getElementById('distribucionChart')?.getContext('2d');
    if (ctxDist) {
        const presentesVal = <?= (int)($statsHoy['presentes'] ?? 0) ?>;
        const tardanzasVal = <?= (int)($statsHoy['tardanzas'] ?? 0) ?>;
        const faltasVal = <?= (int)($statsHoy['faltas'] ?? 0) ?>;
        const justificadosVal = <?= (int)($statsHoy['justificados'] ?? 0) ?>;
        const sinSalidaVal = <?= (int)($statsHoy['sin_salida'] ?? 0) ?>;
        const totalSum = presentesVal + tardanzasVal + faltasVal + justificadosVal + sinSalidaVal;

        // Si no hay datos registrados, mostrar un placeholder gris suave
        const chartData = (totalSum === 0) ? [1] : [presentesVal, tardanzasVal, faltasVal, justificadosVal, sinSalidaVal];
        const chartColors = (totalSum === 0) ? ['#e2e8f0'] : ['#10b981', '#f59e0b', '#f43f5e', '#3b82f6', '#94a3b8'];
        const chartLabels = (totalSum === 0) ? ['Sin registros'] : ['Presentes', 'Tardanzas', 'Faltas', 'Justificados', 'Sin Salida'];

        new Chart(ctxDist, {
            type: 'doughnut',
            data: {
                labels: chartLabels,
                datasets: [{
                    data: chartData,
                    backgroundColor: chartColors,
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 4
                }]
            },
            plugins: [centerDoughnutPlugin],
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleFont: { size: 12, family: "'Plus Jakarta Sans', sans-serif" },
                        bodyFont: { size: 11, family: "'Plus Jakarta Sans', sans-serif" },
                        padding: 8,
                        cornerRadius: 6,
                        callbacks: {
                            label: function(context) {
                                if (totalSum === 0) return ' Sin registros hoy';
                                const val = context.raw || 0;
                                const pct = totalSum > 0 ? Math.round((val / totalSum) * 100) : 0;
                                return ` ${context.label}: ${val} (${pct}%)`;
                            }
                        }
                    }
                },
                cutout: '70%'
            }
        });
    }
});

function openSyncModal() {
    $('#modalSincronizacion').modal('show');
}

function executeSyncMode(mode = 'incremental') {
    $('#modalSincronizacion').modal('hide');
    
    const syncTitle = (mode === 'today') ? 'Sincronizando Marcaciones de Hoy...' : ((mode === 'full') ? 'Sincronizando Histórico Completo...' : 'Sincronizando Marcaciones Pendientes...');

    Swal.fire({
        title: syncTitle,
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
    let errorsCount = 0;
    const timer = setInterval(() => {
        seconds++;
        const timerEl = document.getElementById('swal-timer');
        if (timerEl) timerEl.innerText = `Tiempo transcurrido: ${seconds}s`;

        if (seconds > 120) {
            clearInterval(timer);
            Swal.fire({
                icon: 'info',
                title: 'Proceso en segundo plano',
                text: 'La sincronización sigue ejecutándose. El panel se actualizará.',
                confirmButtonText: 'Actualizar'
            }).then(() => {
                window.location.reload();
            });
        }
    }, 1000);

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
        // Polling de estado
        const poll = setInterval(() => {
            fetch('?route=dispositivos&action=sync_status', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then(statusData => {
                errorsCount = 0;
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
            })
            .catch(e => {
                console.warn("Poll sync error:", e);
                errorsCount++;
                if (errorsCount >= 5) {
                    clearInterval(poll);
                    clearInterval(timer);
                    Swal.fire({
                        icon: 'info',
                        title: 'Sincronización Iniciada',
                        text: 'El proceso se está completando en el servidor.',
                        confirmButtonText: 'Actualizar'
                    }).then(() => {
                        window.location.reload();
                    });
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

