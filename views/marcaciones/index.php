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
<?php endif; ?>

<?php require_once APP_ROOT . '/views/layout/footer.php'; ?>
