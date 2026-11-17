<?php

require_once '/opt/test-tools/vendor/autoload.php';
define('WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-tests-config.php');
define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', '/opt/test-tools/vendor/yoast/phpunit-polyfills');
require_once '/opt/wordpress/tests/phpunit/includes/functions.php';
tests_add_filter('muplugins_loaded', static function () {
    require dirname(__DIR__) . '/plugin/static-mail-sender-configurator.php';
});
require '/opt/wordpress/tests/phpunit/includes/bootstrap.php';
