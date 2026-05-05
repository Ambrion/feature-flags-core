ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-cli

# Системные зависимости + расширения, нужные для библиотек и тестов
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libicu-dev \
    && docker-php-ext-install \
    zip \
    intl \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Кэш Composer (опционально, ускоряет install)
RUN mkdir -p /tmp/composer-cache
ENV COMPOSER_CACHE_DIR=/tmp/composer-cache

WORKDIR /app