@echo off
setlocal
cd /d "%~dp0.."
if "%~1"=="" (
  set /p INCIDENT=Numero de incidente: 
) else (
  set INCIDENT=%~1
)
php tools\find-incident.php "%INCIDENT%"
pause
endlocal
