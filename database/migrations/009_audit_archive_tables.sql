-- ==========================================================
-- MIGRACIÓN 009: TABLAS DE ARCHIVO HISTÓRICO Y REGISTRO DE MANTENIMIENTO
-- Optimización y depuración semestral de tablas de auditoría y colas
-- ==========================================================

USE `control_personal`;

-- 1. Tabla de Archivo Histórico para eventos_asistencia
CREATE TABLE IF NOT EXISTS `eventos_asistencia_historico` (
    `id` BIGINT NOT NULL,
    `aggregate_type` ENUM('MARCACION','ASISTENCIA_DIARIA') NOT NULL,
    `aggregate_id` VARCHAR(64) NOT NULL,
    `event_type` VARCHAR(80) NOT NULL,
    `event_data` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    `version` INT NOT NULL DEFAULT 1,
    `created_by` VARCHAR(100) NOT NULL DEFAULT 'SYSTEM',
    `ip_address` VARCHAR(45) NULL,
    `created_at` TIMESTAMP NOT NULL,
    `archived_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_hist_aggregate` (`aggregate_type`, `aggregate_id`),
    INDEX `idx_hist_created_at` (`created_at`),
    INDEX `idx_hist_archived_at` (`archived_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabla de Archivo Histórico para cola_eventos_asistencia
CREATE TABLE IF NOT EXISTS `cola_eventos_asistencia_historico` (
    `id` BIGINT NOT NULL,
    `id_dispositivo` INT NOT NULL,
    `device_serial` VARCHAR(100) NULL,
    `user_id` VARCHAR(32) NOT NULL,
    `timestamp` DATETIME NOT NULL,
    `punch_type` VARCHAR(30) NOT NULL DEFAULT 'entrada',
    `verify_type` VARCHAR(30) NOT NULL DEFAULT 'huella',
    `uid_dispositivo` BIGINT NULL,
    `origen` ENUM('PUSH', 'PULL', 'LIVE_CAPTURE', 'MANUAL') NOT NULL DEFAULT 'PULL',
    `idempotency_key` VARCHAR(64) NOT NULL,
    `estado` ENUM('PENDING', 'PROCESSING', 'PROCESSED', 'FAILED', 'RETRY') NOT NULL DEFAULT 'PENDING',
    `intentos` INT NOT NULL DEFAULT 0,
    `ultimo_error` TEXT NULL,
    `creado_en` TIMESTAMP NOT NULL,
    `actualizado_en` TIMESTAMP NOT NULL,
    `archived_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_cola_hist_idempotency` (`idempotency_key`),
    INDEX `idx_cola_hist_timestamp` (`timestamp`),
    INDEX `idx_cola_hist_archived_at` (`archived_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Registro de auditoría para operaciones de mantenimiento y depuración
CREATE TABLE IF NOT EXISTS `mantenimiento_auditoria_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `fecha_ejecucion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `usuario` VARCHAR(100) NOT NULL DEFAULT 'SYSTEM',
    `meses_retencion` INT NOT NULL,
    `modo` ENUM('ARCHIVAR_Y_DEPURAR', 'DEPURACION_DIRECTA') NOT NULL DEFAULT 'ARCHIVAR_Y_DEPURAR',
    `eventos_archivados` INT NOT NULL DEFAULT 0,
    `eventos_eliminados` INT NOT NULL DEFAULT 0,
    `cola_archivada` INT NOT NULL DEFAULT 0,
    `cola_eliminada` INT NOT NULL DEFAULT 0,
    `duracion_ms` INT NOT NULL DEFAULT 0,
    `ip_origen` VARCHAR(45) NULL,
    `detalles` TEXT NULL,
    INDEX `idx_maint_fecha` (`fecha_ejecucion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
