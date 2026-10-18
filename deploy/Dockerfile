FROM composer:2@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac AS composer
FROM php:8.4-fpm-bookworm@sha256:43e1ac38217031dbbecae60e84ccf8593722031559178d199bf56adb0145d5d0

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libfreetype6-dev libjpeg62-turbo-dev libpng-dev libzip-dev libicu-dev libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 pdo_mysql mbstring gd intl zip bcmath pcntl opcache exif \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY deploy/php.ini /usr/local/etc/php/conf.d/laundry.ini
WORKDIR /home/hidupjokowi/laundry/current
USER 1000:1000
CMD ["php-fpm", "-F"]
