FROM php:8.3-fpm-alpine AS php_base
RUN apk add --no-cache bash git libpq-dev rabbitmq-c-dev icu-dev libzip-dev oniguruma-dev libpng-dev freetype-dev libjpeg-turbo-dev sqlite-dev linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql pdo_sqlite intl mbstring zip gd sockets
WORKDIR /var/www

FROM composer:2.8 AS vendor_prod
WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --ignore-platform-req=ext-gd --ignore-platform-req=ext-sockets || composer update --no-dev --prefer-dist --no-interaction --no-progress --ignore-platform-req=ext-gd --ignore-platform-req=ext-sockets

FROM composer:2.8 AS vendor_dev
WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --dev --prefer-dist --no-interaction --no-progress --ignore-platform-req=ext-gd --ignore-platform-req=ext-sockets || composer update --with-all-dependencies --prefer-dist --no-interaction --no-progress --ignore-platform-req=ext-gd --ignore-platform-req=ext-sockets

FROM php_base AS runtime
COPY --from=vendor_prod /app/vendor ./vendor
COPY . .
EXPOSE 9000
CMD ["php-fpm", "-F"]

FROM php_base AS test
COPY --from=vendor_dev /app/vendor ./vendor
COPY . .
CMD ["sh", "-lc", "vendor/bin/phpstan analyse && vendor/bin/phpunit --testdox"]
