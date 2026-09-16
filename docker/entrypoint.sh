#!/bin/sh
set -e

# Ensure required storage and bootstrap cache directories exist
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

# If no .env file exists in the container, copy from .env.example
if [ ! -f /var/www/html/.env ] && [ -f /var/www/html/.env.example ]; then
    echo "Creating .env from .env.example..."
    cp /var/www/html/.env.example /var/www/html/.env
fi

# Fix permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Default environment settings if not explicitly injected
export APP_ENV="${APP_ENV:-production}"
export DB_CONNECTION="${DB_CONNECTION:-mysql}"

# Extract DB_HOST from DB_URL if DB_HOST is not set
if [ -z "$DB_HOST" ] && [ -n "$DB_URL" ]; then
    DB_HOST=$(echo "$DB_URL" | sed -e 's/.*@//' -e 's/:.*//' -e 's/\/.*//')
fi

# Create storage symlink if not already created
php artisan storage:link --force || true

# Wait for database if DB_HOST is configured and not localhost
if [ -n "$DB_HOST" ] && [ "$DB_HOST" != "127.0.0.1" ] && [ "$DB_HOST" != "localhost" ]; then
    echo "Waiting for database connection at $DB_HOST:${DB_PORT:-3306}..."
    max_tries=30
    count=0
    while ! nc -z "$DB_HOST" "${DB_PORT:-3306}" 2>/dev/null; do
        count=$((count + 1))
        if [ $count -ge $max_tries ]; then
            echo "Warning: Database not ready after $max_tries attempts. Continuing anyway..."
            break
        fi
        sleep 2
    done
    echo "Database reachable!"
fi

# Run migrations if RUN_MIGRATIONS is set to true
if [ "$RUN_MIGRATIONS" = "true" ] || [ "$RUN_MIGRATIONS" = "1" ]; then
    echo "Running database migrations..."
    php artisan migrate --force || echo "Migration encountered an issue, check logs."
fi

# Run seeders if RUN_SEEDER is set to true
if [ "$RUN_SEEDER" = "true" ] || [ "$RUN_SEEDER" = "1" ]; then
    echo "Running database seeders..."
    php artisan db:seed --force || echo "Database seeding encountered an issue, check logs."
fi

# In production, cache config, routes, and views for high performance
if [ "$APP_ENV" = "production" ]; then
    echo "Caching configuration, routes, and views..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
else
    echo "Running in $APP_ENV environment."
    php artisan config:clear || true
fi

echo "Starting application services..."
exec "$@"
