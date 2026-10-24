@echo off
setlocal
cd /d "%~dp0.."
php tools\enable-diagnostics.php
if errorlevel 1 pause
endlocal
