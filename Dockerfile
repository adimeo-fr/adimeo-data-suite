#============
# DEVELOPMENT
#============
# ADS
FROM php:8.1.16-fpm-alpine AS php

ARG XDEBUG_MODE=develop,debug
ARG XDEBUG_START_WITH_REQUEST=yes

RUN <<-eot
  curl -sS https://getcomposer.org/installer | php
  mv composer.phar /usr/local/bin/composer

  apk update
  apk upgrade --no-cache
  apk add --no-cache linux-headers fcgi $PHPIZE_DEPS

  docker-php-ext-install pdo_mysql pcntl
  pecl install xdebug
  docker-php-ext-enable xdebug pcntl

  touch /var/log/xdebug.log
  chown www-data:www-data /var/log/xdebug.log

  echo "xdebug.mode=$XDEBUG_MODE" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
  echo "xdebug.start_with_request=$XDEBUG_START_WITH_REQUEST" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
  echo "xdebug.log=/var/log/xdebug.log" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
eot
WORKDIR /srv/www/search

# Nginx
FROM nginx:1.20.1-alpine AS nginx
ARG APP_PUBLIC_HOST

COPY <<-eot /etc/nginx/conf.d/search.conf
server {
  listen 80;
  server_name $APP_PUBLIC_HOST;

  root /srv/www/search/public;

  location / {
    try_files \$uri @rewriteapp;
  }

  location @rewriteapp {
    rewrite ^(.*)$ /index.php/\$1 last;
  }

  location ~ ^/index.php(/|$) {
    fastcgi_pass search_ads:9000;
    fastcgi_split_path_info ^(.+\.php)(/.*)$;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
    fastcgi_param HTTPS off;
    client_max_body_size 200M;
    fastcgi_read_timeout 600;
  }
}
eot

EXPOSE 80