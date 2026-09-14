-- ==========================================================
-- MIGRACION 005: SOPORTE DE ASIGNACION MULTIPLE DE DEDOS
-- ==========================================================

ALTER TABLE `empleados`
    ADD COLUMN dedos_reloj VARCHAR(255) DEFAULT '2' COMMENT 'Lista de IDs de dedos seleccionados (1 a 10) separados por comas',
    ADD COLUMN dedos_nombre TEXT NULL COMMENT 'Nombres de los dedos seleccionados separados por comas';
