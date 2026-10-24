@echo off
setlocal
if "%~1"=="" (
  echo Uso: reset-demo.bat CODIGO_INSTALACION
  exit /b 2
)
set "PHP_EXE=C:\xampp\php\php.exe"
if not exist "%PHP_EXE%" set "PHP_EXE=php"
"%PHP_EXE%" "%~dp0reset-installation.php" --demo-only --confirm=%~1
exit /b %ERRORLEVEL%
