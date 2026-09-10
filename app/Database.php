<?php
namespace App;

use PDO;
use PDOException;
use Throwable;

require_once __DIR__ . '/../config/config.php';

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            try {
                $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4, time_zone = '-05:00', sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'"
                ];
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                error_log("[CRITICAL] Database connection error: " . $e->getMessage());
                if (defined('APP_DEBUG') && APP_DEBUG === true) {
                    die("Error crítico de conexión a la base de datos: " . $e->getMessage());
                }
                die("Error de comunicación con el servicio de base de datos. Por favor contacte al administrador del sistema.");
            }
        }
        return self::$instance;
    }

    public static function query(string $sql, array $params = []): array {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function queryOne(string $sql, array $params = []): ?array {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public static function execute(string $sql, array $params = []): int {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function executeSafe(string $sql, array $params = []): array {
        try {
            $rows = self::execute($sql, $params);
            return ['success' => true, 'rows' => $rows];
        } catch (PDOException $e) {
            if ((int)$e->getCode() === 23000 || str_contains($e->getMessage(), 'Duplicate entry')) {
                return ['success' => false, 'error' => 'duplicado'];
            }
            error_log('[DB ERROR] ' . $e->getMessage() . ' | SQL: ' . $sql);
            return ['success' => false, 'error' => 'error_interno'];
        }
    }

    public static function lastInsertId(): string {
        return self::getConnection()->lastInsertId();
    }

    /**
     * Inicia una transacción de base de datos
     */
    public static function beginTransaction(): bool {
        if (!self::getConnection()->inTransaction()) {
            return self::getConnection()->beginTransaction();
        }
        return false;
    }

    /**
     * Confirma la transacción activa
     */
    public static function commit(): bool {
        if (self::getConnection()->inTransaction()) {
            return self::getConnection()->commit();
        }
        return false;
    }

    /**
     * Revierte la transacción activa
     */
    public static function rollBack(): bool {
        if (self::getConnection()->inTransaction()) {
            return self::getConnection()->rollBack();
        }
        return false;
    }

    /**
     * Verifica si existe una transacción activa
     */
    public static function inTransaction(): bool {
        return self::getConnection()->inTransaction();
    }

    /**
     * Ejecuta una función dentro de una transacción atómica segura.
     * Si ocurre cualquier excepción, realiza rollback automático y relanza la excepción.
     * 
     * @param callable $callback Función a ejecutar
     * @return mixed Retorno de la función ejecutada
     * @throws Throwable
     */
    public static function transaction(callable $callback) {
        $alreadyInTransaction = self::inTransaction();
        if (!$alreadyInTransaction) {
            self::beginTransaction();
        }

        try {
            $result = $callback();
            if (!$alreadyInTransaction) {
                self::commit();
            }
            return $result;
        } catch (Throwable $e) {
            if (!$alreadyInTransaction && self::inTransaction()) {
                self::rollBack();
            }
            throw $e;
        }
    }
}
