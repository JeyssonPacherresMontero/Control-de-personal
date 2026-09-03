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
   - Exportación de reportes a **Excel / CSV**.
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

## 🚀 Guía de Instalación y Puesta en Marcha

### Paso 1: Configurar Base de Datos MySQL
1. Asegúrate de tener **MySQL** o **MariaDB** encendido (por ejemplo, mediante XAMPP).
2. Revisa el archivo `.env` en la raíz del proyecto y ajusta las credenciales si es necesario:
   ```ini
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=control_personal
   DB_USER=root
   DB_PASS=
   ```
3. Ejecuta el instalador automático de tablas y datos semilla:
   ```bash
   php database/migrate.php
   ```

### Paso 2: Instalar Dependencias de Python para ZKTeco
Abre una terminal y ejecuta:
```bash
pip install -r sync/requirements.txt
```

### Paso 3: Probar Comunicación con el Reloj Biométrico
Para verificar la conexión con tu reloj ZKTeco en la red:
```bash
python sync/test_device.py 192.168.1.201 4370
```
> Si responde con `[✔] ¡CONEXIÓN EXITOSA!`, el reloj está listo para sincronizar.

### Paso 4: Ejecutar Sincronización
- **Modo One-Shot** (descarga una vez y procesa reglas de asistencia):
  ```bash
  python sync/sync_zkteco.py
  ```
- **Modo Daemon Continuo** (ejecuta en segundo plano cada 10 minutos):
  ```bash
  python sync/sync_zkteco.py --daemon --interval 10
  ```

---

## ⏰ Automatización en Servidores de Producción

### En Windows (con XAMPP / IIS / Servidor Físico)
1. Haz clic derecho sobre `cron/task_scheduler_setup.bat` y selecciona **"Ejecutar como Administrador"**.
2. Esto creará la tarea `ZKTeco_Attendance_Sync` en el Programador de Tareas de Windows para ejecutarse cada 10 minutos de forma silenciosa.

### En Linux (Ubuntu / Debian / CentOS)
Ejecuta el script de crontab:
```bash
chmod +x cron/cron_setup.sh
./cron/cron_setup.sh
```

---

## 🔑 Credenciales de Acceso Web por Defecto

- **URL de Acceso**: `http://localhost/Control%20de%20personal/` o `http://localhost:8000`

| Usuario | Contraseña | Rol | Alcance y Permisos |
|---|---|---|---|
| **`admin`** | `admin123` | **ADMIN** | Acceso total al sistema: configuración de hardware/relojes biométricos, turnos, empleados, asistencias, recalcular y auditoría. |
| **`rrhh`** | `admin123` | **RRHH** | Gestión de personal, creación/edición de turnos, marcaciones manuales, ajustes de asistencia y resolución de justificaciones. |
| **`supervisor`** | `admin123` | **SUPERVISOR** | Supervisión de personal de área, registro de justificaciones y permisos, visualización de asistencias. |
| **`consulta`** | `admin123` | **CONSULTA** | Solo consulta y auditoría: lectura de reportes, marcaciones y asistencias (sin permisos de modificación). |

> **Nota de Seguridad:** Las contraseñas se almacenan con cifrado `BCRYPT`. Para generar una nueva contraseña en PHP utiliza `password_hash('tu_clave', PASSWORD_BCRYPT)`.

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
