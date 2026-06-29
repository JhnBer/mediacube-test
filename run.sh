#!/bin/sh


composer install --no-cache \
    && php artisan key:generate \
    && php artisan migrate \
    && php artisan optimize:clear \
    && php artisan db:seed

php artisan serve --host=0.0.0.0 --port=8000
