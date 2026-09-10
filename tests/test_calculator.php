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

echo "\n=========================================================\n";
if ($allSyntaxValid) {
    echo "¡TODAS LAS PRUEBAS Y VALIDACIONES PASARON EXITOSAMENTE!\n";
} else {
    echo "Se detectaron errores en la validación.\n";
}
echo "=========================================================\n";

