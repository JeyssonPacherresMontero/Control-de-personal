@echo off
REM ==========================================================
REM Script de Ejecución Automática para Windows Task Scheduler
REM ==========================================================
cd /d "%~dp0\.."
if not exist "logs" mkdir "logs"
set PYTHONHOME=
set PYTHONPATH=
if exist "C:\Python313\python.exe" (
    "C:\Python313\python.exe" -E "sync\sync_zkteco.py" >> "logs\sync_output.log" 2>&1
) else (
    python -E "sync\sync_zkteco.py" >> "logs\sync_output.log" 2>&1
)
