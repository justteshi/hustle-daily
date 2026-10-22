FROM php:8.4-apache-bookworm AS app
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev libicu-dev libzip-dev unzip git curl \
    && docker-php-ext-install pdo_pgsql intl zip \
    && a2enmod rewrite \
    && sed -ri -e 's!/var/www/html!/app/public!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --optimize-autoloader
COPY . .
COPY docker/apache.conf /etc/apache2/conf-enabled/hustle.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/hustle.ini
RUN php artisan package:discover --ansi && php artisan filament:assets && chown -R www-data:www-data storage bootstrap/cache
EXPOSE 80

FROM app AS test
USER root
RUN composer install --prefer-dist --no-interaction --no-scripts --optimize-autoloader
COPY tests ./tests
RUN cp .env.example .env && php artisan package:discover --ansi && chown -R www-data:www-data tests storage bootstrap/cache
USER www-data
CMD ["php", "artisan", "test"]
