#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
===================================================================
SISTEMA DE CONTROL DE PERSONAL Y ASISTENCIA - ZKTECO
Motor de Sincronización Automática de Relojes Biométricos con MySQL
===================================================================
"""

import sys
import time
import argparse
import datetime
import logging
import subprocess
from pathlib import Path

try:
    import pymysql
    from pymysql.cursors import DictCursor
except ImportError:
    print("Error: pymysql no está instalado. Ejecuta: pip install -r sync/requirements.txt")
    sys.exit(1)

from config import DB_CONFIG, ZK_CONFIG
from zk_service import ZKDeviceService

# Configurar logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    datefmt='%Y-%m-%d %H:%M:%S'
)
logger = logging.getLogger("SyncEngine")

class AttendanceSynchronizer:
    def __init__(self):
        self.db_conn = None

    def get_db(self):
        """Obtiene o reconecta la conexión a MySQL"""
        try:
            if self.db_conn is None or not self.db_conn.open:
                self.db_conn = pymysql.connect(
                    host=DB_CONFIG['host'],
                    port=DB_CONFIG['port'],
                    user=DB_CONFIG['user'],
                    password=DB_CONFIG['password'],
                    database=DB_CONFIG['database'],
                    charset=DB_CONFIG['charset'],
                    cursorclass=DictCursor,
                    autocommit=True
                )
            return self.db_conn
        except Exception as e:
            logger.error(f"Fallo crítico al conectar a MySQL: {str(e)}")
            raise

    def close_db(self):
        if self.db_conn and self.db_conn.open:
            try:
                self.db_conn.close()
            except Exception:
                pass
            self.db_conn = None

    def get_active_devices(self, device_id=None):
        """Consulta los dispositivos biométricos activos en la base de datos"""
        conn = self.get_db()
        with conn.cursor() as cursor:
            if device_id:
                cursor.execute("SELECT * FROM dispositivos WHERE id = %s AND activo = 1", (device_id,))
            else:
                cursor.execute("SELECT * FROM dispositivos WHERE activo = 1")
            return cursor.fetchall()

    def get_employee_mapping(self):
        """Retorna un diccionario {codigo_reloj: id_empleado} para asociación rápida"""
        conn = self.get_db()
        with conn.cursor() as cursor:
            cursor.execute("SELECT id, codigo_reloj FROM empleados WHERE activo = 1")
            rows = cursor.fetchall()
            return {str(row['codigo_reloj']).strip(): row['id'] for row in rows}

    def sync_users_from_device(self, device_service, device_id):
        """Descarga usuarios del biométrico e inserta nuevos empleados si no existen"""
        try:
            users = device_service.get_users()
            if not users:
                return 0

            conn = self.get_db()
            current_employees = self.get_employee_mapping()
            new_users_count = 0

            with conn.cursor() as cursor:
                for u in users:
                    user_id_str = str(u['user_id']).strip()
                    if not user_id_str:
                        continue

                    # Si el código de reloj no existe en la BD de empleados, se crea un registro base
                    if user_id_str not in current_employees:
                        full_name = u['name'] if u['name'] else f"Empleado {user_id_str}"
                        parts = full_name.split(' ', 1)
                        nombres = parts[0]
                        apellidos = parts[1] if len(parts) > 1 else "Pendiente"
                        dni_dummy = f"ZK{user_id_str.zfill(6)}"

                        try:
                            cursor.execute("""
                                INSERT INTO empleados (codigo_reloj, dni, nombres, apellidos, turno_id, activo)
                                VALUES (%s, %s, %s, %s, 1, 1)
                                ON DUPLICATE KEY UPDATE codigo_reloj = VALUES(codigo_reloj)
                            """, (user_id_str, dni_dummy, nombres, apellidos))
                            new_users_count += 1
                        except Exception as e:
                            logger.warning(f"No se pudo crear empleado para user_id {user_id_str}: {str(e)}")

            if new_users_count > 0:
                logger.info(f"Sincronizados {new_users_count} nuevos empleados desde el biométrico.")
            return new_users_count
        except Exception as e:
            logger.error(f"Error al sincronizar usuarios de dispositivo {device_id}: {str(e)}")
            return 0

    def log_sync_event(self, device_id, event_type, downloaded, inserted, duplicates, status, message, duration):
        """Registra el resultado de la sincronización en log_sincronizacion"""
        try:
            conn = self.get_db()
            with conn.cursor() as cursor:
                cursor.execute("""
                    INSERT INTO log_sincronizacion 
                    (id_dispositivo, tipo_evento, total_descargados, total_insertados, total_duplicados, estado, mensaje, duracion_segundos)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
                """, (device_id, event_type, downloaded, inserted, duplicates, status, message, round(duration, 2)))
        except Exception as e:
            logger.error(f"Error guardando log de sincronización: {str(e)}")

    def update_device_status(self, device_id, status, firmware=None, serial=None, error_msg=None):
        """Actualiza el estado de conexión del biométrico en la base de datos"""
        try:
            conn = self.get_db()
            with conn.cursor() as cursor:
                if status == 'ONLINE':
                    cursor.execute("""
                        UPDATE dispositivos 
                        SET estado_conexion = 'ONLINE',
                            ultimo_sync = NOW(),
                            ultimo_error = NULL,
                            version_firmware = COALESCE(%s, version_firmware),
                            numero_serie = COALESCE(%s, numero_serie)
                        WHERE id = %s
                    """, (firmware, serial, device_id))
                else:
                    cursor.execute("""
                        UPDATE dispositivos 
                        SET estado_conexion = %s,
                            ultimo_error = %s
                        WHERE id = %s
                    """, (status, error_msg, device_id))
        except Exception as e:
            logger.error(f"Error actualizando estado de dispositivo {device_id}: {str(e)}")

    def sync_device(self, device, clear_after=False, sync_users=True):
        """
        Ejecuta la sincronización completa para un dispositivo biométrico específico.
        """
        device_id = device['id']
        ip = device['ip']
        port = device['puerto'] or 4370
        protocol = device['protocolo'] or 'TCP'
        comm_key = device['clave_comunicacion'] or 0
        force_udp = (protocol.upper() == 'UDP')

        start_time = time.time()
        logger.info(f"==> Iniciando sincronización con biométrico #{device_id} [{device['nombre']}] en {ip}:{port} ({protocol})")

        zk_service = ZKDeviceService(
            ip=ip,
            port=port,
            timeout=ZK_CONFIG['default_timeout'],
            password=comm_key,
            force_udp=force_udp
        )

        try:
            zk_service.connect()
            
            # Sincronizar hora del reloj con el servidor
            try:
                zk_service.sync_time()
            except Exception as te:
                logger.warning(f"No se pudo sincronizar la hora con {ip}: {str(te)}")

            # Sincronizar usuarios si está activo
            if sync_users or ZK_CONFIG['sync_users']:
                self.sync_users_from_device(zk_service, device_id)

            # Descargar marcaciones del biométrico
            records = zk_service.get_attendance_records()
            total_downloaded = len(records)

            if total_downloaded == 0:
                duration = time.time() - start_time
                self.update_device_status(device_id, 'ONLINE')
                self.log_sync_event(device_id, 'SYNC_AUTO', 0, 0, 0, 'EXITO', 'Sin nuevas marcaciones en el dispositivo.', duration)
                logger.info(f"Dispositivo #{device_id} no tiene registros de asistencia pendientes.")
                zk_service.disconnect()
                return {"success": True, "downloaded": 0, "inserted": 0, "duplicates": 0}

            # Mapeo de empleados
            emp_map = self.get_employee_mapping()

            # Inserción en Base de Datos (Lotes / Batch)
            conn = self.get_db()
            inserted_count = 0
            duplicates_count = 0

            with conn.cursor() as cursor:
                # Preparamos lista para inserción masiva eficiente
                insert_query = """
                    INSERT IGNORE INTO marcaciones 
                    (id_empleado, codigo_reloj, id_dispositivo, fecha_hora, tipo, uid_dispositivo, procesado)
                    VALUES (%s, %s, %s, %s, %s, %s, 0)
                """
                
                batch_data = []
                for rec in records:
                    user_id_str = rec['user_id']
                    emp_id = emp_map.get(user_id_str, None)
                    batch_data.append((
                        emp_id,
                        user_id_str,
                        device_id,
                        rec['timestamp'].strftime('%Y-%m-%d %H:%M:%S'),
                        rec['tipo'],
                        rec['uid']
                    ))

                # Ejecutar inserción en lotes de 200
                batch_size = 200
                for i in range(0, len(batch_data), batch_size):
                    chunk = batch_data[i:i + batch_size]
                    affected = cursor.executemany(insert_query, chunk)
                    inserted_count += affected

                duplicates_count = total_downloaded - inserted_count

            # Si el usuario solicitó limpiar la memoria del reloj tras descargar y guardar con éxito
            if (clear_after or ZK_CONFIG['clear_after_sync']) and inserted_count > 0:
                try:
                    zk_service.clear_attendance()
                    logger.info(f"Memoria de registros limpiada en el dispositivo #{device_id}")
                except Exception as ce:
                    logger.error(f"Error al limpiar memoria de {ip}: {str(ce)}")

            duration = time.time() - start_time
            msg = f"Sincronización completada: {total_downloaded} descargados, {inserted_count} nuevos insertados, {duplicates_count} ya existentes."
            logger.info(msg)

            self.update_device_status(device_id, 'ONLINE')
            self.log_sync_event(device_id, 'SYNC_AUTO', total_downloaded, inserted_count, duplicates_count, 'EXITO', msg, duration)

            zk_service.disconnect()
            return {
                "success": True,
                "device_id": device_id,
                "downloaded": total_downloaded,
                "inserted": inserted_count,
                "duplicates": duplicates_count
            }

        except Exception as e:
            duration = time.time() - start_time
            err_msg = f"Error en sincronización con {ip}:{port}: {str(e)}"
            logger.error(err_msg)
            
            self.update_device_status(device_id, 'OFFLINE', error_msg=str(e))
            self.log_sync_event(device_id, 'ERROR', 0, 0, 0, 'ERROR', err_msg, duration)
            
            zk_service.disconnect()
            return {
                "success": False,
                "device_id": device_id,
                "error": str(e)
            }

    def run_attendance_calculation(self):
        """Invoca el motor de cálculo de asistencia diaria en PHP tras sincronizar"""
        try:
            import shutil
            base_dir = Path(__file__).resolve().parent.parent
            script_path = base_dir / 'app' / 'Console' / 'process_attendance.php'
            
            if script_path.exists():
                logger.info("Ejecutando motor de cálculo de asistencia diaria...")
                php_bin = shutil.which('php')
                if not php_bin:
                    xampp_php = Path("C:/xampp/php/php.exe")
                    if xampp_php.exists():
                        php_bin = str(xampp_php)
                    else:
                        php_bin = 'php'
                
                result = subprocess.run([php_bin, str(script_path)], capture_output=True, text=True, timeout=60)
                if result.returncode == 0:
                    logger.info(f"Cálculo completado: {result.stdout.strip()}")
                else:
                    logger.warning(f"Aviso en cálculo de asistencia: {result.stderr.strip()}")
        except Exception as e:
            logger.error(f"Error al invocar cálculo de asistencia PHP: {str(e)}")

    def sync_all_devices(self, device_id=None, clear_after=False):
        """Itera y sincroniza todos los biométricos activos sin detenerse por fallos individuales"""
        devices = self.get_active_devices(device_id)
        if not devices:
            logger.info("No hay dispositivos biométricos activos para sincronizar.")
            return

        logger.info(f"Iniciando ciclo de sincronización para {len(devices)} dispositivo(s)...")
        total_new_punches = 0

        for dev in devices:
            res = self.sync_device(dev, clear_after=clear_after)
            if res.get('success', False):
                total_new_punches += res.get('inserted', 0)

        # Si hubo nuevas marcaciones, procesar reglas de asistencia automáticamente
        if total_new_punches > 0:
            self.run_attendance_calculation()

        logger.info("Ciclo de sincronización finalizado exitosamente.")

    def run_daemon(self, interval_minutes=10):
        """Ejecuta el sincronizador como servicio continuo cada N minutos"""
        logger.info(f"Iniciando servicio continuo de sincronización (Intervalo: cada {interval_minutes} minutos). Presiona Ctrl+C para salir.")
        try:
            while True:
                try:
                    self.sync_all_devices()
                except Exception as e:
                    logger.error(f"Error imprevisto en ciclo daemon: {str(e)}")
                
                logger.info(f"Esperando {interval_minutes} minutos hasta el próximo ciclo...")
                time.sleep(interval_minutes * 60)
        except KeyboardInterrupt:
            logger.info("Servicio de sincronización detenido por el usuario.")
        finally:
            self.close_db()

def main():
    parser = argparse.ArgumentParser(description="Motor de Sincronización ZKTeco para Control de Personal")
    parser.add_argument('--device', type=int, help='ID del dispositivo biométrico a sincronizar')
    parser.add_argument('--daemon', action='store_true', help='Ejecuta en modo servicio continuo (loop)')
    parser.add_argument('--interval', type=int, default=10, help='Intervalo en minutos para el modo daemon (def: 10)')
    parser.add_argument('--test', action='store_true', help='Prueba la conexión con los biométricos configurados')
    parser.add_argument('--clear', action='store_true', help='Limpia los registros del reloj tras guardarlos en MySQL')
    
    args = parser.parse_args()

    sync = AttendanceSynchronizer()

    try:
        if args.test:
            devices = sync.get_active_devices(args.device)
            print(f"Probando conexión con {len(devices)} dispositivo(s)...")
            for dev in devices:
                service = ZKDeviceService(dev['ip'], dev['puerto'] or 4370, timeout=5)
                res = service.test_connection()
                print("--------------------------------------------------")
                print(f"Dispositivo #{dev['id']} - {dev['nombre']} ({dev['ip']}:{dev['puerto']})")
                print(f"Resultado: {'CONECTADO (ONLINE)' if res['success'] else 'FALLO (OFFLINE)'}")
                if res['success']:
                    print(f"  Modelo/Serie: {res.get('device_name')} / {res.get('serial_number')}")
                    print(f"  Firmware:     {res.get('firmware')}")
                    print(f"  Usuarios:     {res.get('user_count')}")
                    print(f"  Marcaciones:  {res.get('attendance_count')}")
                    print(f"  Hora Reloj:   {res.get('device_time')}")
                else:
                    print(f"  Error: {res.get('error')}")
            print("--------------------------------------------------")
        elif args.daemon:
            sync.run_daemon(interval_minutes=args.interval)
        else:
            # Ejecución One-Shot (ideal para Programador de Tareas de Windows o Cron de Linux)
            sync.sync_all_devices(device_id=args.device, clear_after=args.clear)
    finally:
        sync.close_db()

if __name__ == '__main__':
    main()
