FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    bash \
    curl \
    git \
    icu-dev \
    libzip-dev \
    zip \
    unzip \
    linux-headers

# Install PHP extensions required by Symfony & MySQL
RUN docker-php-ext-install \
    pdo_mysql \
    intl \
    opcache \
    zip

# Copy OPcache configuration for maximum performance
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Pre-create var directories with correct permissions so www-data can write to them
RUN mkdir -p /var/www/html/var/cache \
    && mkdir -p /var/www/html/var/log \
    && chown -R www-data:www-data /var/www/html/var \
    && chmod -R 775 /var/www/html/var

EXPOSE 9000

CMD ["php-fpm"]

