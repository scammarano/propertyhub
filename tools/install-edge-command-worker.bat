@echo off
setlocal EnableExtensions
set "ROOT=%~dp0.."
set "TASKNAME=PropertyHub Edge Command Worker"
net session >nul 2>&1
if errorlevel 1 (
  echo EJECUTE ESTE ARCHIVO COMO ADMINISTRADOR.
  pause
  exit /b 1
)
if not exist "%ROOT%\tools\edge-command-worker-runner.cmd" (
  echo No se encontro el runner del worker.
  pause
  exit /b 1
)
if not exist "%ROOT%\storage\logs" mkdir "%ROOT%\storage\logs" >nul 2>&1
if exist "%ROOT%\storage\run\edge-command-worker-supervisor.stop" del /q "%ROOT%\storage\run\edge-command-worker-supervisor.stop" >nul 2>&1
schtasks /Delete /TN "%TASKNAME%" /F >nul 2>&1
schtasks /Create /TN "%TASKNAME%" /SC ONSTART /DELAY 0000:45 /RU SYSTEM /RL HIGHEST /TR "cmd.exe /d /c \"%ROOT%\tools\edge-command-worker-runner.cmd\"" /F
if errorlevel 1 (
  echo No se pudo crear la tarea del supervisor Edge.
  pause
  exit /b 1
)
schtasks /Run /TN "%TASKNAME%"
if errorlevel 1 (
  echo La tarea se creo, pero Windows no pudo iniciarla ahora. Revise el Programador de tareas.
  pause
  exit /b 1
)
echo.
echo Supervisor Edge instalado y solicitado (ONSTART + 45 s): %TASKNAME%
echo El runner permanece activo y reintenta el worker cada 15 s si termina.
echo Log de bootstrap: storage\logs\edge-command-worker-bootstrap.log
echo Apache/MariaDB deben estar configurados aparte como servicios de Windows.
echo MediaMTX y Quick Tunnels son recuperados por el supervisor Edge.
pause
