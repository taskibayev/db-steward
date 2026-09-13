FROM php:8.4-fpm-alpine AS base
RUN apk add --no-cache $PHPIZE_DEPS icu-dev linux-headers rabbitmq-c-dev \
    && docker-php-ext-install intl opcache pcntl pdo_mysql \
    && pecl install amqp \
    && docker-php-ext-enable amqp \
    && apk del $PHPIZE_DEPS linux-headers
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php-fpm-healthcheck /usr/local/bin/php-fpm-healthcheck
RUN chmod +x /usr/local/bin/php-fpm-healthcheck
WORKDIR /app

FROM base AS development
COPY composer.json composer.lock symfony.lock ./
RUN composer install --prefer-dist --no-interaction --no-progress --no-scripts
COPY . .
RUN composer run-script post-install-cmd

FROM base AS production
ENV APP_ENV=prod
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --classmap-authoritative --no-interaction --no-progress --no-scripts
COPY . .
RUN composer run-script post-install-cmd
