FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    unzip \
    libcurl4-openssl-dev \
    libpq-dev \
    curl \
    git \
    zip \
    && docker-php-ext-install pdo pdo_pgsql curl \
    && a2enmod rewrite \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html/

COPY . .

RUN [ -f composer.json ] && composer install --no-dev --optimize-autoloader || echo "No composer.json found"

EXPOSE 80
