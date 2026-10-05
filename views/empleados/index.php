<?php require_once APP_ROOT . '/views/layout/header.php'; ?>

<style>
/* ==========================================================
   ESTILOS SELECTOR REALISTA DE MANOS Y DEDOS ZKTECO
   ========================================================== */
.hand-selector-wrapper {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px;
    margin-top: 15px;
}
.hand-box {
    background: #ffffff;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    padding: 14px 12px;
    text-align: center;
    transition: all 0.25s ease;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
}
.hand-box:hover {
    border-color: #cbd5e1;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06);
}
.hand-stage {
    position: relative;
    width: 100%;
    max-width: 260px;
    margin: 0 auto 12px auto;
    border-radius: 12px;
    overflow: hidden;
    background: #ffffff;
    border: 1px solid #f1f5f9;
}
.hand-img {
    width: 100%;
    height: auto;
    display: block;
    border-radius: 10px;
    user-select: none;
    -webkit-user-drag: none;
    transition: transform 0.3s ease;
}
.hand-stage:hover .hand-img {
    transform: scale(1.02);
}
.finger-hotspot-btn {
    position: absolute;
    transform: translate(-50%, -50%);
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.94);
    border: 2px solid #475569;
    color: #1e293b;
    font-weight: 800;
    font-size: 11.5px;
    font-family: var(--font-sans, sans-serif);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.22);
    transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 10;
}
.finger-hotspot-btn:hover {
    background: #dbeafe;
    border-color: #2563eb;
    color: #1d4ed8;
    transform: translate(-50%, -50%) scale(1.22);
    box-shadow: 0 0 14px rgba(37, 99, 235, 0.65);
}
.finger-hotspot-btn.is-active {
    background: #16a34a !important;
    border-color: #ffffff !important;
    color: #ffffff !important;
    font-weight: 900 !important;
    transform: translate(-50%, -50%) scale(1.26);
    box-shadow: 0 0 16px rgba(22, 163, 74, 0.90);
    animation: pulse-node 1.8s infinite;
}
@keyframes pulse-node {
    0% { box-shadow: 0 0 0 0 rgba(22, 163, 74, 0.7); }
    70% { box-shadow: 0 0 0 12px rgba(22, 163, 74, 0); }
    100% { box-shadow: 0 0 0 0 rgba(22, 163, 74, 0); }
}

