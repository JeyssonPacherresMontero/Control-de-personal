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
import json
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
                    autocommit=True,
                    init_command=DB_CONFIG.get('init_command', "SET time_zone = '-05:00'")
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
        """Retorna {codigo_reloj_normalizado: id_empleado}, tolerante a ceros a la izquierda"""
        conn = self.get_db()
        with conn.cursor() as cursor:
            cursor.execute("SELECT id, codigo_reloj FROM empleados WHERE activo = 1")
            rows = cursor.fetchall()
            mapping = {}
            for row in rows:
                raw = str(row['codigo_reloj']).strip()
                mapping[raw] = row['id']                 # match exacto
                mapping[raw.lstrip('0') or '0'] = row['id']  # match sin ceros a la izquierda
            return mapping

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
                    user_id_str = str(u['user_id']).strip().lstrip('0') or '0'
                    if not user_id_str:
                        continue

                    full_name = u['name'].strip() if u['name'] else f"Empleado {user_id_str}"
                    if '.' in full_name:
                        parts = full_name.split('.', 1)
                        nombres = parts[0].strip()
                        apellidos = parts[1].strip() if len(parts) > 1 and parts[1].strip() else " "
                    else:
                        parts = full_name.split(' ', 1)
                        nombres = parts[0].strip()
                        apellidos = parts[1].strip() if len(parts) > 1 and parts[1].strip() else " "

                    dni_dummy = f"ZK{user_id_str.zfill(6)}"

                    try:
                        cursor.execute("""
                            INSERT INTO empleados (codigo_reloj, dni, nombres, apellidos, turno_id, activo)
                            VALUES (%s, %s, %s, %s, 1, 1)
                            ON DUPLICATE KEY UPDATE 
                                nombres = VALUES(nombres),
                                apellidos = VALUES(apellidos),
                                activo = 1
                        """, (user_id_str, dni_dummy, nombres, apellidos))
                        new_users_count += 1
                    except Exception as e:
                        logger.warning(f"No se pudo guardar empleado para user_id {user_id_str}: {str(e)}")

            if new_users_count > 0:
                logger.info(f"Sincronizados/actualizados {new_users_count} empleados desde el biométrico.")
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

    def sync_device(self, device, clear_after=False, sync_users=True, today_only=False, target_date=None, days=None, full=False):
        """
        Ejecuta la sincronización para un dispositivo biométrico específico.
        Soporta filtrado rápido por día (today_only), fecha específica (target_date),
        ventana de días (days), o histórico completo (full).
        """
        device_id = device['id']
        ip = device['ip']
        port = device['puerto'] or 4370
        protocol = device['protocolo'] or 'TCP'
        comm_key = device['clave_comunicacion'] or 0
        force_udp = (protocol.upper() == 'UDP')

        start_time = time.time()
        filter_mode = "HISTÓRICO COMPLETO" if full else ("SOLO HOY" if today_only else (f"FECHA {target_date}" if target_date else (f"ÚLTIMOS {days} DÍAS" if days else "INCREMENTAL (7 DÍAS)")))
        logger.info(f"==> Iniciando sincronización ({filter_mode}) con biométrico #{device_id} [{device['nombre']}] en {ip}:{port} ({protocol})")

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

            # Sincronizar usuarios si está activo y no es modo solo hoy (para máxima velocidad)
            if sync_users and not today_only and ZK_CONFIG.get('sync_users', True):
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
                return {"success": True, "downloaded": 0, "inserted": 0, "duplicates": 0, "affected_dates": []}

            # Mapeo de empleados
            emp_map = self.get_employee_mapping()

            # Inserción Inteligente en Base de Datos (Transacción única + Filtrado incremental / Fecha)
            conn = self.get_db()
            inserted_count = 0
            duplicates_count = 0

            with conn.cursor() as cursor:
                # Filtrado según los parámetros especificados
                today_dt = datetime.date.today()
                
                if today_only:
                    candidate_records = [r for r in records if r['timestamp'].date() == today_dt]
                elif target_date:
                    candidate_records = [r for r in records if r['timestamp'].date() == target_date]
                elif days and days > 0:
                    cutoff_days = datetime.datetime.now() - datetime.timedelta(days=days)
                    candidate_records = [r for r in records if r['timestamp'] >= cutoff_days]
                elif full:
                    # MODO HISTÓRICO COMPLETO: evalúa el 100% de las marcaciones almacenadas en el reloj
                    candidate_records = records
                else:
                    # Modo Incremental inteligente:
                    # Consultar la última marcación registrada de este dispositivo
                    cursor.execute("SELECT MAX(fecha_hora) as max_fecha FROM marcaciones WHERE id_dispositivo = %s", (device_id,))
                    max_row = cursor.fetchone()
                    max_fecha = max_row['max_fecha'] if max_row and max_row['max_fecha'] else None

                    # Ventana de seguridad de 7 días (para proteger fines de semana largos y descansos)
                    if max_fecha and isinstance(max_fecha, datetime.datetime):
                        cutoff = max_fecha - datetime.timedelta(days=7)
                        candidate_records = [r for r in records if r['timestamp'] >= cutoff]
                    else:
                        candidate_records = records

                insert_query = """
                    INSERT IGNORE INTO marcaciones 
                    (id_empleado, codigo_reloj, id_dispositivo, fecha_hora, tipo, tipo_verificacion, uid_dispositivo, procesado)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, 0)
                """
                
                batch_data = []
                event_batch_data = []
                disp_name = device.get('nombre', 'Reloj ZKTeco')
                
                for rec in candidate_records:
                    user_id_str = str(rec['user_id']).strip()
                    emp_id = emp_map.get(user_id_str) or emp_map.get(user_id_str.lstrip('0') or '0')
                    dt_str = rec['timestamp'].strftime('%Y-%m-%d %H:%M:%S')
                    tipo_verif = rec.get('tipo_verificacion', 'huella')
                    
                    batch_data.append((
                        emp_id,
                        user_id_str,
                        device_id,
                        dt_str,
                        rec['tipo'],
                        tipo_verif,
                        rec['uid']
                    ))

                    # Formatear etiqueta amigable para el log inmutable
                    verif_label = "Reconocimiento Facial" if tipo_verif == "facial" else ("Huella Dactilar" if tipo_verif == "huella" else ("Tarjeta RFID" if tipo_verif == "tarjeta" else "Contraseña / PIN"))

                    # Event Sourcing: Preparar payload inmutable del evento
                    agg_id = f"emp_{emp_id}_{dt_str.replace(' ', '_').replace(':', '-')}" if emp_id else f"zk_{user_id_str}_{dt_str.replace(' ', '_').replace(':', '-')}"
                    event_payload = json.dumps({
                        "id_empleado": emp_id,
                        "codigo_reloj": user_id_str,
                        "id_dispositivo": device_id,
                        "dispositivo_nombre": disp_name,
                        "dispositivo_ip": ip,
                        "fecha_hora": dt_str,
                        "tipo": rec['tipo'],
                        "tipo_verificacion": verif_label,
                        "uid_dispositivo": rec['uid']
                    }, ensure_ascii=False)

                    event_batch_data.append((
                        'MARCACION',
                        agg_id,
                        'MARCACION_CAPTURADA_DISPOSITIVO',
                        event_payload,
                        1,
                        f"ZKTECO_SYNC_{disp_name}",
                        ip
                    ))

                # Ejecutar con transacción única para máxima velocidad en disco
                conn.begin()
                batch_size = 2000
                for i in range(0, len(batch_data), batch_size):
                    chunk = batch_data[i:i + batch_size]
                    affected = cursor.executemany(insert_query, chunk)
                    inserted_count += affected

                # Registrar eventos en Event Store si hay inserciones
                if event_batch_data:
                    event_insert_query = """
                        INSERT INTO eventos_asistencia 
                        (aggregate_type, aggregate_id, event_type, event_data, version, created_by, ip_address)
                        VALUES (%s, %s, %s, %s, %s, %s, %s)
                    """
                    for i in range(0, len(event_batch_data), batch_size):
                        chunk_events = event_batch_data[i:i + batch_size]
                        try:
                            cursor.executemany(event_insert_query, chunk_events)
                        except Exception as ee:
                            logger.warning(f"Aviso al guardar eventos de auditoría: {str(ee)}")

                conn.commit()

                duplicates_count = len(candidate_records) - inserted_count

            # Si el usuario solicitó limpiar la memoria del reloj tras descargar y guardar con éxito
            if (clear_after or ZK_CONFIG.get('clear_after_sync', False)) and total_downloaded > 0:
                try:
                    zk_service.clear_attendance()
                    logger.info(f"Memoria de registros limpiada en el dispositivo #{device_id}")
                except Exception as ce:
                    logger.error(f"Error al limpiar memoria de {ip}: {str(ce)}")

            duration = time.time() - start_time
            msg = f"Sincronización ({filter_mode}): {total_downloaded} en reloj, {len(candidate_records)} evaluados, {inserted_count} nuevos insertados, {duplicates_count} ya existentes."
            logger.info(msg)

            self.update_device_status(device_id, 'ONLINE')
            self.log_sync_event(device_id, 'SYNC_AUTO' if not (today_only or full) else 'SYNC_MANUAL', total_downloaded, inserted_count, duplicates_count, 'EXITO', msg, duration)

            # Recolectar fechas únicas de las marcaciones candidatas
            affected_dates = set()
            for rec in candidate_records:
                affected_dates.add(rec['timestamp'].date().strftime('%Y-%m-%d'))

            zk_service.disconnect()
            return {
                "success": True,
                "device_id": device_id,
                "downloaded": total_downloaded,
                "evaluated": len(candidate_records),
                "inserted": inserted_count,
                "duplicates": duplicates_count,
                "affected_dates": list(affected_dates)
            }

        except Exception as e:
            duration = time.time() - start_time
            err_msg = f"Error en sincronización con {ip}:{port}: {str(e)}"
            logger.error(err_msg)
            
            self.update_device_status(device_id, 'OFFLINE', error_msg=str(e))
            self.log_sync_event(device_id, 'ERROR', 0, 0, 0, 'ERROR', err_msg, duration)
            
            try:
                zk_service.disconnect()
            except Exception:
                pass
            return {
                "success": False,
                "device_id": device_id,
                "error": str(e)
            }

    def run_attendance_calculation(self, dates=None):
        """Invoca el motor de cálculo de asistencia diaria en PHP tras sincronizar"""
        try:
            import shutil
            base_dir = Path(__file__).resolve().parent.parent
            script_path = base_dir / 'app' / 'Console' / 'process_attendance.php'
            
            if script_path.exists():
                php_bin = shutil.which('php')
                if not php_bin:
                    xampp_php = Path("C:/xampp/php/php.exe")
                    if xampp_php.exists():
                        php_bin = str(xampp_php)
                    else:
                        php_bin = 'php'
                
                if dates and len(dates) > 0:
                    min_d = min(dates)
                    max_d = max(dates)
                    logger.info(f"Ejecutando motor de cálculo de asistencia diaria para rango {min_d} al {max_d}...")
                    cmd_args = [php_bin, str(script_path), min_d, max_d]
                else:
                    logger.info("Ejecutando motor de cálculo de asistencia diaria para hoy...")
                    cmd_args = [php_bin, str(script_path)]

                result = subprocess.run(cmd_args, capture_output=True, text=True, timeout=180)
                if result.returncode == 0:
                    logger.info(f"Cálculo completado exitosamente: {result.stdout.strip()[:300]}")
                else:
                    logger.warning(f"Aviso en cálculo de asistencia: {result.stderr.strip()[:300]}")
        except Exception as e:
            logger.error(f"Error al invocar cálculo de asistencia PHP: {str(e)}")

    def sync_all_devices(self, device_id=None, clear_after=False, sync_users=True, today_only=False, target_date=None, days=None, full=False):
        """Itera y sincroniza todos los biométricos activos sin detenerse por fallos individuales"""
        devices = self.get_active_devices(device_id)
        if not devices:
            logger.info("No hay dispositivos biométricos activos para sincronizar.")
            return

        mode_desc = "HISTÓRICO COMPLETO" if full else ("SOLO HOY" if today_only else (f"FECHA {target_date}" if target_date else (f"ÚLTIMOS {days} DÍAS" if days else "INCREMENTAL (7 DÍAS)")))
        logger.info(f"Iniciando ciclo de sincronización ({mode_desc}) para {len(devices)} dispositivo(s)...")
        total_new_punches = 0
        all_affected_dates = set()

        for dev in devices:
            res = self.sync_device(
                dev,
                clear_after=clear_after,
                sync_users=sync_users,
                today_only=today_only,
                target_date=target_date,
                days=days,
                full=full
            )
            if res.get('success', False):
                ins = res.get('inserted', 0)
                total_new_punches += ins
                if ins > 0 or full:
                    for d in res.get('affected_dates', []):
                        all_affected_dates.add(d)

        # Si hubo nuevas marcaciones o se solicitó histórico completo, procesar asistencia para las fechas afectadas
        if total_new_punches > 0 or (full and all_affected_dates):
            logger.info(f"Se insertaron {total_new_punches} marcaciones. Recalculando {len(all_affected_dates)} fecha(s) afectadas...")
            self.run_attendance_calculation(dates=all_affected_dates)
        else:
            logger.info("No hubo marcaciones nuevas insertadas en este ciclo.")

        logger.info("Ciclo de sincronización finalizado exitosamente.")

    def run_daemon(self, interval_minutes=10, today_only=True):
        """Ejecuta el sincronizador como servicio continuo cada N minutos"""
        logger.info(f"Iniciando servicio continuo de sincronización (Intervalo: cada {interval_minutes} minutos, Modo Rápido Hoy: {today_only}). Presiona Ctrl+C para salir.")
        try:
            while True:
                try:
                    self.sync_all_devices(today_only=today_only, sync_users=False)
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
    parser.add_argument('--today-only', action='store_true', help='Filtra y procesa únicamente las marcaciones de hoy (Ultra Rápido)')
    parser.add_argument('--full', action='store_true', help='Sincronización histórica completa: evalúa y procesa todas las marcaciones del reloj sin recortar por fecha')
    parser.add_argument('--days', type=int, help='Filtra marcaciones de los últimos N días')
    parser.add_argument('--date', type=str, help='Fecha específica a sincronizar (formato YYYY-MM-DD)')
    parser.add_argument('--skip-users', action='store_true', help='Omite la sincronización de usuarios para mayor velocidad')
    
    args = parser.parse_args()

    sync = AttendanceSynchronizer()

    target_date_parsed = None
    if args.date:
        try:
            target_date_parsed = datetime.datetime.strptime(args.date, '%Y-%m-%d').date()
        except ValueError:
            print("Error: formato de fecha inválido. Utilice YYYY-MM-DD")
            sys.exit(1)

    try:
        if args.test:
            devices = sync.get_active_devices(args.device)
            print(f"Probando conexión con {len(devices)} dispositivo(s)...")
            for dev in devices:
                service = ZKDeviceService(
                    ip=dev['ip'],
                    port=dev['puerto'] or 4370,
                    timeout=5,
                    password=dev.get('clave_comunicacion') or 0,
                    force_udp=(str(dev.get('protocolo', '')).upper() == 'UDP')
                )
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
            sync.run_daemon(interval_minutes=args.interval, today_only=args.today_only)
        else:
            # Ejecución One-Shot (ideal para Programador de Tareas de Windows o Cron de Linux)
            sync.sync_all_devices(
                device_id=args.device,
                clear_after=args.clear,
                sync_users=not args.skip_users,
                today_only=args.today_only,
                target_date=target_date_parsed,
                days=args.days,
                full=args.full
            )
    finally:
        sync.close_db()
        try:
            lock_path = Path(__file__).resolve().parent.parent / 'storage' / 'sync.lock'
            if lock_path.exists():
                lock_path.unlink(missing_ok=True)
        except Exception:
            pass

if __name__ == '__main__':
    main()

