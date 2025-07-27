@echo off
REM EZStream Queue Workers Startup Script for Windows
REM This script starts queue workers in background

echo Starting EZStream Queue Workers...

REM Change to project directory
cd /d "D:\laragon\www\ezstream"

REM Start VPS Provisioning Queue Worker
echo Starting VPS Provisioning Worker...
start "EZStream-VPS-Queue" /MIN php artisan queue:work --queue=vps-provisioning --timeout=600 --memory=512 --tries=3 --sleep=3

REM Start Default Queue Worker  
echo Starting Default Queue Worker...
start "EZStream-Default-Queue" /MIN php artisan queue:work --timeout=300 --memory=256 --tries=3 --sleep=3

REM Start Stream Management Queue Worker
echo Starting Stream Queue Worker...
start "EZStream-Stream-Queue" /MIN php artisan queue:work --queue=streams --timeout=300 --memory=256 --tries=3 --sleep=3

echo.
echo ✅ All queue workers started in background!
echo.
echo To stop workers, close the respective command windows or use:
echo   taskkill /FI "WINDOWTITLE:EZStream-*-Queue"
echo.
echo To monitor workers, check the running command windows.
echo.
pause
