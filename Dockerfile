FROM php:8.3-fpm-bookworm

ARG UID=1000
ARG GID=1000

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
        libicu-dev \
        libpng-dev \
        libzip-dev \
        unzip \
        zip \
    && docker-php-ext-configure gd \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        gd \
        intl \
        opcache \
        pdo_mysql \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN groupadd --gid "${GID}" laravel \
    && useradd --uid "${UID}" --gid laravel --shell /bin/bash --create-home laravel

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts

COPY . .
COPY docker/php/entrypoint.sh /usr/local/bin/laravel-entrypoint
COPY docker/php/99-tempdir.ini /usr/local/etc/php/conf.d/99-tempdir.ini
COPY docker/php/99-production.ini /usr/local/etc/php/conf.d/99-production.ini
COPY docker/php/zz-fpm-env.conf /usr/local/etc/php-fpm.d/zz-fpm-env.conf

RUN chmod +x /usr/local/bin/laravel-entrypoint \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R laravel:laravel storage bootstrap/cache

ENTRYPOINT ["laravel-entrypoint"]
CMD ["php-fpm"]

