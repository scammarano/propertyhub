@echo off
setlocal
set "ROOT=%~dp0.."
if exist "C:\xampp\php\php.exe" (
  "C:\xampp\php\php.exe" "%ROOT%\tools\reseed-node-demo.php"
) else (
  php "%ROOT%\tools\reseed-node-demo.php"
)
pause
