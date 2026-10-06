# LinkForge backend — PHP 8.3 FPM + nginx for concurrent request handling.
# Used for both the API server (default CMD) and the clicks:consume worker
# (override CMD with "php artisan clicks:consume" — the entrypoint detects
# a "php" command and execs it directly, skipping fpm/nginx).
FROM php:8.3-fpm AS base

# --- System packages -------------------------------------------------------
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip curl gnupg2 apt-transport-https ca-certificates \
        librdkafka-dev libzip-dev \
        nginx procps \
    && rm -rf /var/lib/apt/lists/*

# --- Microsoft ODBC driver 18 (for pdo_sqlsrv) -----------------------------
RUN curl -sSL https://packages.microsoft.com/keys/microsoft.asc \
        | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg \
    && curl -sSL https://packages.microsoft.com/config/debian/12/prod.list \
        > /etc/apt/sources.list.d/mssql-release.list \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y --no-install-recommends msodbcsql18 unixodbc-dev \
    && rm -rf /var/lib/apt/lists/*

# --- PHP extensions --------------------------------------------------------
RUN pecl install rdkafka sqlsrv pdo_sqlsrv \
    && docker-php-ext-enable rdkafka sqlsrv pdo_sqlsrv \
    && docker-php-ext-install pcntl zip

# --- Composer --------------------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# --- nginx + FPM config ----------------------------------------------------
COPY docker/nginx-api.conf /etc/nginx/sites-available/default
COPY docker/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts

COPY . .
RUN composer dump-autoload --optimize \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8000

ENTRYPOINT ["entrypoint.sh"]
CMD ["serve"]
