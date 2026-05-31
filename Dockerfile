FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libicu-dev \
        libonig-dev \
        unzip \
        zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mysqli \
        gd \
        zip \
        intl \
        mbstring \
    && a2enmod rewrite headers \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/powerplanner.ini

COPY . /var/www/html/

RUN mkdir -p \
        /var/www/html/storage \
        /var/www/html/storage/backups \
        /var/www/html/storage/exports \
        /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \;

EXPOSE 80

CMD ["apache2-foreground"]