.finger-btn-chip {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 6px 10px;
    margin-bottom: 5px;
    font-size: 0.81rem;
    font-weight: 600;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #334155;
    cursor: pointer;
    transition: all 0.15s ease;
    text-align: left;
    user-select: none;
}
.finger-btn-chip:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
    color: #0f172a;
}
.finger-btn-chip.is-active {
    background: #16a34a !important;
    border-color: #15803d !important;
    color: #ffffff !important;
    box-shadow: 0 2px 6px rgba(22, 163, 74, 0.35);
}
.finger-btn-chip.is-active .chip-badge {
    background: #ffffff !important;
    color: #15803d !important;
    font-weight: 800;
}
.chip-badge {
    font-size: 0.70rem;
    padding: 2px 6px;
    border-radius: 4px;
    background: #e2e8f0;
    color: #475569;
    font-family: monospace;
    font-weight: 700;
}
.selected-finger-summary {
    background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
    border: 1.5px solid #86efac;
    border-radius: 9px;
    padding: 12px 16px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.finger-presets-bar {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}
.finger-preset-btn {
    font-size: 0.76rem;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #334155;
    cursor: pointer;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.finger-preset-btn:hover {
    background: #f1f5f9;
    border-color: #94a3b8;
    color: #0f172a;
}
.selected-finger-tag {
    display: inline-flex;
    align-items: center;
    background: #ffffff;
    border: 1.5px solid #22c55e;
    color: #15803d;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 0.78rem;
    font-weight: 700;
    margin: 2px 4px 2px 0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    transition: all 0.15s ease;
}
.selected-finger-tag:hover {
    border-color: #ef4444;
    color: #b91c1c;
}
.selected-finger-tag .tag-remove-btn {
    margin-left: 6px;
    color: #94a3b8;
    cursor: pointer;
    font-size: 1rem;
    line-height: 1;
    font-weight: bold;
}
.selected-finger-tag:hover .tag-remove-btn {
    color: #ef4444;
}
</style>

<!-- Content Header (Page header) -->
<div class="content-header pb-2">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-sm-6">
                <h1 class="m-0 font-weight-bold text-dark" style="font-size: 1.45rem;">
                    <i class="fa-solid fa-users mr-2 text-primary"></i> Directorio de Personal
                </h1>
                <div class="text-muted small mt-1">Padrón de trabajadores, asignación de turnos y sincronización de huellas biométricas.</div>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right mb-0">
                    <li class="breadcrumb-item"><a href="?route=dashboard">Inicio</a></li>
                    <li class="breadcrumb-item active">Directorio de Personal</li>
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
                'guardado' => ['success', 'Empleado guardado correctamente.'],
                'eliminado' => ['success', 'Empleado eliminado correctamente.'],
                'duplicado' => ['danger', 'Ya existe un empleado con ese DNI o código de reloj.'],
                'dni_invalido' => ['warning', 'El DNI debe tener 8 dígitos numéricos.'],
                'campos_requeridos' => ['warning', 'Completa todos los campos obligatorios.'],
                'error_interno' => ['danger', 'Ocurrió un error al guardar. Intenta nuevamente.'],
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
            $empTotal = count($empleados);
            $empActivos = 0;
            $empConTurno = 0;
            $empConHuellas = 0;
            foreach ($empleados as $e) {
                if (!empty($e['activo'])) $empActivos++;
                if (!empty($e['turno_id'])) $empConTurno++;
                if (!empty($e['huellas_count']) && (int)$e['huellas_count'] > 0) $empConHuellas++;
            }
            $pctActivos = $empTotal > 0 ? round(($empActivos / $empTotal) * 100) : 0;
            $pctHuellas = $empTotal > 0 ? round(($empConHuellas / $empTotal) * 100) : 0;
        ?>

        <!-- KPI SUMMARY CARDS (PANTALLA) -->
        <div class="row no-print mb-2">
            <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                <div class="kpi-card h-100">
                    <div class="kpi-card-header">
                        <div>
                            <div class="kpi-title">Total Personal</div>
                            <div class="kpi-value"><?= $empTotal ?></div>
                            <div class="kpi-subtitle"><b><?= $pctActivos ?>%</b> Activos en el Padrón</div>
                        </div>
                        <div class="kpi-icon-box">
                            <i class="fa-solid fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                <div class="kpi-card h-100">
                    <div class="kpi-card-header">
                        <div>
                            <div class="kpi-title">Personal Activo</div>
                            <div class="kpi-value"><?= $empActivos ?></div>
                            <div class="kpi-subtitle">Habilitados para marcaciones</div>
                        </div>
                        <div class="kpi-icon-box">
                            <i class="fa-solid fa-user-check"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                <div class="kpi-card h-100">
                    <div class="kpi-card-header">
                        <div>
                            <div class="kpi-title">Con Turno Asignado</div>
                            <div class="kpi-value"><?= $empConTurno ?></div>
                            <div class="kpi-subtitle">Horario laboral configurado</div>
                        </div>
                        <div class="kpi-icon-box">
                            <i class="fa-solid fa-business-time"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-3">
                <div class="kpi-card h-100">
                    <div class="kpi-card-header">
                        <div>
                            <div class="kpi-title">Huellas Enroladas</div>
                            <div class="kpi-value"><?= $empConHuellas ?></div>
                            <div class="kpi-subtitle"><b><?= $pctHuellas ?>%</b> con respaldo biométrico</div>
                        </div>
                        <div class="kpi-icon-box">
                            <i class="fa-solid fa-fingerprint"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ACTIONS TOOLBAR -->
        <div class="actions-toolbar no-print mb-3">
            <div class="actions-toolbar-group">
                <span class="font-weight-bold text-dark" style="font-size: 0.95rem;">
                    <i class="fa-solid fa-users-gear mr-2 text-primary"></i> Operaciones y Gestión del Personal
                </span>
            </div>
            <div class="actions-toolbar-group flex-wrap">
                <?php if (in_array($userRole, ['ADMIN', 'RRHH'], true)): ?>
                    <button class="btn btn-primary btn-sm" onclick="openNewEmpleadoModal()">
                        <i class="fa-solid fa-user-plus mr-1"></i> Registrar Empleado
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- FILTER CARD -->
        <div class="card mb-3 no-print">
            <div class="card-header py-2 px-3">
                <h3 class="card-title font-weight-bold text-dark mb-0 d-flex align-items-center" style="font-size: 0.92rem;">
                    <i class="fa-solid fa-filter mr-2 text-primary"></i> Filtros de Búsqueda de Personal
                </h3>
            </div>
            <div class="card-body py-3 px-3">
                <form method="GET" action="" class="row align-items-end">
                    <input type="hidden" name="route" value="empleados">

                    <div class="col-md-3 col-sm-6 mb-2">
                        <label class="form-label-custom"><i class="fa-solid fa-building mr-1"></i> Departamento</label>
                        <select name="departamento_id" class="form-control form-control-sm" <?= ($userRole === 'SUPERVISOR') ? 'disabled' : '' ?>>
                            <?php if ($userRole !== 'SUPERVISOR'): ?>
                                <option value="">-- Todos los Departamentos --</option>
                            <?php endif; ?>
                            <?php foreach ($departamentos as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= ($deptoId ?? '') == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($userRole === 'SUPERVISOR' && !empty($deptoId)): ?>
                            <input type="hidden" name="departamento_id" value="<?= htmlspecialchars((string)$deptoId) ?>">
                        <?php endif; ?>
                    </div>

                    <div class="col-md-2 col-sm-6 mb-2">
                        <label class="form-label-custom"><i class="fa-solid fa-business-time mr-1"></i> Turno</label>
                        <select name="turno_id" class="form-control form-control-sm">
                            <option value="">-- Todos los Turnos --</option>
                            <?php foreach ($turnos as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= ($turnoId ?? '') == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2 col-sm-6 mb-2">
                        <label class="form-label-custom"><i class="fa-solid fa-tag mr-1"></i> Estado</label>
                        <select name="estado" class="form-control form-control-sm">
                            <option value="">-- Todos --</option>
                            <option value="1" <?= ($estado ?? '') === '1' || ($estado ?? '') === 'ACTIVO' ? 'selected' : '' ?>>Activos</option>
                            <option value="0" <?= ($estado ?? '') === '0' || ($estado ?? '') === 'INACTIVO' ? 'selected' : '' ?>>Inactivos</option>
                        </select>
                    </div>

                    <div class="col-md-4 col-sm-8 mb-2">
                        <label class="form-label-custom"><i class="fa-solid fa-magnifying-glass mr-1"></i> Buscar por Nombre, DNI o ID</label>
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Ej: Perez, 70112233, 101..." value="<?= htmlspecialchars($search ?? '') ?>">
                    </div>

                    <div class="col-md-1 col-sm-4 mb-2 d-flex" style="gap: 4px;">
                        <button type="submit" class="btn btn-primary btn-sm flex-fill" title="Filtrar resultados" style="height: 38px; display: inline-flex; align-items: center; justify-content: center;">
                            <i class="fa-solid fa-filter mr-1 d-sm-none"></i><span class="d-none d-sm-inline"><i class="fa-solid fa-filter"></i></span>
                        </button>
                        <a href="?route=empleados" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros" style="height: 38px; width: 38px; display: inline-flex; align-items: center; justify-content: center;">
                            <i class="fa-solid fa-rotate-left"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- MAIN TABLE CARD -->
        <div class="card">
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover datatable text-nowrap table-sm">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 110px;">ID Reloj</th>
                            <th class="text-center" style="width: 100px;">DNI</th>
                            <th>Apellidos y Nombres</th>
                            <th>Departamento y Cargo</th>
                            <th class="text-center">Turno</th>
                            <th class="text-center">Biometría</th>
                            <th>Contacto</th>
                            <th class="text-center" style="width: 90px;">Estado</th>
                            <?php if (in_array($userRole, ['ADMIN', 'RRHH'], true)): ?>
                                <th class="text-center" style="width: 95px;">Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($empleados as $e): ?>
                            <tr>
                                <td class="text-center">
                                    <span class="badge-pill-custom badge-pill-neutral font-monospace font-weight-bold">
                                        ID: <?= htmlspecialchars($e['codigo_reloj']) ?>
                                    </span>
                                </td>
                                <td class="text-center font-monospace font-weight-bold text-dark"><?= htmlspecialchars($e['dni']) ?></td>
                                <td>
                                    <div class="font-weight-bold text-dark"><?= htmlspecialchars($e['apellidos'] . ' ' . $e['nombres']) ?></div>
                                    <small class="text-muted">Ingreso: <?= !empty($e['fecha_ingreso']) ? $e['fecha_ingreso'] : 'No registrado' ?></small>
                                </td>
                                <td>
                                    <div class="text-dark font-weight-bold"><?= htmlspecialchars($e['departamento_nombre'] ?? 'Sin Departamento') ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($e['cargo_nombre'] ?? 'Sin Cargo') ?></small>
                                </td>
                                <td class="text-center">
                                    <span class="badge-pill-custom badge-pill-neutral">
                                        <i class="far fa-clock mr-1 text-primary"></i><?= htmlspecialchars($e['turno_nombre'] ?? 'Sin Turno') ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php 
                                        $assignedIds = !empty($e['dedos_reloj']) ? array_filter(array_map('intval', explode(',', $e['dedos_reloj']))) : [2];
                                        $assignedNamesStr = !empty($e['dedos_nombre']) ? $e['dedos_nombre'] : ($e['dedo_nombre'] ?: 'Índice Mano Derecha');
                                        
                                        $storedZkIndices = !empty($e['huellas_indices']) ? array_map('intval', explode(',', (string)$e['huellas_indices'])) : [];
                                        
                                        $fingerShort = [
                                            1 => 'Pulgar D', 2 => 'Índice D', 3 => 'Medio D', 4 => 'Anular D', 5 => 'Meñique D',
                                            6 => 'Pulgar I', 7 => 'Índice I', 8 => 'Medio I', 9 => 'Anular I', 10 => 'Meñique I'
                                        ];

                                        $totalAssigned = count($assignedIds);
                                        $capturedCount = 0;
                                        foreach ($assignedIds as $fid) {
                                            if (in_array($fid - 1, $storedZkIndices, true)) {
                                                $capturedCount++;
                                            }
                                        }
                                    ?>
                                    <?php if ($totalAssigned > 0 && $capturedCount === $totalAssigned): ?>
                                        <span class="badge-pill-custom badge-pill-presente" title="Todas las huellas asignadas están respaldadas en BDD">
                                            <i class="fa-solid fa-circle-check mr-1"></i> <?= $capturedCount ?>/<?= $totalAssigned ?> Capturadas
                                        </span>
                                    <?php elseif ($capturedCount > 0): ?>
                                        <span class="badge-pill-custom badge-pill-tardanza" title="Faltan huellas por enrolar en el reloj y respaldar en BDD">
                                            <i class="fa-solid fa-triangle-exclamation mr-1"></i> <?= $capturedCount ?>/<?= $totalAssigned ?> Parcial
                                        </span>
                                    <?php else: ?>
                                        <span class="badge-pill-custom badge-pill-neutral" title="No se han capturado huellas en el reloj para este empleado">
                                            <i class="fa-solid fa-clock mr-1"></i> 0/<?= $totalAssigned ?> Pendiente
                                        </span>
                                    <?php endif; ?>

                                    <div class="d-flex flex-wrap justify-content-center mt-1" style="gap: 3px; max-width: 195px; margin: 0 auto;">
                                        <?php foreach ($assignedIds as $fid): 
                                            $isCap = in_array($fid - 1, $storedZkIndices, true);
                                            $sName = $fingerShort[$fid] ?? "Dedo $fid";
                                        ?>
                                            <?php if ($isCap): ?>
                                                <span class="badge-pill-custom badge-pill-presente" style="font-size: 0.68rem; padding: 2px 6px;" title="<?= $sName ?> (ID <?= $fid ?>): Huella respaldada en BDD">
                                                    <i class="fa-solid fa-check mr-0.5"></i> <?= $sName ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge-pill-custom badge-pill-neutral" style="font-size: 0.68rem; padding: 2px 6px;" title="<?= $sName ?> (ID <?= $fid ?>): Pendiente de captura en reloj">
                                                    <i class="fa-regular fa-clock text-secondary mr-0.5"></i> <?= $sName ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="small text-muted"><?= htmlspecialchars($e['email'] ?? '-') ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($e['telefono'] ?? '') ?></div>
                                </td>
                                <td class="text-center">
                                    <?php if ($e['activo']): ?>
                                        <span class="badge-pill-custom badge-pill-online"><i class="fa-solid fa-circle" style="font-size: 6px;"></i> Activo</span>
                                    <?php else: ?>
                                        <span class="badge-pill-custom badge-pill-offline"><i class="fa-solid fa-circle" style="font-size: 6px;"></i> Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <?php if (in_array($userRole, ['ADMIN', 'RRHH'], true)): ?>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="?route=asistencia&empleado_id=<?= $e['id'] ?>" class="btn btn-outline-secondary" title="Ver Reporte Individual de Asistencia">
                                                <i class="fa-solid fa-calendar-check text-primary"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-secondary" onclick="openEditEmpleadoModal(<?= htmlspecialchars(json_encode($e)) ?>)" title="Editar Empleado">
                                                <i class="fa-solid fa-pen text-secondary"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary" onclick="openEmpBiometricModal(<?= htmlspecialchars(json_encode($e)) ?>)" title="Biometría ZKTeco">
                                                <i class="fa-solid fa-fingerprint text-secondary"></i>
                                            </button>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</section>

<?php if (in_array($userRole, ['ADMIN', 'RRHH'], true)): ?>
<!-- MODAL GESTIÓN DE EMPLEADO -->
<div class="modal fade" id="modalEmpleado" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" action="?route=empleados&action=guardar" class="modal-content">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold" id="empModalTitle">
                    <i class="fa-solid fa-user-tie mr-2"></i> Ficha de Empleado
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="id" id="emp_id">
                
                <div class="modal-section-title mt-0">
                    <i class="fa-solid fa-id-card"></i> 1. Identificación y Reloj ZKTeco
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="small font-weight-bold text-secondary mb-0">ID de Usuario en Reloj ZKTeco <span class="text-danger">*</span></label>
                                <button type="button" class="btn btn-xs btn-outline-secondary" onclick="document.getElementById('emp_codigo_reloj').value = '<?= $siguienteCodigo ?>'" title="Genera el siguiente número correlativo disponible">
                                    <i class="fa-solid fa-wand-magic-sparkles mr-1 text-secondary"></i> Sugerir ID (<?= $siguienteCodigo ?>)
                                </button>
                            </div>
                            <input type="text" name="codigo_reloj" id="emp_codigo_reloj" class="form-control font-monospace font-weight-bold" placeholder="Ej: 1, 2, 101..." required>
                            <small class="text-muted">Número correlativo registrado en el reloj</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-secondary">Número de DNI <span class="text-danger">*</span></label>
                            <input type="text" name="dni" id="emp_dni" class="form-control font-monospace" placeholder="8 dígitos" required>
                        </div>
                    </div>
                </div>

                <div class="modal-section-title">
                    <i class="fa-solid fa-user"></i> 2. Información Personal y Contacto
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-secondary">Nombres <span class="text-danger">*</span></label>
                            <input type="text" name="nombres" id="emp_nombres" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-secondary">Apellidos <span class="text-danger">*</span></label>
                            <input type="text" name="apellidos" id="emp_apellidos" class="form-control" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-secondary">Correo Electrónico</label>
                            <input type="email" name="email" id="emp_email" class="form-control" placeholder="usuario@empresa.com">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-secondary">Teléfono</label>
                            <input type="text" name="telefono" id="emp_telefono" class="form-control" placeholder="+51 987 654 321">
                        </div>
                    </div>
                </div>

                <div class="modal-section-title">
                    <i class="fa-solid fa-sitemap"></i> 3. Organización y Turno Asignado
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-secondary">Departamento</label>
                            <select name="departamento_id" id="emp_departamento_id" class="form-control">
                                <option value="">-- Sin Asignar --</option>
                                <?php foreach ($departamentos as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-secondary">Cargo o Puesto</label>
                            <select name="cargo_id" id="emp_cargo_id" class="form-control">
                                <option value="">-- Sin Asignar --</option>
                                <?php foreach ($cargos as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-secondary">Turno Laboral Asignado <span class="text-danger">*</span></label>
                            <select name="turno_id" id="emp_turno_id" class="form-control" required>
                                <?php foreach ($turnos as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-secondary">Fecha de Ingreso</label>
                            <input type="date" name="fecha_ingreso" id="emp_fecha_ingreso" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="col-md-6 d-flex align-items-center">
                        <div class="custom-control custom-switch mt-2">
                            <input type="checkbox" class="custom-control-input" id="emp_activo" name="activo" value="1" checked>
                            <label class="custom-control-label font-weight-bold text-secondary" for="emp_activo">Empleado Activo</label>
                        </div>
                    </div>
                </div>

                <!-- CAMPOS OCULTOS PARA DEDOS SELECCIONADOS EN BD Y RELOJ -->
                <input type="hidden" name="dedo_reloj" id="emp_dedo_reloj" value="2">
                <input type="hidden" name="dedo_nombre" id="emp_dedo_nombre" value="Índice Mano Derecha">
                <input type="hidden" name="dedos_reloj" id="emp_dedos_reloj" value="2">
                <input type="hidden" name="dedos_nombre" id="emp_dedos_nombre" value="Índice Mano Derecha">

                <!-- APARTADO VISUAL INTERACTIVO: SELECCIÓN DE MANOS Y DEDOS -->
                <div class="modal-section-title">
                    <i class="fa-solid fa-hands"></i> 4. Asignación de Dedos para Huella Biométrica
                </div>

                <div class="hand-selector-wrapper">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap" style="gap: 6px;">
                        <span class="small text-muted">
                            Selecciona 1 o más dedos que el empleado registrará en el sensor biométrico:
                        </span>
                        <span class="badge-pill-custom badge-pill-neutral font-weight-bold" style="font-size: 0.72rem;">
                            <i class="fa-solid fa-check-double mr-1 text-secondary"></i> Selección Múltiple Activada
                        </span>
                    </div>

                    <!-- BARRA DE ACCIONES / SELECCIÓN RÁPIDA -->
                    <div class="finger-presets-bar">
                        <span class="small font-weight-bold text-muted mr-1"><i class="fa-solid fa-wand-magic-sparkles mr-1 text-secondary"></i> Atajos:</span>
                        <button type="button" class="finger-preset-btn" onclick="setFingersPreset('sugerido')" title="Solo Índice Derecho (ID 2)">
                            <i class="fa-solid fa-check text-secondary"></i> Índice Sugerido (ID 2)
                        </button>
                        <button type="button" class="finger-preset-btn" onclick="setFingersPreset('indices')" title="Ambos Índices (IDs 2 y 7)">
                            <i class="fa-solid fa-hand-peace text-secondary"></i> Ambos Índices (2 y 7)
                        </button>
                        <button type="button" class="finger-preset-btn" onclick="setFingersPreset('pulgares')" title="Ambos Pulgares (IDs 1 y 6)">
                            <i class="fa-solid fa-thumbs-up text-secondary"></i> Ambos Pulgares (1 y 6)
                        </button>
                        <button type="button" class="finger-preset-btn" onclick="setFingersPreset('todos')" title="Seleccionar los 10 dedos">
                            <i class="fa-solid fa-hands text-secondary"></i> Todos (10 Dedos)
                        </button>
                        <button type="button" class="finger-preset-btn text-muted" onclick="setFingersPreset('limpiar')" title="Limpiar selección">
                            <i class="fa-solid fa-eraser"></i> Limpiar
                        </button>
                    </div>

                    <div class="row">
                        <!-- MANO IZQUIERDA -->
                        <div class="col-md-6 mb-3">
                            <div class="hand-box">
                                <div>
                                    <h6 class="font-weight-bold text-dark small mb-2 d-flex align-items-center justify-content-center">
                                        <i class="fa-solid fa-hand mr-1 text-secondary"></i> Mano Izquierda (IDs 6 - 10)
                                    </h6>
                                    
                                    <!-- MANO IZQUIERDA REALISTA CON PUNTOS INTERACTIVOS (6=Pulgar ... 10=Meñique) -->
                                    <div class="hand-stage">
                                        <img src="<?= asset('img/hand_left.jpg') ?>" class="hand-img" alt="Mano Izquierda Realista" onerror="this.src='img/hand_left.jpg'">
                                        <!-- Yemas interactivas sobre los dedos reales -->
                                        <div class="finger-hotspot-btn" data-finger-id="10" data-finger-name="Meñique Mano Izquierda" style="left: 17%; top: 32%;" onclick="toggleFinger(10)" title="Meñique Izquierdo (ID 10)">10</div>
                                        <div class="finger-hotspot-btn" data-finger-id="9" data-finger-name="Anular Mano Izquierda" style="left: 33%; top: 14%;" onclick="toggleFinger(9)" title="Anular Izquierdo (ID 9)">9</div>
                                        <div class="finger-hotspot-btn" data-finger-id="8" data-finger-name="Medio Mano Izquierda" style="left: 49%; top: 8%;" onclick="toggleFinger(8)" title="Medio Izquierdo (ID 8)">8</div>
                                        <div class="finger-hotspot-btn" data-finger-id="7" data-finger-name="Índice Mano Izquierda" style="left: 66%; top: 15%;" onclick="toggleFinger(7)" title="Índice Izquierdo (ID 7)">7</div>
                                        <div class="finger-hotspot-btn" data-finger-id="6" data-finger-name="Pulgar Mano Izquierda" style="left: 88%; top: 51%;" onclick="toggleFinger(6)" title="Pulgar Izquierdo (ID 6)">6</div>
                                    </div>
                                </div>

                                <!-- Botones de Dedos Mano Izquierda -->
                                <div class="mt-2">
                                    <button type="button" class="finger-btn-chip" data-finger-id="6" onclick="toggleFinger(6)">
                                        <span><i class="fa-solid fa-fingerprint mr-1 text-secondary"></i> Pulgar Izquierdo</span>
                                        <span class="chip-badge">ID 6</span>
                                    </button>
                                    <button type="button" class="finger-btn-chip" data-finger-id="7" onclick="toggleFinger(7)">
                                        <span><i class="fa-solid fa-fingerprint mr-1 text-secondary"></i> Índice Izquierdo</span>
                                        <span class="chip-badge">ID 7</span>
                                    </button>
                                    <button type="button" class="finger-btn-chip" data-finger-id="8" onclick="toggleFinger(8)">
                                        <span><i class="fa-solid fa-fingerprint mr-1 text-secondary"></i> Medio Izquierdo</span>
                                        <span class="chip-badge">ID 8</span>
                                    </button>
                                    <button type="button" class="finger-btn-chip" data-finger-id="9" onclick="toggleFinger(9)">
                                        <span><i class="fa-solid fa-fingerprint mr-1 text-secondary"></i> Anular Izquierdo</span>
                                        <span class="chip-badge">ID 9</span>
                                    </button>
                                    <button type="button" class="finger-btn-chip" data-finger-id="10" onclick="toggleFinger(10)">
                                        <span><i class="fa-solid fa-fingerprint mr-1 text-secondary"></i> Meñique Izquierdo</span>
                                        <span class="chip-badge">ID 10</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- MANO DERECHA -->
                        <div class="col-md-6 mb-3">
                            <div class="hand-box">
                                <div>
                                    <h6 class="font-weight-bold text-dark small mb-2 d-flex align-items-center justify-content-center">
                                        <i class="fa-solid fa-hand mr-1 text-secondary" style="transform: scaleX(-1);"></i> Mano Derecha (IDs 1 - 5)
                                    </h6>

                                    <!-- MANO DERECHA REALISTA CON PUNTOS INTERACTIVOS (1=Pulgar ... 5=Meñique) -->
                                    <div class="hand-stage">
                                        <img src="<?= asset('img/hand_right.jpg') ?>" class="hand-img" alt="Mano Derecha Realista" onerror="this.src='img/hand_right.jpg'">
                                        <!-- Yemas interactivas sobre los dedos reales -->
                                        <div class="finger-hotspot-btn" data-finger-id="1" data-finger-name="Pulgar Mano Derecha" style="left: 12%; top: 51%;" onclick="toggleFinger(1)" title="Pulgar Derecho (ID 1)">1</div>
                                        <div class="finger-hotspot-btn is-active" data-finger-id="2" data-finger-name="Índice Mano Derecha" style="left: 34%; top: 15%;" onclick="toggleFinger(2)" title="Índice Derecho (ID 2 - Sugerido)">2</div>
                                        <div class="finger-hotspot-btn" data-finger-id="3" data-finger-name="Medio Mano Derecha" style="left: 51%; top: 8%;" onclick="toggleFinger(3)" title="Medio Derecho (ID 3)">3</div>
                                        <div class="finger-hotspot-btn" data-finger-id="4" data-finger-name="Anular Mano Derecha" style="left: 67%; top: 14%;" onclick="toggleFinger(4)" title="Anular Derecho (ID 4)">4</div>
                                        <div class="finger-hotspot-btn" data-finger-id="5" data-finger-name="Meñique Mano Derecha" style="left: 83%; top: 32%;" onclick="toggleFinger(5)" title="Meñique Derecho (ID 5)">5</div>
                                    </div>
                                </div>

                                <!-- Botones de Dedos Mano Derecha -->
                                <div class="mt-2">
                                    <button type="button" class="finger-btn-chip" data-finger-id="1" onclick="toggleFinger(1)">
                                        <span><i class="fa-solid fa-fingerprint mr-1 text-secondary"></i> Pulgar Derecho</span>
                                        <span class="chip-badge">ID 1</span>
                                    </button>
                                    <button type="button" class="finger-btn-chip is-active" data-finger-id="2" onclick="toggleFinger(2)">
                                        <span><i class="fa-solid fa-fingerprint mr-1 text-secondary"></i> Índice Derecho <small class="text-secondary font-weight-bold">(Sugerido)</small></span>
                                        <span class="chip-badge">ID 2</span>
                                    </button>
                                    <button type="button" class="finger-btn-chip" data-finger-id="3" onclick="toggleFinger(3)">
                                        <span><i class="fa-solid fa-fingerprint mr-1 text-secondary"></i> Medio Derecho</span>
                                        <span class="chip-badge">ID 3</span>
                                    </button>
                                    <button type="button" class="finger-btn-chip" data-finger-id="4" onclick="toggleFinger(4)">
                                        <span><i class="fa-solid fa-fingerprint mr-1 text-secondary"></i> Anular Derecho</span>
                                        <span class="chip-badge">ID 4</span>
                                    </button>
                                    <button type="button" class="finger-btn-chip" data-finger-id="5" onclick="toggleFinger(5)">
                                        <span><i class="fa-solid fa-fingerprint mr-1 text-secondary"></i> Meñique Derecho</span>
                                        <span class="chip-badge">ID 5</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- BANNER INFORMATIVO DE DEDOS SELECCIONADOS -->
                    <div class="selected-finger-summary mt-2 p-3 rounded" style="background-color: #f8fafc; border: 1px solid #e2e8f0;">
                        <div class="w-100 d-flex justify-content-between align-items-center mb-1 flex-wrap" style="gap: 6px;">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle mr-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; flex-shrink: 0; background-color: #e2e8f0; color: #334155;">
                                    <i class="fa-solid fa-hands"></i>
                                </div>
                                <div>
                                    <small class="text-muted d-block" style="line-height: 1.1;">Dedos Seleccionados para Registro:</small>
                                    <span class="badge-pill-custom badge-pill-neutral font-weight-bold" id="emp_selected_fingers_count_badge">
                                         <i class="fa-solid fa-fingerprint mr-1"></i> 1 Dedo Seleccionado
                                     </span>
                                </div>
                            </div>
                            <div>
                                <span class="small text-muted" style="font-size: 80%;">
                                    <i class="fa-solid fa-mouse-pointer mr-1 text-secondary"></i> Haz clic en los dedos para agregar o quitar
                                </span>
                            </div>
                        </div>
                        <div class="w-100 pt-2 border-top" id="emp_selected_fingers_tags_container" style="border-color: #e2e8f0 !important;">
                            <!-- Tags dinámicos de dedos seleccionados -->
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN INTEGRADA: BIOMETRÍA & RELOJ ZKTECO -->
                <div class="modal-section-title">
                    <i class="fa-solid fa-fingerprint"></i> 5. Biometría & Captura Directa en Reloj
                </div>

                <div class="p-3 rounded" style="background-color: #f8fafc; border: 1px solid #e2e8f0;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="font-weight-bold text-dark small text-uppercase">Acceso y Respaldo Biométrico</span>
                        <span class="badge-pill-custom badge-pill-neutral" id="emp_inline_bio_badge">
                            <i class="fa-solid fa-fingerprint mr-1"></i> <span id="emp_inline_bio_text">0 Huellas en BDD</span>
                        </span>
                    </div>

                    <div class="row align-items-end">
                        <div class="col-md-5 mb-2">
                            <label class="small font-weight-bold text-secondary mb-1">Reloj Biométrico de Captura:</label>
                            <select id="emp_inline_device_id" class="form-control">
                                <?php if (!empty($dispositivos)): ?>
                                    <?php foreach ($dispositivos as $dev): ?>
                                        <option value="<?= $dev['id'] ?>">
                                            <?= htmlspecialchars($dev['nombre']) ?> (<?= $dev['ip'] ?>) - <?= $dev['estado_conexion'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="1">Reloj Principal (192.168.1.201)</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-7 mb-2">
                            <div class="d-flex gap-2" style="gap: 8px;">
                                <button type="button" class="btn btn-sm btn-outline-secondary flex-fill" onclick="quickRegisterInClock()" title="1. Registra el ID y Nombre del empleado en el reloj">
                                    <i class="fa-solid fa-upload mr-1"></i> 1. Enviar a Reloj
                                </button>
                                <button type="button" class="btn btn-sm btn-primary font-weight-bold flex-fill" onclick="quickEnrollFingerInClock()" title="2. Activa el sensor para colocar la huella 3 veces y respalda en MySQL">
                                    <i class="fa-solid fa-fingerprint mr-1"></i> 2. Capturar Huella
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="small text-muted mt-2" style="font-size: 82%;">
                        <i class="fa-solid fa-circle-info text-secondary mr-1"></i> <strong>Instrucciones:</strong> Ingrese el ID de Reloj y Nombres, luego presione <strong>"Capturar Huella"</strong>. El reloj ZKTeco solicitará colocar el dedo 3 veces y la huella quedará registrada en el dispositivo y guardada en MySQL.
                    </div>
                </div>

            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm px-4 font-weight-bold"><i class="fa-solid fa-floppy-disk mr-1"></i> Guardar Empleado</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DE ENROLAMIENTO BIOMÉTRICO (EMPLEADOS) -->
<div class="modal fade" id="modalBiometriaEmp" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 540px;">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold">
                    <i class="fa-solid fa-fingerprint mr-2"></i> Gestión Biométrica ZKTeco (Huella & Rostro)
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="emp_bio_user_id">
                <input type="hidden" id="emp_bio_user_name">

                <div class="p-3 rounded mb-3 text-center" style="background-color: #f8fafc; border: 1px solid #e2e8f0;">
                    <div class="h5 font-weight-bold text-dark mb-1" id="empBioDisplayUser">Empleado</div>
                    <span class="badge-pill-custom badge-pill-neutral font-monospace font-weight-bold" id="empBioDisplayCode">ID Reloj: -</span>
                </div>

                <div class="form-group mb-3">
                    <label class="small font-weight-bold text-secondary">Seleccionar Reloj Biométrico ZKTeco:</label>
                    <select id="emp_bio_device_select" class="form-control">
                        <?php if (!empty($dispositivos)): ?>
                            <?php foreach ($dispositivos as $dev): ?>
                                <option value="<?= $dev['id'] ?>">
                                    <?= htmlspecialchars($dev['nombre']) ?> (<?= $dev['ip'] ?>:<?= $dev['puerto'] ?>) - <?= $dev['estado_conexion'] ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="1">Reloj Principal (192.168.1.201)</option>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- ESTADO DE DEDOS ASIGNADOS VS CAPTURA -->
                <div class="modal-section-title">
                    <i class="fa-solid fa-hands"></i> Estado de Dedos Asignados vs Captura
                </div>
                <div class="p-3 rounded mb-3" style="background-color: #f8fafc; border: 1px solid #e2e8f0;" id="empBioFingersListContainer">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small font-weight-bold text-secondary">Verificación Biométrica:</span>
                        <span id="empBioSummaryBadge" class="badge-pill-custom badge-pill-neutral">Consultando...</span>
                    </div>
                    <div id="empBioFingersList" class="d-flex flex-column" style="gap: 6px;">
                        <div class="text-center text-muted small py-2">
                            <i class="fa-solid fa-spinner fa-spin mr-1"></i> Verificando dedos asignados...
                        </div>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label class="small font-weight-bold text-secondary">Dedo a Enrolar en Reloj:</label>
                    <select id="emp_bio_finger_select" class="form-control">
                        <optgroup label="Mano Derecha">
                            <option value="1">Pulgar Derecho (ID 1)</option>
                            <option value="2" selected>Índice Derecho (ID 2 - Sugerido)</option>
                            <option value="3">Medio Derecho (ID 3)</option>
                            <option value="4">Anular Derecho (ID 4)</option>
                            <option value="5">Meñique Derecho (ID 5)</option>
                        </optgroup>
                        <optgroup label="Mano Izquierda">
                            <option value="6">Pulgar Izquierdo (ID 6)</option>
                            <option value="7">Índice Izquierdo (ID 7)</option>
                            <option value="8">Medio Izquierdo (ID 8)</option>
                            <option value="9">Anular Izquierdo (ID 9)</option>
                            <option value="10">Meñique Izquierdo (ID 10)</option>
                        </optgroup>
                    </select>
                    <small class="text-muted d-block mt-1">Seleccione el dedo para activar el sensor del reloj biométrico.</small>
                </div>

                <!-- ESTADO EN BDD -->
                <div class="modal-section-title">
                    <i class="fa-solid fa-database"></i> Estado en Base de Datos (MySQL)
                </div>
                <div class="p-3 rounded mb-3" style="background-color: #f8fafc; border: 1px solid #e2e8f0;">
                    <div id="empBioStatusLoading" class="text-center text-muted small py-2">
                        <i class="fa-solid fa-spinner fa-spin mr-1"></i> Consultando plantillas biométricas...
                    </div>
                    <div id="empBioStatusContent" style="display: none;">
                        <div class="d-flex justify-content-around text-center">
                            <div>
                                <i class="fa-solid fa-fingerprint fa-2x text-secondary mb-1"></i>
                                <div class="font-weight-bold h6 mb-0" id="empBioCountHuellas">0</div>
                                <small class="text-muted">Huellas en BDD</small>
                            </div>
                            <div class="border-left"></div>
                            <div>
                                <i class="fa-solid fa-camera fa-2x text-secondary mb-1"></i>
                                <div class="font-weight-bold h6 mb-0" id="empBioCountFacial">0</div>
                                <small class="text-muted">Rostro Facial</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ACCIONES CON EL RELOJ BIOMÉTRICO -->
                <div class="modal-section-title">
                    <i class="fa-solid fa-bolt"></i> Acciones de Captura y Sincronización
                </div>
                <div class="d-flex flex-column mb-0" style="gap: 8px;">
                    <button type="button" class="btn btn-outline-secondary btn-block btn-sm text-left py-2 px-3" onclick="sendEmpToClock()">
                        <i class="fa-solid fa-upload mr-2 text-secondary"></i> <strong>1. Registrar y Enviar Empleado al Reloj</strong>
                        <div class="small text-muted pl-4">Crea el ID y nombre del empleado en la memoria del reloj biométrico.</div>
                    </button>

                    <button type="button" class="btn btn-outline-secondary btn-block btn-sm text-left py-2 px-3" onclick="triggerEnrollEmpFinger()">
                        <i class="fa-solid fa-fingerprint mr-2 text-secondary"></i> <strong>2. Capturar Huella Dactilar en Reloj</strong>
                        <div class="small text-muted pl-4">Activa el sensor del reloj para que el empleado coloque el dedo seleccionado 3 veces.</div>
                    </button>

                    <button type="button" class="btn btn-outline-secondary btn-block btn-sm text-left py-2 px-3" onclick="syncEmpTemplatesToDB()">
                        <i class="fa-solid fa-cloud-arrow-down mr-2 text-secondary"></i> <strong>3. Respaldar Huellas y Rostro a la BDD (MySQL)</strong>
                        <div class="small text-muted pl-4">Descarga las plantillas del reloj y las guarda en la tabla `plantillas_biometricas`.</div>
                    </button>
                </div>

            </div>
            <div class="modal-footer justify-content-end">
                <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
// MAPEO OFICIAL DE DEDOS ZKTECO (IDs 1 al 10)
var FINGER_MAP = {
    1: 'Pulgar Mano Derecha',
    2: 'Índice Mano Derecha',
    3: 'Medio Mano Derecha',
    4: 'Anular Mano Derecha',
    5: 'Meñique Mano Derecha',
    6: 'Pulgar Mano Izquierda',
    7: 'Índice Mano Izquierda',
    8: 'Medio Mano Izquierda',
    9: 'Anular Mano Izquierda',
    10: 'Meñique Mano Izquierda'
};

var selectedFingers = [2];

function toggleFinger(fingerId) {
    fingerId = parseInt(fingerId);
    if (isNaN(fingerId) || fingerId < 1 || fingerId > 10) return;

    var idx = selectedFingers.indexOf(fingerId);
    if (idx > -1) {
        selectedFingers.splice(idx, 1);
    } else {
        selectedFingers.push(fingerId);
    }
    selectedFingers.sort(function(a, b) { return a - b; });
    updateFingerUI();
}

function selectFinger(fingerId) {
    toggleFinger(fingerId);
}

function setFingersPreset(preset) {
    if (preset === 'sugerido') {
        selectedFingers = [2];
    } else if (preset === 'indices') {
        selectedFingers = [2, 7];
    } else if (preset === 'pulgares') {
        selectedFingers = [1, 6];
    } else if (preset === 'todos') {
        selectedFingers = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
    } else if (preset === 'limpiar') {
        selectedFingers = [];
    }
    updateFingerUI();
}

function updateFingerUI() {
    // 1. Actualizar inputs ocultos del formulario
    var primaryId = selectedFingers.length > 0 ? selectedFingers[0] : 2;
    var primaryName = FINGER_MAP[primaryId] || 'Índice Mano Derecha';
    var dedosStr = selectedFingers.join(',');
    var dedosNombres = selectedFingers.map(function(id) { return FINGER_MAP[id]; }).join(', ');

    var inputId = document.getElementById('emp_dedo_reloj');
    var inputName = document.getElementById('emp_dedo_nombre');
    var inputDedos = document.getElementById('emp_dedos_reloj');
    var inputDedosNom = document.getElementById('emp_dedos_nombre');

    if (inputId) inputId.value = primaryId;
    if (inputName) inputName.value = primaryName;
    if (inputDedos) inputDedos.value = dedosStr;
    if (inputDedosNom) inputDedosNom.value = dedosNombres;

    // 2. Resaltar puntos táctiles (hotspots) en las manos realistas
    document.querySelectorAll('.finger-hotspot-btn').forEach(function(el) {
        var fid = parseInt(el.getAttribute('data-finger-id'));
        if (selectedFingers.includes(fid)) {
            el.classList.add('is-active');
        } else {
            el.classList.remove('is-active');
        }
    });

    // 3. Resaltar chips en las listas de dedos
    document.querySelectorAll('.finger-btn-chip').forEach(function(btn) {
        var fid = parseInt(btn.getAttribute('data-finger-id'));
        if (selectedFingers.includes(fid)) {
            btn.classList.add('is-active');
        } else {
            btn.classList.remove('is-active');
        }
    });

    // 4. Actualizar Banner Informativo y Tags de selección
    var countBadge = document.getElementById('emp_selected_fingers_count_badge');
    var tagsContainer = document.getElementById('emp_selected_fingers_tags_container');

    if (countBadge) {
        if (selectedFingers.length === 0) {
            countBadge.className = 'badge badge-warning px-2 py-1 font-weight-bold';
            countBadge.innerHTML = '<i class="fa-solid fa-triangle-exclamation mr-1"></i> 0 Dedos Seleccionados';
        } else {
            countBadge.className = 'badge badge-success px-2 py-1 font-weight-bold';
            countBadge.innerHTML = '<i class="fa-solid fa-fingerprint mr-1"></i> ' + selectedFingers.length + ' Dedo(s) Seleccionado(s)';
        }
    }

    if (tagsContainer) {
        if (selectedFingers.length === 0) {
            tagsContainer.innerHTML = '<span class="text-warning small d-block py-1"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Sin dedos seleccionados. Haz clic en las manos o chips para seleccionar uno o varios dedos.</span>';
        } else {
            var html = '';
            selectedFingers.forEach(function(id) {
                var isLeft = id >= 6;
                var sideColor = isLeft ? 'text-info' : 'text-primary';
                html += '<span class="selected-finger-tag" title="Haz clic en la X para quitar este dedo">' +
                        '<i class="fa-solid fa-check text-success mr-1" style="font-size: 0.7rem;"></i>' +
                        '<strong class="' + sideColor + ' mr-1">ID ' + id + ':</strong> ' + FINGER_MAP[id] +
                        '<span class="tag-remove-btn" onclick="event.stopPropagation(); toggleFinger(' + id + ');" title="Quitar">&times;</span>' +
                        '</span>';
            });
            tagsContainer.innerHTML = html;
        }
    }
}

function openNewEmpleadoModal() {
    document.getElementById('empModalTitle').innerText = 'Registrar Nuevo Empleado';
    document.getElementById('emp_id').value = '';
    document.getElementById('emp_codigo_reloj').value = '<?= $siguienteCodigo ?>';
    document.getElementById('emp_dni').value = '';
    document.getElementById('emp_nombres').value = '';
    document.getElementById('emp_apellidos').value = '';
    document.getElementById('emp_email').value = '';
    document.getElementById('emp_telefono').value = '';
    document.getElementById('emp_departamento_id').value = '';
    document.getElementById('emp_cargo_id').value = '';
    document.getElementById('emp_turno_id').value = '1';
    document.getElementById('emp_fecha_ingreso').value = '<?= date('Y-m-d') ?>';
    document.getElementById('emp_activo').checked = true;

    // Resetear selección de dedos a predeterminado (Índice Mano Derecha - ID 2)
    setFingersPreset('sugerido');

    // Reset inline bio
    document.getElementById('emp_inline_bio_text').innerText = '0 Huellas en BDD';
    $('#modalEmpleado').modal('show');
}

function openEditEmpleadoModal(e) {
    document.getElementById('empModalTitle').innerText = 'Editar Datos de Empleado';
    document.getElementById('emp_id').value = e.id;
    document.getElementById('emp_codigo_reloj').value = e.codigo_reloj;
    document.getElementById('emp_dni').value = e.dni;
    document.getElementById('emp_nombres').value = e.nombres;
    document.getElementById('emp_apellidos').value = e.apellidos;
    document.getElementById('emp_email').value = e.email || '';
    document.getElementById('emp_telefono').value = e.telefono || '';
    document.getElementById('emp_departamento_id').value = e.departamento_id || '';
    document.getElementById('emp_cargo_id').value = e.cargo_id || '';
    document.getElementById('emp_turno_id').value = e.turno_id || '';
    document.getElementById('emp_fecha_ingreso').value = e.fecha_ingreso || '';
    document.getElementById('emp_activo').checked = (parseInt(e.activo) === 1);

    // Cargar dedos registrados del empleado (múltiples o individual)
    if (e.dedos_reloj) {
        var parts = String(e.dedos_reloj).split(',');
        selectedFingers = parts.map(function(n) { return parseInt(n.trim()); }).filter(function(n) { return !isNaN(n) && n >= 1 && n <= 10; });
        if (selectedFingers.length === 0) selectedFingers = [2];
    } else if (e.dedo_reloj !== undefined && e.dedo_reloj !== null && e.dedo_reloj !== '') {
        selectedFingers = [parseInt(e.dedo_reloj)];
    } else {
        selectedFingers = [2];
    }
    updateFingerUI();

    var count = parseInt(e.huellas_count || 0);
    document.getElementById('emp_inline_bio_text').innerText = count + ' Huella(s) en BDD';

    $('#modalEmpleado').modal('show');
}

// CAPTURA RÁPIDA DIRECTA DENTRO DEL FORMULARIO DE EMPLEADO
function quickRegisterInClock() {
    var devId = document.getElementById('emp_inline_device_id').value;
    var userId = document.getElementById('emp_codigo_reloj').value.trim();
    var nombres = document.getElementById('emp_nombres').value.trim();
    var apellidos = document.getElementById('emp_apellidos').value.trim();
    var fullName = (nombres + ' ' + apellidos).trim();

    if (!userId) {
        Swal.fire('Atención', 'Por favor ingresa primero el <strong>ID de Usuario en Reloj ZKTeco</strong>.', 'warning');
        document.getElementById('emp_codigo_reloj').focus();
        return;
    }
    if (!fullName) {
        Swal.fire('Atención', 'Por favor ingresa los <strong>Nombres y Apellidos</strong> del empleado.', 'warning');
        document.getElementById('emp_nombres').focus();
        return;
    }

    Swal.fire({
        title: 'Registrando en Reloj Biométrico...',
        text: 'Enviando ID: ' + userId + ' (' + fullName + ') al dispositivo.',
        allowOutsideClick: false,
        didOpen: function() { Swal.showLoading(); }
    });

    var fd = new FormData();
    fd.append('device_id', devId);
    fd.append('user_id', userId);
    fd.append('name', fullName);
    fd.append('privilege', '0');

    fetch('?route=dispositivos&action=enviar_usuario_reloj', {
        method: 'POST',
        body: fd
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) {
            Swal.fire('¡Registrado!', res.message || 'Empleado guardado en la memoria del reloj con éxito.', 'success');
        } else {
            Swal.fire('Aviso', res.error || 'No se pudo comunicar con el reloj.', 'error');
        }
    })
    .catch(function() {
        Swal.fire('Error', 'Fallo de comunicación con el servidor.', 'error');
    });
}

function quickEnrollFingerInClock() {
    var devId = document.getElementById('emp_inline_device_id').value;
    var userId = document.getElementById('emp_codigo_reloj').value.trim();
    var nombres = document.getElementById('emp_nombres').value.trim();
    var apellidos = document.getElementById('emp_apellidos').value.trim();
    var fullName = (nombres + ' ' + apellidos).trim();

    if (!userId) {
        Swal.fire('Atención', 'Por favor ingresa primero el <strong>ID de Usuario en Reloj ZKTeco</strong>.', 'warning');
        document.getElementById('emp_codigo_reloj').focus();
        return;
    }

    if (selectedFingers.length === 0) {
        Swal.fire('Atención', 'Por favor selecciona al menos 1 dedo en las manos antes de iniciar la captura.', 'warning');
        return;
    }

    // Si tiene múltiples dedos seleccionados, permitir escoger cuál enrolar en este paso
    if (selectedFingers.length > 1) {
        var optionsHtml = '';
        selectedFingers.forEach(function(fid, idx) {
            optionsHtml += '<option value="' + fid + '" ' + (idx === 0 ? 'selected' : '') + '>ID ' + fid + ' - ' + FINGER_MAP[fid] + '</option>';
        });

        Swal.fire({
            title: '🖐️ Selecciona el Dedo a Capturar',
            html: '<p class="small text-muted mb-2">El empleado tiene <strong>' + selectedFingers.length + ' dedos asignados</strong>. Elige cuál deseas enrolar en el sensor en este momento:</p>' +
                  '<select id="swal_enroll_finger_select" class="form-control form-control-sm mb-2">' + optionsHtml + '</select>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-fingerprint mr-1"></i> Iniciar Captura',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#16a34a'
        }).then(function(result) {
            if (result.isConfirmed) {
                var selectEl = document.getElementById('swal_enroll_finger_select');
                var chosenFinger = selectEl ? parseInt(selectEl.value) : selectedFingers[0];
                proceedQuickEnroll(devId, userId, fullName, chosenFinger);
            }
        });
    } else {
        proceedQuickEnroll(devId, userId, fullName, selectedFingers[0]);
    }
}

function proceedQuickEnroll(devId, userId, fullName, fingerId) {
    var fingerName = FINGER_MAP[fingerId] || ('Dedo ' + fingerId);

    // 1. Enviar usuario primero si tiene nombre
    if (fullName) {
        var fdUser = new FormData();
        fdUser.append('device_id', devId);
        fdUser.append('user_id', userId);
        fdUser.append('name', fullName);
        fdUser.append('privilege', '0');
        fetch('?route=dispositivos&action=enviar_usuario_reloj', { method: 'POST', body: fdUser });
    }

    // 2. Activar modo captura en el reloj para el dedo exacto seleccionado
    var fdEnroll = new FormData();
    fdEnroll.append('device_id', devId);
    fdEnroll.append('user_id', userId);
    fdEnroll.append('temp_id', fingerId);
    fetch('?route=dispositivos&action=enrolar_huella', { method: 'POST', body: fdEnroll });

    // 3. Mostrar modal interactivo al operador indicando el dedo seleccionado
    Swal.fire({
        title: '🖐️ ¡Coloca el dedo en el sensor del reloj!',
        html: '<div class="text-left">' +
              '<p>Se ha activado el sensor óptico en el reloj biométrico para el usuario <strong>ID ' + userId + '</strong>.</p>' +
              '<div class="p-2 mb-2 bg-light border rounded text-center">' +
              '<span class="badge badge-success px-2 py-1 mr-1"><i class="fa-solid fa-hand-point-right mr-1"></i> Dedo a Registrar:</span> ' +
              '<strong class="text-dark">' + fingerName + '</strong> (ID ' + fingerId + ')' +
              '</div>' +
              '<div class="alert alert-info py-2 px-3 small mb-2">' +
              '<i class="fa-solid fa-circle-info mr-1"></i> <strong>Instrucciones:</strong> Solicita al empleado que coloque su <strong>' + fingerName + '</strong> en el sensor del reloj <strong>3 veces consecutivas</strong> hasta escuchar el pitido de confirmación del reloj.' +
              '</div>' +
              '</div>',
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-cloud-arrow-down mr-1"></i> Ya colocó la huella (Respaldar en BDD)',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#16a34a'
    }).then(function(result) {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Descargando y Respaldando Huella...',
                text: 'Guardando plantilla biométrica en MySQL...',
                allowOutsideClick: false,
                didOpen: function() { Swal.showLoading(); }
            });

            var fdSync = new FormData();
            fdSync.append('device_id', devId);
            fdSync.append('user_id', userId);

            fetch('?route=dispositivos&action=sincronizar_biometria', {
                method: 'POST',
                body: fdSync
            })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    var bioText = document.getElementById('emp_inline_bio_text');
                    if (bioText) bioText.innerText = 'Huellas respaldadas en BDD';

                    // Comprobar si hay un siguiente dedo en la lista de dedos seleccionados (Enrolamiento en Cadena)
                    var currentIdx = selectedFingers.indexOf(fingerId);
                    var nextFingerId = (currentIdx !== -1 && (currentIdx + 1) < selectedFingers.length) ? selectedFingers[currentIdx + 1] : null;

                    if (nextFingerId) {
                        var nextName = FINGER_MAP[nextFingerId] || ('Dedo ' + nextFingerId);
                        Swal.fire({
                            title: '✅ ¡' + fingerName + ' Registrado!',
                            html: '<div class="text-left">' +
                                  '<p class="mb-2">La huella fue capturada en el sensor y respaldada en MySQL con éxito.</p>' +
                                  '<div class="p-3 bg-light rounded border mb-2">' +
                                  '<span class="badge badge-warning text-dark px-2 py-1 mb-1"><i class="fa-solid fa-clock mr-1"></i> Siguiente Dedo Asignado:</span>' +
                                  '<div class="h6 font-weight-bold text-dark mt-1 mb-0"><i class="fa-solid fa-hand-point-right text-primary mr-1"></i> ' + nextName + ' (ID ' + nextFingerId + ')</div>' +
                                  '</div>' +
                                  '<p class="small text-muted mb-0">¿Deseas activar el sensor del reloj biométrico para enrolar este siguiente dedo ahora mismo?</p>' +
                                  '</div>',
                            icon: 'success',
                            showCancelButton: true,
                            confirmButtonText: '<i class="fa-solid fa-fingerprint mr-1"></i> Sí, Enrolar ' + nextName,
                            cancelButtonText: 'Terminar por ahora',
                            confirmButtonColor: '#16a34a',
                            cancelButtonColor: '#6c757d'
                        }).then(function(chainRes) {
                            if (chainRes.isConfirmed) {
                                proceedQuickEnroll(devId, userId, fullName, nextFingerId);
                            }
                        });
                    } else {
                        Swal.fire({
                            title: '🎉 ¡Biometría Multidedo Completa!',
                            html: 'Se han capturado y respaldado exitosamente todas las huellas seleccionadas (<strong>' + selectedFingers.length + ' dedo(s)</strong>) en el reloj ZKTeco y en la base de datos MySQL.',
                            icon: 'success',
                            confirmButtonColor: '#16a34a'
                        });
                    }
                } else {
                    Swal.fire('Aviso', res.error || 'No se pudo descargar la huella.', 'warning');
                }
            })
            .catch(function() {
                Swal.fire('Error', 'Fallo al sincronizar huella.', 'error');
            });
        }
    });
}

var activeEmpBio = null;

function openEmpBiometricModal(e) {
    activeEmpBio = e;
    var fullName = (e.nombres || '') + ' ' + (e.apellidos || '');
    document.getElementById('emp_bio_user_id').value = e.codigo_reloj;
    document.getElementById('emp_bio_user_name').value = fullName.trim();
    document.getElementById('empBioDisplayUser').innerText = fullName.trim();
    document.getElementById('empBioDisplayCode').innerText = 'Código en Reloj: ' + e.codigo_reloj;

    var fingerSelect = document.getElementById('emp_bio_finger_select');
    if (fingerSelect) {
        var defaultF = '2';
        if (e.dedos_reloj) {
            var parts = String(e.dedos_reloj).split(',');
            if (parts.length > 0 && parts[0]) defaultF = String(parts[0]);
        } else if (e.dedo_reloj) {
            defaultF = String(e.dedo_reloj);
        }
        fingerSelect.value = defaultF;
    }

    loadEmpBiometricStatus(e.codigo_reloj);
    $('#modalBiometriaEmp').modal('show');
}

function loadEmpBiometricStatus(userId, offerChainNext) {
    document.getElementById('empBioStatusLoading').style.display = 'block';
    document.getElementById('empBioStatusContent').style.display = 'none';

    fetch('?route=dispositivos&action=obtener_biometria_usuario&user_id=' + encodeURIComponent(userId))
        .then(function(res) { return res.json(); })
        .then(function(data) {
            document.getElementById('empBioStatusLoading').style.display = 'none';
            document.getElementById('empBioStatusContent').style.display = 'block';
            if (data.success) {
                document.getElementById('empBioCountHuellas').innerText = data.huellas_count || 0;
                document.getElementById('empBioCountFacial').innerText = (data.facial_count > 0 ? 'Registrado' : 'No');

                // Procesar huellas descargadas (dedo_indice 0..9)
                var plantillas = data.plantillas || [];
                var storedZkIndices = [];
                plantillas.forEach(function(p) {
                    if (p.tipo === 'HUELLA' && p.dedo_indice !== null && p.dedo_indice !== undefined) {
                        storedZkIndices.push(parseInt(p.dedo_indice));
                    }
                });

                // Obtener dedos asignados al empleado (IDs 1..10)
                var rawAssigned = (activeEmpBio && activeEmpBio.dedos_reloj) ? String(activeEmpBio.dedos_reloj) : (activeEmpBio && activeEmpBio.dedo_reloj ? String(activeEmpBio.dedo_reloj) : '2');
                var assignedFingers = rawAssigned.split(',').map(function(s){ return parseInt(s.trim()); }).filter(function(n){ return !isNaN(n) && n >= 1 && n <= 10; });
                if (assignedFingers.length === 0) assignedFingers = [2];

                var listContainer = document.getElementById('empBioFingersList');
                var summaryBadge = document.getElementById('empBioSummaryBadge');
                var capturedCount = 0;
                var htmlList = '';
                var pendingFingers = [];

                assignedFingers.forEach(function(fid) {
                    var zkIdx = fid - 1;
                    var isCaptured = storedZkIndices.indexOf(zkIdx) !== -1;
                    var fName = FINGER_MAP[fid] || ('Dedo ' + fid);

                    if (isCaptured) {
                        capturedCount++;
                        htmlList += '<div class="d-flex align-items-center justify-content-between p-2 border rounded bg-white shadow-xs">' +
                                        '<div class="d-flex align-items-center">' +
                                            '<span class="badge badge-success mr-2 p-1"><i class="fa-solid fa-check"></i></span>' +
                                            '<div>' +
                                                '<div class="font-weight-bold text-dark small mb-0">' + fName + ' <span class="badge badge-light border">ID ' + fid + '</span></div>' +
                                                '<small class="text-success font-weight-bold"><i class="fa-solid fa-shield-check mr-1"></i>Huella capturada y respaldada en MySQL</small>' +
                                            '</div>' +
                                        '</div>' +
                                        '<div>' +
                                            '<button type="button" class="btn btn-xs btn-outline-secondary" onclick="directEnrollFinger(' + fid + ')" title="Volver a capturar huella en reloj">' +
                                                '<i class="fa-solid fa-rotate mr-1"></i> Re-capturar' +
                                            '</button>' +
                                        '</div>' +
                                    '</div>';
                    } else {
                        pendingFingers.push(fid);
                        htmlList += '<div class="d-flex align-items-center justify-content-between p-2 border rounded bg-white shadow-xs" style="border-left: 4px solid #f59e0b !important;">' +
                                        '<div class="d-flex align-items-center">' +
                                            '<span class="badge badge-warning text-dark mr-2 p-1"><i class="fa-regular fa-clock"></i></span>' +
                                            '<div>' +
                                                '<div class="font-weight-bold text-dark small mb-0">' + fName + ' <span class="badge badge-light border">ID ' + fid + '</span></div>' +
                                                '<small class="text-warning-dark font-weight-bold" style="color: #b45309;"><i class="fa-solid fa-circle-exclamation mr-1"></i>Pendiente de captura en sensor</small>' +
                                            '</div>' +
                                        '</div>' +
                                        '<div>' +
                                            '<button type="button" class="btn btn-xs btn-success font-weight-bold shadow-xs px-2" onclick="directEnrollFinger(' + fid + ')" title="Iniciar captura de este dedo en el reloj">' +
                                                '<i class="fa-solid fa-fingerprint mr-1"></i> Capturar Dedo' +
                                            '</button>' +
                                        '</div>' +
                                    '</div>';
                    }
                });

                if (listContainer) listContainer.innerHTML = htmlList;

                if (summaryBadge) {
                    if (capturedCount === assignedFingers.length && capturedCount > 0) {
                        summaryBadge.className = 'badge badge-success px-2 py-0.5';
                        summaryBadge.innerHTML = '<i class="fa-solid fa-check-circle mr-1"></i> Completo (' + capturedCount + '/' + assignedFingers.length + ' en BDD)';
                    } else if (capturedCount > 0) {
                        summaryBadge.className = 'badge badge-warning text-dark px-2 py-0.5';
                        summaryBadge.innerHTML = '<i class="fa-solid fa-triangle-exclamation mr-1"></i> Parcial (' + capturedCount + '/' + assignedFingers.length + ' en BDD)';
                    } else {
                        summaryBadge.className = 'badge badge-secondary px-2 py-0.5';
                        summaryBadge.innerHTML = '<i class="fa-solid fa-clock mr-1"></i> 0/' + assignedFingers.length + ' Pendiente';
                    }
                }

                // Si viene de un enrolamiento reciente, ofrecer el siguiente dedo automáticamente (Opción A)
                if (offerChainNext === true) {
                    if (pendingFingers.length > 0) {
                        var nextPendingFid = pendingFingers[0];
                        var nextPendingName = FINGER_MAP[nextPendingFid] || ('Dedo ' + nextPendingFid);
                        Swal.fire({
                            title: '✅ ¡Huella Respaldada en MySQL!',
                            html: '<div class="text-left">' +
                                  '<p class="mb-2">La plantilla fue guardada con éxito en la base de datos.</p>' +
                                  '<div class="alert alert-warning py-2 px-3 small mb-2">' +
                                  '<i class="fa-solid fa-clock mr-1"></i> Aún tienes <strong>' + pendingFingers.length + ' dedo(s) pendiente(s)</strong> de registro.' +
                                  '</div>' +
                                  '<div class="p-2 bg-light rounded border mb-2">' +
                                  '<span class="badge badge-warning text-dark mr-1"><i class="fa-solid fa-hand-point-right mr-1"></i> Siguiente Dedo:</span> ' +
                                  '<strong class="text-dark">' + nextPendingName + ' (ID ' + nextPendingFid + ')</strong>' +
                                  '</div>' +
                                  '<p class="small text-muted mb-0">¿Deseas activar el sensor del reloj para enrolar <strong>' + nextPendingName + '</strong> ahora mismo?</p>' +
                                  '</div>',
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonText: '<i class="fa-solid fa-fingerprint mr-1"></i> Sí, Enrolar ' + nextPendingName,
                            cancelButtonText: 'Terminar por ahora',
                            confirmButtonColor: '#16a34a',
                            cancelButtonColor: '#6c757d'
                        }).then(function(nextRes) {
                            if (nextRes.isConfirmed) {
                                directEnrollFinger(nextPendingFid);
                            }
                        });
                    } else {
                        Swal.fire({
                            title: '🎉 ¡Biometría 100% Completa!',
                            html: 'Todas las huellas asignadas para este empleado ya están registradas en el reloj y respaldadas en la base de datos MySQL.',
                            icon: 'success',
                            confirmButtonColor: '#16a34a'
                        });
                    }
                }
            }
        })
        .catch(function() {
            document.getElementById('empBioStatusLoading').style.display = 'none';
            document.getElementById('empBioStatusContent').style.display = 'block';
            document.getElementById('empBioCountHuellas').innerText = '-';
            document.getElementById('empBioCountFacial').innerText = '-';
        });
}

function directEnrollFinger(fingerId) {
    var fingerSelect = document.getElementById('emp_bio_finger_select');
    if (fingerSelect) {
        fingerSelect.value = String(fingerId);
    }
    triggerEnrollEmpFinger();
}

function sendEmpToClock() {
    var devId = document.getElementById('emp_bio_device_select').value;
    var userId = document.getElementById('emp_bio_user_id').value;
    var name = document.getElementById('emp_bio_user_name').value;

    Swal.fire({
        title: 'Enviando al Reloj Biométrico...',
        text: 'Registrando empleado en la memoria del dispositivo.',
        allowOutsideClick: false,
        didOpen: function() { Swal.showLoading(); }
    });

    var fd = new FormData();
    fd.append('device_id', devId);
    fd.append('user_id', userId);
    fd.append('name', name);
    fd.append('privilege', '0');

    fetch('?route=dispositivos&action=enviar_usuario_reloj', {
        method: 'POST',
        body: fd
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) {
            Swal.fire('¡Registrado!', res.message || 'Empleado creado en el reloj con éxito.', 'success');
        } else {
            Swal.fire('Error en el Reloj', res.error || 'No se pudo registrar.', 'error');
        }
    })
    .catch(function() {
        Swal.fire('Error', 'Fallo de comunicación con el servidor.', 'error');
    });
}

