<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database;


class EmpleadosController {
    public function index(): void {
        AuthController::checkAuth();
        $userRole = AuthController::role();
        $currentUser = AuthController::user();
        $supervisorDeptoId = (int)($currentUser['departamento_id'] ?? 0);

        $deptoId = !empty($_GET['departamento_id']) ? (int)$_GET['departamento_id'] : null;
        if ($userRole === 'SUPERVISOR' && $supervisorDeptoId > 0) {
            $deptoId = $supervisorDeptoId;
        }

        $turnoId = !empty($_GET['turno_id']) ? (int)$_GET['turno_id'] : null;
        $estado = isset($_GET['estado']) && $_GET['estado'] !== '' && strtoupper($_GET['estado']) !== 'TODOS' ? trim($_GET['estado']) : null;
        $search = !empty($_GET['search']) ? trim($_GET['search']) : null;

        $sql = "
            SELECT e.*, 
                   d.nombre as departamento_nombre,
                   c.nombre as cargo_nombre,
                   t.nombre as turno_nombre,
                    (SELECT COUNT(*) FROM plantillas_biometricas pb WHERE pb.codigo_reloj = e.codigo_reloj AND pb.tipo = 'HUELLA') as huellas_count,
                    (SELECT COUNT(*) FROM plantillas_biometricas pb WHERE pb.codigo_reloj = e.codigo_reloj AND pb.tipo = 'FACIAL') as facial_count,
                    (SELECT GROUP_CONCAT(pb.dedo_indice) FROM plantillas_biometricas pb WHERE pb.codigo_reloj = e.codigo_reloj AND pb.tipo = 'HUELLA') as huellas_indices
            FROM empleados e
            LEFT JOIN departamentos d ON e.departamento_id = d.id
            LEFT JOIN cargos c ON e.cargo_id = c.id
            LEFT JOIN turnos t ON e.turno_id = t.id
            WHERE 1=1
        ";
        $params = [];

        if ($deptoId) {
            $sql .= " AND e.departamento_id = :depto_id";
            $params[':depto_id'] = $deptoId;
        }

        if ($turnoId) {
            $sql .= " AND e.turno_id = :turno_id";
            $params[':turno_id'] = $turnoId;
        }

        if ($estado !== null) {
            if ($estado === '1' || strtoupper($estado) === 'ACTIVO') {
                $sql .= " AND e.activo = 1";
            } elseif ($estado === '0' || strtoupper($estado) === 'INACTIVO') {
                $sql .= " AND e.activo = 0";
            }
        }

        if ($search) {
            $sql .= " AND (e.nombres LIKE :s1 OR e.apellidos LIKE :s2 OR e.dni LIKE :s3 OR e.codigo_reloj LIKE :s4 OR CONCAT(e.apellidos, ' ', e.nombres) LIKE :s5 OR CONCAT(e.nombres, ' ', e.apellidos) LIKE :s6)";
            $params[':s1'] = "%$search%";
            $params[':s2'] = "%$search%";
            $params[':s3'] = "%$search%";
            $params[':s4'] = "%$search%";
            $params[':s5'] = "%$search%";
            $params[':s6'] = "%$search%";
        }

        $sql .= " ORDER BY e.apellidos ASC, e.nombres ASC";

        $empleados = Database::query($sql, $params);
        if ($userRole === 'SUPERVISOR' && $supervisorDeptoId > 0) {
            $departamentos = Database::query("SELECT * FROM departamentos WHERE id = ?", [$supervisorDeptoId]);
        } else {
            $departamentos = Database::query("SELECT * FROM departamentos WHERE activo = 1 ORDER BY nombre ASC");
        }
        $cargos = Database::query("SELECT * FROM cargos WHERE activo = 1 ORDER BY nombre ASC");
        $turnos = Database::query("SELECT * FROM turnos WHERE activo = 1 ORDER BY nombre ASC");
        $dispositivos = Database::query("SELECT * FROM dispositivos WHERE activo = 1 ORDER BY id ASC");

        // Calcular el siguiente ID correlativo numérico sugerido (ej: 1, 2, 3... 75 -> 76)
        // Ignorando números grandes como DNIs de 8 dígitos para mantener la secuencia normal de reloj
        $maxNum = 0;
        $allCodigos = Database::query("SELECT codigo_reloj FROM empleados");
        foreach ($allCodigos as $cRow) {
            $cVal = trim((string)($cRow['codigo_reloj'] ?? ''));
            if (ctype_digit($cVal)) {
                $num = (int)$cVal;
                if ($num < 100000 && $num > $maxNum) {
                    $maxNum = $num;
                }
            }
        }
        $siguienteCodigo = $maxNum + 1;

        require_once APP_ROOT . '/views/empleados/index.php';
    }


