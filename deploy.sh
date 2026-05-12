#!/bin/sh

# Skynet Inventory Management - Coolify/Nixpacks Deployment Script

set -e

echo "Starting Skynet Inventory deployment..."

echo "Preparing writable Laravel directories..."
mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

echo "Fixing writable directory permissions..."
if chown -R www-data:www-data storage bootstrap/cache 2>/dev/null; then
  chmod -R 775 storage bootstrap/cache
else
  chmod -R 777 storage bootstrap/cache
fi

echo "Removing stale bootstrap cache files..."
rm -f bootstrap/cache/*.php

echo "Waiting for database connection..."
attempt=1
max_attempts=60
until php artisan db:show > /dev/null 2>&1; do
  if [ "$attempt" -ge "$max_attempts" ]; then
    echo "Database connection failed after $max_attempts attempts."
    php artisan db:show
    exit 1
  fi

  echo "  Still waiting for database..."
  attempt=$((attempt + 1))
  sleep 2
done
echo "Database is ready."

echo "Running database migrations..."
php artisan migrate --force

echo "Seeding default roles and users..."
php artisan db:seed --force

echo "Creating storage symlink..."
php artisan storage:link --force || true

echo "Clearing stale caches..."
php artisan cache:clear || true
php artisan config:clear || true
php artisan view:clear || true

echo "Optimizing application cache..."
php artisan config:cache
php artisan view:cache
php artisan filament:cache-components || true

echo "Deployment tasks complete."
