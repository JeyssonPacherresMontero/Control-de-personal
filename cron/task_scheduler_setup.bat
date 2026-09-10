@echo off
REM ==========================================================
REM Registra la tarea programada en Windows para correr cada 10 min
REM Ejecutar como Administrador en Windows
REM ==========================================================

echo Registrando Tarea Programada en Windows: ZKTeco_Attendance_Sync...

set SCRIPT_PATH=%~dp0run_sync_silent.vbs

schtasks /create /tn "ZKTeco_Attendance_Sync" /tr "wscript.exe \"%SCRIPT_PATH%\"" /sc minute /mo 10 /f

if %ERRORLEVEL% EQU 0 (
    echo [OK] Tarea programada registrada exitosamente para ejecutarse silenciosamente cada 10 minutos.
) else (
    echo [ERROR] No se pudo registrar la tarea. Asegurate de ejecutar este script como Administrador.
)
pause
