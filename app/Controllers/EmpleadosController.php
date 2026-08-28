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
                   t.nombre as turno_nombre
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

        require_once APP_ROOT . '/views/empleados/index.php';
    }

    public function guardar(): void {
        AuthController::checkAuth();

        $id = (int)($_POST['id'] ?? 0);
        $codigoReloj = trim($_POST['codigo_reloj'] ?? '');
        $dni = trim($_POST['dni'] ?? '');
        $nombres = trim($_POST['nombres'] ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $deptoId = !empty($_POST['departamento_id']) ? (int)$_POST['departamento_id'] : null;
        $cargoId = !empty($_POST['cargo_id']) ? (int)$_POST['cargo_id'] : null;
        $turnoId = !empty($_POST['turno_id']) ? (int)$_POST['turno_id'] : null;
        $fechaIngreso = !empty($_POST['fecha_ingreso']) ? $_POST['fecha_ingreso'] : null;
        $activo = isset($_POST['activo']) ? 1 : 0;

        if ($id > 0) {
            Database::execute("
                UPDATE empleados 
                SET codigo_reloj = :cod, dni = :dni, nombres = :nom, apellidos = :ape,
                    email = :email, telefono = :tel, departamento_id = :depto, cargo_id = :cargo,
                    turno_id = :turno, fecha_ingreso = :fecha, activo = :act
                WHERE id = :id
            ", [
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
                ':act'   => $activo,
                ':id'    => $id
            ]);
        } else {
            Database::execute("
                INSERT INTO empleados 
                (codigo_reloj, dni, nombres, apellidos, email, telefono, departamento_id, cargo_id, turno_id, fecha_ingreso, activo)
                VALUES (:cod, :dni, :nom, :ape, :email, :tel, :depto, :cargo, :turno, :fecha, :act)
            ", [
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
            ]);
        }

        header('Location: ?route=empleados&msg=guardado');
        exit;
    }

    public function eliminar(): void {
        AuthController::checkAuth();

        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            Database::execute("UPDATE empleados SET activo = 0 WHERE id = ?", [$id]);
        }
        header('Location: ?route=empleados&msg=desactivado');
        exit;
    }
}
