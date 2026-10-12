import os
import time
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
        self.last_latency_ms = None

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

    def connect(self, timeout: int = None):
        """Establece la conexión con el biométrico midiendo latencia"""
        conn_timeout = int(timeout) if timeout is not None else self.timeout
        t_start = time.time()
        try:
            self.zk = ZK(
                self.ip,
                port=self.port,
                timeout=conn_timeout,
                password=self.password,
                force_udp=self.force_udp,
                ommit_ping=True
            )
            self.conn = self.zk.connect()
            self.last_latency_ms = round((time.time() - t_start) * 1000, 2)
            logger.info(f"Conexión exitosa con dispositivo {self.ip}:{self.port} (Latencia: {self.last_latency_ms}ms)")
            return True
        except Exception as e:
            self.last_latency_ms = round((time.time() - t_start) * 1000, 2)
            logger.error(f"Error conectando con {self.ip}:{self.port}: {str(e)}")
            self.conn = None
            raise

    def connect_with_retry(self, max_retries: int = 4, backoff_delays: list = None):
        """
        Establece conexión aplicando estrategia de backoff exponencial:
        1er intento: 2s
        2do intento: 5s
        3er intento: 10s
        4to intento: 30s
        Evita bloqueos indefinidos y realiza intentos controlados.
        """
        if backoff_delays is None:
            backoff_delays = [2, 5, 10, 30]

        last_err = None
        for attempt in range(1, max_retries + 1):
            try:
                logger.info(f"Intentando conectar a {self.ip}:{self.port} (Intento {attempt}/{max_retries})...")
                return self.connect()
            except Exception as e:
                last_err = e
                if attempt < max_retries:
                    delay_idx = min(attempt - 1, len(backoff_delays) - 1)
                    delay = backoff_delays[delay_idx]
                    logger.warning(
                        f"DEVICE: {self.ip}:{self.port} | ACTION: CONNECT | "
                        f"ERROR: {str(e)} | RETRY: Reintentando en {delay}s (Intento {attempt}/{max_retries})"
                    )
                    time.sleep(delay)
                else:
                    logger.error(
                        f"DEVICE: {self.ip}:{self.port} | ACTION: CONNECT | "
                        f"ERROR: Fallaron todos los {max_retries} intentos de conexión: {str(e)}"
                    )

        raise last_err

    def disconnect(self):
        """Cierra la conexión de forma segura"""
        if self.conn:
            try:
                self.conn.cancel_capture()
            except Exception:
                pass
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
            uid_val = int(uid) if uid else (int(user_id) if str(user_id).isdigit() else 1)
            if not (1 <= uid_val <= 65535):
                raise ValueError(f"El UID {uid_val} está fuera del rango permitido por ZKTeco (1 a 65535).")
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

    def enroll_user(self, uid: int, temp_id: int = 0, user_id: str = ''):
        """
        Ejecuta el ciclo de enrolamiento de huella en el reloj (3 capturas en el sensor).
        Retorna True únicamente si pyzk confirmó las 3 lecturas con éxito (res == 0).
        Retorna False si ocurrió timeout (60s), dedo duplicado o cancelación.
        """
        if not self.conn:
            self.connect()
        try:
            uid_val = int(uid)
            temp_val = int(temp_id)
            user_id_str = str(user_id).strip() if user_id else ''

            logger.info(f"Iniciando ciclo de enrolamiento en {self.ip} (UID: {uid_val}, Dedo: {temp_val})...")
            enroll_result = self.conn.enroll_user(uid=uid_val, temp_id=temp_val, user_id=user_id_str)

            success = bool(enroll_result)
            if success:
                logger.info(f"Enrolamiento completado exitosamente en {self.ip} para UID {uid_val}, dedo {temp_val}")
            else:
                logger.warning(f"Enrolamiento NO completado en {self.ip} para UID {uid_val}, dedo {temp_val} (posible timeout, dedo duplicado o cancelado)")

            return success
        except Exception as e:
            logger.error(f"Excepción durante enrolamiento en {self.ip}: {str(e)}")
            raise
        finally:
            try:
                self.conn.cancel_capture()
            except Exception:
                pass
            try:
                self.conn.enable_device()
            except Exception:
                pass
    def get_user_template(self, uid: int, temp_id: int = 0):
        """Descarga la plantilla de un dedo específico de un usuario del reloj"""
        if not self.conn:
            self.connect()
        try:
            self.conn.disable_device()
            if hasattr(self.conn, 'get_user_template'):
                finger = self.conn.get_user_template(uid=int(uid), temp_id=int(temp_id))
                if finger:
                    raw_tmpl = getattr(finger, 'template', b'')
                    return {
                        "uid": getattr(finger, 'uid', uid),
                        "fid": getattr(finger, 'fid', temp_id),
                        "size": getattr(finger, 'size', len(raw_tmpl)),
                        "valid": getattr(finger, 'valid', 1),
                        "template": raw_tmpl
                    }
            return None
        except Exception as e:
            logger.warning(f"Aviso al obtener plantilla individual de {self.ip} (UID {uid}, Dedo {temp_id}): {str(e)}")
            return None
        finally:
            try:
                self.conn.enable_device()
            except Exception:
                pass

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
            naive_now = datetime.datetime.now()
            if ZoneInfo:
                try:
                    now = datetime.datetime.now(ZoneInfo(tz_name))
                    naive_now = now.replace(tzinfo=None)
                except Exception:
                    naive_now = datetime.datetime.now()
            
            self.conn.set_time(naive_now)
            logger.info(f"Hora del reloj {self.ip} sincronizada a: {naive_now} ({tz_name})")
            return True
        except Exception as e:
            logger.warning(f"Aviso al sincronizar hora en {self.ip}: {str(e)}")
            return False

