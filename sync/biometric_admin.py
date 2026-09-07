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

def set_user_action(device_id: int, user_id: str, name: str, privilege: int = 0, password: str = '', card: int = 0):
    dev = get_device(device_id)
    if not dev:
        return {"success": False, "error": f"Dispositivo #{device_id} no encontrado."}
    
    svc = get_device_service(dev)
    try:
        svc.connect()
        uid = int(user_id) if user_id.isdigit() else 1
        svc.set_user(
            uid=uid,
            name=name,
            privilege=privilege,
            password=password,
            group_id=0,
            user_id=user_id,
            card=card
        )
        return {
            "success": True,
            "message": f"Usuario {user_id} ({name}) registrado exitosamente en el reloj {dev['nombre']} ({dev['ip']}).",
            "device_id": device_id,
            "user_id": user_id
        }
    except Exception as e:
        return {"success": False, "error": f"Error comunicando con reloj: {str(e)}"}
    finally:
        svc.disconnect()

def enroll_user_action(device_id: int, user_id: str, temp_id: int = 0):
    dev = get_device(device_id)
    if not dev:
        return {"success": False, "error": f"Dispositivo #{device_id} no encontrado."}
    
    svc = get_device_service(dev)
    try:
        svc.connect()
        uid = int(user_id) if user_id.isdigit() else 1
        svc.enroll_user(uid=uid, temp_id=temp_id)
        return {
            "success": True,
            "message": f"Modo de captura/enrolamiento activado en reloj {dev['nombre']}. Por favor coloque el dedo en el sensor biométrico del reloj 3 veces.",
            "device_id": device_id,
            "user_id": user_id,
            "temp_id": temp_id
        }
    except Exception as e:
        return {"success": False, "error": f"Error activando captura en reloj: {str(e)}"}
    finally:
        svc.disconnect()

def download_templates_action(device_id: int, user_id: str = None):
    dev = get_device(device_id)
    if not dev:
        return {"success": False, "error": f"Dispositivo #{device_id} no encontrado."}
    
    svc = get_device_service(dev)
    try:
        svc.connect()
        # Obtener lista de usuarios para mapear UID -> User ID
        users = svc.get_users()
        uid_to_userid = {u['uid']: str(u['user_id']).strip() for u in users if u.get('uid') is not None}
        
        templates = svc.get_templates()
        if not templates:
            return {"success": True, "downloaded": 0, "saved": 0, "message": "No se encontraron huellas en el reloj."}
        
        conn = get_db()
        saved_count = 0
        with conn.cursor() as cursor:
            for t in templates:
                uid = t['uid']
                user_id_str = uid_to_userid.get(uid, str(uid))
                
                if user_id and user_id_str != str(user_id).strip():
                    continue
                
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
            "downloaded": len(templates),
            "saved": saved_count
        }
    except Exception as e:
        return {"success": False, "error": f"Error respaldando huellas: {str(e)}"}
    finally:
        svc.disconnect()

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
    parser.add_argument('action', choices=['set-user', 'enroll', 'download-templates', 'status'], help="Acción a ejecutar")
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
    elif args.action == 'download-templates':
        result = download_templates_action(
            device_id=args.device,
            user_id=args.user_id
        )
    elif args.action == 'status':
        result = get_user_biometrics_status(user_id=args.user_id)

    print(json.dumps(result, ensure_ascii=False, indent=2))

if __name__ == '__main__':
    main()
