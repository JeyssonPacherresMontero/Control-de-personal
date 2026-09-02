<?php
namespace App\Services;

use App\Database;
use DateTime;
use DateInterval;
use Exception;

require_once __DIR__ . '/../Database.php';

class AttendanceCalculator {
    private int $debounceMinutes;

    public function __construct(int $debounceMinutes = 3) {
        $this->debounceMinutes = $debounceMinutes;
    }

    /**
     * Procesa la asistencia de todos los empleados activos para una fecha específica
     */
    public function processDate(string $date): array {
        $stats = [
            'date' => $date,
            'processed' => 0,
            'present' => 0,
            'late' => 0,
            'absent' => 0,
            'justified' => 0,
            'missing_exit' => 0
        ];

        // 1. Obtener lista de empleados activos con sus turnos asociados
        $employees = Database::query("
            SELECT e.id, e.codigo_reloj, e.nombres, e.apellidos, e.turno_id,
                   t.nombre as turno_nombre, t.hora_entrada, t.hora_salida,
                   t.tolerancia_minutos, t.tolerancia_falta_minutos,
                   t.hora_inicio_refrigerio, t.hora_fin_refrigerio, t.minutos_refrigerio,
                   t.dias_laborables, t.es_nocturno
            FROM empleados e
            LEFT JOIN turnos t ON e.turno_id = t.id
            WHERE e.activo = 1
        ");

        // 2. Verificar si la fecha es feriado nacional/empresa
        $holiday = Database::queryOne("SELECT * FROM feriados WHERE fecha = ?", [$date]);

        foreach ($employees as $emp) {
            $res = $this->processEmployeeDate($emp, $date, $holiday !== null);
            $stats['processed']++;
            if ($res === 'PRESENTE') $stats['present']++;
            elseif ($res === 'TARDANZA') $stats['late']++;
            elseif ($res === 'FALTA' || $res === 'FALTA_INJUSTIFICADA') $stats['absent']++;
            elseif ($res === 'JUSTIFICADO' || $res === 'PERMISO' || $res === 'VACACIONES') $stats['justified']++;
            elseif ($res === 'SALIDA_SIN_MARCAR') $stats['missing_exit']++;
        }

        return $stats;
    }

    /**
     * Procesa un rango de fechas
     */
    public function processDateRange(string $startDate, string $endDate): array {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $end->modify('+1 day');

        $interval = new DateInterval('P1D');
        $period = new \DatePeriod($start, $interval, $end);

        $results = [];
        foreach ($period as $dt) {
            $results[] = $this->processDate($dt->format('Y-m-d'));
        }
        return $results;
    }

    /**
     * Procesa la asistencia individual de un empleado en un día específico
     */
    public function processEmployeeDate(array $employee, string $date, bool $isHoliday = false): string {
        $empId = (int)$employee['id'];
        $turnoId = $employee['turno_id'] ? (int)$employee['turno_id'] : null;

        // Si ya existe un registro manual protegido por RRHH, no sobreescribir automáticamente
        $existing = Database::queryOne("SELECT id, manual FROM asistencia_diaria WHERE id_empleado = ? AND fecha = ?", [$empId, $date]);
        if ($existing && (int)$existing['manual'] === 1) {
            return 'MANUAL_PROTECTED';
        }

        // 1. Determinar día de la semana (1 = Lunes, 7 = Domingo)
        $dayOfWeek = (int)(new DateTime($date))->format('N');
        $workdays = !empty($employee['dias_laborables']) ? explode(',', $employee['dias_laborables']) : [1,2,3,4,5];
        $isWorkday = in_array((string)$dayOfWeek, $workdays);

        // 2. Verificar Justificaciones o Permisos aprobados
        $justification = Database::queryOne("
            SELECT * FROM justificaciones 
            WHERE id_empleado = ? 
              AND ? BETWEEN fecha_inicio AND fecha_fin 
              AND estado = 'APROBADO'
            LIMIT 1
        ", [$empId, $date]);

        // 3. Obtener marcaciones crudas del empleado
        // Si el turno es nocturno, la ventana de marcaciones abarca desde la tarde del día hasta el mediodía del día siguiente
        $esNocturno = !empty($employee['es_nocturno']) && (int)$employee['es_nocturno'] === 1;
        
        if ($esNocturno) {
            $windowStart = "$date 12:00:00";
            $nextDate = (new DateTime($date))->modify('+1 day')->format('Y-m-d');
            $windowEnd = "$nextDate 14:00:00";
        } else {
            $windowStart = "$date 00:00:00";
            $windowEnd = "$date 23:59:59";
        }

        $rawPunches = Database::query("
            SELECT id, fecha_hora, tipo 
            FROM marcaciones 
            WHERE (id_empleado = ? OR codigo_reloj = ?) 
              AND fecha_hora BETWEEN ? AND ?
            ORDER BY fecha_hora ASC
        ", [$empId, $employee['codigo_reloj'], $windowStart, $windowEnd]);

        // 4. Aplicar filtro Debounce (eliminar toques dobles o accidentales dentro de N minutos)
        $cleanPunches = $this->filterDebouncePunches($rawPunches);

        // Si es feriado o día no laborable y no hay marcaciones
        if ($isHoliday && count($cleanPunches) === 0) {
            $this->saveAttendanceRecord($empId, $turnoId, $date, null, null, null, null, 0, 0, 0, 0, 'DESCANSO', 'Feriado / Día no laborable');
            return 'DESCANSO';
        }

        if (!$isWorkday && count($cleanPunches) === 0) {
            $this->saveAttendanceRecord($empId, $turnoId, $date, null, null, null, null, 0, 0, 0, 0, 'DESCANSO', 'Día libre de descanso');
            return 'DESCANSO';
        }

        // Si tiene justificación aprobada y no marcó
        if ($justification && count($cleanPunches) === 0) {
            $estadoJust = $justification['tipo'] === 'VACACIONES' ? 'VACACIONES' : 'JUSTIFICADO';
            $this->saveAttendanceRecord($empId, $turnoId, $date, null, null, null, null, 0, 0, 0, 0, $estadoJust, $justification['motivo']);
            return $estadoJust;
        }

        // 5. Caso: Cero marcaciones en día laborable -> FALTA
        if (count($cleanPunches) === 0) {
            $this->saveAttendanceRecord(
                $empId, $turnoId, $date,
                $employee['hora_entrada'] ?? null,
                $employee['hora_salida'] ?? null,
                null, null, null, null,
                0, 0, 0, 0,
                'FALTA',
                'Inasistencia no justificada'
            );
            return 'FALTA';
        }

        // 6. Caso: Tiene marcaciones. Procesar horas de entrada y salida
        $entryPunch = null;
        $exitPunch = null;
        $breakOutPunch = null;
        $breakInPunch = null;

        // Emparejamiento Inteligente
        if (count($cleanPunches) === 1) {
            // Solo una marcación en todo el día
            $entryPunch = $cleanPunches[0]['fecha_hora'];
            $exitPunch = null;
        } else {
            // Primera marcación es Entrada, Última es Salida
            $entryPunch = $cleanPunches[0]['fecha_hora'];
            $exitPunch = $cleanPunches[count($cleanPunches) - 1]['fecha_hora'];

            // Si hay 4 o más marcaciones, detectar intermedio de refrigerio
            if (count($cleanPunches) >= 4) {
                $breakOutPunch = $cleanPunches[1]['fecha_hora'];
                $breakInPunch = $cleanPunches[2]['fecha_hora'];
            }
        }

        // 7. Cálculos con respecto al Turno Laboral
        $minutosTardanza = 0;
        $minutosTrabajados = 0;
        $minutosExtra = 0;
        $minutosSalidaTemprana = 0;
        $estado = 'PRESENTE';
        $observaciones = [];

        $isToday = ($date === date('Y-m-d'));
        $scheduledEntryStr = $employee['hora_entrada'] ? "$date {$employee['hora_entrada']}" : null;
        $scheduledExitStr = $employee['hora_salida'] ? ($esNocturno ? "$nextDate {$employee['hora_salida']}" : "$date {$employee['hora_salida']}") : null;
        $tolerancia = (int)($employee['tolerancia_minutos'] ?? 10);
        $toleranciaFalta = (int)($employee['tolerancia_falta_minutos'] ?? 60);

        if ($entryPunch && $scheduledEntryStr) {
            $dtEntryReal = new DateTime($entryPunch);
            $dtEntryProg = new DateTime($scheduledEntryStr);
            $dtEntryGrace = (clone $dtEntryProg)->modify("+{$tolerancia} minutes");
            $dtEntryLimitFalta = (clone $dtEntryProg)->modify("+{$toleranciaFalta} minutes");

            // Evaluar Tardanza
            if ($dtEntryReal > $dtEntryGrace) {
                // Se calcula la diferencia exacta desde la hora oficial de entrada
                $diff = $dtEntryReal->getTimestamp() - $dtEntryProg->getTimestamp();
                $minutosTardanza = (int)floor($diff / 60);

                if ($dtEntryReal > $dtEntryLimitFalta) {
                    $estado = 'TARDANZA';
                    $observaciones[] = "Tardanza severa (+{$minutosTardanza} min)";
                } else {
                    $estado = 'TARDANZA';
                    $observaciones[] = "Llegada con tardanza ({$minutosTardanza} min)";
                }
            }
        }

        // Evaluar Refrigerio Real vs Asignado
        $minutosRefrigerioTomados = 0;
        if ($breakOutPunch && $breakInPunch) {
            $dtBreakOut = new DateTime($breakOutPunch);
            $dtBreakIn = new DateTime($breakInPunch);
            if ($dtBreakIn > $dtBreakOut) {
                $minutosRefrigerioTomados = (int)floor(($dtBreakIn->getTimestamp() - $dtBreakOut->getTimestamp()) / 60);
                $minutosRefProgramados = (int)($employee['minutos_refrigerio'] ?? 60);
                if ($minutosRefProgramados > 0 && $minutosRefrigerioTomados > ($minutosRefProgramados + 5)) {
                    $excesoRef = $minutosRefrigerioTomados - $minutosRefProgramados;
                    $observaciones[] = "Exceso en refrigerio (+{$excesoRef} min)";
                }
            }
        }

        // Evaluar Salida y Tiempo Trabajado
        if ($entryPunch && $exitPunch) {
            $dtEntryReal = new DateTime($entryPunch);
            $dtExitReal = new DateTime($exitPunch);

            // Minutos totales brutos entre entrada y salida
            $diffSeconds = $dtExitReal->getTimestamp() - $dtEntryReal->getTimestamp();
            $minutosBrutos = (int)floor($diffSeconds / 60);

            // Descontar refrigerio: si tomó refrigerio real, descontamos el real; sino, el automático
            if ($minutosRefrigerioTomados > 0) {
                $minutosTrabajados = max(0, $minutosBrutos - $minutosRefrigerioTomados);
            } else {
                $minutosDesc = (int)($employee['minutos_refrigerio'] ?? 0);
                if ($minutosBrutos > 240 && $minutosDesc > 0) { // Jornada mayor a 4h
                    $minutosTrabajados = max(0, $minutosBrutos - $minutosDesc);
                } else {
                    $minutosTrabajados = $minutosBrutos;
                }
            }

            // Evaluar Horas Extras / Salida Temprana con respecto al turno oficial
            if ($scheduledExitStr) {
                $dtExitProg = new DateTime($scheduledExitStr);
                
                if ($dtExitReal > $dtExitProg) {
                    // Sobretiempo laborado
                    $extraSec = $dtExitReal->getTimestamp() - $dtExitProg->getTimestamp();
                    $minutosExtraCalc = (int)floor($extraSec / 60);
                    // Filtro de ruido: Solo computar horas extras si supera un umbral de 15 minutos
                    if ($minutosExtraCalc >= 15) {
                        $minutosExtra = $minutosExtraCalc;
                    }
                } elseif ($dtExitReal < $dtExitProg) {
                    // Se retiró antes de tiempo
                    $earlySec = $dtExitProg->getTimestamp() - $dtExitReal->getTimestamp();
                    $minutosSalidaTemprana = (int)floor($earlySec / 60);
                    // Solo marcar si supera 5 minutos de anticipación
                    if ($minutosSalidaTemprana >= 5) {
                        $observaciones[] = "Salida anticipada ({$minutosSalidaTemprana} min)";
                    }
                }
            }
        } elseif ($entryPunch && !$exitPunch) {
            // Solo marcó entrada y no tiene salida
            $dtEntryReal = new DateTime($entryPunch);
            $now = new DateTime();
            
            if ($isToday) {
                // Es el día de hoy: verificar si la jornada sigue en curso
                $dtExitProg = $scheduledExitStr ? new DateTime($scheduledExitStr) : (clone $dtEntryReal)->modify('+9 hours');
                $dtExitLimit = (clone $dtExitProg)->modify('+3 hours');

                if ($now <= $dtExitLimit) {
                    // La persona está actualmente laborando en su turno
                    $observaciones[] = "Jornada en curso (ingreso a las " . $dtEntryReal->format('H:i') . ")";
                } else {
                    // Ya pasó el horario de turno holgadamente y nunca marcó salida
                    $estado = 'SALIDA_SIN_MARCAR';
                    $observaciones[] = "No registró marcación de salida";
                }
            } else {
                // Fecha pasada y no marcó salida
                $estado = 'SALIDA_SIN_MARCAR';
                $observaciones[] = "No registró marcación de salida";
            }
        }

        // Si tiene justificación de tardanza aprobada
        if ($justification && $justification['tipo'] === 'TARDANZA' && $estado === 'TARDANZA') {
            $estado = 'JUSTIFICADO';
            $observaciones[] = 'Tardanza justificada: ' . $justification['motivo'];
        }

        // 8. Guardar en asistencia_diaria
        $this->saveAttendanceRecord(
            $empId,
            $turnoId,
            $date,
            $employee['hora_entrada'] ?? null,
            $employee['hora_salida'] ?? null,
            $entryPunch,
            $exitPunch,
            $breakOutPunch,
            $breakInPunch,
            $minutosTardanza,
            $minutosTrabajados,
            $minutosExtra,
            $minutosSalidaTemprana,
            $estado,
            implode(' | ', $observaciones)
        );

        // 9. Marcar marcaciones crudas como procesadas
        Database::execute("
            UPDATE marcaciones 
            SET procesado = 1 
            WHERE (id_empleado = ? OR codigo_reloj = ?) 
              AND fecha_hora BETWEEN ? AND ?
        ", [$empId, $employee['codigo_reloj'], $windowStart, $windowEnd]);

        return $estado;
    }

    /**
     * Filtro debounce: ignora marcaciones consecutivas dentro de un umbral de $debounceMinutes
     */
    private function filterDebouncePunches(array $punches): array {
        if (count($punches) <= 1) {
            return $punches;
        }

        $filtered = [];
        $lastTimestamp = null;

        foreach ($punches as $p) {
            $currentTs = strtotime($p['fecha_hora']);
            if ($lastTimestamp === null || ($currentTs - $lastTimestamp) >= ($this->debounceMinutes * 60)) {
                $filtered[] = $p;
                $lastTimestamp = $currentTs;
            }
        }

        return $filtered;
    }

    /**
     * Inserta o actualiza el registro consolidado en la tabla asistencia_diaria
     */
    private function saveAttendanceRecord(
        int $empId,
        ?int $turnoId,
        string $fecha,
        ?string $horaEntradaProg,
        ?string $horaSalidaProg,
        ?string $horaEntradaReal,
        ?string $horaSalidaReal,
        ?string $refrigerioSalidaReal,
        ?string $refrigerioEntradaReal,
        int $minutosTardanza,
        int $minutosTrabajados,
        int $minutosExtra,
        int $minutosSalidaTemprana,
        string $estado,
        string $observaciones
    ): void {
        $sql = "
            INSERT INTO asistencia_diaria (
                id_empleado, id_turno, fecha,
                hora_entrada_programada, hora_salida_programada,
                hora_entrada_real, hora_salida_real,
                hora_inicio_refrigerio_real, hora_fin_refrigerio_real,
                minutos_tardanza, minutos_trabajados, minutos_extra, minutos_salida_temprana,
                estado, observaciones, manual
            ) VALUES (
                :id_emp, :id_turno, :fecha,
                :ent_prog, :sal_prog,
                :ent_real, :sal_real,
                :ref_sal, :ref_ent,
                :tardanza, :trabajados, :extra, :temprana,
                :estado, :obs, 0
            )
            ON DUPLICATE KEY UPDATE
                id_turno = VALUES(id_turno),
                hora_entrada_programada = VALUES(hora_entrada_programada),
                hora_salida_programada = VALUES(hora_salida_programada),
                hora_entrada_real = VALUES(hora_entrada_real),
                hora_salida_real = VALUES(hora_salida_real),
                hora_inicio_refrigerio_real = VALUES(hora_inicio_refrigerio_real),
                hora_fin_refrigerio_real = VALUES(hora_fin_refrigerio_real),
                minutos_tardanza = VALUES(minutos_tardanza),
                minutos_trabajados = VALUES(minutos_trabajados),
                minutos_extra = VALUES(minutos_extra),
                minutos_salida_temprana = VALUES(minutos_salida_temprana),
                estado = IF(manual = 1, estado, VALUES(estado)),
                observaciones = IF(manual = 1, observaciones, VALUES(observaciones)),
                procesado_en = NOW()
        ";

        Database::execute($sql, [
            ':id_emp'    => $empId,
            ':id_turno'  => $turnoId,
            ':fecha'     => $fecha,
            ':ent_prog'  => $horaEntradaProg,
            ':sal_prog'  => $horaSalidaProg,
            ':ent_real'  => $horaEntradaReal,
            ':sal_real'  => $horaSalidaReal,
            ':ref_sal'   => $refrigerioSalidaReal,
            ':ref_ent'   => $refrigerioEntradaReal,
            ':tardanza'  => $minutosTardanza,
            ':trabajados'=> $minutosTrabajados,
            ':extra'     => $minutosExtra,
            ':temprana'  => $minutosSalidaTemprana,
            ':estado'    => $estado,
            ':obs'       => $observaciones ?: null
        ]);
    }
}
