#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Test Suite con ZK y MySQL Simulados (Mock)
Verifica los flujos biométricos:
1. Usuario nuevo OK (flujo atómico enroll-full)
2. Cálculo de UID (reutilización, no numérico, DNI > 65535, desbordamiento)
3. Enroll con timeout (pyzk devuelve False)
4. Enroll con dedo duplicado (pyzk devuelve False)
5. Descarga con 0 plantillas (falso éxito corregido)
"""

import sys
import os
import unittest
from unittest.mock import MagicMock, patch

# Asegurar importación desde sync
sys.path.insert(0, os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'sync'))

import biometric_admin
from zk_service import ZKDeviceService


class TestBiometricMock(unittest.TestCase):

    def setUp(self):
        self.mock_device = {
            'id': 1,
            'nombre': 'Reloj Principal Mock',
            'ip': '192.168.1.201',
            'puerto': 4370,
            'clave_comunicacion': 0,
            'protocolo': 'TCP'
        }

    def test_01_uid_allocation_and_reuse(self):
        """Prueba cálculo de UID: reutilización, códigos no numéricos y números > 65535"""
        existing_users = [
            {'uid': 1, 'user_id': '1', 'name': 'Admin'},
            {'uid': 2, 'user_id': '102', 'name': 'Empleado 102'},
            {'uid': 15, 'user_id': 'EMP-05', 'name': 'Empleado Alfanumerico'}
        ]

        # 1. Reutilización: si user_id '102' ya existe, debe devolver su UID 2
        uid_reuse = biometric_admin.resolve_or_allocate_uid(existing_users, '102')
        self.assertEqual(uid_reuse, 2, "Debe reutilizar el UID existente del usuario")

        # 2. Código no numérico nuevo 'EMP-99': debe asignar max(uid) + 1 = 16 (NO sobreescribir UID 1)
        uid_alnum = biometric_admin.resolve_or_allocate_uid(existing_users, 'EMP-99')
        self.assertEqual(uid_alnum, 16, "Debe asignar el siguiente UID libre y no pisar UID 1")

        # 3. Código numérico en rango libre '103': debe asignar 103
        uid_num = biometric_admin.resolve_or_allocate_uid(existing_users, '103')
        self.assertEqual(uid_num, 103, "Debe usar el ID numérico si está en rango 1-65535 y no está tomado")

        # 4. Código numérico > 65535 (ej. DNI 72345678): debe asignar max(uid)+1 = 16 para no romper ZK
        uid_big = biometric_admin.resolve_or_allocate_uid(existing_users, '72345678')
        self.assertEqual(uid_big, 16, "Debe asignar un UID dentro del rango 1-65535 cuando el código excede 65535")

    def test_02_enroll_full_usuario_nuevo_ok(self):
        """Prueba enroll-full exitoso para usuario nuevo (set_user -> get_users -> enroll_user -> get_user_template -> MySQL)"""
        mock_svc = MagicMock()
        mock_svc.connect.return_value = True
        mock_svc.get_users.return_value = [{'uid': 1, 'user_id': '1', 'name': 'Admin'}]
        mock_svc.set_user.return_value = True
        # En la verificación, simular que ahora el usuario ya existe en el reloj
        mock_svc.get_users.side_effect = [
            [{'uid': 1, 'user_id': '1'}],  # Primera llamada en resolve_or_allocate_uid
            [{'uid': 1, 'user_id': '1'}, {'uid': 2, 'user_id': '200', 'name': 'Juan Perez'}]  # Verificación
        ]
        mock_svc.enroll_user.return_value = True
        mock_svc.get_user_template.return_value = {
            'uid': 2,
            'fid': 2,
            'size': 600,
            'valid': 1,
            'template': b'\xaa\xbb\xcc\xdd' * 50
        }

        mock_db = MagicMock()
        mock_cursor = MagicMock()
        mock_db.cursor.return_value.__enter__.return_value = mock_cursor

        with patch('biometric_admin.get_device', return_value=self.mock_device), \
             patch('biometric_admin.get_device_service', return_value=mock_svc), \
             patch('biometric_admin.get_db', return_value=mock_db):

            res = biometric_admin.enroll_full_action(
                device_id=1,
                user_id='200',
                name='Juan Perez',
                temp_id=2
            )

            self.assertTrue(res['success'], f"El enrolamiento completo debe ser exitoso: {res}")
            self.assertEqual(res['saved'], 1)
            mock_svc.set_user.assert_called_once()
            mock_svc.enroll_user.assert_called_once_with(uid=200, temp_id=2, user_id='200')
            mock_cursor.execute.assert_called_once()
            mock_svc.disconnect.assert_called_once()

    def test_03_enroll_timeout(self):
        """Prueba de enroll con timeout: pyzk devuelve False, debe responder success: False con motivo claro"""
        mock_svc = MagicMock()
        mock_svc.connect.return_value = True
        mock_svc.get_users.return_value = [{'uid': 5, 'user_id': '105', 'name': 'Carlos'}]
        mock_svc.enroll_user.return_value = False  # Simula timeout de 60s en pyzk

        with patch('biometric_admin.get_device', return_value=self.mock_device), \
             patch('biometric_admin.get_device_service', return_value=mock_svc):

            res = biometric_admin.enroll_user_action(device_id=1, user_id='105', temp_id=2)

            self.assertFalse(res['success'], "Debe fallar cuando enroll_user devuelve False")
            self.assertIn("tiempo agotado", res['error'].lower(), "El mensaje debe indicar tiempo agotado o dedo duplicado")
            mock_svc.disconnect.assert_called_once()

    def test_04_enroll_dedo_duplicado(self):
        """Prueba de enroll_full cuando el reloj rechaza la huella (dedo duplicado): enroll_user devuelve False"""
        mock_svc = MagicMock()
        mock_svc.connect.return_value = True
        mock_svc.get_users.return_value = [{'uid': 10, 'user_id': '110', 'name': 'Ana'}]
        mock_svc.set_user.return_value = True
        mock_svc.enroll_user.return_value = False  # pyzk retorna False al detectar dedo duplicado (res == 5)

        with patch('biometric_admin.get_device', return_value=self.mock_device), \
             patch('biometric_admin.get_device_service', return_value=mock_svc):

            res = biometric_admin.enroll_full_action(
                device_id=1,
                user_id='110',
                name='Ana',
                temp_id=1
            )

            self.assertFalse(res['success'], "Debe reportar success: False si pyzk rechaza la huella")
            self.assertIn("no completó el enrolamiento", res['error'].lower())

    def test_05_download_templates_cero_huellas_falso_exito(self):
        """Prueba corrección de falso éxito: cuando se busca usuario y no hay huellas, debe devolver success: False"""
        mock_svc = MagicMock()
        mock_svc.connect.return_value = True
        mock_svc.get_users.return_value = [{'uid': 7, 'user_id': '107', 'name': 'Mario'}]
        mock_svc.get_user_template.return_value = None
        mock_svc.get_templates.return_value = []  # Cero huellas

        with patch('biometric_admin.get_device', return_value=self.mock_device), \
             patch('biometric_admin.get_device_service', return_value=mock_svc):

            # Caso A: Con user_id específico y 0 plantillas guardadas -> DEBE ser success: False
            res_user = biometric_admin.download_templates_action(device_id=1, user_id='107', temp_id=2)
            self.assertFalse(res_user['success'], "Debe retornar success: False cuando se pide un usuario y hay 0 plantillas")
            self.assertEqual(res_user['saved'], 0)
            self.assertIn("error", res_user)

            # Caso B: Respaldo masivo sin user_id en reloj vacío -> success: True, saved: 0 (informativo)
            res_all = biometric_admin.download_templates_action(device_id=1, user_id=None)
            self.assertTrue(res_all['success'], "Respaldo global de reloj vacío debe ser True con saved: 0")
            self.assertEqual(res_all['saved'], 0)

    def test_06_zk_service_cleanup_in_finally(self):
        """Verifica que ZKDeviceService ejecute cancel_capture() y enable_device() en finally"""
        svc = ZKDeviceService(ip='127.0.0.1')
        mock_conn = MagicMock()
        mock_conn.enroll_user.side_effect = Exception("Fallo de socket simulado")
        svc.conn = mock_conn

        with self.assertRaises(Exception):
            svc.enroll_user(uid=1, temp_id=0)

        # cancel_capture y enable_device debieron ejecutarse a pesar de la excepción
        mock_conn.cancel_capture.assert_called_once()
        mock_conn.enable_device.assert_called_once()

    def test_07_multiple_fingers_enrollment(self):
        """Verifica que se puedan enrolar múltiples dedos deseados (ej. dedo 0, 1, 6) para el mismo usuario sin colisiones"""
        mock_svc = MagicMock()
        mock_svc.connect.return_value = True
        mock_svc.set_user.return_value = True
        mock_svc.enroll_user.return_value = True

        mock_db = MagicMock()
        mock_cursor = MagicMock()
        mock_db.cursor.return_value.__enter__.return_value = mock_cursor

        with patch('biometric_admin.get_device', return_value=self.mock_device), \
             patch('biometric_admin.get_device_service', return_value=mock_svc), \
             patch('biometric_admin.get_db', return_value=mock_db):

            # Enrolar Dedo 0 (Pulgar D)
            mock_svc.get_users.side_effect = [
                [{'uid': 5, 'user_id': '50', 'name': 'Lucia'}],
                [{'uid': 5, 'user_id': '50', 'name': 'Lucia'}]
            ]
            mock_svc.get_user_template.return_value = {'uid': 5, 'fid': 0, 'size': 512, 'template': b'\x11' * 512}
            res0 = biometric_admin.enroll_full_action(device_id=1, user_id='50', name='Lucia', temp_id=0)
            self.assertTrue(res0['success'])
            self.assertEqual(res0['temp_id'], 0)

            # Enrolar Dedo 1 (Índice D)
            mock_svc.get_users.side_effect = [
                [{'uid': 5, 'user_id': '50', 'name': 'Lucia'}],
                [{'uid': 5, 'user_id': '50', 'name': 'Lucia'}]
            ]
            mock_svc.get_user_template.return_value = {'uid': 5, 'fid': 1, 'size': 512, 'template': b'\x22' * 512}
            res1 = biometric_admin.enroll_full_action(device_id=1, user_id='50', name='Lucia', temp_id=1)
            self.assertTrue(res1['success'])
            self.assertEqual(res1['temp_id'], 1)

            # Enrolar Dedo 6 (Índice I)
            mock_svc.get_users.side_effect = [
                [{'uid': 5, 'user_id': '50', 'name': 'Lucia'}],
                [{'uid': 5, 'user_id': '50', 'name': 'Lucia'}]
            ]
            mock_svc.get_user_template.return_value = {'uid': 5, 'fid': 6, 'size': 512, 'template': b'\x33' * 512}
            res6 = biometric_admin.enroll_full_action(device_id=1, user_id='50', name='Lucia', temp_id=6)
            self.assertTrue(res6['success'])
            self.assertEqual(res6['temp_id'], 6)

            # Verificar que MySQL execute fue llamado 3 veces (1 por cada dedo) con sus respectivos parámetros
            self.assertEqual(mock_cursor.execute.call_count, 3)

    def test_08_device_lock_mutual_exclusion(self):
        """Verifica que DeviceLock garantice exclusión mutua y recuperación de locks obsoletos"""
        from device_lock import DeviceLock
        
        lock1 = DeviceLock(device_id=99, purpose="test_enroll")
        lock2 = DeviceLock(device_id=99, purpose="test_sync")

        # Asegurar estado limpio
        lock1.release()
        lock2.release()

        try:
            # 1. lock1 adquiere el recurso
            self.assertTrue(lock1.acquire(wait_timeout=0.1))

            # 2. lock2 debe ser rechazado inmediatamente (wait_timeout=0.0)
            self.assertFalse(lock2.acquire(wait_timeout=0.0))

            # 3. lock1 libera el recurso
            lock1.release()

            # 4. lock2 ahora puede adquirir el recurso
            self.assertTrue(lock2.acquire(wait_timeout=0.5))
            lock2.release()

            # 5. Prueba de recuperación de lock obsoleto (stale)
            lock_stale = DeviceLock(device_id=99, purpose="stale_test", timeout_stale=1)
            self.assertTrue(lock_stale.acquire(wait_timeout=0.1))
            # Simular envejecimiento del archivo de lock modificando st_mtime
            import time
            old_time = time.time() - 10
            os.utime(str(lock_stale.lock_file), (old_time, old_time))

            # Otro lock debe detectar que está obsoleto y recuperarlo
            lock_new = DeviceLock(device_id=99, purpose="new_after_stale", timeout_stale=1)
            self.assertTrue(lock_new.acquire(wait_timeout=0.2))
            lock_new.release()

        finally:
            lock1.release()
            lock2.release()


if __name__ == '__main__':
    unittest.main()
