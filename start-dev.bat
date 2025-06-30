@echo off
echo Starting Laravel Queue Worker for Development...
echo.
echo Make sure Laragon is running!
echo Web: http://localhost/VPSLiveSeverControl/public
echo.

cd /d "D:\laragon\www\VPSLiveSeverControl"

echo Starting Queue Worker...
php artisan queue:work --daemon --tries=3 --timeout=60

pause 