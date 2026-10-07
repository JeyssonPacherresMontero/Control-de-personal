#!/bin/bash
# ==========================================================
# Configuración de Crontab en Linux (Ubuntu/Debian/CentOS)
# ==========================================================

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PYTHON_EXEC="$(which python3)"
PHP_EXEC="$(which php)"

if [ -z "$PYTHON_EXEC" ]; then
    echo "Python 3 no fue detectado en el sistema."
    exit 1
fi

if [ -z "$PHP_EXEC" ]; then
    echo "PHP CLI no fue detectado en el sistema."
    exit 1
fi

CRON_CMD="*/10 * * * * cd $DIR && $PYTHON_EXEC sync/sync_zkteco.py >> logs/sync_output.log 2>&1"
CRON_CMD2="1 0 * * * cd $DIR && $PHP_EXEC app/Console/process_attendance.php >> logs/attendance_calc.log 2>&1"
CRON_CMD3="0 3 1 1,7 * cd $DIR && $PHP_EXEC cron/purge_audit_logs.php --months=6 >> storage/logs/audit_purge.log 2>&1"

# Añadir al crontab actual si no existe
(crontab -l 2>/dev/null | grep -F "sync_zkteco.py") || (crontab -l 2>/dev/null; echo "$CRON_CMD") | crontab -
(crontab -l 2>/dev/null | grep -F "process_attendance.php") || (crontab -l 2>/dev/null; echo "$CRON_CMD2") | crontab -
(crontab -l 2>/dev/null | grep -F "purge_audit_logs.php") || (crontab -l 2>/dev/null; echo "$CRON_CMD3") | crontab -

echo "[OK] Crontab configurado con éxito:"
echo " - Sincronización biométrica cada 10 min: $CRON_CMD"
echo " - Procesamiento diario de asistencia:   $CRON_CMD2"
echo " - Depuración y archivado semestral:     $CRON_CMD3"
