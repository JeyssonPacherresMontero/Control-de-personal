#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
===================================================================
SISTEMA DE CONTROL DE PERSONAL - PUSH LISTENER (ZKTeco ADMS / Cloud)
Servidor de ingesta en tiempo real compatible con protocolo ZKTeco ADMS
Recibe eventos HTTP directamente del reloj biométrico y reenvía al Backend
===================================================================
"""

import os
import sys
import json
import time
import urllib.parse
import urllib.request
import logging
import argparse
from http.server import HTTPServer, BaseHTTPRequestHandler
from socketserver import ThreadingMixIn
from pathlib import Path
from typing import Dict, Any, Optional

# Cargar configuraciones del proyecto
sys.path.insert(0, str(Path(__file__).resolve().parent))
from config import DB_CONFIG, ZK_CONFIG
from idempotency import compute_idempotency_key
from queue_manager import QueueManager
import pymysql
from pymysql.cursors import DictCursor

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] [PushListener] %(message)s',
    datefmt='%Y-%m-%d %H:%M:%S'
)
logger = logging.getLogger("PushListener")

API_SECRET_KEY = os.getenv('API_SECRET_KEY', 'zk_push_secret_key_8e94a1b89df50c3a218f4a')
BACKEND_API_URL = os.getenv('BACKEND_API_URL', 'http://localhost:8080/control_personal/public/index.php')
PUSH_PORT = int(os.getenv('PUSH_LISTENER_PORT', 8081))

class ThreadedHTTPServer(ThreadingMixIn, HTTPServer):
    """Maneja cada solicitud HTTP en un hilo independiente para alta concurrencia"""
    daemon_threads = True

class ZKPushHandler(BaseHTTPRequestHandler):
    server_version = "ZKTeco-ADMS-Server/2.0"

    def log_message(self, format, *args):
        # Suprimir logs verbosos de http.server y usar logger estructurado
        pass

    def get_db_connection(self):
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

    def get_registered_devices(self) -> Dict[str, Any]:
        """Obtiene el mapa de dispositivos autorizados {serial_number: device_dict}"""
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute("SELECT id, nombre, ip, numero_serie, modo, activo FROM dispositivos WHERE activo = 1")
                rows = cursor.fetchall()
                devices = {}
                for r in rows:
                    if r.get('numero_serie'):
                        devices[r['numero_serie'].strip().upper()] = r
                return devices
        except Exception as e:
            logger.error(f"Error al consultar dispositivos en base de datos: {str(e)}")
            return {}
        finally:
            if conn:
                try:
                    conn.close()
                except Exception:
                    pass

    def forward_to_backend_api(self, payload: Dict[str, Any]) -> Dict[str, Any]:
        """
        Reenvía el evento normalizado al Backend PHP mediante HTTP/HTTPS con X-API-KEY.
        Si el backend no está disponible, realiza fallback directo a MySQL encolando en cola_eventos_asistencia.
        """
        api_target = BACKEND_API_URL
        if '?' not in api_target:
            api_target += '?route=api_attendance_sync'
        elif 'route=' not in api_target:
            api_target += '&route=api_attendance_sync'

        json_data = json.dumps(payload, ensure_ascii=False).encode('utf-8')
        headers = {
            'Content-Type': 'application/json; charset=utf-8',
            'X-API-KEY': API_SECRET_KEY,
            'User-Agent': 'ZKTeco-Push-Listener/2.0'
        }

        req = urllib.request.Request(api_target, data=json_data, headers=headers, method='POST')

        try:
            with urllib.request.urlopen(req, timeout=10) as response:
                resp_text = response.read().decode('utf-8')
                data = json.loads(resp_text)
                return data
        except Exception as http_err:
            logger.warning(f"Backend HTTP temporalmente inaccesible ({str(http_err)}). Activando fallback de resiliencia a BD local...")
            # Fallback de Resiliencia: Guardar directamente en cola_eventos_asistencia
            conn = None
            try:
                conn = self.get_db_connection()
                qm = QueueManager(conn)
                fallback_res = qm.enqueue_event(
                    device_id=payload.get('device_id', 1),
                    device_serial=payload.get('device_serial', ''),
                    user_id=payload.get('user_id', ''),
                    timestamp=payload.get('timestamp', ''),
                    punch_type=payload.get('punch_type', 'entrada'),
                    verify_type=payload.get('verify_type', 'huella'),
                    uid_dispositivo=payload.get('uid_dispositivo'),
                    origen='PUSH',
                    idempotency_key=payload.get('idempotency_key', '')
                )
                return {
                    "success": True,
                    "status": "queued_offline_fallback",
                    "idempotency_key": payload.get('idempotency_key'),
                    "message": "Guardado en cola lógica de contingencia."
                }
            except Exception as db_err:
                logger.error(f"Fallo crítico: ni Backend ni MySQL respondieron: {str(db_err)}")
                return {"success": False, "status": "failed", "error": str(db_err)}
            finally:
                if conn:
                    try:
                        conn.close()
                    except Exception:
                        pass

    # ==========================================================
    # MANEJADORES HTTP GET
    # ==========================================================
    def do_GET(self):
        parsed_url = urllib.parse.urlparse(self.path)
        path = parsed_url.path
        query = urllib.parse.parse_qs(parsed_url.query)

        serial = query.get('SN', [''])[0].strip().upper()

        # 1. Healthcheck / Ping básico
        if path == '/health' or path == '/ping':
            self.send_response(200)
            self.send_header('Content-Type', 'application/json')
            self.end_headers()
            self.wfile.write(b'{"status":"healthy","service":"PushListener","protocol":"ADMS"}')
            return

        # 2. Handshake ADMS: GET /iclock/cdata?SN=XXXXX&options=all
        if path.startswith('/iclock/cdata'):
            devices = self.get_registered_devices()
            if serial and serial not in devices:
                logger.warning(f"Intento de conexión ADMS no autorizado desde serie desconocido: '{serial}' (IP: {self.client_address[0]})")
                self.send_response(403)
                self.send_header('Content-Type', 'text/plain')
                self.end_headers()
                self.wfile.write(b'UNAUTHORIZED DEVICE SERIAL')
                return

            logger.info(f"Handshake ADMS exitoso con terminal serie: {serial} (IP: {self.client_address[0]})")

            # Respuesta oficial requerida por el firmware ZKTeco ADMS
            config_resp = (
                f"GET OPTION FROM: {serial}\n"
                f"Stamp=9999\n"
                f"OpStamp=9999\n"
                f"ErrorDelay=60\n"
                f"Delay=10\n"
                f"TransTimes=00:00;14:05\n"
                f"TransInterval=1\n"
                f"TransFlag=TransData AttLog\n"
                f"TimeZone=-5\n"
                f"Realtime=1\n"
                f"Encrypt=0\n"
            )
            self.send_response(200)
            self.send_header('Content-Type', 'text/plain; charset=utf-8')
            self.send_header('Content-Length', str(len(config_resp.encode('utf-8'))))
            self.end_headers()
            self.wfile.write(config_resp.encode('utf-8'))
            return

        # 3. Heartbeat: GET /iclock/getrequest?SN=XXXXX
        if path.startswith('/iclock/getrequest'):
            self.send_response(200)
            self.send_header('Content-Type', 'text/plain')
            self.end_headers()
            self.wfile.write(b'OK')
            return

        # Ruta desconocida
        self.send_response(404)
        self.end_headers()
        self.wfile.write(b'Not Found')

    # ==========================================================
    # MANEJADORES HTTP POST
    # ==========================================================
    def do_POST(self):
        parsed_url = urllib.parse.urlparse(self.path)
        path = parsed_url.path
        query = urllib.parse.parse_qs(parsed_url.query)

        content_length = int(self.headers.get('Content-Length', 0))
        body = self.rfile.read(content_length).decode('utf-8', errors='replace')

        # 1. Endpoint para pruebas y simulaciones JSON: POST /api/push/test
        if path == '/api/push/test':
            try:
                data = json.loads(body)
                res = self.forward_to_backend_api(data)
                self.send_response(200)
                self.send_header('Content-Type', 'application/json')
                self.end_headers()
                self.wfile.write(json.dumps(res).encode('utf-8'))
            except Exception as e:
                self.send_response(400)
                self.send_header('Content-Type', 'application/json')
                self.end_headers()
                self.wfile.write(json.dumps({"success": False, "error": str(e)}).encode('utf-8'))
            return

        # 2. Protocolo Nativo ZKTeco: POST /iclock/cdata?SN=XXXXX&table=ATTLOG
        if path.startswith('/iclock/cdata'):
            serial = query.get('SN', [''])[0].strip().upper()
            table = query.get('table', ['ATTLOG'])[0].strip().upper()

            devices = self.get_registered_devices()
            device_info = devices.get(serial)

            if not device_info:
                logger.warning(f"Rechazado evento POST de serie no registrado: {serial}")
                self.send_response(403)
                self.end_headers()
                self.wfile.write(b'UNAUTHORIZED DEVICE')
                return

            device_id = device_info['id']
            lines = [l.strip() for l in body.splitlines() if l.strip()]
            success_count = 0

            for line in lines:
                # Formato tabulado estándar de ATTLOG:
                # USERID \t CHECKTIME \t STATUS (punch) \t VERIFYTYPE
                parts = line.split('\t')
                if len(parts) < 2:
                    parts = line.split(',')  # Fallback si viene separado por comas
                if len(parts) < 2:
                    continue

                user_id = parts[0].strip()
                checktime = parts[1].strip()
                punch_code = int(parts[2].strip()) if len(parts) > 2 and parts[2].strip().isdigit() else 0
                verify_code = int(parts[3].strip()) if len(parts) > 3 and parts[3].strip().isdigit() else 1

                # Mapear punch y tipo de verificación
                punch_map = {0: 'entrada', 1: 'salida', 2: 'refrigerio_salida', 3: 'refrigerio_entrada'}
                punch_type = punch_map.get(punch_code, 'entrada')
                
                verify_type = 'huella'
                if verify_code in (4, 15, 20, 25):
                    verify_type = 'facial'
                elif verify_code in (3, 6, 7):
                    verify_type = 'tarjeta'
                elif verify_code in (0, 2, 8):
                    verify_type = 'clave'

                # Cálculo determinista de clave de idempotencia
                idempotency_key = compute_idempotency_key(
                    device_serial=serial,
                    user_id=user_id,
                    timestamp=checktime,
                    punch_type=punch_type,
                    verify_type=verify_type
                )

                payload = {
                    "device_id": device_id,
                    "device_serial": serial,
                    "user_id": user_id,
                    "timestamp": checktime,
                    "punch_type": punch_type,
                    "verify_type": verify_type,
                    "source": "PUSH",
                    "idempotency_key": idempotency_key
                }

                logger.info(f"==> Evento PUSH recibido: Serie={serial} | Usuario={user_id} | Hora={checktime} | Tipo={punch_type} ({verify_type})")
                api_res = self.forward_to_backend_api(payload)
                
                if api_res.get('success', False):
                    success_count += 1
                    status_lbl = api_res.get('status', 'processed')
                    logger.info(f"[OK] Evento PUSH confirmado por Backend ({status_lbl}): {idempotency_key[:16]}...")
                else:
                    logger.error(f"[ERROR] Backend rechazó el evento: {api_res.get('error')}")

            # Respuesta requerida por el reloj ZKTeco para acusar recibo: 'OK: N'
            ack_response = f"OK: {success_count if success_count > 0 else len(lines)}\n"
            self.send_response(200)
            self.send_header('Content-Type', 'text/plain')
            self.send_header('Content-Length', str(len(ack_response)))
            self.end_headers()
            self.wfile.write(ack_response.encode('utf-8'))
            return

        self.send_response(404)
        self.end_headers()
        self.wfile.write(b'Not Found')

def run_push_server(host='0.0.0.0', port=PUSH_PORT):
    server_address = (host, port)
    httpd = ThreadedHTTPServer(server_address, ZKPushHandler)
    logger.info("==========================================================")
    logger.info(f"Servidor Push Listener ZKTeco ADMS iniciado en {host}:{port}")
    logger.info(f"Reenvío a Backend API: {BACKEND_API_URL}?route=api_attendance_sync")
    logger.info("Esperando eventos de marcación en tiempo real...")
    logger.info("==========================================================")
    try:
        httpd.serve_forever()
    except KeyboardInterrupt:
        logger.info("Deteniendo Push Listener...")
        httpd.server_close()
        logger.info("Servidor detenido correctamente.")

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description="Push Listener ZKTeco ADMS Server")
    parser.add_argument('--port', type=int, default=PUSH_PORT, help=f'Puerto de escucha (def: {PUSH_PORT})')
    parser.add_argument('--host', type=str, default='0.0.0.0', help='Host de enlace (def: 0.0.0.0)')
    args = parser.parse_args()

    run_push_server(host=args.host, port=args.port)
