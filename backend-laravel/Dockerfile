FROM php:8.2-fpm AS base

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    libpq-dev \
    libmagickwand-dev \
    && rm -rf /var/lib/apt/lists/*

RUN pecl install imagick \
    && docker-php-ext-enable imagick

RUN docker-php-ext-install pdo pdo_pgsql
# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ARG APP_USER_ID=1000
ARG APP_USERGROUP_ID=1000

RUN if [ $(grep ${APP_USERGROUP_ID} /etc/group) ]; then \
        OLD_NAME=$(grep ${APP_USERGROUP_ID} /etc/group | cut -d ':' -f1); \
        groupmod -n apps $(grep ${APP_USERGROUP_ID} /etc/group | cut -d ':' -f1); \
        echo "group $OLD_NAME renamed"; \
    else \
        groupadd -g ${APP_USERGROUP_ID} apps; \
    fi

RUN useradd -m -g ${APP_USERGROUP_ID} -s /bin/bash -u ${APP_USER_ID} apps && \
    usermod -aG apps www-data && \
    usermod -aG www-data apps


WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --optimize-autoloader

FROM base AS dev

RUN touch /tmp/xdebug.log
# install xdebug
RUN pecl install xdebug
RUN docker-php-ext-enable xdebug
RUN echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
RUN echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
RUN echo "xdebug.log=/tmp/xdebug.log" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
RUN echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
RUN echo "xdebug.client_port=9000" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
RUN echo "xdebug.idekey=\"PHPSTORM\"" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
RUN echo "xdebug.discover_client_host=0" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
RUN echo "display_errors = On" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini    #this for mac
RUN echo "display_startup_errors = On" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini    #this for mac
RUN echo "error_reporting = E_ALL" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini    #this for mac