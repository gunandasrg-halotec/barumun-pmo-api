#!/bin/sh
set -e
cd /var/www/html
pwd

echo "Starting deployment setup..."

# 🔥 FIX: Swap or duplicate the local environment file for Laravel
if [ -f "./docker/staging/.env.dev" ]; then
    echo "Configuring environment using .env.dev..."
    cp ./docker/staging/.env.dev .env
else
    echo "ERROR: .env.dev file not found!"
    exit 1
fi

# Ensure PHP and JS dependencies are up to date
# composer install --no-interaction --prefer-dist --optimize-autoloader
# npm install

# Automate the React build process


# Cache Laravel configurations safely using the newly copied .env file
echo "Optimizing Laravel framework caches..."
 php artisan config:cache
 php artisan route:cache



# Launch Nginx and PHP-FPM processes via Supervisor
echo "Launching Nginx and PHP-FPM processes..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
