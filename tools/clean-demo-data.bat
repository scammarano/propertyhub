@echo off
setlocal
if "%~1"=="" (
  echo Uso: clean-demo-data.bat CODIGO_INSTALACION
  exit /b 2
)
set "ROOT=%~dp0.."
if exist "C:\xampp\php\php.exe" (
  "C:\xampp\php\php.exe" "%ROOT%\tools\clean-demo-data.php" --confirm=%~1
) else (
  php "%ROOT%\tools\clean-demo-data.php" --confirm=%~1
)
endlocal
