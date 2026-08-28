@echo off
REM ==========================================================
REM Script de Ejecución Automática para Windows Task Scheduler
REM ==========================================================
cd /d "%~dp0\.."
if not exist "logs" mkdir "logs"
python "sync\sync_zkteco.py" >> "logs\sync_output.log" 2>&1
