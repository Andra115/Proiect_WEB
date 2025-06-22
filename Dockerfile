
FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    unzip \
    libcurl4-openssl-dev \
    libpq-dev \
    curl \
    git \
    zip \
    && docker-php-ext-install \
    pdo \
    pdo_pgsql \
    curl \
    && a2enmod rewrite

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer


WORKDIR /var/www/html/

COPY . /var/www/html/


RUN [ -f composer.json ] && composer install || echo "No composer.json found"

RUN mkdir -p /var/www/html/uploads/chunks && \
    chmod -R 777 /var/www/html/uploads

EXPOSE 80