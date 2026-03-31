FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    libzip-dev \
    libpq-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-install zip pdo_mysql pdo_pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf

RUN printf '<Directory /var/www/html/public>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>\n' > /etc/apache2/conf-available/laravel.conf \
    && a2enconf laravel

WORKDIR /var/www/html

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY . /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-dev --optimize-autoloader

COPY . /var/www/html

RUN mkdir -p \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache/data \
    storage/logs \
    bootstrap/cache

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

RUN printf '#!/bin/bash\n\
set -e\n\
echo "Clearing Laravel caches..."\n\
php artisan optimize:clear\n\
php artisan config:clear\n\
if [ "$RUN_FRESH_MIGRATION" = "true" ]; then\n\
  echo "Running fresh migration with seed..."\n\
  php artisan migrate:fresh --seed --force\n\
else\n\
  echo "Running migration..."\n\
  php artisan migrate --force\n\
  echo "Running seeders..."\n\
  php artisan db:seed --force\n\
fi\n\
echo "Starting Apache..."\n\
exec apache2-foreground\n' > /usr/local/bin/start.sh \
    && chmod +x /usr/local/bin/start.sh

EXPOSE 80

CMD ["/usr/local/bin/start.sh"]
