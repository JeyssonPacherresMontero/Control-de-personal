-- ==========================================================
-- MIGRACIÓN 006: Asignación Departamental para Rol SUPERVISOR
-- ==========================================================

USE `control_personal`;

-- 1. Agregar columna departamento_id a usuarios_sistema si no existe
SET @exist := (
    SELECT COUNT(*) 
    FROM information_schema.columns 
    WHERE table_schema = DATABASE() 
      AND table_name = 'usuarios_sistema' 
      AND column_name = 'departamento_id'
);
SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE `usuarios_sistema` ADD COLUMN `departamento_id` INT NULL AFTER `rol`, ADD CONSTRAINT `fk_usuarios_departamento` FOREIGN KEY (`departamento_id`) REFERENCES `departamentos` (`id`) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Índice para consultas rápidas por departamento en usuarios
SET @exist_idx := (
    SELECT COUNT(*) 
    FROM information_schema.statistics 
    WHERE table_schema = DATABASE() 
      AND table_name = 'usuarios_sistema' 
      AND index_name = 'idx_usuarios_departamento'
);
SET @sqlstmt_idx := IF(@exist_idx = 0, 'ALTER TABLE `usuarios_sistema` ADD INDEX `idx_usuarios_departamento` (`departamento_id`)', 'SELECT 1');
PREPARE stmt_idx FROM @sqlstmt_idx;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;
