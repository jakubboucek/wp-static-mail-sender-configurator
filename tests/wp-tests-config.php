<?php

// Only the dedicated test account is allowed to install or reset these tables.
define('ABSPATH', '/opt/wordpress/src/');
define('DB_NAME', 'wordpress_tests');
define('DB_USER', 'wordpress_tests');
define('DB_PASSWORD', 'local-tests');
define('DB_HOST', 'db');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');
define('WP_DEBUG', true);
define('WP_TESTS_DOMAIN', 'example.org');
define('WP_TESTS_EMAIL', 'admin@example.org');
define('WP_TESTS_TITLE', 'Plugin integration tests');
define('WP_PHP_BINARY', PHP_BINARY);
$table_prefix = 'wptests_';
