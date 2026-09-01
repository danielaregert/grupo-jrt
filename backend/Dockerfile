FROM php:8.3-fpm-alpine

RUN apk add --no-cache curl unzip \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /opt/mailer-vendor
COPY composer.json .
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && rm -rf /root/.composer /root/.cache

COPY docker-env.conf /usr/local/etc/php-fpm.d/docker-env.conf
