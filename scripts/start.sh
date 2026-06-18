#!/bin/sh
set -ex

echo "==> Running migrations..."
php artisan migrate --force

echo "==> Seeding roles and permissions..."
php artisan db:seed --class=RolesAndPermissionsSeeder --force

echo "==> Seeding dev data..."
php artisan db:seed --class=DevSeeder --force

echo "==> Starting server on port ${PORT:-8080}..."
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8080}
