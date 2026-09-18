#!/bin/sh
set -eu

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is required. Generate one with: php artisan key:generate --show" >&2
    exit 1
fi

mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

php artisan storage:link --force >/dev/null 2>&1 || true
php artisan migrate --force

if [ "${RUN_DB_SEED:-false}" = "true" ]; then
    php artisan db:seed --force
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

# Keep scheduled jobs and the queue worker running alongside the HTTP server (Render single dyno).
php artisan schedule:work --verbose --no-interaction >/proc/1/fd/1 2>/proc/1/fd/2 &
php artisan queue:work database --sleep=1 --tries=3 --max-time=3600 --no-interaction >/proc/1/fd/1 2>/proc/1/fd/2 &

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
