<?php
namespace App\Services;

use App\Database;
use App\Services\EventStore;
use DateTime;
use DateInterval;
use Exception;

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/EventStore.php';

/**
 * Motor de Cálculo y Consolidación de Asistencia Laboral
 * Optimizado para alto rendimiento (Batch Loading en memoria) y evaluación precisa en tiempo real.
 */
class AttendanceCalculator {
    private int $debounceMinutes;

    public function __construct(int $debounceMinutes = 3) {
        $this->debounceMinutes = $debounceMinutes;
    }

    /**
     * Procesa la asistencia de todos los empleados activos para una fecha específica
     * mediante carga por lotes (Batching) en memoria para máximo rendimiento.
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

        // 1. Obtener empleados activos con sus turnos
        $employees = Database::query("
            SELECT e.id, e.codigo_reloj, e.nombres, e.apellidos, e.turno_id,
                   t.nombre as turno_nombre, t.hora_entrada, t.hora_salida,
                   t.hora_entrada_sabado, t.hora_salida_sabado,
                   t.tolerancia_minutos, t.tolerancia_falta_minutos,
                   t.hora_inicio_refrigerio, t.hora_fin_refrigerio, t.minutos_refrigerio,
                   t.dias_laborables, t.es_nocturno
            FROM empleados e
            LEFT JOIN turnos t ON e.turno_id = t.id
            WHERE e.activo = 1
        ");

        if (empty($employees)) {
            return $stats;
        }

        // 2. Carga en Lote (Batch Loading): Feriados
        $holiday = Database::queryOne("SELECT * FROM feriados WHERE fecha = ?", [$date]);
        $isHoliday = ($holiday !== null);

        // 3. Carga en Lote: Registros manuales existentes protegidos
        $existingRecords = Database::query("
            SELECT id_empleado, manual, estado, hora_entrada_real, hora_salida_real, hora_inicio_refrigerio_real, hora_fin_refrigerio_real, observaciones 
            FROM asistencia_diaria 
            WHERE fecha = ?
        ", [$date]);
        $existingByEmp = [];
        foreach ($existingRecords as $er) {
            $existingByEmp[(int)$er['id_empleado']] = $er;
        }

        // 4. Carga en Lote: Justificaciones aprobadas para esta fecha
        $justifications = Database::query("
            SELECT * FROM justificaciones 
            WHERE ? BETWEEN fecha_inicio AND fecha_fin 
              AND estado = 'APROBADO'
        ", [$date]);
        $justByEmp = [];
        foreach ($justifications as $j) {
            $justByEmp[(int)$j['id_empleado']] = $j;
        }

        // 5. Carga en Lote: Marcaciones crudas del período (incluyendo margen nocturno)
        $nextDate = (new DateTime($date))->modify('+1 day')->format('Y-m-d');
        $windowStart = "$date 00:00:00";
        $windowEnd = "$nextDate 14:00:00";

        $rawPunches = Database::query("
            SELECT id, id_empleado, codigo_reloj, fecha_hora, tipo 
            FROM marcaciones 
            WHERE fecha_hora BETWEEN ? AND ?
            ORDER BY fecha_hora ASC
        ", [$windowStart, $windowEnd]);

        // Indexar marcaciones por id_empleado y codigo_reloj (exacto y sin ceros a la izquierda)
        $punchesByEmpId = [];
        $punchesByCode = [];
        foreach ($rawPunches as $p) {
            if (!empty($p['id_empleado'])) {
                $punchesByEmpId[(int)$p['id_empleado']][] = $p;
            }
            if (!empty($p['codigo_reloj'])) {
                $rawCode = trim((string)$p['codigo_reloj']);
                $normCode = ltrim($rawCode, '0');
                if ($normCode === '') $normCode = '0';

                $punchesByCode[$rawCode][] = $p;
                if ($normCode !== $rawCode) {
                    $punchesByCode[$normCode][] = $p;
                }
            }
        }

        $now = new DateTime();
        $isToday = ($date === date('Y-m-d'));
        $dayOfWeek = (int)(new DateTime($date))->format('N');

        $processedPunchIds = [];
        $recordsToUpsert = [];

        foreach ($employees as $emp) {
            $empId = (int)$emp['id'];
            $codigoReloj = trim((string)$emp['codigo_reloj']);
            $normEmpCode = ltrim($codigoReloj, '0');
            if ($normEmpCode === '') $normEmpCode = '0';
            $turnoId = $emp['turno_id'] ? (int)$emp['turno_id'] : null;

            // Si ya existe un registro modificado manualmente por RRHH:
            if (isset($existingByEmp[$empId]) && (int)$existingByEmp[$empId]['manual'] === 1) {
                $exRec = $existingByEmp[$empId];
                $tieneSalidaRegistrada = !empty($exRec['hora_salida_real']);
                $esEstadoCerrado = in_array($exRec['estado'] ?? '', ['JUSTIFICADO', 'PERMISO', 'VACACIONES', 'COMISION_SERVICIO', 'DESCANSO']);

                // Si ya tiene salida asignada o es justificación/vacaciones, respetar el registro completo y no recalcular
                if ($tieneSalidaRegistrada || $esEstadoCerrado) {
                    $stats['processed']++;
                    $st = $exRec['estado'] ?? 'PRESENTE';
                    if ($st === 'PRESENTE') $stats['present']++;
                    elseif ($st === 'TARDANZA') $stats['late']++;
                    elseif ($st === 'FALTA' || $st === 'FALTA_INJUSTIFICADA') $stats['absent']++;
                    elseif ($st === 'JUSTIFICADO' || $st === 'PERMISO' || $st === 'VACACIONES') $stats['justified']++;
                    elseif ($st === 'SALIDA_SIN_MARCAR') $stats['missing_exit']++;
                    continue;
                }

                // Si solo se corrigió el ingreso administrativamente (sin salida):
                // Se continúa el cálculo para incorporar las marcaciones del biométrico durante el día,
                // respetando como oficial la hora de entrada real registrada por el Administrador.
            }

            $workdays = !empty($emp['dias_laborables']) ? explode(',', $emp['dias_laborables']) : [1,2,3,4,5];
            $isWorkday = in_array((string)$dayOfWeek, $workdays);
            $justification = $justByEmp[$empId] ?? null;

            $esNocturno = !empty($emp['es_nocturno']) && (int)$emp['es_nocturno'] === 1;
            $empWindowStart = $esNocturno ? "$date 12:00:00" : "$date 00:00:00";
            $empWindowEnd = $esNocturno ? "$nextDate 14:00:00" : "$date 23:59:59";

            // Obtener marcaciones del empleado en su ventana de turno (unificando por ID y código de reloj)
            $rawPunches = array_merge(
                $punchesByEmpId[$empId] ?? [],
                $punchesByCode[$codigoReloj] ?? [],
                $punchesByCode[$normEmpCode] ?? []
            );
            $uniquePunches = [];
            foreach ($rawPunches as $p) {
                $uniquePunches[$p['id']] = $p;
            }
            $allPunches = array_values($uniquePunches);

            $empPunches = [];
            foreach ($allPunches as $p) {
                if ($p['fecha_hora'] >= $empWindowStart && $p['fecha_hora'] <= $empWindowEnd) {
                    $empPunches[] = $p;
                    $processedPunchIds[] = (int)$p['id'];
                }
            }

            // Ordenar cronológicamente ascendente
            usort($empPunches, function($a, $b) {
                return strcmp($a['fecha_hora'], $b['fecha_hora']);
            });

            // Aplicar filtro debounce
            $cleanPunches = $this->filterDebouncePunches($empPunches);
            $punchCount = count($cleanPunches);

            $isSaturday = ($dayOfWeek === 6);
            if ($isSaturday) {
                // SÁBADO: 08:00 a 13:00 (Sin refrigerio)
                $progHoraEntrada = $emp['hora_entrada_sabado'] ?? $emp['hora_entrada'] ?? '08:00:00';
                $progHoraSalida = $emp['hora_salida_sabado'] ?? '13:00:00';
                $minutosRefProgramados = 0;
            } else {
                // LUNES A VIERNES: 08:00 a 17:00 (Refrigerio 13:00 a 13:45 / 45 min)
                $progHoraEntrada = $emp['hora_entrada'] ?? '08:00:00';
                $progHoraSalida = $emp['hora_salida'] ?? '17:00:00';
                $minutosRefProgramados = (int)($emp['minutos_refrigerio'] ?? 45);
            }

            $scheduledEntryStr = $progHoraEntrada ? "$date $progHoraEntrada" : null;
            $scheduledExitStr = $progHoraSalida ? ($esNocturno ? "$nextDate $progHoraSalida" : "$date $progHoraSalida") : null;
            $tolerancia = (int)($emp['tolerancia_minutos'] ?? 10);
            $toleranciaFalta = (int)($emp['tolerancia_falta_minutos'] ?? 60);

            // Caso: Feriado o Descanso semanal
            if (($isHoliday || !$isWorkday) && $punchCount === 0) {
                $estado = 'DESCANSO';
                $obs = $isHoliday ? 'Feriado / Día no laborable' : 'Día libre de descanso';
                $recordsToUpsert[] = $this->buildRecordData($empId, $turnoId, $date, $progHoraEntrada, $progHoraSalida, null, null, null, null, 0, 0, 0, 0, $estado, $obs, $tolerancia);
                $stats['processed']++;
                continue;
            }

            // Caso: Laboró en Feriado o Día de Descanso Semanal (Domingo / Día libre con marcaciones registradas)
            if (($isHoliday || !$isWorkday) && $punchCount > 0) {
                $entryPunch = $cleanPunches[0]['fecha_hora'];
                $exitPunch = ($punchCount > 1) ? $cleanPunches[$punchCount - 1]['fecha_hora'] : null;
                $breakOutPunch = ($punchCount >= 4) ? $cleanPunches[1]['fecha_hora'] : null;
                $breakInPunch = ($punchCount >= 4) ? $cleanPunches[2]['fecha_hora'] : null;

                $minutosTrabajados = 0;
                $minutosExtra = 0;
                $minutosTardanza = 0;
                $minutosSalidaTemprana = 0;
                $estado = 'PRESENTE';
                $observaciones = [];

                if ($entryPunch && $exitPunch) {
                    $diffSec = strtotime($exitPunch) - strtotime($entryPunch);
                    $minutosBrutos = max(0, (int)floor($diffSec / 60));

                    if ($breakOutPunch && $breakInPunch && strtotime($breakInPunch) > strtotime($breakOutPunch)) {
                        $minRef = (int)floor((strtotime($breakInPunch) - strtotime($breakOutPunch)) / 60);
                        $minutosTrabajados = max(0, $minutosBrutos - $minRef);
                    } elseif ($minutosBrutos >= 300) {
                        $minutosTrabajados = max(0, $minutosBrutos - 45);
                    } else {
                        $minutosTrabajados = $minutosBrutos;
                    }

                    // En día no laborable/feriado/domingo, todo el tiempo laborado son horas extras al 100%
                    $minutosExtra = $minutosTrabajados;
                    $tipoDia = $isHoliday ? "feriado oficial" : "descanso semanal / domingo";
                    $observaciones[] = "Laboró en {$tipoDia}: {$minutosTrabajados} min trabajados (100% horas extras)";
                } else {
                    $dtPunch = new DateTime($entryPunch);
                    $observaciones[] = "Marcación única en día no laborable (" . $dtPunch->format('H:i') . "). Requiere regularización.";
                }

                $recordsToUpsert[] = $this->buildRecordData(
                    $empId, $turnoId, $date,
                    $progHoraEntrada, $progHoraSalida,
                    $entryPunch, $exitPunch, $breakOutPunch, $breakInPunch,
                    $minutosTardanza, $minutosTrabajados, $minutosExtra, $minutosSalidaTemprana,
                    $estado, implode(' | ', $observaciones), $tolerancia
                );
                $stats['processed']++;
                $stats['present']++;
                continue;
            }

            // Caso: Justificación aprobada sin marcaciones
            if ($justification && $punchCount === 0) {
                $tipoJust = $justification['tipo'] ?? 'JUSTIFICADO';
                $comisionDest = $justification['comision_destino'] ?? null;
                $motivoJust = $justification['motivo'] ?? 'Justificación aprobada';

                if ($tipoJust === 'COMISION_SERVICIO') {
                    $estado = 'COMISION_SERVICIO';
                    if ($isSaturday) {
                        $autoEnt = "$date $progHoraEntrada";
                        $autoSal = "$date $progHoraSalida";
                        $autoRefSal = null;
                        $autoRefEnt = null;
                        $autoMinTrab = 300; // 5 hrs en sábado
                    } else {
                        // Lunes a Viernes: 08:00 a 17:00, refrigerio 13:00 a 13:45
                        $autoEnt = "$date 08:00:00";
                        $autoRefSal = "$date 13:00:00";
                        $autoRefEnt = "$date 13:45:00";
                        $autoSal = "$date 17:00:00";
                        $autoMinTrab = 495; // 8h 15m netas trabajadas (8.25 hrs normales de jornada)
                    }
                    $obsComision = "Comisión de Servicio" . ($comisionDest ? " en $comisionDest" : "") . ": $motivoJust";
                    $recordsToUpsert[] = $this->buildRecordData(
                        $empId, $turnoId, $date, $progHoraEntrada, $progHoraSalida,
                        $autoEnt, $autoSal, $autoRefSal, $autoRefEnt,
                        0, $autoMinTrab, 0, 0, $estado, $obsComision, $tolerancia, $comisionDest
                    );
                } elseif ($tipoJust === 'VACACIONES') {
                    $estado = 'VACACIONES';
                    if ($isSaturday) {
                        $autoEnt = "$date $progHoraEntrada";
                        $autoSal = "$date $progHoraSalida";
                        $autoRefSal = null;
                        $autoRefEnt = null;
                        $autoMinTrab = 300; // 5 hrs en sábado
                    } else {
                        // Lunes a Viernes: 08:00 a 17:00, refrigerio 13:00 a 13:45
                        $autoEnt = "$date 08:00:00";
                        $autoRefSal = "$date 13:00:00";
                        $autoRefEnt = "$date 13:45:00";
                        $autoSal = "$date 17:00:00";
                        $autoMinTrab = 495; // 8.25 hrs normales de jornada
                    }
                    $obsVac = "Vacaciones autorizadas (marcación automática oficial de jornada cumplida)";
                    $recordsToUpsert[] = $this->buildRecordData(
                        $empId, $turnoId, $date, $progHoraEntrada, $progHoraSalida,
                        $autoEnt, $autoSal, $autoRefSal, $autoRefEnt,
                        0, $autoMinTrab, 0, 0, $estado, $obsVac, $tolerancia
                    );
                } else {
                    $estado = ($tipoJust === 'PERMISO_MEDICO' || $tipoJust === 'PERMISO') ? 'PERMISO' : 'JUSTIFICADO';
                    $recordsToUpsert[] = $this->buildRecordData(
                        $empId, $turnoId, $date, $progHoraEntrada, $progHoraSalida,
                        null, null, null, null,
                        0, 0, 0, 0, $estado, $motivoJust, $tolerancia
                    );
                }

                $stats['processed']++;
                $stats['justified']++;
                continue;
            }

            // Detectar si el empleado tiene corrección administrativa previa en el ingreso
            $hasManualEntry = (isset($existingByEmp[$empId]) 
                && (int)$existingByEmp[$empId]['manual'] === 1 
                && !empty($existingByEmp[$empId]['hora_entrada_real']) 
                && empty($existingByEmp[$empId]['hora_salida_real']));
            $manualEntryTime = $hasManualEntry ? $existingByEmp[$empId]['hora_entrada_real'] : null;

            // Caso: Cero marcaciones en día laborable y sin ingreso manual administrativo
            if ($punchCount === 0 && !$hasManualEntry) {
                if ($isToday && $scheduledEntryStr) {
                    $dtProgEntry = new DateTime($scheduledEntryStr);
                    $dtLimitFalta = (clone $dtProgEntry)->modify("+{$toleranciaFalta} minutes");

                    // Si el turno aún no empieza o está dentro del margen de llegada, no registrar falta prematura
                    if ($now < $dtLimitFalta) {
                        $estado = 'PENDIENTE';
                        $obs = 'En espera de ingreso laboral';
                        $recordsToUpsert[] = $this->buildRecordData($empId, $turnoId, $date, $progHoraEntrada, $progHoraSalida, null, null, null, null, 0, 0, 0, 0, $estado, $obs, $tolerancia);
                        $stats['processed']++;
                        continue;
                    }
                }

                $estado = 'FALTA';
                $recordsToUpsert[] = $this->buildRecordData($empId, $turnoId, $date, $progHoraEntrada, $progHoraSalida, null, null, null, null, 0, 0, 0, 0, $estado, 'Inasistencia no justificada', $tolerancia);
                $stats['processed']++;
                $stats['absent']++;
                continue;
            }

            // Caso: Existen marcaciones o tiene ingreso manual administrativo
            $entryPunch = null;
            $exitPunch = null;
            $breakOutPunch = null;
            $breakInPunch = null;
            $observaciones = [];

            if ($hasManualEntry) {
                // Preservar estrictamente el ingreso establecido oficialmente por el Administrador
                $entryPunch = $manualEntryTime;
                $tsManual = strtotime($manualEntryTime);

                // Filtrar marcaciones del biométrico que ocurrieron después del ingreso (con margen de 3 minutos)
                $postPunches = [];
                foreach ($cleanPunches as $cp) {
                    if (strtotime($cp['fecha_hora']) > $tsManual + 180) {
                        $postPunches[] = $cp;
                    }
                }
                $postCount = count($postPunches);

                if ($isSaturday) {
                    if ($postCount >= 1) {
                        $exitPunch = $postPunches[$postCount - 1]['fecha_hora'];
                    }
                } else {
                    if ($postCount === 1) {
                        $p1 = $postPunches[0]['fecha_hora'];
                        $p1Time = date('H:i:s', strtotime($p1));
                        if ($p1Time >= '15:00:00') {
                            $exitPunch = $p1;
                        } elseif ($p1Time >= '11:30:00' && $p1Time <= '14:30:00') {
                            $breakOutPunch = $p1;
                            $observaciones[] = "Registró salida a refrigerio (" . substr($p1Time, 0, 5) . "); en jornada";
                        } else {
                            $exitPunch = $p1;
                        }
                    } elseif ($postCount === 2) {
                        $p1 = $postPunches[0]['fecha_hora'];
                        $p2 = $postPunches[1]['fecha_hora'];
                        $p1Time = date('H:i:s', strtotime($p1));
                        $p2Time = date('H:i:s', strtotime($p2));

                        if ($p1Time >= '11:30:00' && $p1Time <= '14:30:00' && $p2Time >= '15:00:00') {
                            $breakOutPunch = $p1;
                            $exitPunch = $p2;
                            $observaciones[] = "Omitió marcación de retorno de refrigerio";
                        } elseif ($p1Time >= '11:30:00' && $p1Time <= '14:30:00' && $p2Time >= '12:30:00' && $p2Time <= '15:15:00') {
                            $breakOutPunch = $p1;
                            $breakInPunch = $p2;
                            $observaciones[] = "Registró refrigerio; en jornada laboral pendiente de salida";
                        } else {
                            $breakOutPunch = $p1;
                            $exitPunch = $p2;
                        }
                    } elseif ($postCount >= 3) {
                        $breakOutPunch = $postPunches[0]['fecha_hora'];
                        $breakInPunch = $postPunches[1]['fecha_hora'];
                        $exitPunch = $postPunches[$postCount - 1]['fecha_hora'];
                        if ($postCount > 3) {
                            $observaciones[] = "Total {$postCount} marcaciones registradas tras el ingreso";
                        }
                    }
                }

                // Preservar refrigerio previo si ya existía en BD y no hubo nueva marca
                if ($breakOutPunch === null && !empty($existingByEmp[$empId]['hora_inicio_refrigerio_real'])) {
                    $breakOutPunch = $existingByEmp[$empId]['hora_inicio_refrigerio_real'];
                }
                if ($breakInPunch === null && !empty($existingByEmp[$empId]['hora_fin_refrigerio_real'])) {
                    $breakInPunch = $existingByEmp[$empId]['hora_fin_refrigerio_real'];
                }
            } elseif ($isSaturday) {
                // SÁBADO: Se esperan 2 registros de huella (Ingreso 08:00 y Salida 13:00)
                if ($punchCount === 1) {
                    $p1Time = date('H:i:s', strtotime($cleanPunches[0]['fecha_hora']));
                    if ($p1Time >= '11:30:00') {
                        // Marcación cercana a la hora de salida: se interpreta como salida omitiendo entrada
                        $exitPunch = $cleanPunches[0]['fecha_hora'];
                        $observaciones[] = "Omitió marcación de ingreso; registró salida a las " . substr($p1Time, 0, 5);
                    } else {
                        $entryPunch = $cleanPunches[0]['fecha_hora'];
                    }
                } else {
                    $entryPunch = $cleanPunches[0]['fecha_hora'];
                    $exitPunch = $cleanPunches[$punchCount - 1]['fecha_hora'];
                    if ($punchCount > 2) {
                        $observaciones[] = "Total {$punchCount} marcaciones en sábado";
                    }
                }
            } else {
                // LUNES A VIERNES: Se esperan 4 registros de huella
                // 1° Huella: Entrada (~08:00)
                // 2° Huella: Salida refrigerio (~13:00)
                // 3° Huella: Retorno refrigerio (~13:45)
                // 4° Huella: Salida jornada (~17:00)
                if ($punchCount === 1) {
                    $p1 = $cleanPunches[0]['fecha_hora'];
                    $p1Time = date('H:i:s', strtotime($p1));
                    if ($p1Time >= '14:00:00') {
                        // Marcación de la tarde/salida: se interpreta como salida omitiendo la entrada
                        $exitPunch = $p1;
                        $observaciones[] = "Omitió marcación de ingreso matutino; registró salida a las " . substr($p1Time, 0, 5);
                    } elseif ($p1Time >= '11:30:00' && $p1Time <= '13:30:00') {
                        // Marcación de mediodía
                        $breakOutPunch = $p1;
                        $observaciones[] = "Solo registró salida a refrigerio (" . substr($p1Time, 0, 5) . "); sin ingreso ni salida";
                    } else {
                        $entryPunch = $p1;
                    }
                } elseif ($punchCount === 2) {
                    $p1 = $cleanPunches[0]['fecha_hora'];
                    $p2 = $cleanPunches[1]['fecha_hora'];
                    $p1Time = date('H:i:s', strtotime($p1));
                    $p2Time = date('H:i:s', strtotime($p2));

                    if ($p1Time < '12:00:00' && $p2Time >= '11:45:00' && $p2Time <= '14:30:00') {
                        // Marcó entrada en la mañana y salida a refrigerio (laboró el turno de la mañana)
                        $entryPunch = $p1;
                        $breakOutPunch = $p2;
                        $observaciones[] = "Laboró turno matutino (" . substr($p1Time, 0, 5) . " a " . substr($p2Time, 0, 5) . "); omitió retorno y salida vespertina";
                    } elseif ($p1Time >= '13:00:00' && $p2Time >= '15:30:00') {
                        // Marcó retorno de almuerzo y salida de la tarde (laboró el turno de la tarde)
                        $breakInPunch = $p1;
                        $exitPunch = $p2;
                        $observaciones[] = "Laboró turno vespertino (" . substr($p1Time, 0, 5) . " a " . substr($p2Time, 0, 5) . "); omitió jornada matutina";
                    } else {
                        // Marcación de entrada y salida general (jornada completa sin registrar refrigerio)
                        $entryPunch = $p1;
                        $exitPunch = $p2;
                    }
                } elseif ($punchCount === 3) {
                    $p1 = $cleanPunches[0]['fecha_hora'];
                    $p2 = $cleanPunches[1]['fecha_hora'];
                    $p3 = $cleanPunches[2]['fecha_hora'];
                    $timeP1 = date('H:i:s', strtotime($p1));
                    $timeP2 = date('H:i:s', strtotime($p2));
                    $timeP3 = date('H:i:s', strtotime($p3));

                    // Heurística horaria para determinar qué huella faltó
                    if ($timeP2 >= '12:00:00' && $timeP2 <= '14:30:00' && $timeP3 >= '12:30:00' && $timeP3 <= '15:15:00') {
                        $entryPunch = $p1;
                        $breakOutPunch = $p2;
                        $breakInPunch = $p3;
                        $observaciones[] = "Registró refrigerio, pero omitió marcación de salida final";
                    } elseif ($timeP2 >= '12:00:00' && $timeP2 <= '14:30:00' && $timeP3 >= '15:30:00') {
                        $entryPunch = $p1;
                        $breakOutPunch = $p2;
                        $exitPunch = $p3;
                        $observaciones[] = "Omitió marcación de retorno de refrigerio";
                    } elseif ($timeP1 < '12:00:00' && $timeP2 >= '13:15:00' && $timeP2 <= '15:15:00' && $timeP3 >= '15:30:00') {
                        $entryPunch = $p1;
                        $breakInPunch = $p2;
                        $exitPunch = $p3;
                        $observaciones[] = "Omitió marcación de salida a refrigerio";
                    } elseif ($timeP1 >= '12:00:00') {
                        // Caso donde las 3 marcas son de tarde
                        $breakOutPunch = $p1;
                        $breakInPunch = $p2;
                        $exitPunch = $p3;
                        $observaciones[] = "Omitió marcación de ingreso matutino";
                    } else {
                        $entryPunch = $p1;
                        $breakOutPunch = $p2;
                        $exitPunch = $p3;
                        $observaciones[] = "Registró 3 marcaciones en el día";
                    }
                } else {
                    $entryPunch = $cleanPunches[0]['fecha_hora'];
                    $breakOutPunch = $cleanPunches[1]['fecha_hora'];
                    $breakInPunch = $cleanPunches[2]['fecha_hora'];
                    $exitPunch = $cleanPunches[$punchCount - 1]['fecha_hora'];
                    if ($punchCount > 4) {
                        $observaciones[] = "Total {$punchCount} marcaciones en el día";
                    }
                }
            }

            $minutosTardanza = 0;
            $minutosTrabajados = 0;
            $minutosExtra = 0;
            $minutosSalidaTemprana = 0;
            $estado = 'PRESENTE';

            if ($entryPunch && $scheduledEntryStr) {
                $dtEntryReal = new DateTime($entryPunch);
                $dtEntryProg = new DateTime($scheduledEntryStr);
                $dtEntryGrace = (clone $dtEntryProg)->modify("+{$tolerancia} minutes");
                $dtEntryLimitFalta = (clone $dtEntryProg)->modify("+{$toleranciaFalta} minutes");

                if ($dtEntryReal <= $dtEntryGrace) {
                    $estado = 'PRESENTE';
                } elseif ($dtEntryReal <= $dtEntryLimitFalta) {
                    $diff = $dtEntryReal->getTimestamp() - $dtEntryProg->getTimestamp();
                    $minutosTardanza = (int)floor($diff / 60);
                    $estado = 'TARDANZA';
                    $observaciones[] = "Llegada con tardanza ({$minutosTardanza} min)";
                } else {
                    $diff = $dtEntryReal->getTimestamp() - $dtEntryProg->getTimestamp();
                    $minutosTardanza = (int)floor($diff / 60);
                    $estado = 'FALTA';
                    $observaciones[] = "Falta por tardanza excesiva (+{$minutosTardanza} min > {$toleranciaFalta} min permitidos)";
                }
            } elseif (!$entryPunch && $exitPunch) {
                // Registró salida pero omitió el ingreso
                $estado = 'ENTRADA_SIN_MARCAR';
            }

            // =========================================================================
            // CÁLCULO DE HORAS TRABAJADAS Y REGLAS DE REFRIGERIO
            // Requiere OBLIGATORIAMENTE marcación de Entrada y Salida General.
            // Si falta Entrada o Salida General, no se pueden calcular horas trabajadas.
            // =========================================================================
            if ($entryPunch && $exitPunch) {
                // CASO A: Marcó Entrada y Salida general completa -> SE REALIZA EL CÁLCULO
                $dtEntryReal = new DateTime($entryPunch);
                $dtExitReal = new DateTime($exitPunch);
                $dtProgEntry = $scheduledEntryStr ? new DateTime($scheduledEntryStr) : new DateTime("$date 08:00:00");
                $dtEffectiveEntry = ($dtEntryReal < $dtProgEntry) ? $dtProgEntry : $dtEntryReal;
                $diffSeconds = $dtExitReal->getTimestamp() - $dtEffectiveEntry->getTimestamp();

                if ($diffSeconds > 0) {
                    $minutosBrutos = (int)floor($diffSeconds / 60);

                    if ($isSaturday) {
                        // Sábado sin refrigerio (08:00 a 13:00): permanencia íntegra
                        $minutosTrabajados = $minutosBrutos;
                    } else {
                        // Lunes a Viernes (08:00 a 17:00):
                        // Regla 1: Si solo marcó salida de refrigerio y NO retorno -> se descuenta 1 HORA (60 min)
                        // Regla 2: En cualquier otro caso -> sí o sí descuento automático obligatorio de 45 min (o tiempo tomado si superó los 45 min)
                        if ($breakOutPunch && !$breakInPunch) {
                            $minutosDescuentoRef = 60;
                            $observaciones[] = "Omitió retorno de refrigerio: se descuenta 1 hora (60 min) reglamentaria";
                        } elseif ($breakOutPunch && $breakInPunch) {
                            $dtBreakOut = new DateTime($breakOutPunch);
                            $dtBreakIn = new DateTime($breakInPunch);
                            $minutosRefrigerioTomados = 0;
                            if ($dtBreakIn > $dtBreakOut) {
                                $minutosRefrigerioTomados = (int)floor(($dtBreakIn->getTimestamp() - $dtBreakOut->getTimestamp()) / 60);
                            }
                            $minutosDescuentoRef = max(45, $minutosRefrigerioTomados);
                            if ($minutosRefrigerioTomados > 50) {
                                $excesoRef = $minutosRefrigerioTomados - 45;
                                $observaciones[] = "Exceso en refrigerio (+{$excesoRef} min tomados)";
                            }
                        } else {
                            // No marcó ninguna huella de refrigerio o solo marcó retorno: descuento automático obligatorio de 45 minutos
                            $minutosDescuentoRef = 45;
                            if (!$breakOutPunch && !$breakInPunch) {
                                $observaciones[] = "Refrigerio no registrado (descuento automático obligatorio de 45 min)";
                            } else {
                                $observaciones[] = "Omitió salida a refrigerio (descuento automático obligatorio de 45 min)";
                            }
                        }

                        $minutosTrabajados = max(0, $minutosBrutos - $minutosDescuentoRef);
                    }
                } else {
                    $minutosTrabajados = 0;
                }

                // Cálculo de horas extras o salida anticipada respecto a la salida programada
                if ($scheduledExitStr) {
                    $dtExitProg = new DateTime($scheduledExitStr);
                    if ($dtExitReal > $dtExitProg) {
                        $extraSec = $dtExitReal->getTimestamp() - $dtExitProg->getTimestamp();
                        $minutosExtraCalc = (int)floor($extraSec / 60);
                        if ($minutosExtraCalc >= 15) {
                            $minutosExtra = $minutosExtraCalc;
                        }
                    } elseif ($dtExitReal < $dtExitProg) {
                        $earlySec = $dtExitProg->getTimestamp() - $dtExitReal->getTimestamp();
                        $minutosSalidaTemprana = (int)floor($earlySec / 60);
                        if ($minutosSalidaTemprana >= 5) {
                            $observaciones[] = "Salida anticipada ({$minutosSalidaTemprana} min)";
                        }
                    }
                }

            } else {
                // CASO SIN ENTRADA O SIN SALIDA GENERAL:
                // "el botón calcular solamente calcule cuando las horas trabajadas, que esté la hora de inicio marcada a las 8 y a las 17 que esté marcado de inicio y salida... si no, no se podría hacer ese cálculo."
                $minutosTrabajados = 0;
                $minutosExtra = 0;

                if ($entryPunch && !$exitPunch) {
                    // MARCÓ ENTRADA PERO NO MARCÓ SALIDA GENERAL (17:00 / 13:00)
                    $dtEntryReal = new DateTime($entryPunch);
                    $dtProgExit = $scheduledExitStr ? new DateTime($scheduledExitStr) : (clone $dtEntryReal)->modify($isSaturday ? '+5 hours' : '+9 hours');
                    $dtExitWaitLimit = (clone $dtProgExit)->modify('+2 hours');

                    if ($isToday && $now < $dtExitWaitLimit) {
                        // Jornada en curso hoy: horas trabajadas se computarán al registrar la salida general
                        if ($estado !== 'TARDANZA' && $estado !== 'FALTA') {
                            $estado = 'PRESENTE';
                        }
                        $observaciones[] = "Jornada en curso (ingreso a las " . $dtEntryReal->format('H:i') . ") - Horas trabajadas se computarán al marcar salida general (" . ($isSaturday ? "13:00" : "17:00") . ")";
                    } else {
                        // Jornada concluida sin marcación de salida: estado SALIDA_SIN_MARCAR y 0 horas trabajadas
                        if ($estado !== 'FALTA') {
                            $estado = 'SALIDA_SIN_MARCAR';
                        }
                        $observaciones[] = "Sin marcación de salida general (" . ($isSaturday ? "13:00" : "17:00") . "): no se realiza cálculo de horas trabajadas";
                    }

                } elseif (!$entryPunch && $exitPunch) {
                    // OMITIÓ ENTRADA Y SOLO MARCÓ SALIDA GENERAL
                    $estado = 'ENTRADA_SIN_MARCAR';
                    $observaciones[] = "Sin marcación de ingreso (" . ($progHoraEntrada ? substr($progHoraEntrada, 0, 5) : "08:00") . "): no se realiza cálculo de horas trabajadas";

                } elseif ($breakOutPunch || $breakInPunch) {
                    // SOLO MARCÓ REFRIGERIO SIN ENTRADA NI SALIDA GENERAL
                    $estado = 'SALIDA_SIN_MARCAR';
                    $observaciones[] = "Solo registró refrigerio sin entrada ni salida general: no se realiza cálculo de horas trabajadas";
                }
            }

            // Justificaciones de tardanza o falta
            if ($justification) {
                if ($justification['tipo'] === 'TARDANZA' && ($estado === 'TARDANZA' || $estado === 'FALTA')) {
                    $estado = 'JUSTIFICADO';
                    $observaciones[] = 'Tardanza justificada: ' . ($justification['motivo'] ?? 'Autorizado por RRHH');
                } elseif ($justification['tipo'] === 'FALTA' && $estado === 'FALTA') {
                    $estado = 'JUSTIFICADO';
                    $observaciones[] = 'Inasistencia justificada: ' . ($justification['motivo'] ?? 'Autorizado por RRHH');
                } elseif (in_array($justification['tipo'], ['PERMISO', 'LICENCIA', 'COMISION', 'VACACIONES'])) {
                    $estado = $justification['tipo'] === 'VACACIONES' ? 'VACACIONES' : 'JUSTIFICADO';
                    $observaciones[] = "Permiso/Comisión autorizada: " . ($justification['motivo'] ?? $justification['tipo']);
                }
            }

            $isManualPreserved = $hasManualEntry ? 1 : 0;
            $recordsToUpsert[] = $this->buildRecordData(
                $empId, $turnoId, $date,
                $progHoraEntrada,
                $progHoraSalida,
                $entryPunch, $exitPunch, $breakOutPunch, $breakInPunch,
                $minutosTardanza, $minutosTrabajados, $minutosExtra, $minutosSalidaTemprana,
                $estado, implode(' | ', $observaciones), $tolerancia, null, $isManualPreserved
            );

            $stats['processed']++;
            if ($estado === 'PRESENTE') $stats['present']++;
            elseif ($estado === 'TARDANZA') $stats['late']++;
            elseif ($estado === 'FALTA' || $estado === 'FALTA_INJUSTIFICADA') $stats['absent']++;
            elseif ($estado === 'JUSTIFICADO' || $estado === 'PERMISO' || $estado === 'VACACIONES') $stats['justified']++;
            elseif ($estado === 'SALIDA_SIN_MARCAR' || $estado === 'ENTRADA_SIN_MARCAR') $stats['missing_exit']++;
        }

        // Ordenar registros determinísticamente por ID de empleado para prevenir deadlocks en InnoDB
        usort($recordsToUpsert, function($a, $b) {
            return $a[':id_emp'] <=> $b[':id_emp'];
        });

        // 6. Persistencia Masiva Transaccional
        Database::transaction(function() use ($recordsToUpsert, $processedPunchIds) {
            $sql = "
                INSERT INTO asistencia_diaria (
                    id_empleado, id_turno, fecha,
                    hora_entrada_programada, hora_salida_programada,
                    hora_entrada_real, hora_salida_real,
                    hora_inicio_refrigerio_real, hora_fin_refrigerio_real,
                    minutos_tardanza, minutos_trabajados, minutos_extra, minutos_salida_temprana,
                    estado, observaciones, comision_destino, manual
                ) VALUES (
                    :id_emp, :id_turno, :fecha,
                    :ent_prog, :sal_prog,
                    :ent_real, :sal_real,
                    :ref_sal, :ref_ent,
                    :tardanza, :trabajados, :extra, :temprana,
                    :estado, :obs, :comision_dest, :manual
                )
                ON DUPLICATE KEY UPDATE
                    id_turno = VALUES(id_turno),
                    hora_entrada_programada = VALUES(hora_entrada_programada),
                    hora_salida_programada  = VALUES(hora_salida_programada),
                    hora_entrada_real = IF(manual = 1, hora_entrada_real, VALUES(hora_entrada_real)),
                    hora_salida_real  = VALUES(hora_salida_real),
                    hora_inicio_refrigerio_real = VALUES(hora_inicio_refrigerio_real),
                    hora_fin_refrigerio_real    = VALUES(hora_fin_refrigerio_real),
                    minutos_tardanza  = VALUES(minutos_tardanza),
                    minutos_trabajados = VALUES(minutos_trabajados),
                    minutos_extra = VALUES(minutos_extra),
                    minutos_salida_temprana = VALUES(minutos_salida_temprana),
                    estado = VALUES(estado),
                    observaciones = VALUES(observaciones),
                    comision_destino = VALUES(comision_destino),
                    procesado_en = NOW()
            ";

            $stmt = Database::getConnection()->prepare($sql);
            foreach ($recordsToUpsert as $rec) {
                $stmt->execute($rec);
            }

            // Marcar marcaciones como procesadas
            if (!empty($processedPunchIds)) {
                $uniquePunchIds = array_unique($processedPunchIds);
                $chunkedIds = array_chunk($uniquePunchIds, 500);
                foreach ($chunkedIds as $chunk) {
                    $inClause = implode(',', $chunk);
                    Database::execute("UPDATE marcaciones SET procesado = 1 WHERE id IN ($inClause)");
                }
            }
        });

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
     * Procesa la asistencia individual de un empleado en un día específico (compatibilidad unitaria)
     */
    public function processEmployeeDate(array $employee, string $date, bool $isHoliday = false): string {
        $this->processDate($date);
        $empId = (int)$employee['id'];
        $row = Database::queryOne("SELECT estado FROM asistencia_diaria WHERE id_empleado = ? AND fecha = ?", [$empId, $date]);
        return $row['estado'] ?? 'PRESENTE';
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

    private function buildRecordData(
        int $empId, ?int $turnoId, string $fecha,
        ?string $horaEntradaProg, ?string $horaSalidaProg,
        ?string $horaEntradaReal, ?string $horaSalidaReal,
        ?string $refrigerioSalidaReal, ?string $refrigerioEntradaReal,
        int $minutosTardanza, int $minutosTrabajados, int $minutosExtra, int $minutosSalidaTemprana,
        string $estado, string $observaciones, int $toleranciaAplicada = 10, ?string $comisionDestino = null,
        int $manual = 0
    ): array {
        return [
            ':id_emp'        => $empId,
            ':id_turno'      => $turnoId,
            ':fecha'         => $fecha,
            ':ent_prog'      => $horaEntradaProg,
            ':sal_prog'      => $horaSalidaProg,
            ':ent_real'      => $horaEntradaReal,
            ':sal_real'      => $horaSalidaReal,
            ':ref_sal'       => $refrigerioSalidaReal,
            ':ref_ent'       => $refrigerioEntradaReal,
            ':tardanza'      => $minutosTardanza,
            ':trabajados'    => $minutosTrabajados,
            ':extra'         => $minutosExtra,
            ':temprana'      => $minutosSalidaTemprana,
            ':estado'        => $estado,
            ':obs'           => $observaciones ?: null,
            ':comision_dest' => $comisionDestino ?: null,
            ':manual'        => $manual
        ];
    }
}
