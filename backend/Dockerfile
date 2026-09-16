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
RUN composer dump-autoload --no-dev --classmap-authoritative --no-interaction \
    && APP_SECRET=build-only-app-secret-0000000000000000 \
    DATABASE_URL=mysql://build:build@127.0.0.1/build?serverVersion=8.4 \
    MESSENGER_TRANSPORT_DSN=amqp://build:build@127.0.0.1/%2f/messages \
    GOOGLE_OAUTH_CLIENT_ID=build-client \
    GOOGLE_OAUTH_CLIENT_SECRET=build-only-google-secret-000000000000 \
    CLIENT_CREDENTIALS_KEY=MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY= \
    CENTRIFUGO_API_URL=http://127.0.0.1:8000 \
    CENTRIFUGO_API_KEY=build-only-api-key-00000000000000000000 \
    CENTRIFUGO_TOKEN_SECRET=build-only-token-secret-00000000000000 \
    composer run-script post-install-cmd
COPY docker/production-entrypoint /usr/local/bin/db-steward-production-entrypoint
RUN chmod +x /usr/local/bin/db-steward-production-entrypoint
ENTRYPOINT ["db-steward-production-entrypoint"]
CMD ["php-fpm"]
