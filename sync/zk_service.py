import logging
import datetime
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

                records.append({
                    "uid": getattr(att, 'uid', None),
                    "user_id": str(getattr(att, 'user_id', '')).strip(),
                    "timestamp": att.timestamp,
                    "punch": punch_code,
                    "tipo": tipo_punch,
                    "status": getattr(att, 'status', 0)
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

    def sync_time(self):
        """Sincroniza la hora del biométrico con la hora del servidor"""
        if not self.conn:
            self.connect()
        try:
            now = datetime.datetime.now()
            self.conn.set_time(now)
            logger.info(f"Hora del reloj {self.ip} sincronizada a: {now}")
            return True
        except Exception as e:
            logger.error(f"Error al sincronizar hora en {self.ip}: {str(e)}")
            raise
