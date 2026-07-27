# syntax=docker/dockerfile:1.6

# Imagen única (php-fpm + nginx) para Coolify.
# vendor/ va en el repo: el host de build no puede descargar de
# api.github.com (curl 60 / certificado SSL incorrecto).
FROM php:8.4-fpm-alpine AS base

# Extensiones PHP necesarias
RUN apk add --no-cache \
        ca-certificates \
        nginx \
        bash \
        curl \
        git \
        icu-dev \
        libpng-dev \
        libzip-dev \
        oniguruma-dev \
        postgresql-dev \
        supervisor \
        zip \
        sqlite-libs \
        sqlite-dev \
    && update-ca-certificates \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        pdo_sqlite \
        zip \
    && rm -rf /var/cache/apk/* /tmp/*

WORKDIR /var/www/html

COPY . .

RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache database \
    && touch database/database.sqlite \
    && chown -R www-data:www-data storage bootstrap/cache database \
    && chmod -R 775 storage bootstrap/cache database \
    && test -f vendor/autoload.php

COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisor/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