function triggerEnrollEmpFinger() {
    var devId = document.getElementById('emp_bio_device_select').value;
    var userId = document.getElementById('emp_bio_user_id').value;
    var fingerSelect = document.getElementById('emp_bio_finger_select');
    var tempId = fingerSelect ? fingerSelect.value : '2';
    var fingerName = fingerSelect ? fingerSelect.options[fingerSelect.selectedIndex].text : 'Dedo';

    Swal.fire({
        title: '¡Coloca el dedo en el sensor!',
        html: 'Se ha activado el modo de captura para <strong>' + fingerName + '</strong>.<br><strong>El empleado debe colocar su dedo en el sensor óptico del reloj biométrico 3 veces consecutivas.</strong>',
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-check mr-1"></i> Ya colocó la huella',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#16a34a'
    }).then(function(result) {
        if (result.isConfirmed) {
            syncEmpTemplatesToDB(true);
        }
    });

    var fd = new FormData();
    fd.append('device_id', devId);
    fd.append('user_id', userId);
    fd.append('temp_id', tempId);

    fetch('?route=dispositivos&action=enrolar_huella', {
        method: 'POST',
        body: fd
    });
}

function syncEmpTemplatesToDB(offerChainNext) {
    var devId = document.getElementById('emp_bio_device_select').value;
    var userId = document.getElementById('emp_bio_user_id').value;

    Swal.fire({
        title: 'Descargando y Respaldando Biometría...',
        text: 'Guardando huellas y rostros del reloj en la base de datos MySQL.',
        allowOutsideClick: false,
        didOpen: function() { Swal.showLoading(); }
    });

    var fd = new FormData();
    fd.append('device_id', devId);
    fd.append('user_id', userId);

    fetch('?route=dispositivos&action=sincronizar_biometria', {
        method: 'POST',
        body: fd
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) {
            loadEmpBiometricStatus(userId, offerChainNext === true);
            if (!offerChainNext) {
                Swal.fire('¡Respaldado!', res.message || 'Plantillas biométricas guardadas en MySQL exitosamente.', 'success');
            }
        } else {
            Swal.fire('Aviso', res.error || 'No se pudieron descargar las plantillas.', 'warning');
        }
    })
    .catch(function() {
        Swal.fire('Error', 'Fallo al procesar sincronización biométrica.', 'error');
    });
}
</script>
<?php endif; ?>

<?php require_once APP_ROOT . '/views/layout/footer.php'; ?>


