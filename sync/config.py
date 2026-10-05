import os
from pathlib import Path
from dotenv import load_dotenv

# Localizar archivo .env en la raíz del proyecto o en el directorio actual
base_dir = Path(__file__).resolve().parent.parent
env_path = base_dir / '.env'
if env_path.exists():
    load_dotenv(dotenv_path=env_path)
else:
    load_dotenv()

DB_CONFIG = {
    'host': os.getenv('DB_HOST', '127.0.0.1'),
    'port': int(os.getenv('DB_PORT', 3306)),
    'user': os.getenv('DB_USER', 'root'),
    'password': os.getenv('DB_PASS', ''),
    'database': os.getenv('DB_NAME', 'control_personal'),
    'charset': 'utf8mb4',
    'autocommit': True,
    'init_command': "SET time_zone = '-05:00'"
}

ZK_CONFIG = {
    'default_timeout': int(os.getenv('ZK_DEFAULT_TIMEOUT', 30)),
    'connect_timeout': int(os.getenv('ZK_CONNECT_TIMEOUT', 6)),
    'max_retries': int(os.getenv('ZK_MAX_RETRIES', 4)),
    'retry_backoff': [2, 5, 10, 30],
    'clear_after_sync': os.getenv('ZK_CLEAR_ATTENDANCE_AFTER_SYNC', 'false').lower() in ('true', '1', 'yes'),
    'sync_users': os.getenv('ZK_SYNC_USERS', 'true').lower() in ('true', '1', 'yes'),
}

APP_TIMEZONE = os.getenv('APP_TIMEZONE', 'America/Lima')
