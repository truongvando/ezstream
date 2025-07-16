@echo off
echo Starting Laravel Queue Worker...
cd /d "D:\laragon\www\ezstream"
php artisan queue:work --verbose --tries=3 --timeout=300 --sleep=3 --max-jobs=1000 --max-time=3600
pause
