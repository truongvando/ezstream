Write-Host "Khoi tao Laravel project..." -ForegroundColor Green

# Tao Laravel project
docker run --rm -v "${PWD}:/app" composer:latest create-project laravel/laravel . --prefer-dist

# Copy env file
Copy-Item "env.docker.example" ".env"

Write-Host "Fix Laravel permissions..." -ForegroundColor Green

# Fix permissions
docker-compose exec app chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
docker-compose exec app chmod -R 775 /var/www/storage /var/www/bootstrap/cache
docker-compose exec app chmod -R 755 /var/www/public

# Clear all caches
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan route:clear
docker-compose exec app php artisan view:clear

# Test Laravel
Write-Host "Testing Laravel..." -ForegroundColor Yellow
docker-compose exec app php artisan about

Write-Host "Laravel is ready!" -ForegroundColor Green
Write-Host "Access: http://localhost:8000" -ForegroundColor Cyan
Write-Host "Admin: admin@livestream.vps / password123" -ForegroundColor Cyan 