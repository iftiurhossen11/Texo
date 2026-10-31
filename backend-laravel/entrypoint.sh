#!/bin/sh
set -eu

mkdir -p \
    bootstrap/cache \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    storage/app/private

php artisan migrate --force

if [ "${SEED_DEMO_DATA:-false}" = "true" ]; then
    php artisan db:seed --class=ProductionDemoSeeder --force
fi

php artisan queue:work --sleep=3 --tries=3 --timeout=120 --no-interaction &
queue_pid=$!
php artisan serve --host=0.0.0.0 --port="${PORT:-10000}" &
server_pid=$!

trap 'kill "$server_pid" "$queue_pid" 2>/dev/null || true' EXIT TERM INT
wait "$server_pid"
