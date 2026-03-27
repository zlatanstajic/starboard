#!/bin/bash
set -e

# Fix permissions for mounted volumes
chmod -R 755 /var/www/html/storage
chmod -R 755 /var/www/html/bootstrap/cache
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache

# Generate application key if not set
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi

# Cache configuration for production
if [ "$APP_ENV" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

# Run database migrations
php artisan migrate --force

exec "$@"
