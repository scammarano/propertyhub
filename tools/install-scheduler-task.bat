@echo off
setlocal EnableExtensions
set "ROOT=%~dp0.."
for %%I in ("%ROOT%") do set "ROOT=%%~fI"
set "TASKNAME=2CAM Property Hub Scheduler"
for /f "tokens=1,* delims==" %%A in ('findstr /B /C:"INSTANCE_CODE=" "%ROOT%\.env" 2^>nul') do set "CODE=%%~B"
set "CODE=%CODE:\"=%"
if not "%CODE%"=="" set "TASKNAME=%TASKNAME% - %CODE%"
echo Instalando tarea: %TASKNAME%
schtasks /Create /TN "%TASKNAME%" /SC MINUTE /MO 5 /TR "\"%ROOT%\tools\backup-scheduled-runner.cmd\"" /F
if errorlevel 1 (
  echo No se pudo crear la tarea. Ejecute este BAT como Administrador.
  pause
  exit /b 1
)
if not exist "%ROOT%\storage\backups" mkdir "%ROOT%\storage\backups"
> "%ROOT%\storage\backups\scheduler-installed.json" echo {"installed_at":"%DATE% %TIME%","task_name":"%TASKNAME%","interval_minutes":5,"runner":"property_hub_scheduler"}
echo.
echo Tarea instalada correctamente.
echo Cada 5 minutos Property Hub ejecutara heartbeat, ordenes remotas y verificara la politica de respaldo.
echo En Edge Agent tambien enviara heartbeat al Property Node.
pause
endlocal
