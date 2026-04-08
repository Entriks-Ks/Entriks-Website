# Use the official PHP + Apache image
FROM php:8.2-apache

# Enable Apache rewrite
RUN a2enmod rewrite

# Fix: AH00534: apache2: Configuration error: More than one MPM loaded.
# Disable event and worker MPMs and enable prefork (standard for mod_php)
RUN a2dismod mpm_event || true && \
    a2dismod mpm_worker || true && \
    a2enmod mpm_prefork

# Install system dependencies including SSL support
RUN apt-get update && apt-get install -y \
    libssl-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    unzip \
    git \
    curl

# Install GD extension
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg && \
    docker-php-ext-install gd zip

# Install MongoDB extension
RUN pecl install mongodb-2.1.4 && \
    echo "extension=mongodb.so" > /usr/local/etc/php/conf.d/mongodb.ini

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy project files
COPY . /var/www/html/

# Clean vendor & lock to ensure fresh install on Render
RUN rm -rf /var/www/html/backend/vendor /var/www/html/backend/composer.lock

# Install backend dependencies
WORKDIR /var/www/html/backend
RUN composer install --no-interaction --prefer-dist

# Permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
