<?php require_once APP_ROOT . '/views/layout/header.php'; ?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 font-weight-bold"><i class="fa-solid fa-gauge-high mr-2 text-primary"></i> Dashboard de Asistencia</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="?route=dashboard">Inicio</a></li>
                    <li class="breadcrumb-item active">Dashboard</li>
                </ol>
            </div>
        </div>
    </div>
</div>
<!-- /.content-header -->

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <!-- SMALL BOXES (Stat box) -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <!-- small box -->
                <div class="small-box bg-success shadow-sm">
                    <div class="inner">
                        <h3><?= (int)($statsHoy['presentes'] ?? 0) ?></h3>
                        <p class="font-weight-bold mb-1">Presentes Hoy</p>
                        <small>de <?= $totalEmpleados ?> empleados activos</small>
                    </div>
                    <div class="icon">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                    <a href="?route=asistencia&fecha_inicio=<?= date('Y-m-d') ?>&fecha_fin=<?= date('Y-m-d') ?>&estado=PRESENTE" class="small-box-footer">
                        Ver listado <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>

            <div class="col-lg-3 col-6">
                <!-- small box -->
                <div class="small-box bg-warning shadow-sm">
                    <div class="inner">
                        <h3 class="text-white"><?= (int)($statsHoy['tardanzas'] ?? 0) ?></h3>
                        <p class="font-weight-bold text-white mb-1">Tardanzas</p>
                        <small class="text-white"><?= (int)($statsHoy['total_minutos_tardanza'] ?? 0) ?> min acumulados</small>
                    </div>
                    <div class="icon">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>
                    <a href="?route=asistencia&fecha_inicio=<?= date('Y-m-d') ?>&fecha_fin=<?= date('Y-m-d') ?>&estado=TARDANZA" class="small-box-footer text-white">
                        Ver detalles <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>

            <div class="col-lg-3 col-6">
                <!-- small box -->
                <div class="small-box bg-danger shadow-sm">
                    <div class="inner">
                        <h3><?= (int)($statsHoy['faltas'] ?? 0) ?></h3>
                        <p class="font-weight-bold mb-1">Inasistencias / Faltas</p>
                        <small><?= (int)($statsHoy['justificados'] ?? 0) ?> justificaciones aprobadas</small>
                    </div>
                    <div class="icon">
                        <i class="fa-solid fa-user-xmark"></i>
                    </div>
                    <a href="?route=asistencia&fecha_inicio=<?= date('Y-m-d') ?>&fecha_fin=<?= date('Y-m-d') ?>&estado=FALTA" class="small-box-footer">
                        Ver inasistencias <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>

            <div class="col-lg-3 col-6">
                <!-- small box -->
                <div class="small-box bg-info shadow-sm">
                    <div class="inner">
                        <h3><?= $dispositivosOnline ?> <sup style="font-size: 18px">/ <?= count($dispositivos) ?></sup></h3>
                        <p class="font-weight-bold mb-1">Relojes ZKTeco</p>
                        <small><?= $dispositivosOnline === count($dispositivos) ? 'Todos en línea (Online)' : 'Atención en conectividad' ?></small>
                    </div>
                    <div class="icon">
                        <i class="fa-solid fa-network-wired"></i>
                    </div>
                    <a href="?route=dispositivos" class="small-box-footer">
                        Gestionar dispositivos <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
        </div>
        <!-- /.row -->

        <!-- MAIN ROW -->
        <div class="row">
            <!-- Feed de Marcaciones en Vivo -->
            <div class="col-lg-7">
                <div class="card card-primary card-outline shadow-sm">
                    <div class="card-header border-0 d-flex justify-content-between align-items-center">
                        <h3 class="card-title font-weight-bold">
                            <i class="fa-solid fa-satellite-dish mr-1 text-primary"></i>
                            Últimas Marcaciones en Tiempo Real
                        </h3>
                        <div class="card-tools">
                            <a href="?route=marcaciones" class="btn btn-tool btn-sm">
                                <i class="fas fa-bars mr-1"></i> Ver todas
                            </a>
                        </div>
                    </div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-striped table-valign-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Hora</th>
                                    <th>Empleado</th>
                                    <th>Dispositivo</th>
                                    <th>Tipo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($ultimasMarcaciones)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">
                                            <i class="fa-regular fa-clock fa-2x mb-2 d-block"></i>
                                            Aún no hay marcaciones registradas el día de hoy.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($ultimasMarcaciones as $m): ?>
                                        <tr>
                                            <td class="font-weight-bold text-secondary"><?= substr($m['fecha_hora'], 11, 8) ?></td>
                                            <td>
                                                <?php if (!empty($m['nombres'])): ?>
                                                    <div class="font-weight-bold text-dark"><?= htmlspecialchars($m['apellidos'] . ' ' . $m['nombres']) ?></div>
                                                    <small class="text-muted">ID ZK: <?= htmlspecialchars($m['codigo_reloj']) ?></small>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">ID Reloj: <?= htmlspecialchars($m['codigo_reloj']) ?> (No asignado)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <i class="fa-solid fa-fingerprint text-muted mr-1"></i>
                                                <?= htmlspecialchars($m['dispositivo_nombre'] ?? 'Reloj') ?>
                                            </td>
                                            <td>
                                                <?php
                                                    $tipo = strtolower($m['tipo']);
                                                    if ($tipo === 'entrada') echo '<span class="badge badge-success px-2 py-1">Entrada</span>';
                                                    elseif ($tipo === 'salida') echo '<span class="badge badge-primary px-2 py-1">Salida</span>';
                                                    elseif (str_contains($tipo, 'refrigerio')) echo '<span class="badge badge-info px-2 py-1">Break</span>';
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

            <!-- Estado de Biométricos y Top Tardanzas -->
            <div class="col-lg-5">
                <!-- Dispositivos Box -->
                <div class="card card-info card-outline shadow-sm mb-3">
                    <div class="card-header border-0">
                        <h3 class="card-title font-weight-bold">
                            <i class="fa-solid fa-server mr-1 text-info"></i>
                            Estado de Biométricos ZKTeco
                        </h3>
                    </div>
                    <div class="card-body p-0">
                        <ul class="products-list product-list-in-card pl-2 pr-2">
                            <?php foreach ($dispositivos as $d): ?>
                                <li class="item d-flex justify-content-between align-items-center p-2 border-bottom">
                                    <div>
                                        <a href="?route=dispositivos" class="product-title font-weight-bold text-dark"><?= htmlspecialchars($d['nombre']) ?></a>
                                        <span class="product-description text-muted small">
                                            <?= htmlspecialchars($d['ip']) ?>:<?= $d['puerto'] ?> &bull; <?= htmlspecialchars($d['ubicacion'] ?? 'General') ?>
                                        </span>
                                    </div>
                                    <div>
                                        <?php if ($d['estado_conexion'] === 'ONLINE'): ?>
                                            <span class="badge badge-success px-2 py-1"><i class="fa-solid fa-signal mr-1"></i> ONLINE</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger px-2 py-1"><i class="fa-solid fa-circle-xmark mr-1"></i> OFFLINE</span>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <!-- Top Tardanzas -->
                <div class="card card-warning card-outline shadow-sm">
                    <div class="card-header border-0">
                        <h3 class="card-title font-weight-bold">
                            <i class="fa-solid fa-triangle-exclamation mr-1 text-warning"></i>
                            Mayores Tardanzas de Hoy
                        </h3>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($topTardanzas)): ?>
                            <p class="text-muted small text-center py-3 mb-0">¡Excelente! Cero tardanzas registradas el día de hoy.</p>
                        <?php else: ?>
                            <ul class="products-list product-list-in-card pl-2 pr-2">
                                <?php foreach ($topTardanzas as $t): ?>
                                    <li class="item d-flex justify-content-between align-items-center p-2 border-bottom">
                                        <div>
                                            <span class="font-weight-bold text-dark"><?= htmlspecialchars($t['apellidos'] . ' ' . $t['nombres']) ?></span>
                                            <span class="product-description text-muted small">
                                                <?= htmlspecialchars($t['depto_nombre'] ?? 'Área General') ?> &bull; Marcó: <?= substr($t['hora_entrada_real'], 11, 5) ?>
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
        <!-- /.row -->

    </div>
</section>
<!-- /.content -->

<?php require_once APP_ROOT . '/views/layout/footer.php'; ?>
