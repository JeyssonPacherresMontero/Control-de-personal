import os
import logging
import datetime
try:
    from zoneinfo import ZoneInfo
except ImportError:
    ZoneInfo = None
from zk import ZK, const

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] [%(name)s] %(message)s'
)
logger = logging.getLogger("ZKService")

class ZKDeviceService:
    """
    Servicio de comunicación robusto con dispositivos biométricos ZKTeco
    utilizando el protocolo ZK sobre TCP/UDP (librería pyzk).
    """

    def __init__(self, ip: str, port: int = 4370, timeout: int = 8, password: int = 0, force_udp: bool = False):
        self.ip = ip
        self.port = int(port)
        self.timeout = int(timeout)
        self.password = int(password)
        self.force_udp = force_udp
        self.zk = None
        self.conn = None

    @staticmethod
    def parse_verification_type(status_code):
        """
        Interpreta el modo de verificación del biométrico ZKTeco:
        1: Huella Dactilar
        2: Clave / PIN
        3: Tarjeta RFID
        4, 15, 20, 25: Reconocimiento Facial (Face ID)
        """
        try:
            st = int(status_code)
        except (ValueError, TypeError):
            return "huella"

        if st in (4, 15, 20, 25):
            return "facial"
        elif st in (1, 5, 9):
            return "huella"
        elif st in (3, 6, 7):
            return "tarjeta"
        elif st in (0, 2, 8):
            return "clave"
        return "huella"

    def connect(self):
        """Establece la conexión con el biométrico"""
        try:
            self.zk = ZK(
                self.ip,
                port=self.port,
                timeout=self.timeout,
                password=self.password,
                force_udp=self.force_udp,
                ommit_ping=True
            )
            self.conn = self.zk.connect()
            logger.info(f"Conexión exitosa con dispositivo {self.ip}:{self.port}")
            return True
        except Exception as e:
            logger.error(f"Error conectando con {self.ip}:{self.port}: {str(e)}")
            self.conn = None
            raise

    def disconnect(self):
        """Cierra la conexión de forma segura"""
        if self.conn:
            try:
                self.conn.enable_device()
            except Exception:
                pass
            try:
                self.conn.disconnect()
                logger.info(f"Desconectado de {self.ip}:{self.port}")
            except Exception as e:
                logger.warning(f"Error al desconectar de {self.ip}: {str(e)}")
            finally:
                self.conn = None

    def test_connection(self):
        """
        Prueba la conexión y recupera metadatos del dispositivo
        (Firmware, Serie, Usuarios registrados, Marcaciones almacenadas)
        """
        try:
            self.connect()
            
            firmware = "Desconocido"
            serial_number = "Desconocido"
            platform = "Desconocido"
            device_name = "ZKTeco Device"
            user_count = 0
            attendance_count = 0

            try:
                self.conn.read_sizes()
                user_count = getattr(self.conn, 'users', 0)
                attendance_count = getattr(self.conn, 'records', 0)
            except Exception:
                pass
            
            try:
                firmware = self.conn.get_firmware_version()
            except Exception:
                pass
                
            try:
                serial_number = self.conn.get_serialnumber()
            except Exception:
                pass
                
            try:
                platform = self.conn.get_platform()
            except Exception:
                pass
                
            try:
                device_name = self.conn.get_device_name()
            except Exception:
                pass

            device_time = "N/A"
            try:
                device_time = str(self.conn.get_time())
            except Exception:
                pass

            return {
                "success": True,
                "ip": self.ip,
                "port": self.port,
                "firmware": firmware,
                "serial_number": serial_number,
                "platform": platform,
                "device_name": device_name,
                "user_count": user_count,
                "attendance_count": attendance_count,
                "device_time": device_time
            }
        except Exception as e:
            return {
                "success": False,
                "ip": self.ip,
                "port": self.port,
                "error": str(e)
            }
        finally:
            self.disconnect()

    def get_attendance_records(self):
        """
        Descarga las marcaciones del biométrico.
        Deshabilita temporalmente el reloj para evitar colisiones de memoria.
        """
        if not self.conn:
            self.connect()

        records = []
        try:
            # Desactivar temporalmente el reloj mientras leemos
            self.conn.disable_device()
            
            raw_attendances = self.conn.get_attendance()
            logger.info(f"Se descargaron {len(raw_attendances)} registros brutos de {self.ip}")

            for att in raw_attendances:
                # att attributes: uid, user_id, timestamp, status, punch
                # punch mapping típico: 0=Entrada, 1=Salida, 2=Salida Break, 3=Entrada Break, 4=Entrada Extra, 5=Salida Extra
                tipo_punch = "desconocido"
                punch_code = getattr(att, 'punch', 0)
                if punch_code == 0:
                    tipo_punch = "entrada"
                elif punch_code == 1:
                    tipo_punch = "salida"
                elif punch_code == 2:
                    tipo_punch = "refrigerio_salida"
                elif punch_code == 3:
                    tipo_punch = "refrigerio_entrada"

                raw_status = getattr(att, 'status', 1)
                tipo_verif = self.parse_verification_type(raw_status)

                records.append({
                    "uid": getattr(att, 'uid', None),
                    "user_id": str(getattr(att, 'user_id', '')).strip(),
                    "timestamp": att.timestamp,
                    "punch": punch_code,
                    "tipo": tipo_punch,
                    "status": raw_status,
                    "tipo_verificacion": tipo_verif
                })

            return records
        except Exception as e:
            logger.error(f"Error al leer marcaciones de {self.ip}: {str(e)}")
            raise
        finally:
            try:
                self.conn.enable_device()
            except Exception:
                pass

    def get_users(self):
        """Descarga la lista de usuarios registrados en el reloj"""
        if not self.conn:
            self.connect()

        try:
            self.conn.disable_device()
            users = self.conn.get_users()
            user_list = []
            for u in users:
                user_list.append({
                    "uid": getattr(u, 'uid', None),
                    "user_id": str(getattr(u, 'user_id', '')).strip(),
                    "name": getattr(u, 'name', '').strip(),
                    "privilege": getattr(u, 'privilege', 0),
                    "password": getattr(u, 'password', ''),
                    "group_id": getattr(u, 'group_id', 0),
                    "card": getattr(u, 'card', 0)
                })
            return user_list
        except Exception as e:
            logger.error(f"Error al obtener usuarios de {self.ip}: {str(e)}")
            raise
        finally:
            try:
                self.conn.enable_device()
            except Exception:
                pass

    def clear_attendance(self):
        """Limpia los registros de asistencia en el reloj para liberar memoria"""
        if not self.conn:
            self.connect()
        try:
            self.conn.disable_device()
            self.conn.clear_attendance()
            logger.warning(f"Memoria de asistencia borrada en {self.ip}")
            return True
        except Exception as e:
            logger.error(f"Error al limpiar asistencia en {self.ip}: {str(e)}")
            raise
        finally:
            try:
                self.conn.enable_device()
            except Exception:
                pass

    def set_user(self, uid: int, name: str, privilege: int = 0, password: str = '', group_id: int = 0, user_id: str = '', card: int = 0):
        """Crea o actualiza un usuario directamente en la memoria del reloj biométrico"""
        if not self.conn:
            self.connect()
        try:
            self.conn.disable_device()
            uid_val = int(uid) if uid else int(user_id) if user_id.isdigit() else 1
            user_id_str = str(user_id).strip()
            name_str = str(name)[:24].strip() # ZKTeco name limit typical 24 chars
            
            res = self.conn.set_user(
                uid=uid_val,
                name=name_str,
                privilege=int(privilege),
                password=str(password),
                group_id=str(group_id),
                user_id=user_id_str,
                card=int(card)
            )
            logger.info(f"Usuario {user_id_str} ({name_str}) guardado en reloj {self.ip}")
            return True
        except Exception as e:
            logger.error(f"Error al guardar usuario en {self.ip}: {str(e)}")
            raise
        finally:
            try:
                self.conn.enable_device()
            except Exception:
                pass

    def delete_user(self, uid: int = None, user_id: str = None):
        """Elimina un usuario del reloj biométrico"""
        if not self.conn:
            self.connect()
        try:
            self.conn.disable_device()
            self.conn.delete_user(uid=uid, user_id=user_id)
            logger.info(f"Usuario uid={uid}/user_id={user_id} eliminado de {self.ip}")
            return True
        except Exception as e:
            logger.error(f"Error al eliminar usuario de {self.ip}: {str(e)}")
            raise
        finally:
            try:
                self.conn.enable_device()
            except Exception:
                pass

    def enroll_user(self, uid: int, temp_id: int = 0):
        """
        Activa el modo de enrolamiento en el reloj biométrico.
        El reloj solicitará al usuario colocar su dedo o ubicarse frente a la cámara.
        temp_id: 0 a 9 (dedo a enrolar)
        """
        if not self.conn:
            self.connect()
        try:
            # Enrolar en el reloj
            res = self.conn.enroll_user(uid=int(uid), temp_id=int(temp_id))
            logger.info(f"Enrolamiento iniciado en {self.ip} para UID {uid}, dedo/tipo {temp_id}")
            return True
        except Exception as e:
            logger.error(f"Error al iniciar enrolamiento en {self.ip}: {str(e)}")
            raise

    def get_templates(self):
        """Descarga todas las plantillas biométricas (huellas) registradas en el reloj"""
        if not self.conn:
            self.connect()
        try:
            self.conn.disable_device()
            templates = self.conn.get_templates()
            template_list = []
            for t in templates:
                template_list.append({
                    "uid": getattr(t, 'uid', None),
                    "fid": getattr(t, 'fid', 0), # Finger ID (0-9)
                    "size": getattr(t, 'size', 0),
                    "valid": getattr(t, 'valid', 1),
                    "template": getattr(t, 'template', b'')
                })
            return template_list
        except Exception as e:
            logger.error(f"Error al obtener plantillas biométricas de {self.ip}: {str(e)}")
            raise
        finally:
            try:
                self.conn.enable_device()
            except Exception:
                pass

    def sync_time(self):
        """Sincroniza la hora del biométrico con la hora del servidor"""
        if not self.conn:
            self.connect()
        try:
            tz_name = os.getenv('APP_TIMEZONE', 'America/Lima')
            if ZoneInfo:
                now = datetime.datetime.now(ZoneInfo(tz_name))
                # pyzk espera un datetime naive en hora local del dispositivo
                naive_now = now.replace(tzinfo=None)
            else:
                naive_now = datetime.datetime.now()
            
            self.conn.set_time(naive_now)
            logger.info(f"Hora del reloj {self.ip} sincronizada a: {naive_now} ({tz_name})")
            return True
        except Exception as e:
            logger.error(f"Error al sincronizar hora en {self.ip}: {str(e)}")
            raise

