@echo off
setlocal
set "ROOT=%~dp0.."
set "PHP_EXE=C:\xampp\php\php.exe"
if not exist "%PHP_EXE%" set "PHP_EXE=php"
"%PHP_EXE%" "%ROOT%\tools\backup-scheduled.php" >> "%ROOT%\storage\logs\backup-scheduler.log" 2>&1
endlocal
