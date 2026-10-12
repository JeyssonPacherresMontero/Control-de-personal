#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
===================================================================
SISTEMA DE CONTROL DE PERSONAL Y ASISTENCIA - ZKTECO
Módulo de Gestión Biométrica (Huellas, Rostro y Usuarios en Reloj)
===================================================================
"""

import sys
import json
import base64
import argparse
import logging
import pymysql
from pymysql.cursors import DictCursor

from config import DB_CONFIG, ZK_CONFIG
from zk_service import ZKDeviceService
from device_lock import DeviceLock

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s'
)
logger = logging.getLogger("BiometricAdmin")

def get_db():
    return pymysql.connect(
        host=DB_CONFIG['host'],
        port=DB_CONFIG['port'],
        user=DB_CONFIG['user'],
        password=DB_CONFIG['password'],
        database=DB_CONFIG['database'],
        charset=DB_CONFIG['charset'],
        cursorclass=DictCursor,
        autocommit=True
    )

def get_device(device_id: int):
    conn = get_db()
    with conn.cursor() as cursor:
        cursor.execute("SELECT * FROM dispositivos WHERE id = %s", (device_id,))
        dev = cursor.fetchone()
        conn.close()
        return dev

def get_device_service(device_data):
    if not device_data:
        raise ValueError("Dispositivo no encontrado en la base de datos.")
    return ZKDeviceService(
        ip=device_data['ip'],
        port=device_data['puerto'] or 4370,
        timeout=ZK_CONFIG['default_timeout'],
        password=device_data.get('clave_comunicacion') or 0,
        force_udp=(str(device_data.get('protocolo', '')).upper() == 'UDP')
    )

def resolve_or_allocate_uid(existing_users: list, user_id: str) -> int:
    """
    Resuelve o asigna un UID seguro dentro del rango ZKTeco (1 a 65535):
    1. Si user_id ya existe en el reloj, reutiliza su UID.
    2. Si user_id es numérico, está libre y en rango 1..65535, lo utiliza.
    3. Si no es numérico, está tomado o supera 65535, busca max(uid) + 1 o el primer hueco libre.
    4. Rechaza con mensaje claro si la capacidad (65535) está agotada.
    """
    user_id_str = str(user_id).strip()
    if not user_id_str:
        raise ValueError("El ID / Código de reloj no puede estar vacío.")

    # 1. Reutilizar si ya existe
    for u in existing_users:
        if str(u.get('user_id', '')).strip() == user_id_str and u.get('uid') is not None:
            allocated_uid = int(u['uid'])
            if 1 <= allocated_uid <= 65535:
                return allocated_uid

    taken_uids = {int(u['uid']) for u in existing_users if u.get('uid') is not None and str(u['uid']).isdigit()}

    # 2. Si es numérico libre en rango
    if user_id_str.isdigit():
        num_val = int(user_id_str)
        if 1 <= num_val <= 65535 and num_val not in taken_uids:
            return num_val

    # 3. Asignar siguiente correlativo disponible
    candidate = (max(taken_uids) + 1) if taken_uids else 1
    if candidate > 65535:
        # Buscar primer slot libre entre 1 y 65535
        available_slots = set(range(1, 65536)) - taken_uids
        if not available_slots:
            raise ValueError("El reloj biométrico ha alcanzado su capacidad máxima (65,535 UIDs agotados).")
        candidate = min(available_slots)

    return candidate

def set_user_action(device_id: int, user_id: str, name: str, privilege: int = 0, password: str = '', card: int = 0):
    dev = get_device(device_id)
    if not dev:
        return {"success": False, "error": f"Dispositivo #{device_id} no encontrado."}
    
    lock = DeviceLock(device_id, purpose="set_user")
    if not lock.acquire(wait_timeout=5.0, retry_interval=0.5):
        return {
            "success": False,
            "error": f"El reloj biométrico #{device_id} está ocupado por otra operación o sincronización en segundo plano. Por favor reintente en unos segundos."
        }

    svc = get_device_service(dev)
    try:
        svc.connect()
        existing_users = svc.get_users()
        uid = resolve_or_allocate_uid(existing_users, user_id)
        svc.set_user(
            uid=uid,
            name=name,
            privilege=privilege,
            password=password,
            group_id=0,
            user_id=str(user_id).strip(),
            card=card
        )
        return {
            "success": True,
            "message": f"Usuario {user_id} ({name}) registrado exitosamente en el reloj {dev['nombre']} con UID {uid}.",
            "device_id": device_id,
            "user_id": str(user_id).strip(),
            "uid": uid
        }
    except ValueError as ve:
        return {"success": False, "error": str(ve)}
    except Exception as e:
        return {"success": False, "error": f"Error comunicando con reloj: {str(e)}"}
    finally:
        svc.disconnect()
        lock.release()

def enroll_user_action(device_id: int, user_id: str, temp_id: int = 0):
    dev = get_device(device_id)
    if not dev:
        return {"success": False, "error": f"Dispositivo #{device_id} no encontrado."}
    
    lock = DeviceLock(device_id, purpose="enroll_user")
    if not lock.acquire(wait_timeout=6.0, retry_interval=0.5):
        return {
            "success": False,
            "error": f"El reloj biométrico #{device_id} está ocupado por otra operación o sincronización en segundo plano. Por favor reintente en unos segundos."
        }

    svc = get_device_service(dev)
    try:
        svc.connect()
        user_id_str = str(user_id).strip()
        
        # Verificar existencia del usuario en el reloj
        users = svc.get_users()
        target_user = next((u for u in users if str(u.get('user_id', '')).strip() == user_id_str), None)
        if not target_user:
            return {
                "success": False,
                "error": f"El usuario {user_id_str} no existe en el reloj biométrico. Debe enviarlo/registrarlo al reloj antes de capturar su huella."
            }
        
        uid = int(target_user['uid'])
        try:
            if hasattr(svc.conn, 'cancel_capture'):
                svc.conn.cancel_capture()
        except Exception:
            pass

        enroll_ok = svc.enroll_user(uid=uid, temp_id=temp_id, user_id=user_id_str)
        
        if not enroll_ok:
            return {
                "success": False,
                "error": f"El reloj biométrico no completó el enrolamiento para el usuario {user_id_str}. Motivo probable: tiempo agotado en el sensor (no se colocó el dedo 3 veces), dedo ya enrolado/duplicado, o captura cancelada."
            }
        
        return {
            "success": True,
            "message": f"Huella enrolada exitosamente en el reloj {dev['nombre']} para el usuario {user_id_str} (dedo {temp_id}).",
            "device_id": device_id,
            "user_id": user_id_str,
            "uid": uid,
            "temp_id": temp_id
        }
    except Exception as e:
        return {"success": False, "error": f"Error comunicando con el reloj durante el enrolamiento: {str(e)}"}
    finally:
        svc.disconnect()
        lock.release()

def enroll_full_action(device_id: int, user_id: str, name: str, temp_id: int = 0, privilege: int = 0, password: str = '', card: int = 0):
    """
    Flujo atómico en UNA sola conexión y UN solo proceso con DeviceLock:
    1. Adquirir lock del dispositivo (evitar colisión en puerto 4370)
    2. Conectar al reloj
    3. Registrar/actualizar usuario (set_user)
    4. Verificar que el usuario existe en el reloj (get_users)
    5. Iniciar enrolamiento de huella (enroll_user - 3 lecturas)
    6. Extraer plantilla del dedo enrolado (get_user_template con fallback)
    7. Respaldar plantilla en MySQL (plantillas_biometricas)
    8. Liberar lock y desconectar limpiamente
    """
    dev = get_device(device_id)
    if not dev:
        return {"success": False, "error": f"Dispositivo #{device_id} no encontrado en la base de datos."}
    
    lock = DeviceLock(device_id, purpose="enroll_full")
    if not lock.acquire(wait_timeout=6.0, retry_interval=0.5):
        return {
            "success": False,
            "error": f"El reloj biométrico #{device_id} está ocupado por otra operación o sincronización en segundo plano. Por favor reintente en unos segundos."
        }

    svc = get_device_service(dev)
    try:
        svc.connect()
        user_id_str = str(user_id).strip()
        
        existing_users = svc.get_users()
        uid = resolve_or_allocate_uid(existing_users, user_id_str)
        
        # 1. Registrar / Actualizar usuario en el reloj
        svc.set_user(
            uid=uid,
            name=name,
            privilege=privilege,
            password=password,
            group_id=0,
            user_id=user_id_str,
            card=card
        )
        
        # 2. Verificar existencia en el dispositivo
        verify_users = svc.get_users()
        verified = any(str(u.get('user_id', '')).strip() == user_id_str for u in verify_users)
        if not verified:
            return {"success": False, "error": f"No se pudo verificar la creación del usuario {user_id_str} en el reloj biométrico."}
        
        # 3. Limpiar sensor y enrolar huella (bloqueante hasta completar 3 capturas o timeout)
        try:
            if hasattr(svc.conn, 'cancel_capture'):
                svc.conn.cancel_capture()
        except Exception:
            pass

        enroll_ok = svc.enroll_user(uid=uid, temp_id=temp_id, user_id=user_id_str)
        if not enroll_ok:
            return {
                "success": False,
                "error": f"El reloj no completó el enrolamiento del dedo {temp_id} (tiempo agotado, dedo duplicado o proceso cancelado)."
            }
        
        # 4. Obtener la plantilla del dedo enrolado con doble fallback
        template_entry = svc.get_user_template(uid=uid, temp_id=temp_id)
        if not template_entry or not template_entry.get('template'):
            # Fallback a get_templates
            all_templates = svc.get_templates()
            template_entry = next((
                t for t in all_templates 
                if (t.get('uid') == uid or str(t.get('uid')) == user_id_str or str(t.get('user_id', '')) == user_id_str)
                and int(t.get('fid', -1)) == int(temp_id)
            ), None)
        
        if not template_entry or not template_entry.get('template'):
            return {
                "success": False,
                "error": f"Huella capturada en el reloj, pero no se pudo extraer la plantilla del dedo {temp_id} para respaldarla."
            }
        
        # 5. Respaldar en MySQL
        raw_template = template_entry['template']
        b64_template = base64.b64encode(raw_template).decode('utf-8') if isinstance(raw_template, (bytes, bytearray)) else str(raw_template)
        fid = template_entry.get('fid', temp_id)
        size = template_entry.get('size', len(raw_template))
        
        conn = get_db()
        with conn.cursor() as cursor:
            cursor.execute("""
                INSERT INTO plantillas_biometricas 
                (codigo_reloj, tipo, dedo_indice, tamano, template_data, id_dispositivo_origen)
                VALUES (%s, 'HUELLA', %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE 
                    tamano = VALUES(tamano),
                    template_data = VALUES(template_data),
                    id_dispositivo_origen = VALUES(id_dispositivo_origen),
                    actualizado_en = NOW()
            """, (user_id_str, fid, size, b64_template, device_id))
        conn.close()
        
        return {
            "success": True,
            "message": f"Usuario {user_id_str} ({name}) registrado, huella (dedo {temp_id}) capturada y respaldada en MySQL exitosamente.",
            "device_id": device_id,
            "user_id": user_id_str,
            "temp_id": temp_id,
            "saved": 1
        }
    except ValueError as ve:
        return {"success": False, "error": str(ve)}
    except Exception as e:
        return {"success": False, "error": f"Error durante el proceso de enrolamiento completo: {str(e)}"}
    finally:
        svc.disconnect()
        lock.release()

def download_templates_action(device_id: int, user_id: str = None, temp_id: int = None):
    dev = get_device(device_id)
    if not dev:
        return {"success": False, "error": f"Dispositivo #{device_id} no encontrado."}
    
    lock = DeviceLock(device_id, purpose="download_templates")
    if not lock.acquire(wait_timeout=6.0, retry_interval=0.5):
        return {
            "success": False,
            "error": f"El reloj biométrico #{device_id} está ocupado por otra operación o sincronización en segundo plano. Por favor reintente en unos segundos."
        }

    svc = get_device_service(dev)
    try:
        svc.connect()
        # Obtener lista de usuarios para mapear UID -> User ID
        users = svc.get_users()
        uid_to_userid = {u['uid']: str(u['user_id']).strip() for u in users if u.get('uid') is not None}
        
        target_templates = []
        total_downloaded = 0

        if user_id:
            user_id_str = str(user_id).strip()
            target_user = next((u for u in users if str(u.get('user_id', '')).strip() == user_id_str), None)
            if not target_user:
                return {
                    "success": False,
                    "downloaded": 0,
                    "saved": 0,
                    "error": f"El usuario {user_id_str} no existe en el reloj biométrico {dev['nombre']}."
                }
            uid = int(target_user['uid'])

            # 1. Intento rápido con get_user_template
            if temp_id is not None and 0 <= int(temp_id) <= 9:
                t = svc.get_user_template(uid=uid, temp_id=int(temp_id))
                if t and t.get('template'):
                    target_templates.append(t)
            else:
                for fid in range(10):
                    t = svc.get_user_template(uid=uid, temp_id=fid)
                    if t and t.get('template'):
                        target_templates.append(t)

            # 2. Fallback a get_templates() si el comando individual no obtuvo resultados
            if not target_templates:
                all_templates = svc.get_templates()
                total_downloaded = len(all_templates)
                for t in all_templates:
                    if t.get('uid') == uid:
                        if temp_id is not None and int(t.get('fid', -1)) != int(temp_id):
                            continue
                        target_templates.append(t)
            else:
                total_downloaded = len(target_templates)
        else:
            # Modo masivo: descargar todas las plantillas del reloj
            target_templates = svc.get_templates()
            total_downloaded = len(target_templates)

        if not target_templates:
            if user_id:
                motivo = f"dedo {temp_id}" if temp_id is not None else "usuario"
                return {
                    "success": False,
                    "downloaded": total_downloaded,
                    "saved": 0,
                    "error": f"El reloj {dev['nombre']} no tiene registrada la plantilla de huella ({motivo}) para el usuario {user_id}."
                }
            return {"success": True, "downloaded": 0, "saved": 0, "message": "No se encontraron huellas registradas en el reloj."}
        
        conn = get_db()
        saved_count = 0
        with conn.cursor() as cursor:
            for t in target_templates:
                uid = t['uid']
                user_id_str = uid_to_userid.get(uid, str(uid))
                
                raw_template = t['template']
                b64_template = base64.b64encode(raw_template).decode('utf-8') if isinstance(raw_template, (bytes, bytearray)) else str(raw_template)
                fid = t.get('fid', 0)
                size = t.get('size', len(raw_template))
                
                cursor.execute("""
                    INSERT INTO plantillas_biometricas 
                    (codigo_reloj, tipo, dedo_indice, tamano, template_data, id_dispositivo_origen)
                    VALUES (%s, 'HUELLA', %s, %s, %s, %s)
                    ON DUPLICATE KEY UPDATE 
                        tamano = VALUES(tamano),
                        template_data = VALUES(template_data),
                        id_dispositivo_origen = VALUES(id_dispositivo_origen),
                        actualizado_en = NOW()
                """, (user_id_str, fid, size, b64_template, device_id))
                saved_count += 1
                
        conn.close()
        
        return {
            "success": True,
            "message": f"Se respaldaron {saved_count} plantilla(s) biométrica(s) desde el reloj {dev['nombre']} hacia MySQL.",
            "downloaded": total_downloaded,
            "saved": saved_count
        }
    except Exception as e:
        return {"success": False, "error": f"Error respaldando huellas: {str(e)}"}
    finally:
        svc.disconnect()
        lock.release()

def get_user_biometrics_status(user_id: str):
    conn = get_db()
    with conn.cursor() as cursor:
        cursor.execute("""
            SELECT pb.*, d.nombre as dispositivo_nombre 
            FROM plantillas_biometricas pb
            LEFT JOIN dispositivos d ON pb.id_dispositivo_origen = d.id
            WHERE pb.codigo_reloj = %s
        """, (user_id,))
        rows = cursor.fetchall()
        conn.close()
        
        finger_count = sum(1 for r in rows if r['tipo'] == 'HUELLA')
        face_count = sum(1 for r in rows if r['tipo'] == 'FACIAL')
        
        # Formatear datetime a string para serializar en JSON
        details = []
        for r in rows:
            r_copy = dict(r)
            if 'actualizado_en' in r_copy and r_copy['actualizado_en']:
                r_copy['actualizado_en'] = str(r_copy['actualizado_en'])
            # Omitir el payload binario pesado en la respuesta de status
            r_copy.pop('template_data', None)
            details.append(r_copy)
        
        return {
            "success": True,
            "user_id": user_id,
            "fingerprints": finger_count,
            "face": face_count,
            "details": details
        }

def main():
    parser = argparse.ArgumentParser(description="Administración Biométrica ZKTeco")
    parser.add_argument('action', choices=['set-user', 'enroll', 'enroll-full', 'download-templates', 'status'], help="Acción a ejecutar")
    parser.add_argument('--device', type=int, default=1, help="ID del dispositivo en la base de datos")
    parser.add_argument('--user-id', type=str, required=True, help="Código / ID del usuario en el reloj")
    parser.add_argument('--name', type=str, default='', help="Nombre del usuario para el reloj")
    parser.add_argument('--privilege', type=int, default=0, help="0: Usuario Normal, 14: Administrador")
    parser.add_argument('--password', type=str, default='', help="PIN o contraseña para el reloj")
    parser.add_argument('--card', type=int, default=0, help="Número de tarjeta RFID")
    parser.add_argument('--temp-id', type=int, default=0, help="ID del dedo a enrolar (0-9)")

    args = parser.parse_args()

    result = {}
    if args.action == 'set-user':
        result = set_user_action(
            device_id=args.device,
            user_id=args.user_id,
            name=args.name,
            privilege=args.privilege,
            password=args.password,
            card=args.card
        )
    elif args.action == 'enroll':
        result = enroll_user_action(
            device_id=args.device,
            user_id=args.user_id,
            temp_id=args.temp_id
        )
    elif args.action == 'enroll-full':
        result = enroll_full_action(
            device_id=args.device,
            user_id=args.user_id,
            name=args.name,
            temp_id=args.temp_id,
            privilege=args.privilege,
            password=args.password,
            card=args.card
        )
    elif args.action == 'download-templates':
        result = download_templates_action(
            device_id=args.device,
            user_id=args.user_id,
            temp_id=args.temp_id if args.temp_id is not None else None
        )
    elif args.action == 'status':
        result = get_user_biometrics_status(user_id=args.user_id)

    print(json.dumps(result, ensure_ascii=False, indent=2))

if __name__ == '__main__':
    main()
