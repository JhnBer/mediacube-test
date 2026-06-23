FROM php:8.5-fpm-alpine

ARG UID=1000
ARG GID=1000

WORKDIR /var/www/

RUN apk add --no-cache --virtual .build-deps \
        build-base \
        $PHPIZE_DEPS \
        && apk add --no-cache \
        curl \
        git \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        postgresql-dev \
        shadow \
        unzip \
        zip


RUN docker-php-ext-install \
    pdo_pgsql \
    bcmath \
    pcntl \
    zip \
    intl

RUN pecl install redis \
    && docker-php-ext-enable redis

RUN usermod -u "${UID}" www-data \
    && groupmod -g "${GID}" www-data

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

USER www-data

EXPOSE 8000

#CMD ["sh", "-c", "if [ -f artisan ]; then php artisan serve --host=0.0.0.0 --port=8000; else echo 'Laravel project not found. Create it in this directory, then restart the container.'; tail -f /dev/null; fi"]
CMD ["tail", "-f", "/dev/null"]
