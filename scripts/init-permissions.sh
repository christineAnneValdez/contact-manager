#!/bin/sh
set -e

cd /app

# Ensure writable runtime paths exist.
mkdir -p storage/app/public/basset
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p bootstrap/cache

# Grant ownership/permissions only where Laravel needs write access.
chown -R nobody:nogroup storage bootstrap/cache public/storage || true
chmod -R ug+rwX storage bootstrap/cache

# Refresh Laravel links/caches and Backpack Basset cache.
php artisan storage:link || true
php artisan basset:fresh || true
php artisan optimize:clear || true
php artisan config:cache || true

