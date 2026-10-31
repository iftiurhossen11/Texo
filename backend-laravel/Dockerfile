FROM php:8.3-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libsqlite3-dev unzip \
    && docker-php-ext-install pdo_pgsql pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs

EXPOSE 10000

CMD ["sh", "-c", "mkdir -p \"$(dirname \"${DB_DATABASE:-/var/data/database.sqlite}\")\" && touch \"${DB_DATABASE:-/var/data/database.sqlite}\" && php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=\"${PORT:-10000}\""]