    public function guardar(): void {
        AuthController::checkAuth();
        AuthController::requireRole(['ADMIN', 'RRHH'], 'empleados');
        \App\Security\Csrf::validate();

        $id = (int)($_POST['id'] ?? 0);
        $codigoReloj = trim($_POST['codigo_reloj'] ?? '');
        $dni = trim($_POST['dni'] ?? '');
        $nombres = trim($_POST['nombres'] ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');

        // Validación básica de negocio antes de tocar la BD
        if (empty($codigoReloj) || empty($dni) || empty($nombres) || empty($apellidos)) {
            header('Location: ?route=empleados&msg=campos_requeridos');
            exit;
        }
        if (!preg_match('/^\d{8}$/', $dni)) {
            header('Location: ?route=empleados&msg=dni_invalido');
            exit;
        }

        $email = trim($_POST['email'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $deptoId = !empty($_POST['departamento_id']) ? (int)$_POST['departamento_id'] : null;
        $cargoId = !empty($_POST['cargo_id']) ? (int)$_POST['cargo_id'] : null;
        $turnoId = !empty($_POST['turno_id']) ? (int)$_POST['turno_id'] : null;
        $fechaIngreso = !empty($_POST['fecha_ingreso']) ? $_POST['fecha_ingreso'] : null;
        $activo = isset($_POST['activo']) ? 1 : 0;

        $dedoMap = [
            1 => 'Pulgar Mano Derecha',
            2 => 'Índice Mano Derecha',
            3 => 'Medio Mano Derecha',
            4 => 'Anular Mano Derecha',
            5 => 'Meñique Mano Derecha',
            6 => 'Pulgar Mano Izquierda',
            7 => 'Índice Mano Izquierda',
            8 => 'Medio Mano Izquierda',
            9 => 'Anular Mano Izquierda',
            10 => 'Meñique Mano Izquierda'
        ];

        // Procesar selección múltiple de dedos (ej: '1,2,7')
        $rawDedos = $_POST['dedos_reloj'] ?? ($_POST['dedo_reloj'] ?? '2');
        if (is_array($rawDedos)) {
            $dedosArr = array_filter(array_map('intval', $rawDedos), fn($f) => $f >= 1 && $f <= 10);
        } else {
            $parts = explode(',', (string)$rawDedos);
            $dedosArr = array_filter(array_map('intval', $parts), fn($f) => $f >= 1 && $f <= 10);
        }
        if (empty($dedosArr)) {
            $dedosArr = [2];
        }
        $dedosArr = array_values(array_unique($dedosArr));
        sort($dedosArr);

        $dedosReloj = implode(',', $dedosArr);
        $dedoReloj = $dedosArr[0]; // Primer dedo principal

        $nombresArr = [];
        foreach ($dedosArr as $df) {
            $nombresArr[] = $dedoMap[$df] ?? "Dedo $df";
        }
        $dedosNombre = implode(', ', $nombresArr);
        $dedoNombre = $nombresArr[0] ?? 'Índice Mano Derecha';

        $params = [
            ':cod'   => $codigoReloj,
            ':dni'   => $dni,
            ':nom'   => $nombres,
            ':ape'   => $apellidos,
            ':email' => $email ?: null,
            ':tel'   => $telefono ?: null,
            ':depto' => $deptoId,
            ':cargo' => $cargoId,
            ':turno' => $turnoId,
            ':fecha' => $fechaIngreso,
            ':dedo_idx' => $dedoReloj,
            ':dedo_nom' => $dedoNombre,
            ':dedos_idx' => $dedosReloj,
            ':dedos_nom' => $dedosNombre,
            ':act'   => $activo
        ];

        if ($id > 0) {
            $params[':id'] = $id;
            $result = Database::executeSafe("
                UPDATE empleados 
                SET codigo_reloj = :cod, dni = :dni, nombres = :nom, apellidos = :ape,
                    email = :email, telefono = :tel, departamento_id = :depto, cargo_id = :cargo,
                    turno_id = :turno, fecha_ingreso = :fecha, 
                    dedo_reloj = :dedo_idx, dedo_nombre = :dedo_nom,
                    dedos_reloj = :dedos_idx, dedos_nombre = :dedos_nom,
                    activo = :act
                WHERE id = :id
            ", $params);
        } else {
            $result = Database::executeSafe("
                INSERT INTO empleados 
                (codigo_reloj, dni, nombres, apellidos, email, telefono, departamento_id, cargo_id, turno_id, fecha_ingreso, dedo_reloj, dedo_nombre, dedos_reloj, dedos_nombre, activo)
                VALUES (:cod, :dni, :nom, :ape, :email, :tel, :depto, :cargo, :turno, :fecha, :dedo_idx, :dedo_nom, :dedos_idx, :dedos_nom, :act)
            ", $params);
        }

        if (!$result['success']) {
            header('Location: ?route=empleados&msg=' . $result['error']);
            exit;
        }

        header('Location: ?route=empleados&msg=guardado');
        exit;
    }

    public function eliminar(): void {
        AuthController::checkAuth();
        AuthController::requireRole(['ADMIN', 'RRHH'], 'empleados');
        \App\Csrf::validateRequest();

        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                Database::execute("UPDATE empleados SET activo = 0 WHERE id = ?", [$id]);
                header('Location: ?route=empleados&msg=desactivado');
                exit;
            } catch (\PDOException $e) {
                header('Location: ?route=empleados&error=db_error');
                exit;
            }
        }
        header('Location: ?route=empleados');
        exit;
    }
}
