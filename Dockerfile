# Use official PHP image with Apache web server
FROM php:8.2-apache

# Install PHP extensions needed for PostgreSQL support
RUN docker-php-ext-install pdo pdo_pgsql

# Copy your project files to Apache's web root
COPY . /var/www/html/

# Expose port 80 (default HTTP port)
EXPOSE 80