#!/bin/sh
set -e

cd /app

# Use the PHP/web runtime user. Override in Dokploy env if needed.
RUNTIME_USER="${APP_RUNTIME_USER:-www-data}"
RUNTIME_GROUP="${APP_RUNTIME_GROUP:-www-data}"

# Ensure writable runtime paths exist.
mkdir -p storage/app/public/basset
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p bootstrap/cache

# Grant ownership/permissions only where Laravel needs write access.
chown -R "$RUNTIME_USER:$RUNTIME_GROUP" storage bootstrap/cache public/storage || true
chmod -R ug+rwX storage bootstrap/cache
chmod -R o+rX storage bootstrap/cache
chmod -R 775 storage/app/public/basset bootstrap/cache

# Refresh Laravel links/caches and Backpack Basset cache.
php artisan storage:link || true
php artisan basset:fresh || true
php artisan optimize:clear || true
php artisan config:cache || true
