@echo off
setlocal EnableExtensions EnableDelayedExpansion
cd /d "%~dp0.."
set "ROOT=%CD%"
set "PHP_EXE=C:\xampp\php\php.exe"
set "LOG_DIR=%ROOT%\storage\logs"
set "LOG_FILE=%LOG_DIR%\edge-command-worker-bootstrap.log"
set "STOP_FILE=%ROOT%\storage\run\edge-command-worker-supervisor.stop"
if not exist "%LOG_DIR%" mkdir "%LOG_DIR%" >nul 2>&1

if exist "%PHP_EXE%" goto supervisor
for /f "delims=" %%P in ('where php.exe 2^>nul') do (
  set "PHP_EXE=%%P"
  goto supervisor
)
>>"%LOG_FILE%" echo [%date% %time%] ERROR No se encontro php.exe. Instale/configure PHP o XAMPP.
exit /b 2

:supervisor
if exist "%STOP_FILE%" del /q "%STOP_FILE%" >nul 2>&1
>>"%LOG_FILE%" echo [%date% %time%] Supervisor Edge iniciado. PHP=!PHP_EXE! ROOT=!ROOT!

:loop
if exist "%STOP_FILE%" goto stopped
>>"%LOG_FILE%" echo [%date% %time%] Iniciando edge-command-worker.php
"%PHP_EXE%" "%~dp0edge-command-worker.php" >>"%LOG_FILE%" 2>&1
set "RC=!ERRORLEVEL!"
>>"%LOG_FILE%" echo [%date% %time%] Worker termino con codigo !RC!. Reintento en 15 segundos.
for /l %%S in (1,1,15) do (
  if exist "%STOP_FILE%" goto stopped
  timeout /t 1 /nobreak >nul
)
goto loop

:stopped
>>"%LOG_FILE%" echo [%date% %time%] Supervisor Edge detenido por archivo stop.
exit /b 0
