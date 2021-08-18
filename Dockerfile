################ <DESCRIPRION> ################
# This image is a PHP8.0 image with an Apache server.
# It contains some default php extensions (see "EXTENSION INSTALLATIONS" section below)
################ </DESCRIPRION> ################

FROM php:8.0-apache

################ <BUILD ARGUMENTS> ################
ARG ELASTICSEARCH_SERVER_URL
ARG STAT_ELASTICSEARCH_SERVER_URL=$ELASTICSEARCH_SERVER_URL
ARG RECO_ELASTICSEARCH_SERVER_URL=$ELASTICSEARCH_SERVER_URL
ARG ADS_INDEX_NB_SHARDS=1
ARG ADS_INDEX_NB_REPLICAS=1
ARG ADS_STAT_INDEX_NB_SHARDS=1
ARG ADS_STAT_INDEX_NB_REPLICAS=1
ARG ADS_RECO_INDEX_NB_SHARDS=1
ARG ADS_RECO_INDEX_NB_REPLICAS=1
ARG ADS_API_APPLY_BOOSTING=0
ARG SYNONYMS_DICTIONARIES_PATH
ARG COLLECT_STATS=1
ARG IS_LEGACY=1
ARG MAX_REPLICAS=0
################ </BUILD ARGUMENTS> ################

ENV APACHE_RUN_USER www-data

RUN apt-get update

################ <EXTENSION INSTALLATIONS> ################
# zip
RUN apt-get install -y \
    libzip-dev \
    zip && \
    docker-php-ext-install zip

# xdebug
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug
################ </ EXTENSION INSTALLATIONS> ################

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY .docker/config/prod/php/conf.d/custom.ini /usr/local/etc/php/conf.d/custom.ini
COPY ./.docker/config/general/httpd/symfony.conf /etc/apache2/sites-available/symfony.conf

RUN a2dissite 000-default.conf
RUN a2ensite symfony.conf

COPY --chown=www-data:www-data . /var/www/html

WORKDIR /var/www/html

################ < CONFIGURATION > ################
RUN echo APP_ENV=prod > .env
RUN echo APP_SECRET=`cat /dev/urandom | tr -dc 'a-z0-9' | fold -w 32 | head -n 1` >>.env
RUN echo ELASTICSEARCH_SERVER_URL=$ELASTICSEARCH_SERVER_URL >>.env
RUN echo STAT_ELASTICSEARCH_SERVER_URL=$STAT_ELASTICSEARCH_SERVER_URL >>.env
RUN echo RECO_ELASTICSEARCH_SERVER_URL=$RECO_ELASTICSEARCH_SERVER_URL >>.env
RUN echo ADS_INDEX_NB_SHARDS=$ADS_INDEX_NB_SHARDS >>.env
RUN echo ADS_INDEX_NB_REPLICAS=$ADS_INDEX_NB_REPLICAS >>.env
RUN echo ADS_STAT_INDEX_NB_SHARDS=$ADS_STAT_INDEX_NB_SHARDS >>.env
RUN echo ADS_STAT_INDEX_NB_REPLICAS=$ADS_STAT_INDEX_NB_REPLICAS >>.env
RUN echo ADS_RECO_INDEX_NB_SHARDS=$ADS_RECO_INDEX_NB_SHARDS >>.env
RUN echo ADS_RECO_INDEX_NB_REPLICAS=$ADS_RECO_INDEX_NB_REPLICAS >>.env
RUN echo ADS_API_APPLY_BOOSTING=$ADS_API_APPLY_BOOSTING >>.env
RUN echo SYNONYMS_DICTIONARIES_PATH=$SYNONYMS_DICTIONARIES_PATH >>.env
RUN echo COLLECT_STATS=$COLLECT_STATS >>.env
RUN echo IS_LEGACY=$IS_LEGACY >>.env
RUN echo MAX_REPLICAS=$MAX_REPLICAS >>.env
################ </ CONFIGURATION > ################

RUN groupadd -g 1000 adimeo
RUN useradd -m -u 1000 -g 1000 -s /bin/bash adimeo

USER www-data

RUN APP_ENV=prod composer install --no-dev

USER root

# to build this image you can run a command like this one:
#docker build \
#   --build-arg ELASTICSEARCH_SERVER_URL=ads_elk:9200 \
#   --build-arg STAT_ELASTICSEARCH_SERVER_URL=ads_elk:9200 \
#   --build-arg RECO_ELASTICSEARCH_SERVER_URL=ads_elk:9200 \
#   --build-arg ADS_INDEX_NB_SHARDS=1 \
#   --build-arg ADS_INDEX_NB_REPLICAS=1 \
#   --build-arg ADS_STAT_INDEX_NB_SHARDS=1 \
#   --build-arg ADS_STAT_INDEX_NB_REPLICAS=1 \
#   --build-arg ADS_RECO_INDEX_NB_SHARDS=1 \
#   --build-arg ADS_RECO_INDEX_NB_REPLICAS=1 \
#   --build-arg ADS_API_APPLY_BOOSTING=0 \
#   --build-arg SYNONYMS_DICTIONARIES_PATH= \
#   --build-arg COLLECT_STATS=1 \
#   --build-arg IS_LEGACY=1 \
#   --build-arg MAX_REPLICAS=1 \
#   -t adimeotech/adimeo-data-suite:TAG .

# by convention the tag should be named like X_Y where:
    # X is the version of elastic search supported
    # Y the version of php that is supported

# examples:
    # adimeotech/adimeo-data-suite:elk5.6_php7.2
    # adimeotech/adimeo-data-suite:elk5.6_php8.0