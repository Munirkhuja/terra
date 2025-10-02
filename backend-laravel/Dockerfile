FROM php:8.2-fpm

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

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer --version

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

RUN composer install

