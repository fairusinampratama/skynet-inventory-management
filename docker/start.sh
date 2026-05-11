#!/bin/sh
set -e

mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

docker/wait-for-db.sh

php artisan storage:link --force || true
php artisan migrate --force
php artisan db:seed --force
php artisan optimize:clear

php artisan serve --host=0.0.0.0 --port=8000
