@echo off
setlocal
cd /d "%~dp0.."
php tools\show-latest-log.php
pause
endlocal
