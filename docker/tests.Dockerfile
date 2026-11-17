ARG PHP_IMAGE=jakubboucek/lamp-devstack-php:8.4.27
FROM ${PHP_IMAGE}
WORKDIR /opt/test-tools
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress
ARG WP_VERSION
RUN case "$WP_VERSION" in *.*.*) test_tag="$WP_VERSION" ;; *) test_tag="$WP_VERSION.0" ;; esac \
    && git clone --depth 1 --filter=blob:none --sparse --branch "$test_tag" \
       https://github.com/WordPress/wordpress-develop.git /opt/wordpress \
    && git -C /opt/wordpress sparse-checkout set src tests/phpunit/includes \
    && rm -rf /opt/wordpress/.git
WORKDIR /workspace
ENTRYPOINT ["/opt/test-tools/vendor/bin/phpunit"]
CMD ["--configuration", "/workspace/phpunit.xml.dist"]
