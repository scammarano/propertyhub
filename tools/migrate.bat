@echo off
setlocal
set ROOT=%~dp0..
if exist "C:\xampp\php\php.exe" (
  "C:\xampp\php\php.exe" "%ROOT%\tools\migrate.php"
) else (
  php "%ROOT%\tools\migrate.php"
)
pause
