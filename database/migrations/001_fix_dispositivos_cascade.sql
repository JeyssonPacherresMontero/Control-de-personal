-- ==========================================================
-- MIGRACIÓN: FIX ON DELETE CASCADE PELIGROSO EN DISPOSITIVOS
-- ==========================================================

ALTER TABLE `marcaciones` DROP FOREIGN KEY `fk_marcaciones_dispositivo`;
ALTER TABLE `marcaciones`
    ADD CONSTRAINT `fk_marcaciones_dispositivo`
    FOREIGN KEY (`id_dispositivo`) REFERENCES `dispositivos` (`id`)
    ON DELETE RESTRICT;
