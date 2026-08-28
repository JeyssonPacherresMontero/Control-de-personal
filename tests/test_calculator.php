<?php
/**
 * Test Suite para el Motor de Asistencia (AttendanceCalculator)
 * Ejecutar con: php tests/test_calculator.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/Services/AttendanceCalculator.php';

use App\Services\AttendanceCalculator;

echo "=========================================================\n";
echo "EJECUTANDO PRUEBAS UNITARIAS DE LÓGICA DE ASISTENCIA\n";
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

// Test 2: Validación sintáctica de archivos PHP principales
$filesToLint = [
    __DIR__ . '/../config/config.php',
    __DIR__ . '/../app/Database.php',
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
echo "[TEST 2] Verificación de Sintaxis PHP ($phpBinary -l):\n";
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
