FROM php:8.4-cli-alpine

RUN apk add --no-cache postgresql-dev libzip-dev $PHPIZE_DEPS \
    && docker-php-ext-install pdo_pgsql pcntl zip \
    && pecl install redis && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
RUN composer dump-autoload --optimize --no-dev

EXPOSE 8000

# Dev-grade server; production would sit behind php-fpm / FrankenPHP.
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
