FROM webdevops/php-apache:7.2

RUN apt-get update

RUN mkdir -p /var/log/apache2

RUN ln -snf /usr/share/zoneinfo/Europe/Paris /etc/localtime
RUN echo "Europe/Paris" > /etc/timezone

COPY .docker/conf/httpd/default.conf /opt/docker/etc/httpd/vhost.common.d/gt.conf
COPY .docker/conf/php/custom.ini /opt/docker/etc/php/php.ini

USER application

COPY --chown=application:application . /app

WORKDIR /app

RUN composer1 install
