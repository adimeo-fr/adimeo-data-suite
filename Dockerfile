#============
# CI DEV
#============
# ADS
FROM php:8.1.16-fpm-alpine AS php

RUN curl -sS https://getcomposer.org/installer | php && \
  mv composer.phar /usr/local/bin/composer && \

  apk update && \
  apk upgrade --no-cache && \
  apk add --no-cache linux-headers fcgi $PHPIZE_DEPS && \

  docker-php-ext-install pdo_mysql pcntl && \
  pecl install xdebug && \
  docker-php-ext-enable xdebug pcntl && \
  touch /var/log/xdebug.log && \
  chown www-data:www-data /var/log/xdebug.log


COPY .docker/config/prod/php/conf.d/custom.ini /usr/local/etc/php/conf.d/custom.ini
COPY .docker/config/dev/php/conf.d/xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
COPY --chown=adimeo:adimeo . /srv/www/search


WORKDIR /srv/www/search
RUN APP_ENV=prod composer install --no-interaction --ignore-platform-reqs --optimize-autoloader

USER www-data

# Nginx
FROM nginx:1.20.1-alpine AS nginx
ARG APP_PUBLIC_HOST

COPY .docker/config/nginx/search.conf /etc/nginx/conf.d/search.conf
COPY ./public /srv/www/search/public

EXPOSE 80