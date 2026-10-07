<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database;


class TurnosController {
    public function index(): void {
        AuthController::checkAuth();
        AuthController::requirePermission('turnos');

        $turnos = Database::query("
            SELECT t.*, COUNT(e.id) as total_empleados
            FROM turnos t
            LEFT JOIN empleados e ON e.turno_id = t.id AND e.activo = 1
            GROUP BY t.id
            ORDER BY t.id ASC
        ");

        require_once APP_ROOT . '/views/turnos/index.php';
    }

    public function guardar(): void {
        AuthController::checkAuth();
        AuthController::requireRole(['ADMIN', 'RRHH'], 'turnos');
        \App\Security\Csrf::validate();

        $id = (int)($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $horaEntrada = $_POST['hora_entrada'] ?? '08:00:00';
        $horaSalida = $_POST['hora_salida'] ?? '17:00:00';
        $horaEntradaSab = !empty($_POST['hora_entrada_sabado']) ? $_POST['hora_entrada_sabado'] : '08:00:00';
        $horaSalidaSab = !empty($_POST['hora_salida_sabado']) ? $_POST['hora_salida_sabado'] : '13:00:00';
        $tolerancia = (int)($_POST['tolerancia_minutos'] ?? 10);
        $toleranciaFalta = (int)($_POST['tolerancia_falta_minutos'] ?? 60);
        $horaInicioRef = !empty($_POST['hora_inicio_refrigerio']) ? $_POST['hora_inicio_refrigerio'] : null;
        $horaFinRef = !empty($_POST['hora_fin_refrigerio']) ? $_POST['hora_fin_refrigerio'] : null;
        $minutosRef = (int)($_POST['minutos_refrigerio'] ?? 45);
        $diasLab = isset($_POST['dias_laborables']) ? (is_array($_POST['dias_laborables']) ? implode(',', $_POST['dias_laborables']) : (string)$_POST['dias_laborables']) : '1,2,3,4,5,6';
        $esNocturno = (!empty($_POST['es_nocturno']) && $_POST['es_nocturno'] !== '0' && $_POST['es_nocturno'] !== 0) ? 1 : 0;
        $activo = (!empty($_POST['activo']) && $_POST['activo'] !== '0' && $_POST['activo'] !== 0) ? 1 : 0;

        if (empty($nombre)) {
            header('Location: ?route=turnos&msg=campos_requeridos');
            exit;
        }

        // Validación de lógica de horario laboral
        if (!$esNocturno && strtotime($horaEntrada) >= strtotime($horaSalida)) {
            header('Location: ?route=turnos&msg=horario_invalido');
            exit;
        }

        $params = [
            ':nom'      => $nombre,
            ':ent'      => $horaEntrada,
            ':sal'      => $horaSalida,
            ':entsab'   => $horaEntradaSab,
            ':salsab'   => $horaSalidaSab,
            ':tol'      => $tolerancia,
            ':tolfalta' => $toleranciaFalta,
            ':refini'   => $horaInicioRef,
            ':reffin'   => $horaFinRef,
            ':refmin'   => $minutosRef,
            ':dias'     => $diasLab,
            ':noc'      => $esNocturno,
            ':act'      => $activo
        ];

        if ($id > 0) {
            $params[':id'] = $id;
            $result = Database::executeSafe("
                UPDATE turnos 
                SET nombre = :nom, hora_entrada = :ent, hora_salida = :sal,
                    hora_entrada_sabado = :entsab, hora_salida_sabado = :salsab,
                    tolerancia_minutos = :tol, tolerancia_falta_minutos = :tolfalta,
                    hora_inicio_refrigerio = :refini, hora_fin_refrigerio = :reffin,
                    minutos_refrigerio = :refmin, dias_laborables = :dias,
                    es_nocturno = :noc, activo = :act
                WHERE id = :id
            ", $params);
        } else {
            $result = Database::executeSafe("
                INSERT INTO turnos 
                (nombre, hora_entrada, hora_salida, hora_entrada_sabado, hora_salida_sabado,
                 tolerancia_minutos, tolerancia_falta_minutos,
                 hora_inicio_refrigerio, hora_fin_refrigerio, minutos_refrigerio, dias_laborables, es_nocturno, activo)
                VALUES (:nom, :ent, :sal, :entsab, :salsab, :tol, :tolfalta, :refini, :reffin, :refmin, :dias, :noc, :act)
            ", $params);
        }

        if (!$result['success']) {
            header('Location: ?route=turnos&msg=' . ($result['error'] === 'duplicado' ? 'duplicado' : 'error_interno'));
            exit;
        }

        header('Location: ?route=turnos&msg=guardado');
        exit;
    }

    public function eliminar(): void {
        AuthController::checkAuth();
        AuthController::requireRole(['ADMIN', 'RRHH'], 'turnos');
        \App\Csrf::validateRequest();

        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $empCount = (int)(Database::queryOne("SELECT COUNT(*) as c FROM empleados WHERE turno_id = ? AND activo = 1", [$id])['c'] ?? 0);
            
            if ($empCount > 0) {
                Database::execute("UPDATE turnos SET activo = 0 WHERE id = ?", [$id]);
                header('Location: ?route=turnos&msg=desactivado_por_empleados');
                exit;
            }

            try {
                Database::execute("DELETE FROM turnos WHERE id = ?", [$id]);
                header('Location: ?route=turnos&msg=eliminado');
                exit;
            } catch (\PDOException $e) {
                Database::execute("UPDATE turnos SET activo = 0 WHERE id = ?", [$id]);
                header('Location: ?route=turnos&msg=desactivado');
                exit;
            }
        }
        header('Location: ?route=turnos');
        exit;
    }
}
