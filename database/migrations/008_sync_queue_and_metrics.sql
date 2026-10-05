-- ==========================================================
-- MIGRACIÓN 008: COLA LÓGICA DE EVENTOS Y MÉTRICAS DE SINCRONIZACIÓN
-- Soporta búfer desacoplado con estados PENDING, PROCESSING, PROCESSED, FAILED, RETRY
-- ==========================================================

USE `control_personal`;

-- 1. Tabla de Cola Lógica de Procesamiento Resiliente
CREATE TABLE IF NOT EXISTS `cola_eventos_asistencia` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
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
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_cola_dispositivo` FOREIGN KEY (`id_dispositivo`) REFERENCES `dispositivos` (`id`) ON DELETE CASCADE,
    UNIQUE KEY `uniq_cola_idempotency` (`idempotency_key`),
    INDEX `idx_cola_estado_intentos` (`estado`, `intentos`),
    INDEX `idx_cola_dispositivo` (`id_dispositivo`, `creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Columna modo en log_sincronizacion
SET @exist_log_modo := (
    SELECT COUNT(*) 
    FROM information_schema.columns 
    WHERE table_schema = DATABASE() 
      AND table_name = 'log_sincronizacion' 
      AND column_name = 'modo'
);
SET @sql_log_modo := IF(@exist_log_modo = 0, 
    "ALTER TABLE `log_sincronizacion` ADD COLUMN `modo` ENUM('PULL', 'PUSH', 'HYBRID') NOT NULL DEFAULT 'PULL' AFTER `tipo_evento`", 
    "SELECT 1"
);
PREPARE stmt_log_modo FROM @sql_log_modo;
EXECUTE stmt_log_modo;
DEALLOCATE PREPARE stmt_log_modo;

-- 3. Columna total_fallidos en log_sincronizacion
SET @exist_log_fallidos := (
    SELECT COUNT(*) 
    FROM information_schema.columns 
    WHERE table_schema = DATABASE() 
      AND table_name = 'log_sincronizacion' 
      AND column_name = 'total_fallidos'
);
SET @sql_log_fallidos := IF(@exist_log_fallidos = 0, 
    "ALTER TABLE `log_sincronizacion` ADD COLUMN `total_fallidos` INT DEFAULT 0 AFTER `total_duplicados`", 
    "SELECT 1"
);
PREPARE stmt_log_fallidos FROM @sql_log_fallidos;
EXECUTE stmt_log_fallidos;
DEALLOCATE PREPARE stmt_log_fallidos;

-- 4. Columna latencia_ms en log_sincronizacion
SET @exist_log_latencia := (
    SELECT COUNT(*) 
    FROM information_schema.columns 
    WHERE table_schema = DATABASE() 
      AND table_name = 'log_sincronizacion' 
      AND column_name = 'latencia_ms'
);
SET @sql_log_latencia := IF(@exist_log_latencia = 0, 
    "ALTER TABLE `log_sincronizacion` ADD COLUMN `latencia_ms` INT DEFAULT 0 AFTER `duracion_segundos`", 
    "SELECT 1"
);
PREPARE stmt_log_latencia FROM @sql_log_latencia;
EXECUTE stmt_log_latencia;
DEALLOCATE PREPARE stmt_log_latencia;
