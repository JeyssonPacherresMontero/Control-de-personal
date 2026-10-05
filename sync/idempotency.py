#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
===================================================================
Módulo de Idempotencia y Deduplicación para Marcaciones ZKTeco
Garantiza unicidad matemática ante eventos repetidos (PUSH o PULL)
===================================================================
"""

import hashlib
import datetime
from typing import Union

def normalize_user_id(user_id: Union[str, int]) -> str:
    """Normaliza el ID de usuario eliminando ceros a la izquierda y espacios"""
    raw = str(user_id or '').strip()
    return raw.lstrip('0') or '0'

def normalize_timestamp(ts: Union[datetime.datetime, str]) -> str:
    """Normaliza marcas de tiempo a formato estándar YYYY-MM-DD HH:MM:SS"""
    if isinstance(ts, (datetime.datetime, datetime.date)):
        return ts.strftime('%Y-%m-%d %H:%M:%S')
    raw = str(ts or '').strip()
    # Si viene con 'T', reemplazar por espacio
    if 'T' in raw:
        raw = raw.replace('T', ' ')
    # Si tiene milisegundos o zona horaria, truncar a segundos
    if len(raw) > 19:
        raw = raw[:19]
    return raw

def compute_idempotency_key(
    device_serial: str,
    user_id: Union[str, int],
    timestamp: Union[datetime.datetime, str],
    punch_type: Union[str, int] = 'entrada',
    verify_type: str = 'huella'
) -> str:
    """
    Genera un hash SHA-256 determinista a partir de los atributos intrínsecos del evento:
    idempotency_key = SHA256(device_serial + user_id + timestamp + punch + verify_type)

    Garantiza que sin importar si el registro llega por PUSH en tiempo real
    o por PULL posterior durante la recuperación, ambos tendrán EXACTAMENTE
    la misma clave y la base de datos responderá 'already_processed'.
    """
    norm_serial = str(device_serial or 'UNKNOWN_SERIAL').strip().upper()
    norm_user = normalize_user_id(user_id)
    norm_time = normalize_timestamp(timestamp)
    norm_punch = str(punch_type or 'entrada').strip().lower()
    norm_verify = str(verify_type or 'huella').strip().lower()

    canonical_payload = f"{norm_serial}|{norm_user}|{norm_time}|{norm_punch}|{norm_verify}"
    return hashlib.sha256(canonical_payload.encode('utf-8')).hexdigest()
