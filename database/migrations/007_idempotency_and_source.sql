-- ==========================================================
-- MIGRACIÓN 007: SOPORTE DE IDEMPOTENCIA, MODO DE SINCRONIZACIÓN Y ORIGEN
-- Permite arquitectura híbrida PUSH/PULL sin duplicación de registros
-- ==========================================================

USE `control_personal`;

-- 1. Columna modo en dispositivos (PULL, PUSH, HYBRID)
SET @exist_modo := (
    SELECT COUNT(*) 
    FROM information_schema.columns 
    WHERE table_schema = DATABASE() 
      AND table_name = 'dispositivos' 
      AND column_name = 'modo'
);
SET @sql_modo := IF(@exist_modo = 0, 
    "ALTER TABLE `dispositivos` ADD COLUMN `modo` ENUM('PULL', 'PUSH', 'HYBRID') NOT NULL DEFAULT 'PULL' AFTER `protocolo`", 
    "SELECT 1"
);
PREPARE stmt_modo FROM @sql_modo;
EXECUTE stmt_modo;
DEALLOCATE PREPARE stmt_modo;

-- 2. Columna api_token en dispositivos (para autenticación de Push Listener)
SET @exist_token := (
    SELECT COUNT(*) 
    FROM information_schema.columns 
    WHERE table_schema = DATABASE() 
      AND table_name = 'dispositivos' 
      AND column_name = 'api_token'
);
SET @sql_token := IF(@exist_token = 0, 
    "ALTER TABLE `dispositivos` ADD COLUMN `api_token` VARCHAR(64) NULL AFTER `clave_comunicacion`", 
    "SELECT 1"
);
PREPARE stmt_token FROM @sql_token;
EXECUTE stmt_token;
DEALLOCATE PREPARE stmt_token;

-- 3. Columna idempotency_key en marcaciones
SET @exist_idem := (
    SELECT COUNT(*) 
    FROM information_schema.columns 
    WHERE table_schema = DATABASE() 
      AND table_name = 'marcaciones' 
      AND column_name = 'idempotency_key'
);
SET @sql_idem := IF(@exist_idem = 0, 
    "ALTER TABLE `marcaciones` ADD COLUMN `idempotency_key` VARCHAR(64) NULL AFTER `uid_dispositivo`", 
    "SELECT 1"
);
PREPARE stmt_idem FROM @sql_idem;
EXECUTE stmt_idem;
DEALLOCATE PREPARE stmt_idem;

-- 4. Índice único para idempotency_key
SET @exist_idx_idem := (
    SELECT COUNT(*) 
    FROM information_schema.statistics 
    WHERE table_schema = DATABASE() 
      AND table_name = 'marcaciones' 
      AND index_name = 'uniq_idempotency'
);
SET @sql_idx_idem := IF(@exist_idx_idem = 0, 
    "ALTER TABLE `marcaciones` ADD UNIQUE INDEX `uniq_idempotency` (`idempotency_key`)", 
    "SELECT 1"
);
PREPARE stmt_idx_idem FROM @sql_idx_idem;
EXECUTE stmt_idx_idem;
DEALLOCATE PREPARE stmt_idx_idem;

-- 5. Columna origen en marcaciones (PULL, PUSH, LIVE_CAPTURE, MANUAL)
SET @exist_origen := (
    SELECT COUNT(*) 
    FROM information_schema.columns 
    WHERE table_schema = DATABASE() 
      AND table_name = 'marcaciones' 
      AND column_name = 'origen'
);
SET @sql_origen := IF(@exist_origen = 0, 
    "ALTER TABLE `marcaciones` ADD COLUMN `origen` ENUM('PULL', 'PUSH', 'LIVE_CAPTURE', 'MANUAL') NOT NULL DEFAULT 'PULL' AFTER `idempotency_key`", 
    "SELECT 1"
);
PREPARE stmt_origen FROM @sql_origen;
EXECUTE stmt_origen;
DEALLOCATE PREPARE stmt_origen;
