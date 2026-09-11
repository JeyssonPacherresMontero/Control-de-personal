-- ==========================================================
-- MIGRACIÓN 004: COLUMNAS DE SELECCIÓN DE DEDO EN EMPLEADOS
-- ==========================================================

ALTER TABLE `empleados`
    ADD COLUMN `dedo_reloj` INT DEFAULT 2 COMMENT '1: Pulgar D., 2: Índice D., 3: Medio D., 4: Anular D., 5: Meñique D., 6: Pulgar I., 7: Índice I., 8: Medio I., 9: Anular I., 10: Meñique I.',
    ADD COLUMN `dedo_nombre` VARCHAR(60) DEFAULT 'Índice Mano Derecha' COMMENT 'Nombre legible del dedo seleccionado';
