#!/bin/sh
set -e

# If arguments look like a PHP command (e.g. "php artisan clicks:consume"),
# run that directly — no nginx/fpm needed (used by the clicks-worker).
if [ "$1" = "php" ]; then
    exec "$@"
fi

php-fpm -D
exec nginx -g 'daemon off;'
