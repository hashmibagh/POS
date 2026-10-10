# Zangarry POS on Render (free tier) via Docker.
FROM php:8.3-apache

# Drivers for the app's dual DB layer (Neon Postgres + SQLite fallback).
RUN docker-php-ext-install pdo_pgsql pdo_sqlite

COPY . /var/www/html/
COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh \
    && mkdir -p /var/www/html/data \
    && chown -R www-data:www-data /var/www/html

CMD ["/usr/local/bin/start.sh"]
