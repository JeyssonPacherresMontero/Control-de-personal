<?php
/**
 * Script de migración inicial de Base de Datos y Datos Semilla
 * Ejecutar: php database/migrate.php
 */

require_once __DIR__ . '/../config/config.php';

echo "==========================================================\n";
echo "Instalador de Base de Datos - Control de Personal ZKTeco\n";
echo "==========================================================\n";

try {
    // Conectar sin especificar base de datos para poder crearla si no existe
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);

    echo "1. Conectado al servidor MySQL (" . DB_HOST . ":" . DB_PORT . ").\n";

    // 1. Ejecutar Schema
    $schemaFile = __DIR__ . '/schema.sql';
    if (file_exists($schemaFile)) {
        echo "2. Ejecutando schema.sql...\n";
        $schemaSql = file_get_contents($schemaFile);
        $pdo->exec($schemaSql);
        echo "   [OK] Tablas creadas correctamente.\n";
    }

    // 2. Ejecutar Seeds
    $seedsFile = __DIR__ . '/seeds.sql';
    if (file_exists($seedsFile)) {
        echo "3. Ejecutando seeds.sql...\n";
        $seedsSql = file_get_contents($seedsFile);
        $pdo->exec($seedsSql);
        echo "   [OK] Datos iniciales (turnos, departamentos, admin) insertados.\n";
    }

    echo "\n==========================================================\n";
    echo "¡INSTALACIÓN COMPLETADA EXITOSAMENTE!\n";
    echo "Credenciales de acceso web por defecto:\n";
    echo "  Usuario: admin\n";
    echo "  Clave:   admin123\n";
    echo "==========================================================\n";

} catch (Exception $e) {
    echo "\n[ERROR] Falló la migración: " . $e->getMessage() . "\n";
    exit(1);
}
