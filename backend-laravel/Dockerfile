FROM php:8.2-fpm AS base

# Install system deps
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libkrb5-dev \
    libpq-dev \
    libxml2-dev \
    libxslt1.1 \
    libxslt1-dev \
    libmagickwand-dev \
    libmagickcore-dev \
    librdkafka-dev \ 
    && pecl install imagick rdkafka \ 
    && docker-php-ext-enable imagick rdkafka \
    && rm -rf /var/lib/apt/lists/*

# Core PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    exif \
    bcmath \
    calendar \
    fileinfo \
    gettext \
    pcntl \
    sockets \
    xmlwriter \
    xsl \
    opcache \
    zip \
    gd

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# App user
ARG APP_USER_ID=1000
ARG APP_USERGROUP_ID=1000

RUN if getent group ${APP_USERGROUP_ID} >/dev/null; then \
        groupmod -n apps $(getent group ${APP_USERGROUP_ID} | cut -d ':' -f1); \
    else \
        groupadd -g ${APP_USERGROUP_ID} apps; \
    fi \
    && useradd -m -g ${APP_USERGROUP_ID} -s /bin/bash -u ${APP_USER_ID} apps \
    && usermod -aG apps www-data \
    && usermod -aG www-data apps

WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --optimize-autoloader

# ===================================================
# Dev stage with Xdebug
# ===================================================
FROM base AS dev

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Write all xdebug + dev settings into one ini file
RUN { \
    echo "zend_extension=xdebug.so"; \
    echo "xdebug.mode=debug"; \
    echo "xdebug.start_with_request=yes"; \
    echo "xdebug.client_host=host.docker.internal"; \
    echo "xdebug.client_port=9000"; \
    echo "xdebug.idekey=PHPSTORM"; \
    echo "xdebug.log=/tmp/xdebug.log"; \
    echo "xdebug.discover_client_host=0"; \
    echo "display_errors=On"; \
    echo "display_startup_errors=On"; \
    echo "error_reporting=E_ALL"; \
} > /usr/local/etc/php/conf.d/99-xdebug.ini
