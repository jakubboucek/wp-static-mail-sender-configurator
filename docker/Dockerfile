ARG PHP_IMAGE=jakubboucek/lamp-devstack-php:8.4.27
FROM ${PHP_IMAGE}
RUN curl -fsSL https://github.com/wp-cli/wp-cli/releases/download/v2.12.0/wp-cli-2.12.0.phar -o /usr/local/bin/wp \
    && chmod +x /usr/local/bin/wp \
    && wp --allow-root --info
WORKDIR /var/www/html/www
