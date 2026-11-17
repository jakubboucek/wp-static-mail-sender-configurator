ARG PHP_IMAGE=jakubboucek/lamp-devstack-php:8.4.27
FROM ${PHP_IMAGE}
WORKDIR /opt/test-tools
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress
WORKDIR /workspace
