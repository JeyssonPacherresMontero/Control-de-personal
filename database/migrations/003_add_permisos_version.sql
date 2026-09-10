-- ==========================================================
-- MIGRACIÓN 003: COLUMNA PERMISOS_VERSION PARA INVALIDACIÓN DE SESIONES
-- ==========================================================

ALTER TABLE `usuarios_sistema`
    ADD COLUMN `permisos_version` INT DEFAULT 1;
