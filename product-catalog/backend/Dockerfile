# syntax=docker/dockerfile:1

# ---------- Stage 1: Composer dependencies ----------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock* ./

RUN if [ -f composer.lock ]; then \
        composer install --no-interaction --prefer-dist --no-scripts --ignore-platform-reqs; \
    else \
        composer update --no-interaction --prefer-dist --no-scripts --ignore-platform-reqs; \
    fi

COPY . .

RUN composer dump-autoload --optimize --ignore-platform-reqs

# ---------- Stage 2: PHP-FPM runtime ----------
FROM php:8.3-fpm-alpine AS runtime

RUN apk add --no-cache \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql intl zip mbstring opcache \
    && pecl install amqp \
    && docker-php-ext-enable amqp \
    && apk del $PHPIZE_DEPS \
    && rm -rf /tmp/pear

WORKDIR /var/www/html

COPY --from=vendor /app /var/www/html
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini

RUN mkdir -p var/cache var/uploads public/uploads/products \
    && chown -R www-data:www-data var public/uploads

USER www-data

EXPOSE 9000

CMD ["php-fpm"]
