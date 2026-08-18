#!/bin/sh
set -e

cd /app

echo "==> Ensuring storage directories exist..."
mkdir -p storage/logs \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views

# The committed public/storage symlink points at an absolute path from this
# machine; point it at the container's storage tree instead.
ln -sfn /app/storage/app/public /app/public/storage

echo "==> Waiting for MySQL..."
until mysqladmin ping -h "${DB_HOST:-mysql}" -P "${DB_PORT:-3306}" -u "${DB_USERNAME:-root}" -p"${DB_PASSWORD:-}" --silent 2>/dev/null; do
    echo "    MySQL not ready - retrying in 2s..."
    sleep 2
done
echo "==> MySQL is up."

# Only used if the operator runs the image without injecting APP_KEY via env.
if [ -z "${APP_KEY}" ]; then
    echo "==> APP_KEY not set - generating one..."
    php artisan key:generate --force
fi

echo "==> Running migrations..."
php artisan migrate --force

# Seed on first boot only. DatabaseSeeder uses firstOrCreate for users/roles and
# lookups but always adds 25 sample tickets, so it must not run on every restart.
if [ "$(php artisan tinker --execute 'echo \App\Models\User::count() > 0 ? "1" : "0";')" = "0" ]; then
    echo "==> Seeding fresh database (demo users, lookups, sample tickets)..."
    php artisan db:seed --force
fi

echo "==> Generating Passport OAuth keys..."
php artisan passport:keys --force

# Personal access client is required for the API login/token endpoints.
# Passport >= 12 stores the grant in a `grant_types` JSON column.
if [ "$(php artisan tinker --execute 'echo \Laravel\Passport\Client::whereJsonContains("grant_types", "personal_access")->exists() ? "1" : "0";')" = "0" ]; then
    echo "==> Creating Passport personal access client..."
    php artisan passport:client --personal --name="Support Desk" --no-interaction
fi

echo "==> Caching configuration, routes, and views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Starting supervisord (Octane :8000 + Reverb :8080)..."
exec supervisord -c supervisord.conf -n