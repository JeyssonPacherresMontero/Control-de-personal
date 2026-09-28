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
            SELECT id_empleado, manual, estado 
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

            // Si ya existe un registro modificado manualmente por RRHH, respetarlo
            if (isset($existingByEmp[$empId]) && (int)$existingByEmp[$empId]['manual'] === 1) {
                $stats['processed']++;
                $st = $existingByEmp[$empId]['estado'] ?? 'PRESENTE';
                if ($st === 'PRESENTE') $stats['present']++;
                elseif ($st === 'TARDANZA') $stats['late']++;
                elseif ($st === 'FALTA' || $st === 'FALTA_INJUSTIFICADA') $stats['absent']++;
                elseif ($st === 'JUSTIFICADO' || $st === 'PERMISO' || $st === 'VACACIONES') $stats['justified']++;
                elseif ($st === 'SALIDA_SIN_MARCAR') $stats['missing_exit']++;
                continue;
            }

            $workdays = !empty($emp['dias_laborables']) ? explode(',', $emp['dias_laborables']) : [1,2,3,4,5];
            $isWorkday = in_array((string)$dayOfWeek, $workdays);
            $justification = $justByEmp[$empId] ?? null;

            $esNocturno = !empty($emp['es_nocturno']) && (int)$emp['es_nocturno'] === 1;
            $empWindowStart = $esNocturno ? "$date 12:00:00" : "$date 00:00:00";
            $empWindowEnd = $esNocturno ? "$nextDate 14:00:00" : "$date 23:59:59";

            // Obtener marcaciones del empleado en su ventana de turno
            $allPunches = $punchesByEmpId[$empId] ?? ($punchesByCode[$codigoReloj] ?? ($punchesByCode[$normEmpCode] ?? []));
            $empPunches = [];
            foreach ($allPunches as $p) {
                if ($p['fecha_hora'] >= $empWindowStart && $p['fecha_hora'] <= $empWindowEnd) {
                    $empPunches[] = $p;
                    $processedPunchIds[] = (int)$p['id'];
                }
            }

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

            // Caso: Cero marcaciones en día laborable
            if ($punchCount === 0) {
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

            // Caso: Existen marcaciones
            $entryPunch = null;
            $exitPunch = null;
            $breakOutPunch = null;
            $breakInPunch = null;
            $observaciones = [];

            if ($isSaturday) {
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

            // Refrigerio (solo aplica de Lunes a Viernes)
            $minutosRefrigerioTomados = 0;
            if (!$isSaturday) {
                if ($breakOutPunch && $breakInPunch) {
                    $dtBreakOut = new DateTime($breakOutPunch);
                    $dtBreakIn = new DateTime($breakInPunch);
                    if ($dtBreakIn > $dtBreakOut) {
                        $minutosRefrigerioTomados = (int)floor(($dtBreakIn->getTimestamp() - $dtBreakOut->getTimestamp()) / 60);
                        if ($minutosRefrigerioTomados > 50) {
                            $excesoRef = $minutosRefrigerioTomados - 45;
                            $observaciones[] = "Exceso en refrigerio (+{$excesoRef} min)";
                        }
                    }
                } elseif ($breakOutPunch && !$breakInPunch && $exitPunch) {
                    // Marcó salida a refrigerio, omitió retorno, pero SÍ marcó salida final al concluir la jornada
                    $minutosRefrigerioTomados = 50; // 45 min reglamentarios + 5 min retardo
                    $horaSalidaStr = date('H:i', strtotime($exitPunch));
                    $observaciones[] = "Omitió retorno de refrigerio: se descontaron 50 min (45 min reglamentarios + 5 min retardo) al verificar salida final registrada ($horaSalidaStr)";
                }
            }

            // Salida y Jornada de Trabajo Efectivo (Horas Trabajadas SIN contar refrigerio)
            if ($entryPunch && $exitPunch) {
                $dtEntryReal = new DateTime($entryPunch);
                $dtExitReal = new DateTime($exitPunch);
                $diffSeconds = $dtExitReal->getTimestamp() - $dtEntryReal->getTimestamp();
                if ($diffSeconds > 0) {
                    $minutosBrutos = (int)floor($diffSeconds / 60);
                    if ($isSaturday) {
                        // En Sábado no hay refrigerio: horas trabajadas = permanencia íntegra (5h si 08:00 a 13:00)
                        $minutosTrabajados = $minutosBrutos;
                    } else {
                        // En Lunes a Viernes: HORAS TRABAJADAS SIN CONTAR LA HORA DE REFRIGERIO
                        if ($minutosRefrigerioTomados > 0) {
                            $minutosTrabajados = max(0, $minutosBrutos - $minutosRefrigerioTomados);
                        } elseif ($minutosBrutos >= 300) {
                            // Si cubrió la jornada pero omitió registrar las 2 huellas de refrigerio, se descuenta el refrigerio reglamentario (45 min)
                            $minutosDeducir = $minutosRefProgramados > 0 ? $minutosRefProgramados : 45;
                            $minutosTrabajados = max(0, $minutosBrutos - $minutosDeducir);
                            $observaciones[] = "Omitió marcar refrigerio (se descontó refrigerio reglamentario de {$minutosDeducir} min)";
                        } else {
                            $minutosTrabajados = $minutosBrutos;
                        }
                    }
                }

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
            } elseif ($entryPunch && !$exitPunch) {
                // Entrada registrada, pero sin salida final
                if ($breakOutPunch) {
                    // Se rescata el turno matutino trabajado
                    $minutosMatutinos = (int)floor((strtotime($breakOutPunch) - strtotime($entryPunch)) / 60);
                    if ($minutosMatutinos > 0) {
                        $minutosTrabajados = $minutosMatutinos;
                        $observaciones[] = "Horas laboradas calculadas de jornada matutina";
                    }
                }

                $dtEntryReal = new DateTime($entryPunch);
                if ($isToday) {
                    $dtExitProg = $scheduledExitStr ? new DateTime($scheduledExitStr) : (clone $dtEntryReal)->modify($isSaturday ? '+5 hours' : '+9 hours');
                    $dtExitLimit = (clone $dtExitProg)->modify('+3 hours');

                    if ($now <= $dtExitLimit) {
                        $observaciones[] = "Jornada en curso (ingreso a las " . $dtEntryReal->format('H:i') . ")";
                    } else {
                        $estado = 'SALIDA_SIN_MARCAR';
                        $observaciones[] = "No registró marcación de salida";
                    }
                } else {
                    $estado = 'SALIDA_SIN_MARCAR';
                    $observaciones[] = "No registró marcación de salida";
                }
            } elseif (!$entryPunch && $exitPunch) {
                // Salida registrada, pero sin entrada matutina
                if ($breakInPunch) {
                    // Se rescata el turno vespertino trabajado
                    $minutosVespertinos = (int)floor((strtotime($exitPunch) - strtotime($breakInPunch)) / 60);
                    if ($minutosVespertinos > 0) {
                        $minutosTrabajados = $minutosVespertinos;
                        $observaciones[] = "Horas laboradas calculadas de jornada vespertina";
                    }
                }
                $estado = 'ENTRADA_SIN_MARCAR';
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

            $recordsToUpsert[] = $this->buildRecordData(
                $empId, $turnoId, $date,
                $progHoraEntrada,
                $progHoraSalida,
                $entryPunch, $exitPunch, $breakOutPunch, $breakInPunch,
                $minutosTardanza, $minutosTrabajados, $minutosExtra, $minutosSalidaTemprana,
                $estado, implode(' | ', $observaciones), $tolerancia
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
                    :estado, :obs, :comision_dest, 0
                )
                ON DUPLICATE KEY UPDATE
                    id_turno = IF(manual = 1, id_turno, VALUES(id_turno)),
                    hora_entrada_programada = IF(manual = 1, hora_entrada_programada, VALUES(hora_entrada_programada)),
                    hora_salida_programada  = IF(manual = 1, hora_salida_programada, VALUES(hora_salida_programada)),
                    hora_entrada_real = IF(manual = 1, hora_entrada_real, VALUES(hora_entrada_real)),
                    hora_salida_real  = IF(manual = 1, hora_salida_real, VALUES(hora_salida_real)),
                    hora_inicio_refrigerio_real = IF(manual = 1, hora_inicio_refrigerio_real, VALUES(hora_inicio_refrigerio_real)),
                    hora_fin_refrigerio_real    = IF(manual = 1, hora_fin_refrigerio_real, VALUES(hora_fin_refrigerio_real)),
                    minutos_tardanza  = IF(manual = 1, minutos_tardanza, VALUES(minutos_tardanza)),
                    minutos_trabajados = IF(manual = 1, minutos_trabajados, VALUES(minutos_trabajados)),
                    minutos_extra = IF(manual = 1, minutos_extra, VALUES(minutos_extra)),
                    minutos_salida_temprana = IF(manual = 1, minutos_salida_temprana, VALUES(minutos_salida_temprana)),
                    estado = IF(manual = 1, estado, VALUES(estado)),
                    observaciones = IF(manual = 1, observaciones, VALUES(observaciones)),
                    comision_destino = IF(manual = 1, comision_destino, VALUES(comision_destino)),
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
        string $estado, string $observaciones, int $toleranciaAplicada = 10, ?string $comisionDestino = null
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
            ':comision_dest' => $comisionDestino ?: null
        ];
    }
}
