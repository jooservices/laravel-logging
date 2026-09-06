# PHP 8.5 CLI (Bookworm) — local tooling image for jooservices/laravel-logging
FROM php:8.5-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libssl-dev \
        libzip-dev \
        pkg-config \
        $PHPIZE_DEPS \
    && docker-php-ext-install -j$(nproc) intl \
    && pecl install mongodb pcov \
    && docker-php-ext-enable mongodb pcov \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer

WORKDIR /app

CMD ["php", "-v"]
