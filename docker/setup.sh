#!/usr/bin/env bash
set -euo pipefail
wp() { command wp --allow-root --url="$SITE_URL" "$@"; }
if [ ! -f wp-includes/version.php ]; then
    wp core download --version="$WP_VERSION" --force
fi
installed_version=$(wp core version)
if [ "$installed_version" != "$WP_VERSION" ]; then
    echo "WordPress data directory contains $installed_version; expected $WP_VERSION. Use a separate data directory." >&2
    exit 1
fi
if [ ! -f wp-config.php ]; then
    wp config create --dbname=wordpress --dbuser=wordpress --dbpass=local-wordpress --dbhost=db --skip-check
    wp config set WP_DEBUG true --raw
    wp config set WP_DEBUG_LOG true --raw
    wp config set WP_DEBUG_DISPLAY false --raw
    wp config set AUTOMATIC_UPDATER_DISABLED true --raw
    wp config set DISALLOW_FILE_MODS true --raw
fi
if ! wp core is-installed; then
    wp core install --url="$SITE_URL" --title='Mail Sender Development' --admin_user=admin --admin_password=local-admin-password --admin_email=admin@example.test --skip-email
    wp user create subscriber subscriber@example.test --role=subscriber --user_pass=local-subscriber-password
fi
wp plugin activate static-mail-sender-configurator
touch wp-content/debug.log
chown www-data:www-data . wp-content wp-content/debug.log
