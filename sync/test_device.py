#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Herramienta de diagnóstico de línea de comandos para probar comunicación con cualquier ZKTeco
Uso: python sync/test_device.py 192.168.1.201 4370
"""

import sys
from zk_service import ZKDeviceService

def main():
    if len(sys.argv) < 2:
        print("Uso: python test_device.py <IP_DISPOSITIVO> [PUERTO] [COMKEY] [UDP: 0|1]")
        print("Ejemplo: python test_device.py 192.168.1.201 4370 0 0")
        sys.exit(1)

    ip = sys.argv[1]
    port = int(sys.argv[2]) if len(sys.argv) > 2 else 4370
    password = int(sys.argv[3]) if len(sys.argv) > 3 else 0
    force_udp = (sys.argv[4] == '1') if len(sys.argv) > 4 else False

    print(f"\n=======================================================")
    print(f"Probando comunicación con Biométrico ZKTeco")
    print(f"IP: {ip} | Puerto: {port} | Clave: {password} | UDP: {force_udp}")
    print(f"=======================================================\n")

    service = ZKDeviceService(ip=ip, port=port, timeout=8, password=password, force_udp=force_udp)
    result = service.test_connection()

    if result['success']:
        print("[OK] CONEXIÓN EXITOSA!")
        print(f"  • Dispositivo:      {result.get('device_name')}")
        print(f"  • Plataforma:       {result.get('platform')}")
        print(f"  • Versión Firmware: {result.get('firmware')}")
        print(f"  • Número de Serie:  {result.get('serial_number')}")
        print(f"  • Hora en Equipo:   {result.get('device_time')}")
        print(f"  • Total Usuarios:   {result.get('user_count')}")
        print(f"  • Marcaciones:      {result.get('attendance_count')}")
    else:
        print("[ERROR] ERROR DE CONEXIÓN:")
        print(f"  {result.get('error')}")
        print("\nRecomendaciones de diagnóstico:")
        print("1. Verifica que la PC y el biométrico estén en la misma subred de red local.")
        print("2. Prueba hacer 'ping " + ip + "' desde la terminal.")
        print("3. Revisa en el menú del reloj si la Clave de Comunicación (ComKey) está en 0.")
        print("4. Verifica que el puerto 4370 no esté bloqueado por un Firewall.")

if __name__ == '__main__':
    main()
