-- ==========================================================
-- PARCHE DE PRODUCCIÓN Y BLINDAJE DE SEGURIDAD
-- 1. Tabla persistente de Rate Limiting (login_intentos)
-- 2. Índices de optimización de rendimiento para Asistencia
-- ==========================================================

USE `control_personal`;

-- 1. Crear tabla login_intentos si no existe
CREATE TABLE IF NOT EXISTS `login_intentos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ip` VARCHAR(45) NOT NULL,
    `usuario` VARCHAR(100) NULL,
    `intentos` INT DEFAULT 1,
    `ultimo_intento` DATETIME NOT NULL,
    `bloqueado_hasta` DATETIME NULL,
    UNIQUE KEY `uniq_login_ip` (`ip`),
    INDEX `idx_login_usuario` (`usuario`),
    INDEX `idx_login_bloqueo` (`bloqueado_hasta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Optimización de índices para acelerar AttendanceCalculator y reportes
ALTER TABLE `asistencia_diaria` 
ADD INDEX IF NOT EXISTS `idx_asist_emp_fecha` (`id_empleado`, `fecha`);

ALTER TABLE `justificaciones` 
ADD INDEX IF NOT EXISTS `idx_justif_emp_rango` (`id_empleado`, `fecha_inicio`, `fecha_fin`, `estado`);

ALTER TABLE `marcaciones` 
ADD INDEX IF NOT EXISTS `idx_marc_emp_fecha` (`id_empleado`, `fecha_hora`);

SELECT 'Parche de producción aplicado con éxito.' AS estado;
