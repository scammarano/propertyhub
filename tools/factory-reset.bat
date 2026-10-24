@echo off
setlocal
if "%~1"=="" (
  echo Uso: factory-reset.bat CODIGO_INSTALACION
  exit /b 2
)
echo ADVERTENCIA: esta accion elimina todas las tablas y habilita una nueva instalacion.
set /p ANSWER=Escriba REINICIAR para continuar: 
if /I not "%ANSWER%"=="REINICIAR" (
  echo Operacion cancelada.
  exit /b 3
)
set "PHP_EXE=C:\xampp\php\php.exe"
if not exist "%PHP_EXE%" set "PHP_EXE=php"
"%PHP_EXE%" "%~dp0reset-installation.php" --factory-reset --confirm=%~1
exit /b %ERRORLEVEL%
