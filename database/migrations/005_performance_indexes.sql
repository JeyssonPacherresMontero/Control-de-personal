-- ==========================================================
-- MIGRACIÓN 005: Índices Compuestos de Rendimiento para Producción
-- ==========================================================

USE `control_personal`;

-- 1. Índice compuesto para consultas de marcaciones por empleado y rango de fechas
SET @exist := (
    SELECT COUNT(*) 
    FROM information_schema.statistics 
    WHERE table_schema = DATABASE() 
      AND table_name = 'marcaciones' 
      AND index_name = 'idx_marcaciones_emp_fecha'
);
SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE `marcaciones` ADD INDEX `idx_marcaciones_emp_fecha` (`id_empleado`, `fecha_hora`)', 'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Índice compuesto para login y rate limiting
SET @exist := (
    SELECT COUNT(*) 
    FROM information_schema.statistics 
    WHERE table_schema = DATABASE() 
      AND table_name = 'login_intentos' 
      AND index_name = 'idx_login_ip_usuario'
);
SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE `login_intentos` ADD INDEX `idx_login_ip_usuario` (`ip`, `usuario`)', 'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
