-- ==========================================================
-- SISTEMA DE CONTROL DE PERSONAL Y ASISTENCIA - ZKTECO
-- Datos Iniciales de Prueba y Configuración (Seeds)
-- ==========================================================

USE `control_personal`;

-- 1. Insertar Configuraciones Generales
INSERT INTO `configuracion` (`clave`, `valor`, `descripcion`, `tipo`) VALUES
('empresa_nombre', 'Corporación Empresarial S.A.C.', 'Nombre oficial de la empresa', 'string'),
('empresa_ruc', '20123456789', 'RUC o Identificación Tributaria', 'string'),
('sync_intervalo_minutos', '10', 'Intervalo sugerido de sincronización con ZKTeco', 'number'),
('debounce_minutos_marcacion', '3', 'Minutos mínimos entre marcaciones para ignorar doble toque accidental', 'number'),
('auto_procesar_asistencia', '1', '1 para recalcular asistencia diaria automáticamente al sincronizar', 'boolean'),
('dias_tolerancia_retroactiva', '7', 'Días permitidos hacia atrás para procesar marcaciones pasadas', 'number')
ON DUPLICATE KEY UPDATE `valor` = VALUES(`valor`);

-- 2. Insertar Departamentos
INSERT INTO `departamentos` (`id`, `nombre`, `codigo`, `descripcion`) VALUES
(1, 'Administración y Finanzas', 'ADM', 'Área contable, financiera y administrativa'),
(2, 'Tecnología de la Información', 'TI', 'Sistemas, infraestructura y desarrollo'),
(3, 'Operaciones y Logística', 'OPE', 'Almacén, distribución y operaciones'),
(4, 'Recursos Humanos', 'RRHH', 'Gestión de talento y personal'),
(5, 'Ventas y Comercial', 'COM', 'Fuerza de ventas y atención al cliente')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- 3. Insertar Cargos
INSERT INTO `cargos` (`id`, `departamento_id`, `nombre`, `descripcion`) VALUES
(1, 1, 'Gerente Administrativo', 'Responsable de finanzas y administración'),
(2, 2, 'Especialista de Sistemas', 'Soporte y administración de servidores'),
(3, 3, 'Supervisor de Almacén', 'Control de stock y despachos'),
(4, 4, 'Analista de RRHH', 'Control de personal y nómina'),
(5, 5, 'Ejecutivo de Cuentas', 'Venta corporativa y fidelización')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- 4. Insertar Turnos Típicos de Producción
INSERT INTO `turnos` (`id`, `nombre`, `hora_entrada`, `hora_salida`, `tolerancia_minutos`, `tolerancia_falta_minutos`, `hora_inicio_refrigerio`, `hora_fin_refrigerio`, `minutos_refrigerio`, `dias_laborables`, `es_nocturno`) VALUES
(1, 'Turno Administrativo (08:00 - 17:00)', '08:00:00', '17:00:00', 10, 60, '13:00:00', '14:00:00', 60, '1,2,3,4,5', 0),
(2, 'Turno Mañana Operativo (07:00 - 15:30)', '07:00:00', '15:30:00', 5, 45, '12:00:00', '12:30:00', 30, '1,2,3,4,5,6', 0),
(3, 'Turno Tarde Operativo (15:00 - 23:00)', '15:00:00', '23:00:00', 5, 45, '18:00:00', '18:30:00', 30, '1,2,3,4,5,6', 0),
(4, 'Turno Noche Rotativo (23:00 - 07:00)', '23:00:00', '07:00:00', 10, 60, '03:00:00', '03:30:00', 30, '1,2,3,4,5,6', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- 5. Insertar Empleados de Ejemplo (Vinculados con IDs de reloj ZKTeco 1, 2, 3, etc.)
INSERT INTO `empleados` (`id`, `codigo_reloj`, `dni`, `nombres`, `apellidos`, `email`, `telefono`, `departamento_id`, `cargo_id`, `turno_id`, `fecha_ingreso`) VALUES
(1, '1', '70112233', 'Carlos Alberto', 'Mendoza Quispe', 'carlos.mendoza@empresa.com', '987654321', 2, 2, 1, '2023-01-15'),
(2, '2', '70223344', 'Ana Lucia', 'Flores Torres', 'ana.flores@empresa.com', '987654322', 4, 4, 1, '2023-02-01'),
(3, '3', '70334455', 'Jorge Luis', 'Ramirez Soto', 'jorge.ramirez@empresa.com', '987654323', 3, 3, 2, '2023-03-10'),
(4, '4', '70445566', 'Maria Elena', 'Gomez Benitez', 'maria.gomez@empresa.com', '987654324', 1, 1, 1, '2023-04-05'),
(5, '5', '70556677', 'Pedro Pablo', 'Castillo Rivas', 'pedro.castillo@empresa.com', '987654325', 5, 5, 1, '2023-05-12')
ON DUPLICATE KEY UPDATE `codigo_reloj` = VALUES(`codigo_reloj`);

-- 6. Insertar Dispositivo ZKTeco por Defecto
INSERT INTO `dispositivos` (`id`, `nombre`, `ip`, `puerto`, `protocolo`, `clave_comunicacion`, `ubicacion`, `modelo`, `activo`, `estado_conexion`) VALUES
(1, 'Reloj Principal - Entrada', '192.168.1.201', 4370, 'TCP', 415703, 'Puerta Principal - Recepción', 'ZKTeco MB460', 1, 'OFFLINE'),
(2, 'Reloj Secundario - Almacén', '192.168.1.202', 4370, 'TCP', 0, 'Puerta Posterior Almacén', 'ZKTeco SilkBio-101TC', 0, 'OFFLINE')
ON DUPLICATE KEY UPDATE `ip` = VALUES(`ip`), `clave_comunicacion` = VALUES(`clave_comunicacion`), `modelo` = VALUES(`modelo`);

-- 7. Insertar Usuarios del Sistema por Defecto (Password: 'admin123' con BCRYPT)
INSERT INTO `usuarios_sistema` (`id`, `usuario`, `password`, `nombre_completo`, `email`, `rol`, `permisos`, `activo`) VALUES
(1, 'admin', '$2y$10$nfEJAy9pnSjPCfPWhoP1JeIEpIDtfU8TFawCuu/kp08eb0SlGkNoO', 'Administrador del Sistema', 'admin@empresa.com', 'ADMIN', '["*"]', 1),
(2, 'rrhh', '$2y$10$nfEJAy9pnSjPCfPWhoP1JeIEpIDtfU8TFawCuu/kp08eb0SlGkNoO', 'Gestor de Recursos Humanos', 'rrhh@empresa.com', 'RRHH', '["dashboard","asistencia","marcaciones","empleados","turnos","justificaciones"]', 1),
(3, 'supervisor', '$2y$10$nfEJAy9pnSjPCfPWhoP1JeIEpIDtfU8TFawCuu/kp08eb0SlGkNoO', 'Supervisor de Operaciones', 'supervisor@empresa.com', 'SUPERVISOR', '["dashboard","asistencia","marcaciones","empleados","justificaciones"]', 1),
(4, 'consulta', '$2y$10$nfEJAy9pnSjPCfPWhoP1JeIEpIDtfU8TFawCuu/kp08eb0SlGkNoO', 'Auditor de Asistencias', 'auditoria@empresa.com', 'CONSULTA', '["dashboard","asistencia","marcaciones"]', 1)
ON DUPLICATE KEY UPDATE `password` = VALUES(`password`), `rol` = VALUES(`rol`), `permisos` = VALUES(`permisos`), `activo` = VALUES(`activo`);

-- Nota de Seguridad:
-- Para generar una nueva contraseña en PHP: password_hash('tu_clave', PASSWORD_BCRYPT)

