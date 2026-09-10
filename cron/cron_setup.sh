#!/bin/bash
# ==========================================================
# Configuración de Crontab en Linux (Ubuntu/Debian/CentOS)
# ==========================================================

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PYTHON_EXEC="$(which python3)"

if [ -z "$PYTHON_EXEC" ]; then
    echo "Python 3 no fue detectado en el sistema."
    exit 1
fi

CRON_CMD="*/10 * * * * cd $DIR && $PYTHON_EXEC sync/sync_zkteco.py >> logs/sync_output.log 2>&1"
CRON_CMD2="1 0 * * * cd $DIR && php app/Console/process_attendance.php >> logs/attendance_calc.log 2>&1"

# Añadir al crontab actual si no existe
(crontab -l 2>/dev/null | grep -F "sync_zkteco.py") || (crontab -l 2>/dev/null; echo "$CRON_CMD") | crontab -
(crontab -l 2>/dev/null | grep -F "process_attendance.php") || (crontab -l 2>/dev/null; echo "$CRON_CMD2") | crontab -

echo "[OK] Crontab configurado para sincronizar cada 10 minutos y procesar asistencia diariamente."
echo "Comandos registrados:"
echo " - $CRON_CMD"
echo " - $CRON_CMD2"
