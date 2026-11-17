#!/usr/bin/env bash
set -euo pipefail
wp() { command wp --allow-root --path=/opt/wordpress --url=http://example.org "$@"; }
wp config create --dbname=wordpress_tests --dbuser=wordpress_tests --dbpass=local-tests --dbhost=db --dbprefix=tester_ --skip-check
wp config set WP_DEBUG true --raw
wp config set WP_DEBUG_DISPLAY false --raw
wp config set AUTOMATIC_UPDATER_DISABLED true --raw
if ! wp core is-installed; then
    wp core install --url=http://example.org --title='Plugin tests' --admin_user=test-admin --admin_password=local-tests-only --admin_email=admin@example.org --skip-email
fi
exec /opt/test-tools/vendor/bin/tester -C -j 1 -s /workspace/tests "$@"
