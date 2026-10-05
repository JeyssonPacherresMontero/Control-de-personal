# Guía de Despliegue y Puesta en Producción: Arquitectura Híbrida ZKTeco

Esta guía describe cómo operar la arquitectura híbrida de asistencia en producción para los relojes biométricos ZKTeco (MB460 y compatibles).

---

## 1. Arquitectura del Sistema

```text
[ Reloj Biométrico ZKTeco ]
        |
        +---> [PUSH / ADMS (Puerto 8081)] ---> sync/push_listener.py ---> POST ?route=api_attendance_sync
        |                                                                           |
        |                                                                           v
        |                                                                  [ MySQL: marcaciones ]
        |                                                                 (Idempotencia SHA-256)
        |                                                                           ^
        +<--- [PULL / pyzk (Puerto 4370)] <--- sync/sync_zkteco.py -----------------+
              (Respaldo cada 5 min / Drenado de colas / Auditoría)
```

1. **Tiempo Real (PUSH / ADMS)**: En cuanto el empleado coloca su huella o rostro, el reloj envía de inmediato el evento HTTP al `push_listener.py` (puerto 8081).
2. **Respaldo e Histórico (PULL / pyzk)**: Sondeo periódico programado (cada 5 min) que descarga eventos pendientes si la red cayó y drena la tabla `cola_eventos_asistencia`.
3. **Idempotencia Estricta**: Cada marcación genera una clave SHA-256 determinista basada en `SERIE + USUARIO + TIMESTAMP + PUNCH + VERIFY`. Es imposible duplicar marcaciones.
4. **Protección de Memoria del Reloj**: Las lecturas son no destructivas; la memoria del reloj nunca se borra automáticamente.

---

## 2. Configuración en el Reloj ZKTeco MB460 (Físico)

Para habilitar la transmisión en tiempo real (PUSH / ADMS) desde la terminal:

1. Presione el botón **M/OK** en el reloj para ingresar al Menú Principal.
2. Navegue a **Comunicaciones** (o *Red*) y presione **M/OK**.
3. Seleccione **Configuración de Servidor de Nube** (o *Ajustes de Servidor Web* / *Configuración ADMS*).
4. Ingrese los siguientes parámetros:
   - **Habilitar Nombre de Dominio**: `Desactivado` (usar IP)
   - **Dirección IP del Servidor**: Dirección IP de la computadora o servidor donde corre el sistema (por ejemplo, `192.168.1.100` o la IP local del servidor).
   - **Puerto del Servidor**: `8081`
   - **Habilitar Proxy**: `Desactivado`
5. Guarde y reinicie el biométrico si el equipo lo solicita.
6. El icono de nube/red en la pantalla del reloj cambiará a conectado una vez establecida la comunicación.

---

## 3. Puesta en Marcha en Windows

### Opción A: Ejecución Directa (Prueba o Modo Consola)
Haga doble clic en:
```bat
scripts\start_hybrid_service.bat
```
O ejecute desde la terminal:
```bash
python sync/hybrid_service.py --host 0.0.0.0 --port 8081 --pull-interval 300
```

### Opción B: Ejecución Continua como Tarea Programada de Windows (Recomendada)
Para que el servicio inicie automáticamente al encender la computadora sin necesidad de iniciar sesión:

1. Abra **Programador de Tareas** (`taskschd.msc`).
2. Haga clic en **Crear tarea básica...**
   - Nombre: `ZKTeco Hybrid Sync Service`
   - Desencadenador: **Al iniciar el sistema**
   - Acción: **Iniciar un programa**
   - Programa o script: `C:\Windows\System32\cmd.exe`
   - Argumentos: `/c "C:\Users\JEYSSON\Downloads\Control de personal\scripts\start_hybrid_service.bat"`
   - Iniciar en: `C:\Users\JEYSSON\Downloads\Control de personal`
3. En las propiedades de la tarea:
   - Marque: **Ejecutar tanto si el usuario inició sesión como si no**.
   - Marque: **Ejecutar con los privilegios más altos**.

---

## 4. Modos de Dispositivo en el Panel Web

Desde la vista **Relojes Biométricos** (`?route=dispositivos`):

| Modo | Comportamiento | Uso recomendado |
|---|---|---|
| **HYBRID** | Recibe PUSH en tiempo real y ejecuta PULL de respaldo/recuperación periódica. | **Recomendado para el reloj principal MB460**. |
| **PULL** | Descarga marcaciones únicamente mediante conexión directa TCP/UDP al puerto 4370. | Relojes sin firmware ADMS o en redes cerradas. |
| **PUSH** | Solo recibe eventos enviados por el reloj vía HTTP al puerto 8081 (omite PULL rutinario). | Terminales remotas detrás de NAT o routers sin puerto 4370 abierto. |

---

## 5. Monitoreo y Telemetría

- **Latencia de Red**: Visualización en tiempo real del tiempo de respuesta del socket ZK (verde < 200ms, amarillo < 1000ms).
- **Cola de Eventos**: Monitoreo de `cola_eventos_asistencia` para eventos pendientes o en reintento.
- **Sondeo en Vivo**: La interfaz web actualiza automáticamente el estado de cada terminal cada 20 segundos sin recargar la página.
