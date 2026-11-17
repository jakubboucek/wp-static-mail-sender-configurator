<?php

declare(strict_types=1);

namespace JakubBoucek\WpPlugin\MailSenderConfig;

/*
 * Plugin Name: Static Mail Sender Configurator
 * Plugin URI: https://github.com/jakubboucek/wp-static-mail-sender-configurator
 * Description: Simple & static configure WordPress internal mailer sender's (`From:`) address.
 * Version: 0.10.1
 * Requires at least: 6.0
 * Requires PHP: 7.4.0
 * Author: Jakub Bouček
 * Author URI: https://www.jakub-boucek.cz/
 * License: MIT
 * License URI: https://github.com/jakubboucek/wp-static-mail-sender-configurator/blob/master/LICENSE
 * Text Domain: static-mail-sender-configurator
 * Update URI: https://wordpress.org/plugins/static-mail-sender-configurator/
 */

class StaticMailSenderConfigurator
{
    public const WP_MAIL_FROM_KEY = 'WP_MAIL_FROM';

    public static function register(): void
    {
        /** @see \add_filter() from wp-includes/plugin.php */
        add_filter('wp_mail', [static::class, 'wp_mail'], 1);
        add_action('admin_menu', [static::class, 'register_admin_page']);
    }

    public static function register_admin_page(): void
    {
        add_management_page(
            __('Mail Sender', 'static-mail-sender-configurator'),
            __('Mail Sender', 'static-mail-sender-configurator'),
            'manage_options',
            'static-mail-sender-configurator',
            [static::class, 'render_admin_page']
        );
    }

    public static function render_admin_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('Sorry, you are not allowed to access this page.', 'static-mail-sender-configurator'),
                '',
                ['response' => 403]
            );
        }

        echo '<div class="wrap"><h1>';
        echo esc_html__('Mail Sender', 'static-mail-sender-configurator');
        echo '</h1></div>';
    }

    /**
     * @param array<string, mixed> $atts Mail arguments, including any third-party fields.
     * @return array<string, mixed>
     */
    public static function wp_mail(array $atts): array
    {
        if (defined(self::WP_MAIL_FROM_KEY) === false && getenv(self::WP_MAIL_FROM_KEY) === false) {
            // No configuration found, skip
            return $atts;
        }

        /**
         * Retype headers to array
         * @see \wp_mail() from wp-includes/pluggable.php
         */
        $headers = $atts['headers'];
        if (!is_array($headers)) {
            // Explode the headers out, so this function can take
            // both string headers and an array of headers.
            $headers = explode("\n", str_replace("\r\n", "\n", $headers));
        }

        if (defined(self::WP_MAIL_FROM_KEY)) {
            $headers[] = "From:" . constant(self::WP_MAIL_FROM_KEY);
        } elseif (is_string($env = getenv(self::WP_MAIL_FROM_KEY))) {
            $headers[] = "From:$env";
        }

        $atts['headers'] = $headers;
        return $atts;
    }
}

StaticMailSenderConfigurator::register();
