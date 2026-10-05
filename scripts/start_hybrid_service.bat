@echo off
TITLE Servicio Hibrido ZKTeco (PUSH ADMS + PULL) - JUSHSAL
COLOR 0A

echo ===================================================================
echo   INICIANDO SERVICIO HIBRIDO DE ASISTENCIA ZKTECO
echo   JUSHSAL - Control de Personal y Asistencia
echo ===================================================================
echo.

cd /d "%~dp0\.."

REM Verificar interprete de Python
set PYTHON_CMD=python
if exist "C:\Python313\python.exe" (
    set PYTHON_CMD=C:\Python313\python.exe
)

echo [*] Utilizando interprete Python: %PYTHON_CMD%
%PYTHON_CMD% --version
if errorlevel 1 (
    COLOR 0C
    echo [ERROR] No se pudo encontrar Python en el sistema.
    pause
    exit /b 1
)

echo [*] Iniciando Orchestrator Hibrido (Push Listener en puerto 8081 + Pull de Respaldo cada 5 min)...
echo.
%PYTHON_CMD% sync\hybrid_service.py --host 0.0.0.0 --port 8081 --pull-interval 300

pause
