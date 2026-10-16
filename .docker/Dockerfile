ARG PHP_VERSION=8.4
FROM php:${PHP_VERSION}-cli-alpine

RUN apk add --no-cache git unzip icu-dev libzip-dev \
    && docker-php-ext-install intl zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_HOME=/tmp/composer
WORKDIR /module
