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
            __('Mail Config', 'static-mail-sender-configurator'),
            __('Mail Config', 'static-mail-sender-configurator'),
            'manage_options',
            'static-mail-sender-configurator',
            [static::class, 'render_admin_page'],
        );
    }

    public static function render_admin_page(): void
    {
        if (!current_user_can('manage_options')) {
            /** @noinspection ForgottenDebugOutputInspection */
            wp_die(
                esc_html__('Sorry, you are not allowed to access this page.', 'static-mail-sender-configurator'),
                '',
                ['response' => 403],
            );
        }

        echo '<div class="wrap"><h1>';
        echo esc_html__('Mail Config', 'static-mail-sender-configurator');
        echo '</h1>';

        $configuration = static::get_configuration();
        $sources = [
            'constant' => __('PHP constant', 'static-mail-sender-configurator'),
            'environment' => __('Environment variable', 'static-mail-sender-configurator'),
            'none' => __('Not configured', 'static-mail-sender-configurator'),
        ];
        $statuses = [
            'missing' => __(
                '⚪ Not configured. The plugin leaves mail arguments unchanged.',
                'static-mail-sender-configurator',
            ),
            'empty' => __('◌ The configured value is empty. Set a sender address.', 'static-mail-sender-configurator'),
            'invalid' => __(
                '🚫 Invalid sender format. Use a single email address, optionally with a display name.',
                'static-mail-sender-configurator',
            ),
            'valid' => __(
                '✅ The configured sender format is valid.',
                'static-mail-sender-configurator',
            ),
        ];

        echo '<h2>' . esc_html__('Configuration overview', 'static-mail-sender-configurator') . '</h2>';
        echo '<table class="widefat striped"><tbody><tr><th scope="row">';
        echo esc_html__('Value', 'static-mail-sender-configurator');
        echo '</th><td>';
        if ($configuration['source'] === 'none') {
            echo esc_html__('Not set', 'static-mail-sender-configurator');
        } elseif (is_string($configuration['value'])) {
            echo '<code>' . esc_html($configuration['value']) . '</code>';
        } else {
            echo esc_html__('The configured value is not a string.', 'static-mail-sender-configurator');
        }
        echo '</td></tr><tr><th scope="row">';
        echo esc_html__('Source', 'static-mail-sender-configurator');
        echo '</th><td>' . esc_html($sources[$configuration['source']]) . '</td></tr><tr><th scope="row">';
        echo esc_html__('Status', 'static-mail-sender-configurator');
        echo '</th><td>' . esc_html($statuses[$configuration['status']]) . '</td></tr></tbody></table>';

        $sender_filters = [
            'wp_mail_from' => __(
                'It may override the sender email address configured by this plugin.',
                'static-mail-sender-configurator'
            ),
            'wp_mail_from_name' => __(
                'It may override the sender display name configured by this plugin.',
                'static-mail-sender-configurator'
            ),
        ];
        foreach ($sender_filters as $hook => $description) {
            if (has_filter($hook)) {
                echo '<div class="notice notice-warning inline"><p>';
                echo wp_kses(
                    sprintf(
                        /* translators: 1: Filter name in code tags, 2: Explanation of its effect on the sender. */
                        __(
                            'Another component has registered the %1$s filter. %2$s',
                            'static-mail-sender-configurator'
                        ),
                        "<code>$hook</code>",
                        $description,
                    ),
                    ['code' => []],
                );
                echo ' ' . esc_html__(
                    'We cannot identify its source. Check mail settings in other plugins or ask your administrator.',
                    'static-mail-sender-configurator',
                );
                echo '</p></div>';
            }
        }

        self::render_test_form();

        echo '<h2>' . esc_html__('How to set up', 'static-mail-sender-configurator') . '</h2><p>';
        echo wp_kses(
            sprintf(
                /* translators: 1: Configuration constant in code tags, 2: Configuration filename in code tags. */
                __(
                    'Set %1$s in %2$s before WordPress loads, or in the PHP process environment.',
                    'static-mail-sender-configurator'
                ),
                '<code>WP_MAIL_FROM</code>',
                '<code>wp-config.php</code>',
            ),
            ['code' => []],
        );
        echo '</p><pre><code>' . esc_html("define('WP_MAIL_FROM', 'sender@example.com');") . '</code></pre><p>';
        echo esc_html__('To include a display name, use this value:', 'static-mail-sender-configurator');
        echo ' <code>' . esc_html('"Site Name" <sender@example.com>') . '</code></p><p>';
        echo esc_html__(
            'The PHP constant takes precedence over the environment variable, even when it is empty or invalid.',
            'static-mail-sender-configurator',
        );
        echo '</p><p>';
        echo esc_html__(
            'Configuration is read-only. These checks do not change mail handling or verify the final sender.',
            'static-mail-sender-configurator',
        );
        echo '</p></div>';
    }

    private static function render_test_form(): void
    {
        $recipient = wp_get_current_user()->user_email;
        $result = null;
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $recipient = is_string($_POST['recipient'] ?? null) ? trim(wp_unslash($_POST['recipient'])) : '';
            $result = static::send_test_mail($_POST);
        }

        echo '<h2>' . esc_html__('Send a test email', 'static-mail-sender-configurator') . '</h2>';
        if ($result !== null) {
            echo '<div id="mail-config-result" role="status" class="notice notice-';
            echo esc_attr($result['status']) . ' inline"><p>' . esc_html($result['message']) . '</p>';
            if ($result['errors'] !== []) {
                echo '<ul>';
                foreach ($result['errors'] as $error) {
                    echo '<li>' . esc_html($error) . '</li>';
                }
                echo '</ul>';
            }
            echo '</div>';
        }
        echo '<form method="post" action="';
        echo esc_url(admin_url('tools.php?page=static-mail-sender-configurator')) . '">';
        wp_nonce_field('mail_config_send_test', 'mail_config_nonce', false);
        echo '<p><label for="mail-config-recipient">';
        echo esc_html__('Recipient email address', 'static-mail-sender-configurator');
        echo '</label><br><input type="email" class="regular-text" id="mail-config-recipient"';
        echo ' name="recipient" required value="' . esc_attr($recipient) . '"></p>';
        submit_button(__('Send test email', 'static-mail-sender-configurator'));
        echo '</form>';
    }

    /**
     * @param array<string, mixed> $input Submitted fields with WordPress request slashes.
     * @return array{status: 'success'|'error'|'warning', message: string, errors: list<string>}
     */
    public static function send_test_mail(array $input): array
    {
        if (!current_user_can('manage_options')) {
            return ['status' => 'error', 'message' => __(
                'You are not allowed to send a test email.',
                'static-mail-sender-configurator'
            ), 'errors' => []];
        }
        $nonce = $input['mail_config_nonce'] ?? null;
        if (!is_string($nonce) || wp_verify_nonce(wp_unslash($nonce), 'mail_config_send_test') === false) {
            return ['status' => 'error', 'message' => __(
                'The security check failed. Reload the page and try again.',
                'static-mail-sender-configurator'
            ), 'errors' => []];
        }
        $recipient = is_string($input['recipient'] ?? null) ? trim(wp_unslash($input['recipient'])) : '';
        if (strpbrk($recipient, "\r\n") !== false || is_email($recipient) === false) {
            return ['status' => 'error', 'message' => __(
                'Enter one valid recipient email address.',
                'static-mail-sender-configurator'
            ), 'errors' => []];
        }

        $succeeded = 0;
        $failed = 0;
        $errors = [];
        $on_success = static function () use (&$succeeded): void {
            $succeeded++;
        };
        $on_failure = static function (\WP_Error $error) use (&$failed, &$errors): void {
            $failed++;
            foreach ($error->get_error_messages() as $message) {
                $errors[] = $message;
            }
        };
        add_action('wp_mail_succeeded', $on_success);
        add_action('wp_mail_failed', $on_failure);
        $sent = null;
        $exception = false;
        try {
            $sent = wp_mail(
                $recipient,
                __('Mail Config test email', 'static-mail-sender-configurator'),
                sprintf(
                    /* translators: %s: Plugin name. */
                    __(
                        'This is a test email from the %s plugin. Check the sender shown by your email client.',
                        'static-mail-sender-configurator'
                    ),
                    'Static Mail Sender Configurator',
                ),
                ['Content-Type: text/plain; charset=UTF-8'],
            );
        } catch (\Throwable $error) {
            $exception = true;
            $errors[] = $error->getMessage();
        } finally {
            remove_action('wp_mail_succeeded', $on_success);
            remove_action('wp_mail_failed', $on_failure);
        }

        if (!$exception && $sent === true && $succeeded === 1 && $failed === 0) {
            return ['status' => 'success', 'message' => __(
                'WordPress reported successful sending. Check the recipient inbox and spam folder to confirm delivery.',
                'static-mail-sender-configurator'
            ), 'errors' => []];
        }
        if (!$exception && $sent === false && $failed === 1 && $succeeded === 0) {
            return ['status' => 'error', 'message' => __(
                'WordPress reported a sending failure. Review the error below and check your mail settings.',
                'static-mail-sender-configurator'
            ), 'errors' => $errors];
        }
        return [
            'status' => 'warning',
            'message' => __(
                'Sending could not be confirmed. Check your mail settings and the recipient inbox before retrying.',
                'static-mail-sender-configurator'
            ),
            'errors' => $errors,
        ];
    }

    /** @return array{source: 'constant'|'environment'|'none', value: mixed, status: 'missing'|'empty'|'invalid'|'valid'} */
    public static function get_configuration(): array
    {
        if (defined(self::WP_MAIL_FROM_KEY)) {
            $source = 'constant';
            $value = constant(self::WP_MAIL_FROM_KEY);
        } elseif (false !== ($value = getenv(self::WP_MAIL_FROM_KEY))) {
            $source = 'environment';
        } else {
            return ['source' => 'none', 'value' => null, 'status' => 'missing'];
        }

        $status = 'invalid';
        if (is_string($value) && strpbrk($value, "\r\n") === false) {
            $address = trim($value);
            if ($address === '') {
                $status = 'empty';
            } else {
                if (preg_match('/^[^<>]*<([^<>]+)>$/D', $address, $matches) === 1) {
                    $address = trim($matches[1]);
                }
                if (is_email($address) !== false) {
                    $status = 'valid';
                }
            }
        }

        return ['source' => $source, 'value' => $value, 'status' => $status];
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
