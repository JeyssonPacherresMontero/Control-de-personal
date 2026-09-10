<?php 
require_once APP_ROOT . '/views/layout/header.php'; 

$kpiTotalMarcaciones = (int)($kpis['total'] ?? count($marcaciones));
$kpiEntradas = (int)($kpis['entradas'] ?? 0);
$kpiSalidas = (int)($kpis['salidas'] ?? 0);
$kpiRefrigerios = (int)($kpis['refrigerios'] ?? 0);
$kpiOtros = (int)($kpis['otros'] ?? 0);
$kpiProcesados = (int)($kpis['procesados'] ?? 0);
$kpiPctProcesado = $kpiTotalMarcaciones > 0 ? round(($kpiProcesados / $kpiTotalMarcaciones) * 100, 1) : 0;

$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$perPage = $perPage ?? 250;

$dispNombreFiltro = 'Todos los Relojes Biométricos';
if (!empty($dispositivoId)) {
    foreach ($dispositivos as $d) {
        if ($d['id'] == $dispositivoId) {
            $dispNombreFiltro = $d['nombre'] . ' (' . $d['ip'] . ')';
            break;
        }
    }
}
?>

<!-- Content Header (Page header) -->
<div class="content-header no-print pb-2">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-sm-6">
                <h1 class="m-0 font-weight-bold text-dark" style="font-size: 1.45rem;">
                    <i class="fa-solid fa-clock-rotate-left mr-2 text-primary"></i> Registro de Marcaciones de Relojes Biométricos
                </h1>
                <div class="text-muted small mt-1">Auditoría y trazabilidad de eventos crudos registrados en terminales biométricas.</div>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right mb-0">
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

        <!-- MEMBRETE OFICIAL DE IMPRESIÓN Y PDF (Solo visible al imprimir o exportar a PDF) -->
        <div class="print-only mb-3">
            <table style="width: 100%; border-collapse: collapse; border-bottom: 2px solid #0f766e; padding-bottom: 8px;">
                <tr>
                    <td style="width: 120px; vertical-align: middle; text-align: center; padding-right: 15px;">
                        <img src="<?= jushsal_logo_data_uri('full') ?: asset('img/logo_jushsal.png') ?>" alt="JUSHSAL" style="max-height: 60px; max-width: 110px; object-fit: contain;">
                    </td>
                    <td style="vertical-align: middle;">
                        <div style="font-size: 13pt; font-weight: 800; color: #0f766e; text-transform: uppercase;">JUNTA DE USUARIOS DEL SECTOR HIDRÁULICO MENOR SAN LORENZO (JUSHSAL)</div>
                        <div style="font-size: 9pt; color: #475569; font-weight: 600;">SISTEMA INTEGRADO DE CONTROL DE PERSONAL Y ASISTENCIA LABORAL</div>
                        <div style="font-size: 11pt; font-weight: 700; color: #0f172a; margin-top: 4px;">REGISTRO OFICIAL DE MARCACIONES DE RELOJES BIOMÉTRICOS</div>
                    </td>
                    <td style="width: 200px; text-align: right; vertical-align: middle; font-size: 8pt; color: #64748b;">
                        <div><b>Período:</b> <?= date('d/m/Y', strtotime($fechaInicio)) ?> al <?= date('d/m/Y', strtotime($fechaFin)) ?></div>
                        <div><b>Reloj:</b> <?= htmlspecialchars($dispNombreFiltro) ?></div>
                        <div><b>Emisión:</b> <?= date('d/m/Y H:i:s') ?></div>
                        <div><b>Usuario:</b> <?= htmlspecialchars($currentUser['nombre'] ?? 'Administrador') ?></div>
                    </td>
                </tr>
            </table>

            <!-- Resumen de Marcaciones para Impresión -->
            <table class="table table-sm table-bordered mt-2 mb-2" style="font-size: 8pt; text-align: center;">
                <thead style="background-color: #f1f5f9;">
                    <tr>
                        <th>Total Marcaciones</th>
                        <th>Entradas</th>
                        <th>Salidas</th>
                        <th>Refrigerios</th>
                        <th>Otras</th>
                        <th>Procesadas en Asistencia</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><b><?= $kpiTotalMarcaciones ?></b></td>
                        <td style="color: #166534;"><b><?= $kpiEntradas ?></b></td>
                        <td style="color: #3730a3;"><b><?= $kpiSalidas ?></b></td>
                        <td style="color: #075985;"><b><?= $kpiRefrigerios ?></b></td>
                        <td><b><?= $kpiOtros ?></b></td>
                        <td style="color: #15803d;"><b><?= $kpiProcesados ?> (<?= $kpiPctProcesado ?>%)</b></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- KPI SUMMARY CARDS (PANTALLA) -->
        <div class="row no-print">
            <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                <div class="kpi-card h-100">
                    <div class="kpi-card-header">
                        <div>
                            <div class="kpi-title">Total Marcaciones</div>
                            <div class="kpi-value text-dark"><?= $kpiTotalMarcaciones ?></div>
                            <div class="kpi-subtitle">Registros capturados en el período</div>
                        </div>
                        <div class="kpi-icon-box kpi-icon-indigo">
                            <i class="fa-solid fa-fingerprint"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                <div class="kpi-card h-100">
                    <div class="kpi-card-header">
                        <div>
                            <div class="kpi-title">Entradas Registradas</div>
                            <div class="kpi-value text-success"><?= $kpiEntradas ?></div>
                            <div class="kpi-subtitle">Marcaciones de inicio de jornada</div>
                        </div>
                        <div class="kpi-icon-box kpi-icon-emerald">
                            <i class="fa-solid fa-arrow-right-to-bracket"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                <div class="kpi-card h-100">
                    <div class="kpi-card-header">
                        <div>
                            <div class="kpi-title">Salidas Registradas</div>
                            <div class="kpi-value text-primary"><?= $kpiSalidas ?></div>
                            <div class="kpi-subtitle">Marcaciones de fin de jornada</div>
                        </div>
                        <div class="kpi-icon-box kpi-icon-blue">
                            <i class="fa-solid fa-arrow-right-from-bracket"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                <div class="kpi-card h-100">
                    <div class="kpi-card-header">
                        <div>
                            <div class="kpi-title">Procesadas en Asistencia</div>
                            <div class="kpi-value text-info"><?= $kpiProcesados ?> <span style="font-size: 0.95rem; color: #64748b; font-weight: 600;">(<?= $kpiPctProcesado ?>%)</span></div>
                            <div class="kpi-subtitle">Eventos procesados por el motor</div>
                        </div>
                        <div class="kpi-icon-box kpi-icon-slate">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- FILTER AND ACTIONS CARD -->
        <div class="card mb-3 no-print">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap py-2 px-3">
                <h3 class="card-title font-weight-bold text-dark mb-0 d-flex align-items-center" style="font-size: 0.92rem;">
                    <i class="fa-solid fa-filter mr-2 text-primary"></i> Filtros de Auditoría
                </h3>
                <div class="card-tools d-flex align-items-center flex-wrap my-1" style="gap: 6px;">
                    <?php if (in_array($userRole, ['ADMIN', 'RRHH'], true)): ?>
                        <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalNuevaMarcacion">
                            <i class="fa-solid fa-plus mr-1"></i> Registrar Marcación
                        </button>
                    <?php endif; ?>

                    <div class="btn-group btn-group-sm ml-md-1">
                        <a href="?route=marcaciones&fecha_inicio=<?= $fechaInicio ?>&fecha_fin=<?= $fechaFin ?>&dispositivo_id=<?= $dispositivoId ?>&tipo=<?= $tipo ?? '' ?>&search=<?= urlencode($search ?? '') ?>&export=excel" class="btn btn-success btn-sm" title="Descargar reporte en Excel">
                            <i class="fa-solid fa-file-excel mr-1"></i> Excel
                        </a>
                        <a href="?route=marcaciones&fecha_inicio=<?= $fechaInicio ?>&fecha_fin=<?= $fechaFin ?>&dispositivo_id=<?= $dispositivoId ?>&tipo=<?= $tipo ?? '' ?>&search=<?= urlencode($search ?? '') ?>&export=csv" class="btn btn-outline-secondary btn-sm" title="Exportar archivo CSV">
                            <i class="fa-solid fa-file-csv mr-1"></i> CSV
                        </a>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()" title="Imprimir reporte oficial o Guardar como PDF">
                            <i class="fa-solid fa-print mr-1"></i> PDF
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body py-3 px-3">
                <form method="GET" action="" class="row align-items-end">
                    <input type="hidden" name="route" value="marcaciones">

                    <div class="col-md-2 col-sm-6 mb-2">
                        <label class="form-label-custom"><i class="fa-regular fa-calendar mr-1"></i> Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" class="form-control form-control-sm" value="<?= htmlspecialchars($fechaInicio) ?>" required>
                    </div>

                    <div class="col-md-2 col-sm-6 mb-2">
                        <label class="form-label-custom"><i class="fa-regular fa-calendar-check mr-1"></i> Fecha Fin</label>
                        <input type="date" name="fecha_fin" class="form-control form-control-sm" value="<?= htmlspecialchars($fechaFin) ?>" required>
                    </div>

                    <div class="col-md-3 col-sm-6 mb-2">
                        <label class="form-label-custom"><i class="fa-solid fa-network-wired mr-1"></i> Dispositivo Biométrico</label>
                        <select name="dispositivo_id" class="form-control form-control-sm">
                            <option value="">-- Todos los Relojes --</option>
                            <?php foreach ($dispositivos as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= $dispositivoId == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['nombre']) ?> (<?= htmlspecialchars($d['ip']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2 col-sm-6 mb-2">
                        <label class="form-label-custom"><i class="fa-solid fa-list-check mr-1"></i> Tipo Marcación</label>
                        <select name="tipo" class="form-control form-control-sm">
                            <option value="">-- Todas las Marcaciones --</option>
                            <option value="entrada" <?= ($tipo ?? '') === 'entrada' ? 'selected' : '' ?>>Entrada (Inicio de Jornada)</option>
                            <option value="salida" <?= ($tipo ?? '') === 'salida' ? 'selected' : '' ?>>Salida (Fin de Jornada)</option>
                            <option value="refrigerio" <?= ($tipo ?? '') === 'refrigerio' ? 'selected' : '' ?>>Todos los Refrigerios</option>
                            <option value="refrigerio_salida" <?= ($tipo ?? '') === 'refrigerio_salida' ? 'selected' : '' ?>>Salida a Refrigerio</option>
                            <option value="refrigerio_entrada" <?= ($tipo ?? '') === 'refrigerio_entrada' ? 'selected' : '' ?>>Retorno de Refrigerio</option>
                        </select>
                    </div>

                    <div class="col-md-2 col-sm-8 mb-2">
                        <label class="form-label-custom"><i class="fa-solid fa-magnifying-glass mr-1"></i> Buscar Empleado</label>
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Nombre, DNI o ID..." value="<?= htmlspecialchars($search ?? '') ?>">
                    </div>

                    <div class="col-md-1 col-sm-4 mb-2">
                        <button type="submit" class="btn btn-primary btn-sm btn-block" title="Filtrar resultados" style="height: 34px;">
                            <i class="fa-solid fa-filter mr-1"></i> Filtrar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MAIN TABLE CARD -->
        <div class="card">
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover text-nowrap table-sm">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 80px;">N° Registro</th>
                            <th class="text-center">Fecha y Hora</th>
                            <th class="text-center">ID en Reloj</th>
                            <th>Empleado Identificado</th>
                            <th>Reloj Biométrico</th>
                            <th class="text-center">Tipo de Marcación</th>
                            <th class="text-center">Método de Verificación</th>
                            <th class="text-center">Estado Procesado</th>
                            <th class="text-center no-print">Trazabilidad Event Sourcing</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($marcaciones as $m): ?>
                            <tr>
                                <td class="text-center text-muted font-monospace small">#<?= $m['id'] ?></td>
                                <td class="text-center font-weight-bold text-dark font-monospace small"><?= $m['fecha_hora'] ?></td>
                                <td class="text-center"><span class="badge-pill-custom badge-pill-neutral">ID: <?= htmlspecialchars($m['codigo_reloj']) ?></span></td>
                                <td>
                                    <?php if (!empty($m['nombres'])): ?>
                                        <div class="font-weight-bold text-dark"><?= htmlspecialchars($m['apellidos'] . ' ' . $m['nombres']) ?></div>
                                        <small class="text-muted">DNI: <?= htmlspecialchars($m['dni']) ?></small>
                                    <?php else: ?>
                                        <span class="badge-pill-custom badge-pill-tardanza"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Sin vincular</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="text-dark font-weight-bold"><?= htmlspecialchars($m['dispositivo_nombre'] ?? 'Desconocido') ?></div>
                                    <small class="text-muted font-monospace"><?= htmlspecialchars($m['dispositivo_ip'] ?? '') ?></small>
                                </td>
                                <td class="text-center">
                                    <?php
                                        $t = strtolower($m['tipo']);
                                        if ($t === 'entrada') echo '<span class="badge-pill-custom badge-pill-presente"><i class="fa-solid fa-arrow-right-to-bracket mr-1"></i> Entrada</span>';
                                        elseif ($t === 'salida') echo '<span class="badge-pill-custom badge-pill-justificado"><i class="fa-solid fa-arrow-right-from-bracket mr-1"></i> Salida</span>';
                                        elseif (str_contains($t, 'refrigerio')) echo '<span class="badge-pill-custom badge-pill-neutral"><i class="fa-solid fa-utensils mr-1"></i> Refrigerio</span>';
                                        else echo '<span class="badge-pill-custom badge-pill-neutral">Marcación</span>';
                                    ?>
                                </td>
                                <td class="text-center">
                                    <span class="small">
                                    <?php
                                        $verif = strtolower($m['tipo_verificacion'] ?? '');
                                        if (str_contains($verif, 'huella') || $verif === 'fingerprint') {
                                             echo '<i class="fa-solid fa-fingerprint text-primary mr-1"></i> Huella Dactilar';
                                        } elseif (str_contains($verif, 'facial') || str_contains($verif, 'face')) {
                                            echo '<i class="fa-solid fa-camera text-info mr-1"></i> Facial';
                                        } elseif (str_contains($verif, 'tarjeta') || str_contains($verif, 'card') || str_contains($verif, 'rfid')) {
                                            echo '<i class="fa-solid fa-id-card text-success mr-1"></i> Tarjeta RFID';
                                        } elseif (str_contains($verif, 'manual')) {
                                            echo '<i class="fa-solid fa-keyboard text-secondary mr-1"></i> Manual RRHH';
                                        } elseif (str_contains($verif, 'clave') || str_contains($verif, 'pin') || str_contains($verif, 'password')) {
                                            echo '<i class="fa-solid fa-key text-warning mr-1"></i> Contraseña o PIN';
                                        } else {
                                            echo '<i class="fa-solid fa-check text-muted mr-1"></i> ' . htmlspecialchars($m['tipo_verificacion'] ?: 'Biométrico');
                                        }
                                    ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($m['procesado']): ?>
                                        <span class="badge-pill-custom badge-pill-presente"><i class="fa-solid fa-circle-check mr-1"></i> Procesado</span>
                                    <?php else: ?>
                                        <span class="badge-pill-custom badge-pill-tardanza"><i class="fa-solid fa-clock mr-1"></i> Pendiente</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center no-print">
                                    <?php if (!empty($m['id_empleado'])): ?>
                                        <button class="btn btn-xs btn-outline-info" onclick="openTimelineModal(<?= $m['id_empleado'] ?>, '<?= substr($m['fecha_hora'], 0, 10) ?>', '<?= htmlspecialchars(addslashes(($m['apellidos'] ?? '') . ' ' . ($m['nombres'] ?? ''))) ?>')" title="Ver auditoría de eventos">
                                             <i class="fa-solid fa-timeline mr-1"></i> Eventos
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="thead-light">
                        <tr class="font-weight-bold">
                            <th colspan="7" class="text-right">TOTAL DE MARCACIONES EN EL PERÍODO:</th>
                            <th class="text-center text-success"><?= $kpiTotalMarcaciones ?></th>
                            <th class="no-print"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white border-top py-2 px-3 d-flex flex-wrap justify-content-between align-items-center no-print">
                <div class="small text-muted mb-2 mb-md-0">
                    Mostrando página <b><?= $page ?></b> de <b><?= $totalPages ?></b> (<b><?= number_format($kpiTotalMarcaciones) ?></b> marcaciones en total)
                </div>
                <nav aria-label="Paginación de marcaciones">
                    <ul class="pagination pagination-sm mb-0">
                        <?php
                            $queryParams = $_GET;
                            $buildPageUrl = function($p) use ($queryParams) {
                                $queryParams['page'] = $p;
                                return '?' . http_build_query($queryParams);
                            };
                        ?>
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $buildPageUrl(1) ?>" title="Primera página"><i class="fa-solid fa-angles-left"></i></a>
                        </li>
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $buildPageUrl($page - 1) ?>" title="Página anterior"><i class="fa-solid fa-angle-left"></i></a>
                        </li>
                        
                        <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <li class="page-item <?= ($i === $page) ? 'active font-weight-bold' : '' ?>">
                                <a class="page-link" href="<?= $buildPageUrl($i) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>

                        <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $buildPageUrl($page + 1) ?>" title="Página siguiente"><i class="fa-solid fa-angle-right"></i></a>
                        </li>
                        <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $buildPageUrl($totalPages) ?>" title="Última página"><i class="fa-solid fa-angles-right"></i></a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>

        <!-- BLOQUE DE FIRMAS OFICIALES DE IMPRESIÓN (Solo visible al imprimir / PDF) -->
        <div class="print-only print-signatures">
            <table style="width: 100%; text-align: center; border: none;">
                <tr>
                    <td style="width: 50%; padding-top: 50px; border: none;">
                        <div style="display: inline-block; width: 260px; border-top: 1.5px solid #334155; padding-top: 6px;">
                            <div style="font-weight: 700; font-size: 8.5pt; color: #0f172a;">RESPONSABLE DE CONTROL DE ASISTENCIA</div>
                            <div style="font-size: 7.5pt; color: #64748b;">Recursos Humanos - JUSHSAL</div>
                        </div>
                    </td>
                    <td style="width: 50%; padding-top: 50px; border: none;">
                        <div style="display: inline-block; width: 260px; border-top: 1.5px solid #334155; padding-top: 6px;">
                            <div style="font-weight: 700; font-size: 8.5pt; color: #0f172a;">RESPONSABLE DE TI & SISTEMAS</div>
                            <div style="font-size: 7.5pt; color: #64748b;">Auditoría de Dispositivos Biométricos</div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

    </div>
</section>

<?php if (in_array($userRole, ['ADMIN', 'RRHH'], true)): ?>
<!-- MODAL NUEVA MARCACION MANUAL -->
<div class="modal fade" id="modalNuevaMarcacion" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="?route=marcaciones&action=guardar_manual" class="modal-content">
            <?= csrf_field() ?>
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
