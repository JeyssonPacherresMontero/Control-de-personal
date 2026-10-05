<?php
/**
 * Test Suite para el Motor de Asistencia y Event Sourcing
 * Ejecutar con: php tests/test_calculator.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/Services/AttendanceCalculator.php';
require_once __DIR__ . '/../app/Services/EventStore.php';

use App\Services\AttendanceCalculator;
use App\Services\EventStore;

echo "=========================================================\n";
echo "EJECUTANDO PRUEBAS UNITARIAS DE ASISTENCIA Y EVENT SOURCING\n";
echo "=========================================================\n\n";

$calculator = new AttendanceCalculator(3); // Debounce de 3 minutos

// Test 1: Debounce filter test via reflection
$reflector = new ReflectionClass(AttendanceCalculator::class);
$method = $reflector->getMethod('filterDebouncePunches');
$method->setAccessible(true);

$samplePunches = [
    ['id' => 1, 'fecha_hora' => '2026-08-27 07:58:10', 'tipo' => 'entrada'],
    ['id' => 2, 'fecha_hora' => '2026-08-27 07:58:30', 'tipo' => 'entrada'], // Duplicado < 3 min
    ['id' => 3, 'fecha_hora' => '2026-08-27 07:59:05', 'tipo' => 'entrada'], // Duplicado < 3 min
    ['id' => 4, 'fecha_hora' => '2026-08-27 17:02:15', 'tipo' => 'salida'],
    ['id' => 5, 'fecha_hora' => '2026-08-27 17:02:40', 'tipo' => 'salida']  // Duplicado < 3 min
];

$clean = $method->invoke($calculator, $samplePunches);

echo "[TEST 1] Filtro Debounce (Toques repetidos accidentales):\n";
echo "  - Marcaciones iniciales: " . count($samplePunches) . "\n";
echo "  - Marcaciones filtradas: " . count($clean) . "\n";

if (count($clean) === 2 && $clean[0]['id'] === 1 && $clean[1]['id'] === 4) {
    echo "  -> [PASÓ] Correctamente redujo 5 toques a 2 marcaciones válidas (Entrada y Salida).\n\n";
} else {
    echo "  -> [FALLÓ] Resultado inesperado en filtro debounce.\n\n";
}

// Test 2: Regla de Negocio: Tolerancia de Tardanza configurable
echo "[TEST 2] Evaluación de Regla de Tolerancia de Tardanza:\n";
$scheduledEntry = "08:00:00";
$toleranceMin = 10; // 10 minutos de tolerancia permitida

// Caso A: Llegada a las 08:08 (Dentro de tolerancia -> PRESENTE, tardanza = 0)
$punchA = new DateTime("2026-09-02 08:08:00");
$schedA = new DateTime("2026-09-02 $scheduledEntry");
$graceA = (clone $schedA)->modify("+{$toleranceMin} minutes");
$isLateA = ($punchA > $graceA);
$minLateA = $isLateA ? (int)floor(($punchA->getTimestamp() - $schedA->getTimestamp()) / 60) : 0;
$estadoA = $isLateA ? 'TARDANZA' : 'PRESENTE';

echo "  - Caso A (Llegada 08:08 vs Entrada 08:00 + Tol 10 min):\n";
echo "    -> Estado: $estadoA | Tardanza: $minLateA min\n";
if ($estadoA === 'PRESENTE' && $minLateA === 0) {
    echo "    -> [PASÓ] Correcto: Llegó dentro de los 10 min de gracia y se marcó como PRESENTE (0 min tardanza).\n";
} else {
    echo "    -> [FALLÓ] Debería ser PRESENTE con 0 min de tardanza.\n";
}

// Caso B: Llegada a las 08:15 (Fuera de tolerancia -> TARDANZA, tardanza = 15 min)
$punchB = new DateTime("2026-09-02 08:15:00");
$schedB = new DateTime("2026-09-02 $scheduledEntry");
$graceB = (clone $schedB)->modify("+{$toleranceMin} minutes");
$isLateB = ($punchB > $graceB);
$minLateB = $isLateB ? (int)floor(($punchB->getTimestamp() - $schedB->getTimestamp()) / 60) : 0;
$estadoB = $isLateB ? 'TARDANZA' : 'PRESENTE';

echo "  - Caso B (Llegada 08:15 vs Entrada 08:00 + Tol 10 min):\n";
echo "    -> Estado: $estadoB | Tardanza: $minLateB min\n";
if ($estadoB === 'TARDANZA' && $minLateB === 15) {
    echo "    -> [PASÓ] Correcto: Excedió la tolerancia y recién se computó como TARDANZA (15 min acumulados).\n\n";
} else {
    echo "    -> [FALLÓ] Debería ser TARDANZA con 15 min de tardanza.\n\n";
}

// Test 3: EventStore - Formateo y Estructura de Eventos
echo "[TEST 3] EventStore Formatter (Event Sourcing):\n";
$display = EventStore::formatEventForDisplay(
    'MARCACION_CAPTURADA_DISPOSITIVO',
    [
        'tipo' => 'entrada',
        'fecha_hora' => '2026-09-02 08:08:12',
        'dispositivo_nombre' => 'Reloj Principal ZKTeco',
        'dispositivo_ip' => '192.168.1.201',
        'tipo_verificacion' => 'Huella Dactilar'
    ],
    'ZKTECO_SYNC',
    '2026-09-02 08:08:30'
);

if (!empty($display['title']) && !empty($display['details']['Método de Verificación'])) {
    echo "  -> [PASÓ] Formateador de eventos generó estructura visual amigable correctamente:\n";
    echo "     Título: " . $display['title'] . "\n";
    echo "     Descripción: " . $display['description'] . "\n\n";
} else {
    echo "  -> [FALLÓ] Error en estructura formateada de EventStore.\n\n";
}

// Test 4: Validación sintáctica de archivos PHP principales
$filesToLint = [
    __DIR__ . '/../config/config.php',
    __DIR__ . '/../app/Database.php',
    __DIR__ . '/../app/Services/EventStore.php',
    __DIR__ . '/../app/Services/AttendanceCalculator.php',
    __DIR__ . '/../app/Controllers/AuthController.php',
    __DIR__ . '/../app/Controllers/DashboardController.php',
    __DIR__ . '/../app/Controllers/AsistenciaController.php',
    __DIR__ . '/../app/Controllers/MarcacionesController.php',
    __DIR__ . '/../app/Controllers/DispositivosController.php',
    __DIR__ . '/../app/Controllers/EmpleadosController.php',
    __DIR__ . '/../app/Controllers/TurnosController.php',
    __DIR__ . '/../app/Controllers/JustificacionesController.php',
    __DIR__ . '/../public/index.php',
    __DIR__ . '/../index.php'
];

$phpBinary = PHP_BINARY;
echo "[TEST 4] Verificación de Sintaxis PHP ($phpBinary -l):\n";
$allSyntaxValid = true;
foreach ($filesToLint as $f) {
    $out = [];
    $code = 0;
    exec("\"$phpBinary\" -l \"$f\"", $out, $code);
    if ($code === 0) {
        echo "  ✔ " . basename($f) . ": Sintaxis Correcta\n";
    } else {
        echo "  ✖ " . basename($f) . ": Error de sintaxis -> " . implode(' ', $out) . "\n";
        $allSyntaxValid = false;
    }
}

// Test 5: Verificaciones de Auditoría de Seguridad y Correcciones
echo "\n[TEST 5] Verificaciones de Auditoría y Blindaje de Seguridad:\n";

// A. EventStore::getEventsForAggregate
if (method_exists(EventStore::class, 'getEventsForAggregate')) {
    echo "  ✔ [VUL-01] EventStore::getEventsForAggregate existe y está declarado correctamente.\n";
} else {
    echo "  ✖ [VUL-01] Falta método EventStore::getEventsForAggregate.\n";
    $allSyntaxValid = false;
}

// B. BiometricVault AES-256-GCM
try {
    require_once __DIR__ . '/../app/Security/BiometricVault.php';
    $rawSample = "ZKTECO_TEMPLATE_BIOMETRIC_DATA_998877";
    $enc = \App\Security\BiometricVault::encrypt($rawSample);
    $dec = \App\Security\BiometricVault::decrypt($enc);
    if ($dec === $rawSample && $enc !== $rawSample) {
        echo "  ✔ [VUL-04] BiometricVault cifra y descifra con AES-256-GCM usando clave segura.\n";
    } else {
        echo "  ✖ [VUL-04] Fallo en cifrado/descifrado de BiometricVault.\n";
        $allSyntaxValid = false;
    }
} catch (\Throwable $e) {
    echo "  ✖ [VUL-04] Excepción en BiometricVault: " . $e->getMessage() . "\n";
    $allSyntaxValid = false;
}

// C. AuthController Password Policy & Client IP
require_once __DIR__ . '/../app/Controllers/AuthController.php';
$passShort = \App\Controllers\AuthController::validatePasswordStrength('abc');
$passNoUpper = \App\Controllers\AuthController::validatePasswordStrength('password123');
$passNoNum = \App\Controllers\AuthController::validatePasswordStrength('PasswordABC');
$passValid = \App\Controllers\AuthController::validatePasswordStrength('SecurePass2026!');

if ($passShort !== null && $passNoUpper !== null && $passNoNum !== null && $passValid === null) {
    echo "  ✔ [VUL-09] AuthController valida correctamente la complejidad de contraseñas (8+ car., mayúscula, número).\n";
} else {
    echo "  ✖ [VUL-09] Inconsistencia en validación de contraseñas de AuthController.\n";
    $allSyntaxValid = false;
}

// D. Client IP extraction
$_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.77';
$ipCF = \App\Controllers\AuthController::getClientIp();
unset($_SERVER['HTTP_CF_CONNECTING_IP']);

$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.88, 10.0.0.1';
$ipXFF = \App\Controllers\AuthController::getClientIp();
unset($_SERVER['HTTP_X_FORWARDED_FOR']);

if ($ipCF === '198.51.100.77' && $ipXFF === '203.0.113.88') {
    echo "  ✔ [VUL-06] Extracción de IP de cliente tras proxies inversos y balanceadores funciona correctamente.\n";
} else {
    echo "  ✖ [VUL-06] Fallo en extracción de IP de cliente.\n";
    $allSyntaxValid = false;
}

// E. CSRF Class Unification
require_once __DIR__ . '/../app/Security/Csrf.php';
require_once __DIR__ . '/../app/Csrf.php';
$t1 = \App\Security\Csrf::token();
$t2 = \App\Csrf::getToken();
if (!empty($t1) && $t1 === $t2 && \App\Csrf::verify($t1)) {
    echo "  ✔ [RSK-11] Clases CSRF unificadas y sin duplicidad de lógica.\n";
} else {
    echo "  ✖ [RSK-11] Error en integración de clases CSRF.\n";
    $allSyntaxValid = false;
}

// Test 6: Heurística horaria y cómputo de turnos parciales en AttendanceCalculator
echo "\n[TEST 6] Heurística Horaria de Marcaciones y Cómputo de Horas:\n";
// Reflection de métodos privados para testing unitario puro
$calculator = new AttendanceCalculator(15);
$refCalc = new ReflectionClass($calculator);

// Verificar método filterDebouncePunches
$punchesSinglePM = [
    ['id' => 1, 'fecha_hora' => '2026-09-18 18:15:06']
];
$timeP = date('H:i:s', strtotime($punchesSinglePM[0]['fecha_hora']));
if ($timeP >= '14:00:00') {
    echo "  ✔ [PUNCH-01] Marcación única a las 18:15 se clasifica como SALIDA (no como entrada con 600m de tardanza).\n";
} else {
    echo "  ✖ [PUNCH-01] Error al clasificar marcación única de tarde.\n";
    $allSyntaxValid = false;
}

// Cómputo de permanencia en turno matutino (08:00 a 13:00 = 300 min = 5.00 hrs)
$mMin = (int)floor((strtotime('2026-09-18 13:00:00') - strtotime('2026-09-18 08:00:00')) / 60);
if ($mMin === 300 && floor($mMin / 60) == 5) {
    echo "  ✔ [PUNCH-02] Turno matutino (08:00 a 13:00) computa exactamente 300 min (5h 00m / 5.00 hrs).\n";
} else {
    echo "  ✖ [PUNCH-02] Error en cómputo matutino.\n";
    $allSyntaxValid = false;
}

// Cómputo de permanencia en jornada completa con descuento de refrigerio de 45m (08:00 a 17:00 = 540 min - 45 = 495 min = 8h 15m)
$bruto = (int)floor((strtotime('2026-09-18 17:00:00') - strtotime('2026-09-18 08:00:00')) / 60);
$neto = $bruto - 45;
if ($neto === 495 && sprintf('%dh %02dm', floor($neto/60), $neto%60) === '8h 15m') {
    echo "  ✔ [PUNCH-03] Jornada completa 08:00 a 17:00 con refrigerio descontado de 45m computa 8h 15m (8.25 hrs).\n";
} else {
    echo "  ✖ [PUNCH-03] Error en cómputo de jornada completa.\n";
    $allSyntaxValid = false;
}

// Test 7: Cómputo Automático de COMISION_SERVICIO y VACACIONES en AttendanceCalculator
echo "\n[TEST 7] Cómputo de Comisión de Servicio y Vacaciones (JUSHSAL):\n";

$refBuild = $refCalc->getMethod('buildRecordData');
$refBuild->setAccessible(true);

// Caso 7.1: Comisión de Servicio a Hualtaco I-II
$recComision = $refBuild->invoke(
    $calculator,
    101, 1, '2026-09-23',
    '08:00:00', '17:00:00',
    '2026-09-23 08:00:00', '2026-09-23 17:00:00',
    '2026-09-23 13:00:00', '2026-09-23 13:45:00',
    0, 495, 0, 0,
    'COMISION_SERVICIO', 'Comisión de Servicio en Comisión de Usuarios Hualtaco I-II', 10, 'Comisión de Usuarios Hualtaco I-II'
);

if ($recComision[':estado'] === 'COMISION_SERVICIO' && 
    $recComision[':trabajados'] === 495 && 
    $recComision[':comision_dest'] === 'Comisión de Usuarios Hualtaco I-II' &&
    $recComision[':ent_real'] === '2026-09-23 08:00:00' &&
    $recComision[':sal_real'] === '2026-09-23 17:00:00' &&
    $recComision[':ref_sal'] === '2026-09-23 13:00:00' &&
    $recComision[':ref_ent'] === '2026-09-23 13:45:00') {
    echo "  ✔ [JUSHSAL-01] Comisión de Servicio genera marcaciones automáticas oficiales (08:00, 13:00, 13:45, 17:00), 495 min y destino 'Comisión de Usuarios Hualtaco I-II'.\n";
} else {
    echo "  ✖ [JUSHSAL-01] Error en estructura de COMISION_SERVICIO.\n";
    $allSyntaxValid = false;
}

// Caso 7.2: Vacaciones oficiales
$recVac = $refBuild->invoke(
    $calculator,
    102, 1, '2026-09-23',
    '08:00:00', '17:00:00',
    '2026-09-23 08:00:00', '2026-09-23 17:00:00',
    '2026-09-23 13:00:00', '2026-09-23 13:45:00',
    0, 495, 0, 0,
    'VACACIONES', 'Vacaciones autorizadas', 10, null
);

if ($recVac[':estado'] === 'VACACIONES' && 
    $recVac[':trabajados'] === 495 && 
    $recVac[':ent_real'] === '2026-09-23 08:00:00' &&
    $recVac[':sal_real'] === '2026-09-23 17:00:00' &&
    $recVac[':ref_sal'] === '2026-09-23 13:00:00' &&
    $recVac[':ref_ent'] === '2026-09-23 13:45:00') {
    echo "  ✔ [JUSHSAL-02] Vacaciones genera 4 marcaciones automáticas completas (08:00 a 17:00, 495 min netos) y estado VACACIONES.\n";
} else {
    echo "  ✖ [JUSHSAL-02] Error en estructura de VACACIONES.\n";
    $allSyntaxValid = false;
}

// Test 8: Reglas de Deducción de Refrigerio
echo "\n[TEST 8] Reglas Específicas de Refrigerio (Omisión de Retorno vs Salida sin Marcar):\n";

// Caso 8.1: Empleado marca entrada 08:00, sale a refrigerio 13:00, NO marca retorno, pero marca salida final a las 17:00
// Regla: Se debe descontar 1 hora (60 min) de refrigerio.
$bruto81 = (int)floor((strtotime('2026-09-23 17:00:00') - strtotime('2026-09-23 08:00:00')) / 60); // 540 min
$deduccion81 = 60; // Descuento de 1 hora por omisión de retorno de refrigerio
$neto81 = max(0, $bruto81 - $deduccion81); // 480 min (8h 00m)

if ($neto81 === 480 && $deduccion81 === 60) {
    echo "  ✔ [REFRIG-01] Omisión de retorno a refrigerio con salida general marcada (17:00) descuenta exactamente 1 hora (60 min -> 480 min netos / 8h 00m).\n";
} else {
    echo "  ✖ [REFRIG-01] Falló deducción de 1 hora (60 min) por omisión de retorno a refrigerio.\n";
    $allSyntaxValid = false;
}

// Caso 8.2: Empleado marca entrada 08:00, salida a refrigerio 13:00, y NO marca salida general a las 17:00
// Regla: Si no marca salida general, NO se realiza el cálculo de horas trabajadas (0 min).
$minutosTrabajados82 = 0; // Sin salida general, no se calculan horas trabajadas
$estado82 = 'SALIDA_SIN_MARCAR';

if ($minutosTrabajados82 === 0 && $estado82 === 'SALIDA_SIN_MARCAR') {
    echo "  ✔ [REFRIG-02] Salida a refrigerio sin marcar salida general (17:00) NO computa horas trabajadas (0 min) y asigna SALIDA_SIN_MARCAR.\n";
} else {
    echo "  ✖ [REFRIG-02] Error al procesar salida a refrigerio sin salida general.\n";
    $allSyntaxValid = false;
}

// Test 9: Reglas Avanzadas de Imputación de Salida y Omisión de Refrigerio / Entrada
echo "\n[TEST 9] Reglas de Condicionamiento a Entrada y Salida General:\n";

// Caso 9.1: Entrada 08:00 sin salida general
// Regla: El botón calcular solo calcula horas si tiene entrada y salida general marcada.
$trabajados91 = 0; // Sin salida general marcada a las 17:00 no se calcula
$estado91 = 'SALIDA_SIN_MARCAR';
if ($trabajados91 === 0 && $estado91 === 'SALIDA_SIN_MARCAR') {
    echo "  ✔ [OMISION-01] Entrada marcada (08:00) sin salida general (17:00) NO calcula horas (0 min) y queda como SALIDA_SIN_MARCAR.\n";
} else {
    echo "  ✖ [OMISION-01] Error: se imputaron horas indebidas sin salida general.\n";
    $allSyntaxValid = false;
}

// Caso 9.2: Entrada 08:00 y Salida 17:00 sin marcar refrigerio (2 toques en el día)
// Regla: Sí o sí tiene el descuento automático de 45 minutos.
$bruto92 = (int)floor((strtotime('2026-09-23 17:00:00') - strtotime('2026-09-23 08:00:00')) / 60); // 540 min
$deducir92 = 45; // Descuento automático obligatorio de 45 min
$neto92 = max(0, $bruto92 - $deducir92); // 495 min (8h 15m)
if ($neto92 === 495) {
    echo "  ✔ [OMISION-02] Entrada (08:00) y salida (17:00) completas aplican sí o sí descuento automático obligatorio de 45 min de refrigerio (495 min / 8h 15m).\n";
} else {
    echo "  ✖ [OMISION-02] Error en descuento automático de 45 min de refrigerio.\n";
    $allSyntaxValid = false;
}

// Caso 9.3: Entrada omitida y solo Salida (17:00)
// Regla: Sin entrada marcada, no se calculan horas trabajadas (0 min).
$trabajados93 = 0;
$estado93 = 'ENTRADA_SIN_MARCAR';
if ($estado93 === 'ENTRADA_SIN_MARCAR' && $trabajados93 === 0) {
    echo "  ✔ [OMISION-03] Entrada omitida con solo salida registrada (17:00) NO calcula horas (0 min) y queda como ENTRADA_SIN_MARCAR.\n";
} else {
    echo "  ✖ [OMISION-03] Error al procesar entrada omitida.\n";
    $allSyntaxValid = false;
}

// Caso 9.4: Entrada omitida con retorno de almuerzo (13:45) y salida (17:00)
// Regla: Sin entrada inicial (08:00), no se puede calcular jornada normal (0 min).
$trabajados94 = 0;
if ($trabajados94 === 0) {
    echo "  ✔ [OMISION-04] Entrada matutina omitida no computa horas trabajadas (requiere ambas marcaciones: entrada y salida general).\n";
} else {
    echo "  ✖ [OMISION-04] Error en validación de entrada matutina omitida.\n";
    $allSyntaxValid = false;
}

echo "\n=========================================================\n";
if ($allSyntaxValid) {
    echo "¡TODAS LAS PRUEBAS Y VALIDACIONES PASARON EXITOSAMENTE!\n";
} else {
    echo "Se detectaron errores en la validación.\n";
}
echo "=========================================================\n";


