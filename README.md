# JUSHSAL - Sistema de Control de Personal y Asistencia Biométrico
**Junta de Usuarios del Sector Hidráulico Menor San Lorenzo**

Sistema profesional de control de personal y asistencia laboral diseñado para **entornos reales de producción**. Integra relojes biométricos **ZKTeco** (huella digital, facial, tarjeta RFID, clave) mediante un puente en **Python (`pyzk`)** hacia **MySQL / MariaDB**, con un backend y panel de control web en **PHP 8.x**.

---

## 🌟 Características Principales

1. **Sincronización Idempotente y Cero Pérdida de Datos**:
   - Inserción por lotes con restricción `UNIQUE KEY (codigo_reloj, fecha_hora, id_dispositivo)` para prevenir registros duplicados.
   - Sincronización automática de reloj/hora del servidor con el biométrico en cada ciclo.
   - Sincronización automática de nuevos usuarios registrados directamente en el reloj.
   - Diagnóstico en vivo de conexión con reporte de firmware, número de serie y total de registros.

2. **Lógica de Negocio y Reglas de Asistencia**:
   - **Debouncing de Toques Accidentales**: Filtra marcaciones dobles consecutivas dentro de una ventana de $N$ minutos.
   - **Turnos Fijos y Nocturnos**: Soporte para turnos que cruzan la medianoche (ej: 23:00 a 07:00).
   - **Tolerancias y Tardanzas**: Cálculo exacto de minutos de tardanza tras superar el tiempo de gracia (`tolerancia_minutos`).
   - **Horas Extras y Salidas Anticipadas**: Detección de sobretiempo y alertas por retirarse antes del horario de salida.
   - **Salidas Omitidas (`SALIDA_SIN_MARCAR`)**: Detección de marcaciones impares cuando el empleado olvida marcar salida.
   - **Faltas Automáticas y Justificaciones**: Marcado de inasistencias en días laborables y módulo de permisos médicos/vacaciones con recálculo en cascada.

3. **Múltiples Biométricos**:
   - Soporte para $N$ relojes repartidos en diferentes sucursales/puertas con IP fija y puerto configurable (TCP/UDP).
   - Tabla de auditoría `log_sincronizacion` que registra latencia, registros descargados y fallas de red.

4. **Panel Web Moderno y Responsivo**:
   - Dashboard en tiempo real con KPIs y feed de marcaciones en vivo.
   - Filtros avanzados por fecha, empleado, área y estado de asistencia.
   - Exportación de reportes a **Excel / PDF**.
   - Ajustes y justificaciones manuales con trazabilidad para RRHH.

---

## 📁 Estructura del Proyecto

```text
Control de personal/
├── app/
│   ├── Console/
│   │   └── process_attendance.php       # Script CLI para procesar asistencia
│   ├── Controllers/
│   │   ├── AuthController.php          # Login, logout y sesiones
│   │   ├── DashboardController.php     # Métricas y feed en vivo
│   │   ├── AsistenciaController.php    # Consolidado diario y exportación
│   │   ├── MarcacionesController.php   # Marcaciones crudas y registro manual
│   │   ├── DispositivosController.php  # CRUD y test de comunicación ZKTeco
│   │   ├── EmpleadosController.php     # Gestión de personal y turnos
│   │   ├── TurnosController.php        # Configuración de horarios y tolerancias
│   │   └── JustificacionesController.php # Permisos, licencias y vacaciones
│   ├── Services/
│   │   └── AttendanceCalculator.php    # CORE DE LA LÓGICA DE ASISTENCIA
│   └── Database.php                    # Conexión PDO y consultas preparadas
├── config/
│   └── config.php                      # Carga de variables de entorno y constantes
├── cron/
│   ├── run_sync.bat                    # Script para Windows Task Scheduler
│   ├── task_scheduler_setup.bat        # Instalador de tarea en Windows
│   └── cron_setup.sh                   # Script de instalación para Linux crontab
├── database/
│   ├── schema.sql                      # Esquema completo de tablas e índices
│   ├── seeds.sql                       # Datos iniciales, turnos y usuario admin
│   └── migrate.php                     # Instalador de base de datos en 1 paso
├── public/
│   └── index.php                       # Front Controller y Router
├── sync/
│   ├── config.py                       # Configuración de base de datos para Python
│   ├── zk_service.py                   # Driver pyzk con reconexión y diagnóstico
│   ├── sync_zkteco.py                  # Motor principal de sincronización
│   ├── test_device.py                  # Herramienta CLI de prueba de conexión
│   └── requirements.txt                # Dependencias Python (pyzk, pymysql)
├── views/                              # Vistas HTML con Bootstrap 5 y DataTables
├── .env.example                        # Plantilla de variables de entorno
├── .env                                # Configuración activa del sistema
└── index.php                           # Entrada principal
```

---



## ⚙️ Reglas de Negocio Implementadas

| Regla | Comportamiento |
|---|---|
| **Tolerancia de Entrada** | Minutos de gracia configurables por turno (ej: 10 min). Si la entrada es a las 08:00 y marca 08:08, se considera puntual. Si marca 08:12, se calculan 12 minutos de tardanza. |
| **Límite de Inasistencia** | Si llega después del límite (ej: +60 min), se alerta como tardanza excesiva / inasistencia. |
| **Filtro Debounce** | Si el empleado coloca la huella 3 veces en 1 minuto, solo se procesa la primera marcación válida. |
| **Turno Nocturno** | Agrupa marcaciones entre la tarde del día $D$ y la mañana del día $D+1$ como una sola jornada continua. |
| **Faltas** | Si en un día laborable el trabajador no tiene marcaciones ni permisos aprobados, el sistema marca `FALTA`. |
| **Justificaciones** | Al aprobar una justificación médica o de vacaciones, el sistema recalcula automáticamente el estado del empleado. |
