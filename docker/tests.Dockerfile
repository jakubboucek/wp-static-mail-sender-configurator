ARG PHP_IMAGE=jakubboucek/lamp-devstack-php:8.4.27
FROM ${PHP_IMAGE}
WORKDIR /opt/test-tools
ARG TESTER_CONFIG=.
COPY ${TESTER_CONFIG}/composer.json ${TESTER_CONFIG}/composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress
RUN curl -fsSL https://github.com/wp-cli/wp-cli/releases/download/v2.12.0/wp-cli-2.12.0.phar -o /usr/local/bin/wp \
    && chmod +x /usr/local/bin/wp
ARG WP_VERSION
RUN mkdir /opt/wordpress \
    && wp --allow-root core download --path=/opt/wordpress --version="$WP_VERSION"
COPY docker/run-tests.sh /opt/run-tests.sh
WORKDIR /workspace
ENTRYPOINT ["bash", "/opt/run-tests.sh"]
