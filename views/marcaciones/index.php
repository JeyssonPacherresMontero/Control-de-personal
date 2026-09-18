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
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap" style="padding: 0.75rem 1.25rem;">
                <div class="d-flex align-items-center">
                    <h3 class="card-title font-weight-bold text-dark mb-0 d-flex align-items-center" style="font-size: 0.92rem;">
                        <i class="fa-solid fa-filter mr-2 text-primary"></i> Filtros de Auditoría
                    </h3>
                </div>
                <div class="d-flex align-items-center flex-wrap" style="gap: 8px; margin-left: auto;">
                    <?php if (in_array($userRole, ['ADMIN', 'RRHH'], true)): ?>
                        <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalNuevaMarcacion">
                            <i class="fa-solid fa-plus mr-1"></i> Registrar Marcación
                        </button>
                    <?php endif; ?>

                    <a href="?route=marcaciones&fecha_inicio=<?= urlencode($fechaInicio) ?>&fecha_fin=<?= urlencode($fechaFin) ?>&dispositivo_id=<?= urlencode((string)($dispositivoId ?? '')) ?>&tipo=<?= urlencode((string)($tipo ?? '')) ?>&search=<?= urlencode($search ?? '') ?>&export=excel" class="btn btn-success btn-sm" title="Descargar reporte en Excel">
                        <i class="fa-solid fa-file-excel mr-1"></i> Excel
                    </a>
                    <a href="?route=marcaciones&fecha_inicio=<?= urlencode($fechaInicio) ?>&fecha_fin=<?= urlencode($fechaFin) ?>&dispositivo_id=<?= urlencode((string)($dispositivoId ?? '')) ?>&tipo=<?= urlencode((string)($tipo ?? '')) ?>&search=<?= urlencode($search ?? '') ?>&export=csv" class="btn btn-outline-secondary btn-sm" title="Exportar archivo CSV">
                        <i class="fa-solid fa-file-csv mr-1"></i> CSV
                    </a>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()" title="Imprimir reporte oficial o Guardar como PDF">
                        <i class="fa-solid fa-print mr-1"></i> PDF
                    </button>
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
                            <option value="desconocido" <?= ($tipo ?? '') === 'desconocido' ? 'selected' : '' ?>>Otras / Desconocidas</option>
                        </select>
                    </div>

                    <div class="col-md-2 col-sm-8 mb-2">
                        <label class="form-label-custom"><i class="fa-solid fa-magnifying-glass mr-1"></i> Buscar Empleado</label>
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Nombre, DNI o ID..." value="<?= htmlspecialchars($search ?? '') ?>">
                    </div>

                    <div class="col-md-1 col-sm-4 mb-2 d-flex" style="gap: 4px;">
                        <button type="submit" class="btn btn-primary btn-sm flex-fill" title="Filtrar resultados" style="height: 34px;">
                            <i class="fa-solid fa-filter"></i>
                        </button>
                        <a href="?route=marcaciones" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros" style="height: 34px; display: inline-flex; align-items: center; justify-content: center;">
                            <i class="fa-solid fa-rotate-left"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- MAIN TABLE CARD -->
        <style>
            .table-marcaciones-fit {
                width: 100% !important;
                table-layout: auto;
            }
            .table-marcaciones-fit th,
            .table-marcaciones-fit td {
                padding: 0.42rem 0.45rem !important;
                vertical-align: middle !important;
                font-size: 0.815rem !important;
            }
            .table-marcaciones-fit th {
                font-size: 0.73rem !important;
                white-space: nowrap;
                letter-spacing: 0.02em !important;
            }
            .table-marcaciones-fit .cell-nowrap {
                white-space: nowrap !important;
            }
            .table-marcaciones-fit .badge-pill-custom {
                padding: 2px 6px !important;
                font-size: 0.70rem !important;
                white-space: nowrap;
            }
        </style>
        <div class="card">
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover table-sm table-marcaciones-fit mb-0">
                    <thead>
                        <tr>
                            <th class="text-center cell-nowrap" style="width: 55px;">N° Reg</th>
                            <th class="text-center cell-nowrap" style="width: 90px;">Fecha</th>
                            <th class="text-center cell-nowrap" style="width: 80px;">Hora</th>
                            <th class="text-center cell-nowrap" style="width: 85px;">ID Reloj</th>
                            <th style="min-width: 160px;">Empleado Identificado</th>
                            <th style="min-width: 130px;">Reloj Biométrico</th>
                            <th class="text-center cell-nowrap" style="width: 95px;">Tipo</th>
                            <th class="text-center cell-nowrap" style="width: 110px;">Verificación</th>
                            <th class="text-center cell-nowrap" style="width: 90px;">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($marcaciones)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-folder-open fa-2x mb-2 d-block text-secondary"></i>
                                    <div>No se encontraron marcaciones para los filtros seleccionados.</div>
                                    <a href="?route=marcaciones" class="btn btn-xs btn-outline-primary mt-2">
                                        <i class="fa-solid fa-rotate-left mr-1"></i> Reestablecer filtros
                                    </a>
                                </td>
                            </tr>
                        <?php else: ?>
                        <?php foreach ($marcaciones as $m): ?>
                            <?php
                                $fechaRaw = !empty($m['fecha_hora']) ? substr($m['fecha_hora'], 0, 10) : '';
                                $horaRaw  = !empty($m['fecha_hora']) ? substr($m['fecha_hora'], 11, 8) : '--:--:--';
                                $fechaFmt = $fechaRaw ? date('d/m/Y', strtotime($fechaRaw)) : '--';
                            ?>
                            <tr>
                                <td class="text-center text-muted font-monospace small cell-nowrap">#<?= $m['id'] ?></td>
                                <td class="text-center font-monospace small text-dark font-weight-bold cell-nowrap">
                                    <i class="fa-regular fa-calendar mr-1 text-muted"></i><?= $fechaFmt ?>
                                </td>
                                <td class="text-center font-monospace small text-primary font-weight-bold cell-nowrap">
                                    <i class="fa-regular fa-clock mr-1 text-secondary"></i><?= $horaRaw ?>
                                </td>
                                <td class="text-center cell-nowrap"><span class="badge-pill-custom badge-pill-neutral">ID: <?= htmlspecialchars($m['codigo_reloj']) ?></span></td>
                                <td>
                                    <?php if (!empty($m['nombres'])): ?>
                                        <div class="font-weight-bold text-dark" style="line-height: 1.25;"><?= htmlspecialchars($m['apellidos'] . ' ' . $m['nombres']) ?></div>
                                        <small class="text-muted">DNI: <?= htmlspecialchars($m['dni']) ?></small>
                                    <?php else: ?>
                                        <span class="badge-pill-custom badge-pill-tardanza"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Sin vincular</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="text-dark font-weight-bold" style="line-height: 1.25;"><?= htmlspecialchars($m['dispositivo_nombre'] ?? 'Desconocido') ?></div>
                                    <small class="text-muted font-monospace"><?= htmlspecialchars($m['dispositivo_ip'] ?? '') ?></small>
                                </td>
                                <td class="text-center cell-nowrap">
                                    <?php
                                        $t = strtolower($m['tipo']);
                                        if ($t === 'entrada') echo '<span class="badge-pill-custom badge-pill-presente"><i class="fa-solid fa-arrow-right-to-bracket mr-1"></i> Entrada</span>';
                                        elseif ($t === 'salida') echo '<span class="badge-pill-custom badge-pill-justificado"><i class="fa-solid fa-arrow-right-from-bracket mr-1"></i> Salida</span>';
                                        elseif (str_contains($t, 'refrigerio')) echo '<span class="badge-pill-custom badge-pill-neutral"><i class="fa-solid fa-utensils mr-1"></i> Refrigerio</span>';
                                        else echo '<span class="badge-pill-custom badge-pill-neutral">Marcación</span>';
                                    ?>
                                </td>
                                <td class="text-center cell-nowrap">
                                    <span class="small">
                                    <?php
                                        $verif = strtolower($m['tipo_verificacion'] ?? '');
                                        if (str_contains($verif, 'huella') || $verif === 'fingerprint') {
                                             echo '<i class="fa-solid fa-fingerprint text-primary mr-1"></i> Huella';
                                        } elseif (str_contains($verif, 'facial') || str_contains($verif, 'face')) {
                                            echo '<i class="fa-solid fa-camera text-info mr-1"></i> Facial';
                                        } elseif (str_contains($verif, 'tarjeta') || str_contains($verif, 'card') || str_contains($verif, 'rfid')) {
                                            echo '<i class="fa-solid fa-id-card text-success mr-1"></i> Tarjeta';
                                        } elseif (str_contains($verif, 'manual')) {
                                            echo '<i class="fa-solid fa-keyboard text-secondary mr-1"></i> Manual';
                                        } elseif (str_contains($verif, 'clave') || str_contains($verif, 'pin') || str_contains($verif, 'password')) {
                                            echo '<i class="fa-solid fa-key text-warning mr-1"></i> PIN';
                                        } else {
                                            echo '<i class="fa-solid fa-check text-muted mr-1"></i> ' . htmlspecialchars($m['tipo_verificacion'] ?: 'Biométrico');
                                        }
                                    ?>
                                    </span>
                                </td>
                                <td class="text-center cell-nowrap">
                                    <?php if ($m['procesado']): ?>
                                        <span class="badge-pill-custom badge-pill-presente"><i class="fa-solid fa-circle-check mr-1"></i> Procesado</span>
                                    <?php else: ?>
                                        <span class="badge-pill-custom badge-pill-tardanza"><i class="fa-solid fa-clock mr-1"></i> Pendiente</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="thead-light">
                        <tr class="font-weight-bold">
                            <th colspan="8" class="text-right">TOTAL DE MARCACIONES EN EL PERÍODO:</th>
                            <th class="text-center text-success"><?= $kpiTotalMarcaciones ?></th>
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
                    <select name="id_empleado" class="form-control form-control-sm select2-worker" style="width: 100%;" required>
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

<script>

$(document).ready(function() {
    if ($.fn.select2) {
        $('#modalNuevaMarcacion select[name="id_empleado"]').select2({
            theme: 'bootstrap4',
            dropdownParent: $('#modalNuevaMarcacion'),
            placeholder: '-- Seleccionar Empleado --',
            width: '100%'
        });
    }
});
</script>
<?php endif; ?>

<?php require_once APP_ROOT . '/views/layout/footer.php'; ?>
