-- ==========================================================
-- PARCHE DE INTEGRIDAD DE BASE DE DATOS
-- Corrección de FK en marcaciones -> dispositivos
-- Evita el borrado accidental en cascada de marcaciones históricas
-- ==========================================================

USE `control_personal`;

-- 1. Eliminar la clave foránea anterior con CASCADE
ALTER TABLE `marcaciones` 
DROP FOREIGN KEY `fk_marcaciones_dispositivo`;

-- 2. Volver a crear la clave foránea con regla ON DELETE RESTRICT
ALTER TABLE `marcaciones` 
ADD CONSTRAINT `fk_marcaciones_dispositivo` 
FOREIGN KEY (`id_dispositivo`) REFERENCES `dispositivos` (`id`) 
ON DELETE RESTRICT;

-- Verificación de estado
SELECT 'Parche aplicado correctamente: fk_marcaciones_dispositivo ahora es ON DELETE RESTRICT' AS resultado;
