# LinkForge backend — PHP 8.3 CLI with ext-rdkafka + pdo_sqlsrv.
# Used for both the API server and the clicks:consume worker.
FROM php:8.3-cli AS base

# --- System packages -------------------------------------------------------
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip curl gnupg2 apt-transport-https ca-certificates \
        librdkafka-dev libzip-dev \
    && rm -rf /var/lib/apt/lists/*

# --- Microsoft ODBC driver 18 (for pdo_sqlsrv) -----------------------------
RUN curl -sSL https://packages.microsoft.com/keys/microsoft.asc \
        | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg \
    && curl -sSL https://packages.microsoft.com/config/debian/12/prod.list \
        | sed 's#deb #deb [signed-by=/usr/share/keyrings/microsoft-prod.gpg] #' \
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

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts

COPY . .
RUN composer dump-autoload --optimize

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
