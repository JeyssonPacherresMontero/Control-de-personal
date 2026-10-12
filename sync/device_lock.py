#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
===================================================================
SISTEMA DE CONTROL DE PERSONAL Y ASISTENCIA - ZKTECO
Módulo de Bloqueo Exclusivo de Dispositivo (Device Mutex Lock)
===================================================================
Previene colisiones de sockets en el puerto 4370 de ZKTeco.
Los dispositivos ZKTeco sólo soportan UNA conexión TCP activa
a la vez. Este mutex coordina el acceso entre:
- Tareas programadas de sincronización (sync_zkteco.py)
- Capturas interactivas de huellas (biometric_admin.py)
- Descarga de plantillas o consultas ad-hoc
===================================================================
"""

import os
import time
import json
import logging
from pathlib import Path

logger = logging.getLogger("DeviceLock")

LOCK_DIR = Path(__file__).resolve().parent.parent / 'storage' / 'locks'

class DeviceLock:
    """
    Mutex de dispositivo basado en archivos con soporte para timeout,
    reintentos y recuperación automática de locks obsoletos (stale locks).
    """

    def __init__(self, device_id: int, purpose: str = "operation", timeout_stale: int = 90):
        self.device_id = int(device_id)
        self.purpose = purpose
        self.timeout_stale = int(timeout_stale)
        try:
            LOCK_DIR.mkdir(parents=True, exist_ok=True)
        except Exception:
            pass
        self.lock_file = LOCK_DIR / f"device_{self.device_id}.lock"
        self.acquired = False

    def acquire(self, wait_timeout: float = 0.0, retry_interval: float = 0.5) -> bool:
        """
        Intenta adquirir el lock para el dispositivo.
        Si wait_timeout > 0, reintenta periódicamente hasta el límite de tiempo.
        Retorna True si adquirió el lock con éxito, False en caso contrario.
        """
        start = time.time()
        while True:
            if self._try_acquire():
                self.acquired = True
                return True

            elapsed = time.time() - start
            if elapsed >= wait_timeout:
                return False

            time.sleep(retry_interval)

    def _try_acquire(self) -> bool:
        """Intento atómico de creación de lock"""
        if self.lock_file.exists():
            try:
                stat = self.lock_file.stat()
                age = time.time() - stat.st_mtime
                if age > self.timeout_stale:
                    logger.warning(
                        f"Lock para dispositivo #{self.device_id} expirado "
                        f"(edad: {round(age, 1)}s > límite: {self.timeout_stale}s). "
                        f"Liberando lock obsoleto..."
                    )
                    self.lock_file.unlink(missing_ok=True)
                else:
                    return False
            except Exception:
                pass

        try:
            payload = {
                "device_id": self.device_id,
                "purpose": self.purpose,
                "pid": os.getpid(),
                "time": time.time(),
                "created_at": time.strftime("%Y-%m-%d %H:%M:%S")
            }
            # En Windows/POSIX, os.O_CREAT | os.O_EXCL garantiza atomicidad
            fd = os.open(str(self.lock_file), os.O_CREAT | os.O_EXCL | os.O_WRONLY)
            with os.fdopen(fd, 'w', encoding='utf-8') as f:
                json.dump(payload, f)
            return True
        except FileExistsError:
            return False
        except Exception as e:
            logger.warning(f"Aviso al intentar crear lock #{self.device_id}: {str(e)}")
            return False

    def release(self):
        """Libera el lock si pertenece a este proceso o si está obsoleto"""
        if not self.acquired and not self.lock_file.exists():
            return

        try:
            if self.lock_file.exists():
                try:
                    with open(self.lock_file, 'r', encoding='utf-8') as f:
                        data = json.load(f)
                    lock_pid = data.get('pid')
                    if lock_pid == os.getpid() or (time.time() - self.lock_file.stat().st_mtime > self.timeout_stale):
                        self.lock_file.unlink(missing_ok=True)
                except Exception:
                    self.lock_file.unlink(missing_ok=True)
        except Exception as e:
            logger.warning(f"Error liberando lock para dispositivo #{self.device_id}: {str(e)}")
        finally:
            self.acquired = False

    def __enter__(self):
        if not self.acquire(wait_timeout=6.0):
            raise TimeoutError(f"El dispositivo biométrico #{self.device_id} está ocupado por otra operación.")
        return self

    def __exit__(self, exc_type, exc_val, exc_tb):
        self.release()
