<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Services\AttendanceCalculator;

class JustificacionesController {
    public function index(): void {
        AuthController::checkAuth();
        $userRole = AuthController::role();
        $currentUser = AuthController::user();
        $supervisorDeptoId = (int)($currentUser['departamento_id'] ?? 0);

        $todasFechas = isset($_GET['todas_fechas']) && $_GET['todas_fechas'] === '1';
        $fechaInicio = !empty($_GET['fecha_inicio']) ? trim($_GET['fecha_inicio']) : ($todasFechas ? '' : date('Y-m-01'));
        $fechaFin = !empty($_GET['fecha_fin']) ? trim($_GET['fecha_fin']) : ($todasFechas ? '' : date('Y-m-d'));
        if (!empty($fechaInicio) && !empty($fechaFin) && $fechaInicio > $fechaFin) {
            $tmp = $fechaInicio;
            $fechaInicio = $fechaFin;
            $fechaFin = $tmp;
        }

        $estado = !empty($_GET['estado']) ? strtoupper(trim($_GET['estado'])) : 'TODOS';
        $tipo = !empty($_GET['tipo']) ? trim($_GET['tipo']) : 'TODOS';
        $deptoId = !empty($_GET['departamento_id']) ? (int)$_GET['departamento_id'] : null;
        if ($userRole === 'SUPERVISOR' && $supervisorDeptoId > 0) {
            $deptoId = $supervisorDeptoId;
        }
        $search = !empty($_GET['search']) ? trim($_GET['search']) : null;

        $sql = "
            SELECT j.*, e.nombres, e.apellidos, e.dni, e.codigo_reloj, d.nombre as departamento_nombre
            FROM justificaciones j
            JOIN empleados e ON j.id_empleado = e.id
            LEFT JOIN departamentos d ON e.departamento_id = d.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($fechaInicio) && !empty($fechaFin)) {
            $sql .= " AND (j.fecha_inicio <= :f2 AND j.fecha_fin >= :f1)";
            $params[':f1'] = $fechaInicio;
            $params[':f2'] = $fechaFin;
        } elseif (!empty($fechaInicio)) {
            $sql .= " AND j.fecha_fin >= :f1";
            $params[':f1'] = $fechaInicio;
        } elseif (!empty($fechaFin)) {
            $sql .= " AND j.fecha_inicio <= :f2";
            $params[':f2'] = $fechaFin;
        }

        // Alcance Departamental
        if ($deptoId) {
            $sql .= " AND e.departamento_id = :depto_id";
            $params[':depto_id'] = $deptoId;
        }

        if ($estado !== 'TODOS') {
            $sql .= " AND j.estado = :estado";
            $params[':estado'] = $estado;
        }

        if ($tipo !== 'TODOS') {
            $sql .= " AND j.tipo = :tipo";
            $params[':tipo'] = $tipo;
        }

        if ($search) {
            $sql .= " AND (e.nombres LIKE :s1 OR e.apellidos LIKE :s2 OR e.dni LIKE :s3 OR e.codigo_reloj LIKE :s4 OR CONCAT(e.apellidos, ' ', e.nombres) LIKE :s5 OR CONCAT(e.nombres, ' ', e.apellidos) LIKE :s6 OR j.motivo LIKE :s7)";
            $params[':s1'] = "%$search%";
            $params[':s2'] = "%$search%";
            $params[':s3'] = "%$search%";
            $params[':s4'] = "%$search%";
            $params[':s5'] = "%$search%";
            $params[':s6'] = "%$search%";
            $params[':s7'] = "%$search%";
        }

        $sql .= " ORDER BY j.creado_en DESC";

        $justificaciones = Database::query($sql, $params);

        // Listado de empleados para el modal de registro
        if ($userRole === 'SUPERVISOR' && $supervisorDeptoId > 0) {
            $empleados = Database::query("SELECT id, codigo_reloj, dni, nombres, apellidos FROM empleados WHERE activo = 1 AND departamento_id = ? ORDER BY apellidos ASC", [$supervisorDeptoId]);
            $departamentos = Database::query("SELECT * FROM departamentos WHERE id = ?", [$supervisorDeptoId]);
        } else {
            $empleados = Database::query("SELECT id, codigo_reloj, dni, nombres, apellidos FROM empleados WHERE activo = 1 ORDER BY apellidos ASC");
            $departamentos = Database::query("SELECT * FROM departamentos WHERE activo = 1 ORDER BY nombre ASC");
        }

