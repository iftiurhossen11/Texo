FROM php:8.3-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libsqlite3-dev unzip \
    && docker-php-ext-install pdo_pgsql pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY backend-laravel/ ./
COPY ["Project Demo Document/", "/var/www/Project Demo Document/"]
COPY backend-laravel/entrypoint.sh /usr/local/bin/texo-entrypoint

RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/private \
    && sed -i 's/\r$//' /usr/local/bin/texo-entrypoint \
    && chmod +x /usr/local/bin/texo-entrypoint

EXPOSE 10000

ENTRYPOINT ["/usr/local/bin/texo-entrypoint"]
