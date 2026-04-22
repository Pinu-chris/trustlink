FROM php:8.2-apache

# Install PostgreSQL extension
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pgsql pdo pdo_pgsql

# Copy project files
COPY . /var/www/html/

# Enable Apache mod_rewrite
RUN a2enmod rewrite

EXPOSE 80