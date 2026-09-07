-- ==========================================================
-- SISTEMA DE CONTROL DE PERSONAL Y ASISTENCIA - ZKTECO
-- Esquema de Base de Datos para Producción
-- ==========================================================

CREATE DATABASE IF NOT EXISTS `control_personal` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `control_personal`;

-- Desactivar temporalmente revisión de claves foráneas
SET FOREIGN_KEY_CHECKS = 0;

-- 1. TABLA: CONFIGURACIÓN GENERAL DEL SISTEMA
CREATE TABLE IF NOT EXISTS `configuracion` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `clave` VARCHAR(64) NOT NULL UNIQUE,
    `valor` TEXT NULL,
    `descripcion` VARCHAR(255) NULL,
    `tipo` ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    `actualizado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. TABLA: DEPARTAMENTOS / ÁREAS
CREATE TABLE IF NOT EXISTS `departamentos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(100) NOT NULL,
    `codigo` VARCHAR(20) NULL UNIQUE,
    `descripcion` VARCHAR(255) NULL,
    `activo` TINYINT(1) DEFAULT 1,
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. TABLA: CARGOS / PUESTOS
CREATE TABLE IF NOT EXISTS `cargos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `departamento_id` INT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` VARCHAR(255) NULL,
    `activo` TINYINT(1) DEFAULT 1,
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_cargos_departamento` FOREIGN KEY (`departamento_id`) REFERENCES `departamentos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. TABLA: TURNOS Y HORARIOS LABORALES
CREATE TABLE IF NOT EXISTS `turnos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(100) NOT NULL,
    `hora_entrada` TIME NOT NULL,
    `hora_salida` TIME NOT NULL,
    `tolerancia_minutos` INT DEFAULT 10 COMMENT 'Minutos de gracia antes de considerar tardanza',
    `tolerancia_falta_minutos` INT DEFAULT 60 COMMENT 'Minutos después de los cuales se considera inasistencia/falta',
    `hora_inicio_refrigerio` TIME NULL,
    `hora_fin_refrigerio` TIME NULL,
    `minutos_refrigerio` INT DEFAULT 60,
    `dias_laborables` VARCHAR(50) DEFAULT '1,2,3,4,5' COMMENT '1=Lunes, 7=Domingo separados por comas',
    `es_nocturno` TINYINT(1) DEFAULT 0 COMMENT '1 si la jornada cruza la medianoche',
    `activo` TINYINT(1) DEFAULT 1,
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. TABLA: EMPLEADOS
CREATE TABLE IF NOT EXISTS `empleados` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `codigo_reloj` VARCHAR(32) NOT NULL UNIQUE COMMENT 'ID asignado en el dispositivo biométrico ZKTeco (User ID / Enroll Number)',
    `dni` VARCHAR(20) NOT NULL UNIQUE,
    `nombres` VARCHAR(100) NOT NULL,
    `apellidos` VARCHAR(100) NOT NULL,
    `email` VARCHAR(120) NULL,
    `telefono` VARCHAR(30) NULL,
    `departamento_id` INT NULL,
    `cargo_id` INT NULL,
    `turno_id` INT NULL,
    `fecha_ingreso` DATE NULL,
    `foto` VARCHAR(255) NULL,
    `activo` TINYINT(1) DEFAULT 1,
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_empleados_departamento` FOREIGN KEY (`departamento_id`) REFERENCES `departamentos` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_empleados_cargo` FOREIGN KEY (`cargo_id`) REFERENCES `cargos` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_empleados_turno` FOREIGN KEY (`turno_id`) REFERENCES `turnos` (`id`) ON DELETE SET NULL,
    INDEX `idx_empleados_codigo_reloj` (`codigo_reloj`),
    INDEX `idx_empleados_dni` (`dni`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. TABLA: DISPOSITIVOS ZKTECO
CREATE TABLE IF NOT EXISTS `dispositivos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(100) NOT NULL,
    `ip` VARCHAR(45) NOT NULL COMMENT 'IPv4 o IPv6 fija del biométrico',
    `puerto` INT DEFAULT 4370 COMMENT 'Puerto estándar ZKTeco (4370)',
    `protocolo` ENUM('TCP', 'UDP') DEFAULT 'TCP',
    `clave_comunicacion` INT DEFAULT 0 COMMENT 'ComKey configurada en el reloj (por defecto 0)',
    `ubicacion` VARCHAR(150) NULL COMMENT 'Ej: Puerta Principal, Almacén, Sede Norte',
    `modelo` VARCHAR(50) NULL,
    `numero_serie` VARCHAR(100) NULL,
    `version_firmware` VARCHAR(100) NULL,
    `activo` TINYINT(1) DEFAULT 1,
    `estado_conexion` ENUM('ONLINE', 'OFFLINE', 'ERROR') DEFAULT 'OFFLINE',
    `ultimo_sync` DATETIME NULL,
    `ultimo_error` TEXT NULL,
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_dispositivo_ip_puerto` (`ip`, `puerto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. TABLA: MARCACIONES CRUDAS (RAW PUNCH LOGS)
-- Diseñada para cero duplicados y resistencia a re-sincronizaciones
CREATE TABLE IF NOT EXISTS `marcaciones` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `id_empleado` INT NULL COMMENT 'FK a empleados (puede ser null si el usuario aun no fue creado en el sistema)',
    `codigo_reloj` VARCHAR(32) NOT NULL COMMENT 'ID original proveniente del reloj',
    `id_dispositivo` INT NOT NULL COMMENT 'FK a dispositivos',
    `fecha_hora` DATETIME NOT NULL,
    `tipo` ENUM('entrada', 'salida', 'refrigerio_salida', 'refrigerio_entrada', 'desconocido') DEFAULT 'desconocido',
    `tipo_verificacion` VARCHAR(30) DEFAULT 'huella' COMMENT 'huella, facial, tarjeta, clave',
    `uid_dispositivo` BIGINT NULL COMMENT 'Número de registro interno del reloj ZKTeco',
    `procesado` TINYINT(1) DEFAULT 0 COMMENT '0: Pendiente de cálculo, 1: Procesado en asistencia_diaria',
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_marcaciones_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleados` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_marcaciones_dispositivo` FOREIGN KEY (`id_dispositivo`) REFERENCES `dispositivos` (`id`) ON DELETE CASCADE,
    -- Clave Única para evitar duplicados en re-intentos de sincronización
    UNIQUE KEY `uniq_marcacion` (`codigo_reloj`, `fecha_hora`, `id_dispositivo`),
    INDEX `idx_marcaciones_fecha_hora` (`fecha_hora`),
    INDEX `idx_marcaciones_procesado` (`procesado`),
    INDEX `idx_marcaciones_codigo_reloj` (`codigo_reloj`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. TABLA: ASISTENCIA DIARIA CONSOLIDADA (CALCULADA)
CREATE TABLE IF NOT EXISTS `asistencia_diaria` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `id_empleado` INT NOT NULL,
    `id_turno` INT NULL,
    `fecha` DATE NOT NULL,
    `hora_entrada_programada` TIME NULL,
    `hora_salida_programada` TIME NULL,
    `hora_entrada_real` DATETIME NULL,
    `hora_salida_real` DATETIME NULL,
    `hora_inicio_refrigerio_real` DATETIME NULL,
    `hora_fin_refrigerio_real` DATETIME NULL,
    `minutos_tardanza` INT DEFAULT 0 COMMENT 'Minutos de tardanza calculados tras superar la tolerancia',
    `minutos_trabajados` INT DEFAULT 0 COMMENT 'Tiempo efectivo laborado en minutos',
    `minutos_extra` INT DEFAULT 0 COMMENT 'Minutos laborados por encima de la jornada oficial',
    `minutos_salida_temprana` INT DEFAULT 0 COMMENT 'Minutos que se retiró antes del horario de salida',
    `estado` ENUM('PRESENTE', 'TARDANZA', 'FALTA', 'FALTA_INJUSTIFICADA', 'JUSTIFICADO', 'PERMISO', 'VACACIONES', 'DESCANSO', 'SALIDA_SIN_MARCAR') DEFAULT 'PRESENTE',
    `observaciones` TEXT NULL,
    `manual` TINYINT(1) DEFAULT 0 COMMENT '1 si fue modificado o creado manualmente por RRHH',
    `procesado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_asistencia_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleados` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_asistencia_turno` FOREIGN KEY (`id_turno`) REFERENCES `turnos` (`id`) ON DELETE SET NULL,
    UNIQUE KEY `uniq_empleado_fecha` (`id_empleado`, `fecha`),
    INDEX `idx_asistencia_fecha` (`fecha`),
    INDEX `idx_asistencia_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. TABLA: JUSTIFICACIONES, PERMISOS Y LICENCIAS
CREATE TABLE IF NOT EXISTS `justificaciones` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_empleado` INT NOT NULL,
    `tipo` ENUM('TARDANZA', 'FALTA', 'PERMISO_MEDICO', 'COMISION_SERVICIO', 'VACACIONES', 'LICENCIA_MATERNIDAD_PATERNIDAD', 'OTRO') NOT NULL,
    `fecha_inicio` DATE NOT NULL,
    `fecha_fin` DATE NOT NULL,
    `hora_inicio` TIME NULL,
    `hora_fin` TIME NULL,
    `motivo` TEXT NOT NULL,
    `archivo_adjunto` VARCHAR(255) NULL,
    `estado` ENUM('PENDIENTE', 'APROBADO', 'RECHAZADO') DEFAULT 'PENDIENTE',
    `aprobado_por` VARCHAR(100) NULL,
    `fecha_resolucion` DATETIME NULL,
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_justificaciones_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleados` (`id`) ON DELETE CASCADE,
    INDEX `idx_justificaciones_fechas` (`fecha_inicio`, `fecha_fin`),
    INDEX `idx_justificaciones_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. TABLA: DÍAS FERIADOS / NO LABORABLES
CREATE TABLE IF NOT EXISTS `feriados` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `fecha` DATE NOT NULL UNIQUE,
    `descripcion` VARCHAR(150) NOT NULL,
    `aplica_todos` TINYINT(1) DEFAULT 1,
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. TABLA: LOG DE SINCRONIZACIÓN Y AUDITORÍA
CREATE TABLE IF NOT EXISTS `log_sincronizacion` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `id_dispositivo` INT NULL,
    `fecha_hora` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `tipo_evento` ENUM('SYNC_AUTO', 'SYNC_MANUAL', 'TEST_CONEXION', 'CLEAR_ATTENDANCE', 'SYNC_USERS', 'ERROR') NOT NULL,
    `total_descargados` INT DEFAULT 0,
    `total_insertados` INT DEFAULT 0,
    `total_duplicados` INT DEFAULT 0,
    `estado` ENUM('EXITO', 'ERROR', 'ADVERTENCIA') NOT NULL,
    `mensaje` TEXT NULL,
    `duracion_segundos` DECIMAL(6, 2) DEFAULT 0.00,
    CONSTRAINT `fk_logs_dispositivo` FOREIGN KEY (`id_dispositivo`) REFERENCES `dispositivos` (`id`) ON DELETE SET NULL,
    INDEX `idx_logs_fecha` (`fecha_hora`),
    INDEX `idx_logs_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. TABLA: USUARIOS DEL SISTEMA WEB (ADMINISTRADORES / RRHH)
CREATE TABLE IF NOT EXISTS `usuarios_sistema` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `usuario` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `nombre_completo` VARCHAR(120) NOT NULL,
    `email` VARCHAR(100) NULL,
    `rol` ENUM('ADMIN', 'RRHH', 'SUPERVISOR', 'CONSULTA') DEFAULT 'RRHH',
    `permisos` TEXT NULL COMMENT 'JSON array de módulos permitidos en el menú',
    `activo` TINYINT(1) DEFAULT 1,
    `ultimo_login` DATETIME NULL,
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. TABLA: EVENT STORE (EVENT SOURCING PARA MARCACIONES Y ASISTENCIA)
-- Registro inmutable y de solo anexado (Append-Only) para trazabilidad total
CREATE TABLE IF NOT EXISTS `eventos_asistencia` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `aggregate_type` ENUM('MARCACION', 'ASISTENCIA_DIARIA') NOT NULL COMMENT 'Tipo de agregado al que pertenece el evento',
    `aggregate_id` VARCHAR(64) NOT NULL COMMENT 'Identificador único del stream (ej: emp_5_2026-09-02 o punch_104)',
    `event_type` VARCHAR(80) NOT NULL COMMENT 'Tipo de evento: MARCACION_CAPTURADA_DISPOSITIVO, ASISTENCIA_CALCULADA, ASISTENCIA_MODIFICADA_MANUAL, etc.',
    `event_data` JSON NOT NULL COMMENT 'Carga útil (Payload estructurado con snapshot, diffs y metadatos)',
    `version` INT NOT NULL DEFAULT 1 COMMENT 'Versión secuencial del stream para concurrencia optimista',
    `created_by` VARCHAR(100) NOT NULL DEFAULT 'SYSTEM' COMMENT 'Usuario que originó el evento o subsistema',
    `ip_address` VARCHAR(45) NULL COMMENT 'IP de origen del cliente o dispositivo biométrico',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_events_aggregate` (`aggregate_type`, `aggregate_id`),
    INDEX `idx_events_type` (`event_type`),
    INDEX `idx_events_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. TABLA: PLANTILLAS BIOMÉTRICAS (HUELLA DACTILAR Y RECONOCIMIENTO FACIAL)
CREATE TABLE IF NOT EXISTS `plantillas_biometricas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `codigo_reloj` VARCHAR(32) NOT NULL,
    `tipo` ENUM('HUELLA', 'FACIAL', 'TARJETA', 'PASSWORD') NOT NULL DEFAULT 'HUELLA',
    `dedo_indice` INT DEFAULT 0 COMMENT '0 a 9 para huellas, 0 para facial',
    `tamano` INT DEFAULT 0,
    `template_data` LONGTEXT NOT NULL COMMENT 'Base64 o payload de plantilla',
    `id_dispositivo_origen` INT NULL,
    `actualizado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_plantillas_dispositivo` FOREIGN KEY (`id_dispositivo_origen`) REFERENCES `dispositivos` (`id`) ON DELETE SET NULL,
    UNIQUE KEY `uniq_biometria_usuario` (`codigo_reloj`, `tipo`, `dedo_indice`),
    INDEX `idx_biometria_codigo` (`codigo_reloj`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
