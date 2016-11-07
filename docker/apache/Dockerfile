## -*- docker-image-name: "oauth-apache" -*-
FROM php:5.6-apache
MAINTAINER Tomas Kacur <tomas.kacur@keboola.com>


RUN apt-get update -q \
  && apt-get install git libmcrypt-dev unzip -y

RUN docker-php-ext-install mcrypt pdo_mysql

RUN pecl install oauth-1.2.3 \
  && docker-php-ext-enable oauth

WORKDIR /root

RUN cd \
  && curl -sS https://getcomposer.org/installer | php \
  && mv /root/composer.phar /usr/local/bin/composer

# RUN composer install
