#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
===================================================================
SISTEMA DE CONTROL DE PERSONAL - SERVICIO HÍBRIDO (PUSH + PULL)
Orquestador unificado de alta disponibilidad
- Hilo 1: Push Listener ZKTeco ADMS (Recepción en tiempo real)
- Hilo 2: Motor de Sincronización PULL (Respaldo, drenado y recuperación)
===================================================================
"""

import sys
import time
import threading
import logging
import argparse
from pathlib import Path

# Cargar dependencias locales
sys.path.insert(0, str(Path(__file__).resolve().parent))
from push_listener import run_push_server, PUSH_PORT
from sync_zkteco import AttendanceSynchronizer

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] [HybridService] %(message)s',
    datefmt='%Y-%m-%d %H:%M:%S'
)
logger = logging.getLogger("HybridService")

def start_push_listener_thread(host='0.0.0.0', port=PUSH_PORT):
    """Ejecuta el servidor Push Listener en un hilo independiente (Daemon)"""
    logger.info(f"Iniciando subproceso Push Listener en {host}:{port}...")
    run_push_server(host=host, port=port)

def run_hybrid_orchestrator(interval_minutes=10, port=PUSH_PORT, host='0.0.0.0'):
    """Orquestador híbrido que combina Push Listener continuo con sondeo PULL periódico"""
    logger.info("==========================================================")
    logger.info("INICIANDO SERVICIO HÍBRIDO ZKTECO (PUSH + PULL)")
    logger.info(f"• Puerto Push Listener: {port}")
    logger.info(f"• Intervalo PULL de Respaldo: cada {interval_minutes} minutos")
    logger.info("==========================================================")

    # 1. Lanzar Push Listener en segundo plano
    push_thread = threading.Thread(
        target=start_push_listener_thread,
        kwargs={'host': host, 'port': port},
        name="ZKPushListenerThread",
        daemon=True
    )
    push_thread.start()

    time.sleep(1) # Permitir que el socket HTTP se enlace limpiamente

    # 2. Bucle principal de Sincronización PULL de Respaldo
    sync_engine = AttendanceSynchronizer()
    try:
        while True:
            logger.info("--> [CICLO PULL] Ejecutando sincronización de respaldo y drenado de cola...")
            try:
                sync_engine.sync_all_devices(today_only=True, sync_users=False)
            except Exception as e:
                logger.error(f"Aviso en ciclo PULL: {str(e)}")

            logger.info(f"--> [CICLO PULL] Completado. Esperando {interval_minutes} minutos para el próximo ciclo de respaldo...")
            time.sleep(interval_minutes * 60)

    except KeyboardInterrupt:
        logger.info("Servicio híbrido detenido por el operador (Ctrl+C).")
    finally:
        sync_engine.close_db()
        logger.info("Recursos liberados correctamente.")

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description="Servicio Híbrido ZKTeco PUSH + PULL")
    parser.add_argument('--interval', type=int, default=10, help='Intervalo en minutos para el ciclo PULL de respaldo (def: 10)')
    parser.add_argument('--port', type=int, default=PUSH_PORT, help=f'Puerto de escucha Push Listener (def: {PUSH_PORT})')
    parser.add_argument('--host', type=str, default='0.0.0.0', help='Host de escucha Push Listener (def: 0.0.0.0)')
    args = parser.parse_args()

    run_hybrid_orchestrator(interval_minutes=args.interval, port=args.port, host=args.host)