        // KPIs de justificaciones
        $kpiTotal = count($justificaciones);
        $kpiPendientes = 0;
        $kpiAprobadas = 0;
        $kpiRechazadas = 0;
        foreach ($justificaciones as $jItem) {
            $st = strtoupper($jItem['estado'] ?? '');
            if ($st === 'PENDIENTE') $kpiPendientes++;
            elseif ($st === 'APROBADO' || $st === 'APROBADA') $kpiAprobadas++;
            elseif ($st === 'RECHAZADO' || $st === 'RECHAZADA') $kpiRechazadas++;
        }

        require_once APP_ROOT . '/views/justificaciones/index.php';
    }

    public function guardar(): void {
        AuthController::checkAuth();
        AuthController::requireRole(['ADMIN', 'RRHH', 'SUPERVISOR'], 'justificaciones');
        \App\Csrf::validateRequest();

        $userRole = AuthController::role();
        $currentUser = AuthController::user();
        $supervisorDeptoId = (int)($currentUser['departamento_id'] ?? 0);

        $idEmpleado = (int)($_POST['id_empleado'] ?? 0);
        $tipo = $_POST['tipo'] ?? 'TARDANZA';
        $fechaInicio = $_POST['fecha_inicio'] ?? date('Y-m-d');
        $fechaFin = $_POST['fecha_fin'] ?? $fechaInicio;
        $comisionDestino = trim($_POST['comision_destino'] ?? '');
        $motivo = trim($_POST['motivo'] ?? '');

        if ($idEmpleado <= 0 || empty($motivo)) {
            header('Location: ?route=justificaciones&error=campos_requeridos');
            exit;
        }

        // Validación de alcance para SUPERVISOR: solo empleados de su departamento asignado
        if ($userRole === 'SUPERVISOR' && $supervisorDeptoId > 0) {
            $empCheck = Database::queryOne("SELECT id FROM empleados WHERE id = ? AND departamento_id = ? AND activo = 1", [$idEmpleado, $supervisorDeptoId]);
            if (!$empCheck) {
                header('Location: ?route=justificaciones&error=fuera_de_alcance');
                exit;
            }
        }

        // Validación lógica de rango de fechas
        if (strtotime($fechaInicio) > strtotime($fechaFin)) {
            header('Location: ?route=justificaciones&error=rango_invalido');
            exit;
        }

        // Manejo y Validación de Archivo Adjunto (Comprobante / Certificado Médico)
        $archivoAdjunto = null;
        if (isset($_FILES['archivo_adjunto']) && $_FILES['archivo_adjunto']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['archivo_adjunto'];
            $maxBytes = 5 * 1024 * 1024; // 5 MB
            if ($file['size'] > $maxBytes) {
                header('Location: ?route=justificaciones&error=archivo_grande');
                exit;
            }

            $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
            $originalName = $file['name'];
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedExtensions, true)) {
                header('Location: ?route=justificaciones&error=formato_invalido');
                exit;
            }

            // Validar MIME type real con fileinfo
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowedMimes = [
                'application/pdf',
                'image/jpeg',
                'image/png',
                'image/webp'
            ];

            if (!in_array($mimeType, $allowedMimes, true)) {
                header('Location: ?route=justificaciones&error=formato_invalido');
                exit;
            }

            $storageDir = APP_ROOT . '/storage/justificaciones';
            if (!file_exists($storageDir)) {
                @mkdir($storageDir, 0755, true);
                @file_put_contents($storageDir . '/.htaccess', "Options -Indexes\n<Files *.php>\nOrder Deny,Allow\nDeny from all\n</Files>\n");
            }

            $safeFilename = 'justif_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $destPath = $storageDir . '/' . $safeFilename;

            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                $archivoAdjunto = $safeFilename;
            }
        }

        // Segregación de Funciones (Separation of Duties - SoD):
        // - Si es SUPERVISOR: el estado es 'PENDIENTE' para revisión por RRHH / Admin. No se auto-aprueba ni recalcula asistencia aún.
        // - Si es ADMIN o RRHH: se aprueba de forma directa y se recalcula la asistencia.
        if ($userRole === 'SUPERVISOR') {
            $estado = 'PENDIENTE';
            $aprobadoPor = null;
            $fechaResolucion = null;
        } else {
            $estado = 'APROBADO';
            $aprobadoPor = $currentUser['nombre'] ?? 'Administración';
            $fechaResolucion = date('Y-m-d H:i:s');
        }

        try {
            Database::execute("
                INSERT INTO justificaciones 
                (id_empleado, tipo, fecha_inicio, fecha_fin, motivo, comision_destino, archivo_adjunto, estado, aprobado_por, fecha_resolucion, creado_en)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ", [$idEmpleado, $tipo, $fechaInicio, $fechaFin, $motivo, ($tipo === 'COMISION_SERVICIO' ? $comisionDestino : null), $archivoAdjunto, $estado, $aprobadoPor, $fechaResolucion]);

            // Recalcular asistencia únicamente si fue aprobada inmediatamente por RRHH/Admin
            if ($estado === 'APROBADO') {
                $calculator = new AttendanceCalculator(ATTENDANCE_DEBOUNCE_MINUTES);
                $calculator->processDateRange($fechaInicio, $fechaFin);
            }


            $msgKey = ($estado === 'PENDIENTE') ? 'solicitud_enviada' : 'guardado';
            header("Location: ?route=justificaciones&msg={$msgKey}");
            exit;
        } catch (\PDOException $e) {
            header('Location: ?route=justificaciones&error=db_error');
            exit;
        }
    }

    /**
     * Resuelve (Aprueba o Rechaza) una solicitud de justificación pendiente.
     * Exclusivo para ADMIN y RRHH.
     */
    public function resolver(): void {
        AuthController::checkAuth();
        AuthController::requireRole(['ADMIN', 'RRHH'], 'justificaciones');
        \App\Csrf::validateRequest();

        $id = (int)($_POST['id'] ?? 0);
        $nuevoEstado = $_POST['estado'] ?? 'APROBADO';
        if (!in_array($nuevoEstado, ['APROBADO', 'RECHAZADO'], true)) {
            $nuevoEstado = 'APROBADO';
        }

        $usuario = AuthController::user()['nombre'] ?? 'Administrador';

        if ($id > 0) {
            try {
                $just = Database::queryOne("SELECT * FROM justificaciones WHERE id = ?", [$id]);
                if ($just) {
                    Database::execute("
                        UPDATE justificaciones 
                        SET estado = :estado, aprobado_por = :user, fecha_resolucion = NOW()
                        WHERE id = :id
                    ", [
                        ':estado' => $nuevoEstado,
                        ':user'   => $usuario,
                        ':id'     => $id
                    ]);

                    // Recalcular asistencia en el rango de fechas de la justificación
                    $calculator = new AttendanceCalculator(ATTENDANCE_DEBOUNCE_MINUTES);
                    $calculator->processDateRange($just['fecha_inicio'], $just['fecha_fin']);
                }
                header('Location: ?route=justificaciones&msg=resuelto');
                exit;
            } catch (\PDOException $e) {
                header('Location: ?route=justificaciones&error=db_error');
                exit;
            }
        }

        header('Location: ?route=justificaciones');
        exit;
    }

    /**
     * Descarga / visualización segura de comprobantes adjuntos
     */
    public function verAdjunto(): void {
        AuthController::checkAuth();

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(404);
            echo "Archivo no especificado.";
            exit;
        }

        $just = Database::queryOne("SELECT archivo_adjunto FROM justificaciones WHERE id = ?", [$id]);
        if (!$just || empty($just['archivo_adjunto'])) {
            http_response_code(404);
            echo "No existe archivo adjunto para esta justificación.";
            exit;
        }

        $filename = basename($just['archivo_adjunto']);
        $filePath = APP_ROOT . '/storage/justificaciones/' . $filename;

        if (!file_exists($filePath)) {
            http_response_code(404);
            echo "El archivo físico no fue encontrado en el servidor.";
            exit;
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mime = match($ext) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'application/octet-stream'
        };

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($filePath));
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=3600');
        readfile($filePath);
        exit;
    }
}
