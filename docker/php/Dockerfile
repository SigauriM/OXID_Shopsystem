FROM php:8.3-apache

ENV APACHE_DOCUMENT_ROOT=/var/www/html/source \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1

# OXID 7.5 table: GD, PDO_MySQL, BC Math, mbstring, cURL, SOAP, DOM.
# JSON / tokenizer / iconv / cURL / DOM ship with this image. zip is for Composer.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libzip-dev \
        libxml2-dev \
        libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        gd \
        pdo_mysql \
        bcmath \
        mbstring \
        soap \
        zip \
    && a2enmod rewrite \
    && echo 'umask 000' >> /etc/apache2/envvars \
    && sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf \
    && sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && printf '%s\n' \
        '<Directory /var/www/html/source>' \
        '    AllowOverride All' \
        '    Options FollowSymLinks' \
        '    Require all granted' \
        '</Directory>' \
        > /etc/apache2/conf-available/oxid-document-root.conf \
    && a2enconf oxid-document-root \
    && printf '%s\n' 'memory_limit=512M' 'display_errors=Off' 'expose_php=Off' > /usr/local/etc/php/conf.d/oxid-memory.ini \
    && rm -rf /var/lib/apt/lists/*

# Docs 7.5: Composer 2.8 or 2.9. Pin 2.8, not the floating composer:2 line.
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
