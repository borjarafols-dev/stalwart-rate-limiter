# Stage 1: Install Composer dependencies
FROM composer:2.8 AS composer-build

ARG GITHUB_TOKEN
WORKDIR /app
COPY composer.json composer.lock symfony.lock ./
RUN if [ -n "$GITHUB_TOKEN" ]; then composer config --global github-oauth.github.com "$GITHUB_TOKEN"; fi \
    && composer install \
    --ignore-platform-reqs \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# Stage 2: Build PHP extensions
FROM dunglas/frankenphp:php8.4-bookworm AS php-extensions-build

RUN install-php-extensions pdo_pgsql intl opcache opentelemetry protobuf pcov

# Stage 3: Application base
FROM dunglas/frankenphp:php8.4-bookworm AS app

LABEL org.opencontainers.image.source="https://github.com/borjarafols-dev/sample-symfony-api-platform"

COPY --from=php-extensions-build /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=php-extensions-build /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
COPY --from=php-extensions-build /usr/lib/*-linux-gnu/ /usr/lib/x86_64-linux-gnu/

WORKDIR /app

COPY --from=composer-build /app/vendor ./vendor
COPY . .

COPY docker/Caddyfile /etc/frankenphp/Caddyfile

EXPOSE 8080

# Stage 4: Production
FROM app AS prod

ENV APP_ENV=prod
ENV APP_SECRET=change-me-in-production
ENV OTEL_PHP_AUTOLOAD_ENABLED=true

COPY --from=composer-build /app/vendor ./vendor
COPY --from=composer-build /usr/bin/composer /usr/bin/composer
RUN composer dump-autoload --no-dev --classmap-authoritative \
    && php bin/console assets:install --env=prod \
    && php bin/console cache:warmup --env=prod \
    && rm /usr/bin/composer

# Stage 5: Dev (quality tools + dev dependencies)
FROM app AS dev

ENV APP_ENV=dev
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

RUN apt-get update && apt-get install -y --no-install-recommends git \
    && rm -rf /var/lib/apt/lists/*

ARG GITHUB_TOKEN
COPY --from=composer-build /usr/bin/composer /usr/bin/composer
RUN if [ -n "$GITHUB_TOKEN" ]; then composer config --global github-oauth.github.com "$GITHUB_TOKEN"; fi \
    && composer install --no-interaction --no-scripts

# Stage 6: Test / CI
FROM app AS test

ENV APP_ENV=test
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

ARG GITHUB_TOKEN
COPY --from=composer-build /usr/bin/composer /usr/bin/composer
RUN if [ -n "$GITHUB_TOKEN" ]; then composer config --global github-oauth.github.com "$GITHUB_TOKEN"; fi \
    && composer install --no-interaction --no-scripts
