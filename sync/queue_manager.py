#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
===================================================================
Gestor de Cola Lógica de Eventos de Asistencia (Queue Manager)
Maneja el ciclo de vida: PENDING -> PROCESSING -> PROCESSED | FAILED | RETRY
===================================================================
"""

import logging
import datetime
from typing import Dict, Any, List, Optional
import pymysql

logger = logging.getLogger("QueueManager")

class QueueManager:
    def __init__(self, db_conn):
        self.conn = db_conn

    def enqueue_event(
        self,
        device_id: int,
        device_serial: str,
        user_id: str,
        timestamp: str,
        punch_type: str = 'entrada',
        verify_type: str = 'huella',
        uid_dispositivo: Optional[int] = None,
        origen: str = 'PULL',
        idempotency_key: str = ''
    ) -> Dict[str, Any]:
        """
        Encola un evento de marcación en cola_eventos_asistencia.
        Si la clave de idempotencia ya existe, respeta el estado actual.
        """
        with self.conn.cursor() as cursor:
            # Verificar si ya existe por idempotency_key
            cursor.execute(
                "SELECT id, estado FROM cola_eventos_asistencia WHERE idempotency_key = %s",
                (idempotency_key,)
            )
            existing = cursor.fetchone()
            if existing:
                return {
                    "success": True,
                    "event_id": existing['id'] if isinstance(existing, dict) else existing[0],
                    "status": "already_processed" if (existing['estado'] if isinstance(existing, dict) else existing[1]) == 'PROCESSED' else "already_queued",
                    "idempotency_key": idempotency_key
                }

            sql = """
                INSERT INTO cola_eventos_asistencia
                (id_dispositivo, device_serial, user_id, timestamp, punch_type, verify_type, uid_dispositivo, origen, idempotency_key, estado, intentos)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, 'PENDING', 0)
            """
            cursor.execute(sql, (
                device_id,
                device_serial,
                user_id,
                timestamp,
                punch_type,
                verify_type,
                uid_dispositivo,
                origen,
                idempotency_key
            ))
            event_id = cursor.lastrowid
            self.conn.commit()
            return {
                "success": True,
                "event_id": event_id,
                "status": "queued",
                "idempotency_key": idempotency_key
            }

    def process_pending_events(self, employee_mapping: Dict[str, int], batch_size: int = 500, max_retries: int = 5) -> Dict[str, Any]:
        """
        Drena y procesa los eventos en estado PENDING o RETRY.
        Transiciona a PROCESSING -> PROCESSED o FAILED / RETRY.
        """
        affected_dates = set()
        inserted_count = 0
        failed_count = 0
        retried_count = 0

        with self.conn.cursor() as cursor:
            cursor.execute("""
                SELECT id, id_dispositivo, user_id, timestamp, punch_type, verify_type, uid_dispositivo, origen, idempotency_key, intentos
                FROM cola_eventos_asistencia
                WHERE estado IN ('PENDING', 'RETRY') AND intentos < %s
                ORDER BY id ASC
                LIMIT %s
            """, (max_retries, batch_size))
            rows = cursor.fetchall()

            if not rows:
                return {
                    "total": 0,
                    "inserted": 0,
                    "failed": 0,
                    "retried": 0,
                    "affected_dates": []
                }

            event_ids = [r['id'] if isinstance(r, dict) else r[0] for r in rows]
            format_ids = ','.join(['%s'] * len(event_ids))
            
            # Pasar a PROCESSING
            cursor.execute(f"UPDATE cola_eventos_asistencia SET estado = 'PROCESSING' WHERE id IN ({format_ids})", tuple(event_ids))

            insert_query = """
                INSERT IGNORE INTO marcaciones 
                (id_empleado, codigo_reloj, id_dispositivo, fecha_hora, tipo, tipo_verificacion, uid_dispositivo, procesado, idempotency_key, origen)
                VALUES (%s, %s, %s, %s, %s, %s, %s, 0, %s, %s)
            """

            for r in rows:
                item = r if isinstance(r, dict) else {
                    'id': r[0], 'id_dispositivo': r[1], 'user_id': r[2], 'timestamp': r[3],
                    'punch_type': r[4], 'verify_type': r[5], 'uid_dispositivo': r[6],
                    'origen': r[7], 'idempotency_key': r[8], 'intentos': r[9]
                }

                eid = item['id']
                uid_str = str(item['user_id']).strip()
                emp_id = employee_mapping.get(uid_str) or employee_mapping.get(uid_str.lstrip('0') or '0')
                dt_val = item['timestamp']
                dt_str = dt_val.strftime('%Y-%m-%d %H:%M:%S') if isinstance(dt_val, (datetime.datetime, datetime.date)) else str(dt_val)

                try:
                    cursor.execute(insert_query, (
                        emp_id,
                        uid_str,
                        item['id_dispositivo'],
                        dt_str,
                        item['punch_type'],
                        item['verify_type'],
                        item['uid_dispositivo'],
                        item['idempotency_key'],
                        item['origen']
                    ))
                    
                    cursor.execute("UPDATE cola_eventos_asistencia SET estado = 'PROCESSED', ultimo_error = NULL WHERE id = %s", (eid,))
                    inserted_count += 1
                    affected_dates.add(dt_str[:10])

                except Exception as e:
                    new_intentos = item['intentos'] + 1
                    new_estado = 'RETRY' if new_intentos < max_retries else 'FAILED'
                    err_msg = str(e)[:250]
                    cursor.execute("""
                        UPDATE cola_eventos_asistencia 
                        SET estado = %s, intentos = %s, ultimo_error = %s 
                        WHERE id = %s
                    """, (new_estado, new_intentos, err_msg, eid))

                    if new_estado == 'RETRY':
                        retried_count += 1
                    else:
                        failed_count += 1

            self.conn.commit()

        return {
            "total": len(rows),
            "inserted": inserted_count,
            "failed": failed_count,
            "retried": retried_count,
            "affected_dates": list(affected_dates)
        }
