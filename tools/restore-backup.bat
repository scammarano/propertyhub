@echo off
setlocal
set "ROOT=%~dp0.."
set "PHP_EXE=C:\xampp\php\php.exe"
if not exist "%PHP_EXE%" set "PHP_EXE=php"
if "%~1"=="" (
  echo Uso: restore-backup.bat "C:\ruta\archivo.phbackup" CODIGO_ACTUAL
  exit /b 2
)
set "FILE=%~1"
set "CONFIRM=%~2"
if "%CONFIRM%"=="" set /p CONFIRM=Codigo actual de la instalacion: 
set /p KEY=Clave de recuperacion: 
set /p MODE=Modo [same_installation/safe_clone] ^(default same_installation^): 
if "%MODE%"=="" set "MODE=same_installation"
"%PHP_EXE%" "%ROOT%\tools\restore-backup.php" --file="%FILE%" --key="%KEY%" --mode="%MODE%" --confirm="%CONFIRM%"
pause
endlocal
