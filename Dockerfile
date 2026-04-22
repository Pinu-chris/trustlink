# 1. Use the official PHP 8.2 Apache image as our base
FROM php:8.2-apache

# 2. Install system dependencies & PostgreSQL extension
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean

# 3. Copy all your application code into the container
COPY . /var/www/html/

# 4. Set the document root to your 'public' folder
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 5. Enable Apache's mod_rewrite for clean URLs
RUN a2enmod rewrite

# 6. Set permissions for Apache to read/write if needed
RUN chown -R www-data:www-data /var/www/html