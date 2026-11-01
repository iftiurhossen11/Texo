#!/bin/sh
set -eu

mkdir -p \
    bootstrap/cache \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    storage/app/private

if [ "${DB_CONNECTION:-}" = "pgsql" ] && [ -n "${DB_URL:-}" ]; then
    migration_db_url=$(printf '%s' "$DB_URL" | sed -E 's#^((postgres|postgresql)://[^@/]+@[^/?#:]+)-pooler\.#\1.#')
    DB_URL="$migration_db_url" php artisan migrate --force
else
    php artisan migrate --force
fi

if [ "${SEED_DEMO_DATA:-false}" = "true" ]; then
    demo_users_password="${DEMO_USERS_PASSWORD:-}"
    if [ "${#demo_users_password}" -lt 16 ]; then
        echo "Skipping production demo seeding: DEMO_USERS_PASSWORD must be at least 16 characters." >&2
    else
        php artisan db:seed --class=ProductionDemoSeeder --force
    fi
    unset demo_users_password
fi

php artisan queue:work --sleep=3 --tries=3 --timeout=120 --no-interaction &
queue_pid=$!
php artisan serve --host=0.0.0.0 --port="${PORT:-10000}" &
server_pid=$!

trap 'kill "$server_pid" "$queue_pid" 2>/dev/null || true' EXIT TERM INT
wait "$server_pid"
