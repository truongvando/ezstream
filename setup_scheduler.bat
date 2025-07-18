@echo off
echo Setting up Laravel Scheduler for EZStream...

REM Create a batch file to run Laravel scheduler
echo @echo off > run_scheduler.bat
echo cd /d "d:\laragon\www\ezstream" >> run_scheduler.bat
echo php artisan schedule:run >> run_scheduler.bat

REM Create Windows Task Scheduler entry
schtasks /create /tn "EZStream Laravel Scheduler" /tr "d:\laragon\www\ezstream\run_scheduler.bat" /sc minute /mo 1 /f

echo Laravel Scheduler has been set up to run every minute.
echo You can also manually run: php artisan schedule:run
pause
