<?php
namespace App\Controllers;

use App\Database;

class EmpleadosController {
    public function index(): void {
        AuthController::checkAuth();

        $deptoId = !empty($_GET['departamento_id']) ? (int)$_GET['departamento_id'] : null;
        $search = !empty($_GET['search']) ? trim($_GET['search']) : null;

        $sql = "
            SELECT e.*, 
                   d.nombre as departamento_nombre,
                   c.nombre as cargo_nombre,
                   t.nombre as turno_nombre,
                   (SELECT COUNT(*) FROM plantillas_biometricas pb WHERE pb.codigo_reloj = e.codigo_reloj AND pb.tipo = 'HUELLA') as huellas_count,
                   (SELECT COUNT(*) FROM plantillas_biometricas pb WHERE pb.codigo_reloj = e.codigo_reloj AND pb.tipo = 'FACIAL') as facial_count
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

        if ($search) {
            $sql .= " AND (e.nombres LIKE :s1 OR e.apellidos LIKE :s2 OR e.dni LIKE :s3 OR e.codigo_reloj LIKE :s4)";
            $params[':s1'] = "%$search%";
            $params[':s2'] = "%$search%";
            $params[':s3'] = "%$search%";
            $params[':s4'] = "%$search%";
        }

        $sql .= " ORDER BY e.apellidos ASC, e.nombres ASC";

        $empleados = Database::query($sql, $params);
        $departamentos = Database::query("SELECT * FROM departamentos WHERE activo = 1 ORDER BY nombre ASC");
        $cargos = Database::query("SELECT * FROM cargos WHERE activo = 1 ORDER BY nombre ASC");
        $turnos = Database::query("SELECT * FROM turnos WHERE activo = 1 ORDER BY nombre ASC");
        $dispositivos = Database::query("SELECT * FROM dispositivos WHERE activo = 1 ORDER BY id ASC");

        // Calcular el siguiente ID correlativo numérico sugerido (ej: 1, 2, 3... 75 -> 76)
        // Ignorando números grandes como DNIs de 8 dígitos para mantener la secuencia normal de reloj
        $maxCodigoRow = Database::queryOne("
            SELECT MAX(CAST(codigo_reloj AS UNSIGNED)) as max_c 
            FROM empleados 
            WHERE codigo_reloj REGEXP '^[0-9]+$' 
              AND CAST(codigo_reloj AS UNSIGNED) < 100000
        ");
        $siguienteCodigo = !empty($maxCodigoRow['max_c']) ? ((int)$maxCodigoRow['max_c'] + 1) : 1;

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
            ':act'   => $activo
        ];

        if ($id > 0) {
            $params[':id'] = $id;
            $result = Database::executeSafe("
                UPDATE empleados 
                SET codigo_reloj = :cod, dni = :dni, nombres = :nom, apellidos = :ape,
                    email = :email, telefono = :tel, departamento_id = :depto, cargo_id = :cargo,
                    turno_id = :turno, fecha_ingreso = :fecha, activo = :act
                WHERE id = :id
            ", $params);
        } else {
            $result = Database::executeSafe("
                INSERT INTO empleados 
                (codigo_reloj, dni, nombres, apellidos, email, telefono, departamento_id, cargo_id, turno_id, fecha_ingreso, activo)
                VALUES (:cod, :dni, :nom, :ape, :email, :tel, :depto, :cargo, :turno, :fecha, :act)
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
