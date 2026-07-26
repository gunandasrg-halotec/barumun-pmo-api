#!/bin/sh
set -e
cd /var/www/html

echo "=== PMO API Local Startup ==="

echo "Copying local environment..."
cp ./docker/local/.env.local .env

echo "Waiting for MySQL to be ready..."
for i in $(seq 1 30); do
    if mysqladmin ping -h pmo-db-local -u pmo_user -ppmo_local_pass --silent --ssl=0 2>/dev/null; then
        echo "MySQL is ready."
        break
    fi
    echo "  Attempt $i/30 — waiting 2s..."
    sleep 2
done

echo "Running migrations..."
php artisan migrate --force

echo "Caching Laravel config and routes..."
php artisan config:cache
php artisan route:cache

echo "Launching Nginx and PHP-FPM..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
