@echo off
REM ==========================================================
REM Script para Depuración y Archivado Periódico de Auditoría
REM Ejecutable en Windows Task Scheduler (Semestral)
REM ==========================================================
cd /d "%~dp0\.."
if not exist "storage\logs" mkdir "storage\logs"

if exist "C:\xampp\php\php.exe" (
    "C:\xampp\php\php.exe" "cron\purge_audit_logs.php" --months=6 >> "storage\logs\audit_purge.log" 2>&1
) else (
    php "cron\purge_audit_logs.php" --months=6 >> "storage\logs\audit_purge.log" 2>&1
)
