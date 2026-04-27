FROM php:8.2-apache

# Install MySQL extensions with SSL support
RUN apt-get update && apt-get install -y \
    libssl-dev \
    && docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache rewrite module
RUN a2enmod rewrite

# Copy project files
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80