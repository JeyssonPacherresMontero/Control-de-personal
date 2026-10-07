@echo off
REM ==========================================================
REM Registra las tareas programadas en Windows Task Scheduler
REM Ejecutar como Administrador en Windows
REM ==========================================================

echo Registrando Tareas Programadas de JUSHSAL...
echo.

set SCRIPT_SYNC=%~dp0run_sync_silent.vbs
set SCRIPT_PURGE=%~dp0run_audit_purge.bat

echo 1. Registrando Sincronizacion Biometrica cada 10 min (ZKTeco_Attendance_Sync)...
schtasks /create /tn "ZKTeco_Attendance_Sync" /tr "wscript.exe \"%SCRIPT_SYNC%\"" /sc minute /mo 10 /f

if %ERRORLEVEL% EQU 0 (
    echo [OK] Tarea de sincronizacion registrada exitosamente.
) else (
    echo [ERROR] No se pudo registrar la tarea de sincronizacion.
)

echo.
echo 2. Registrando Mantenimiento y Depuracion Semestral (ZKTeco_Audit_Purge)...
schtasks /create /tn "ZKTeco_Audit_Purge" /tr "\"%SCRIPT_PURGE%\"" /sc monthly /d 1 /st 03:00 /f

if %ERRORLEVEL% EQU 0 (
    echo [OK] Tarea de mantenimiento semestral registrada exitosamente (Dia 1 de cada mes a las 03:00 AM).
) else (
    echo [ERROR] No se pudo registrar la tarea de mantenimiento.
)

echo.
echo ==========================================================
echo Configuración completada.
echo ==========================================================
pause
