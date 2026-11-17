<?php

use JakubBoucek\WpPlugin\MailSenderConfig\StaticMailSenderConfigurator;

/**
 * Constants cannot be undefined, so every scenario gets a fresh WordPress process.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class MailSenderTest extends WP_UnitTestCase
{
    private $originalEnvironment;

    public function set_up()
    {
        parent::set_up();
        $this->originalEnvironment = getenv('WP_MAIL_FROM');
        putenv('WP_MAIL_FROM');
        reset_phpmailer_instance();
    }

    public function tear_down()
    {
        if ($this->originalEnvironment === false) {
            putenv('WP_MAIL_FROM');
        } else {
            putenv('WP_MAIL_FROM=' . $this->originalEnvironment);
        }
        reset_phpmailer_instance();
        // WP_UnitTestCase restores the hook registry after each test.
        parent::tear_down();
    }

    private function send($headers = '')
    {
        $this->assertTrue(wp_mail('recipient@example.org', 'Regression test', 'Message body.', $headers));
        $mailer = tests_retrieve_phpmailer_instance();
        $message = $mailer->get_sent();
        $this->assertNotFalse($message);
        $this->assertSame('recipient@example.org', $mailer->get_recipient('to')->address);
        $this->assertSame('Regression test', $message->subject);
        $this->assertStringContainsString('Message body.', $message->body);
        return $message;
    }

    private function assertSender($email, $name = 'WordPress', $headers = '')
    {
        $message = $this->send($headers);
        $mailer = tests_retrieve_phpmailer_instance();
        $this->assertSame($email, $mailer->From);
        $this->assertSame($name, $mailer->FromName);
        $this->assertStringContainsString('From: ' . $name . ' <' . $email . '>', $message->header);
        return $message;
    }

    public function test_registration_uses_early_priority()
    {
        $this->assertSame(1, has_filter('wp_mail', [StaticMailSenderConfigurator::class, 'wp_mail']));
    }

    public function test_absent_configuration_preserves_all_arguments()
    {
        $args = [
            'to' => 'recipient@example.org',
            'subject' => 'Subject',
            'message' => 'Body',
            'headers' => "From: Existing <existing@example.org>\r\nX-Test: unchanged",
            'attachments' => [],
            'custom' => ['keep' => true],
        ];
        $this->assertSame($args, apply_filters('wp_mail', $args));
        $this->assertSender('existing@example.org', 'Existing', $args['headers']);
    }

    public function test_absent_configuration_uses_wordpress_default_sender()
    {
        $this->assertSender('wordpress@example.org');
    }

    public function test_environment_address()
    {
        putenv('WP_MAIL_FROM=environment@example.org');
        $this->assertSender('environment@example.org');
    }

    public function test_constant_address()
    {
        define('WP_MAIL_FROM', 'constant@example.org');
        $this->assertSender('constant@example.org');
    }

    public function test_constant_takes_precedence_over_environment()
    {
        putenv('WP_MAIL_FROM=environment@example.org');
        define('WP_MAIL_FROM', 'constant@example.org');
        $this->assertSender('constant@example.org');
    }

    public function test_empty_constant_does_not_fall_back_to_environment()
    {
        putenv('WP_MAIL_FROM=environment@example.org');
        define('WP_MAIL_FROM', '');
        $args = apply_filters('wp_mail', ['headers' => []]);
        $this->assertSame(['From:'], $args['headers']);
        // WordPress ignores an empty From value and supplies its own default.
        $this->assertSender('wordpress@example.org');
    }

    public function test_empty_environment_is_distinct_from_missing_configuration()
    {
        putenv('WP_MAIL_FROM=');
        $args = apply_filters('wp_mail', ['headers' => []]);
        $this->assertSame(['From:'], $args['headers']);
        $this->assertSender('wordpress@example.org');
    }

    public function test_named_environment_sender()
    {
        putenv('WP_MAIL_FROM="Environment Sender" <environment@example.org>');
        $this->assertSender('environment@example.org', 'Environment Sender');
    }

    public function test_named_constant_sender()
    {
        define('WP_MAIL_FROM', '"Constant Sender" <constant@example.org>');
        $this->assertSender('constant@example.org', 'Constant Sender');
    }

    public function test_array_headers_preserve_custom_header_and_reply_to()
    {
        putenv('WP_MAIL_FROM=environment@example.org');
        $message = $this->assertSender('environment@example.org', 'WordPress', [
            'X-Regression: preserved',
            'Reply-To: reply@example.org',
        ]);
        $this->assertStringContainsString('X-Regression: preserved', $message->header);
        $this->assertStringContainsString('Reply-To: reply@example.org', $message->header);
    }

    public function test_crlf_string_headers_preserve_custom_header_and_reply_to()
    {
        putenv('WP_MAIL_FROM=environment@example.org');
        $message = $this->assertSender('environment@example.org', 'WordPress',
            "X-Regression: preserved\r\nReply-To: reply@example.org");
        $this->assertStringContainsString('X-Regression: preserved', $message->header);
        $this->assertStringContainsString('Reply-To: reply@example.org', $message->header);
    }

    public function test_lf_string_headers_and_existing_sender()
    {
        putenv('WP_MAIL_FROM=environment@example.org');
        $message = $this->assertSender('environment@example.org', 'WordPress',
            "From: original@example.org\nX-Regression: preserved");
        $this->assertStringContainsString('X-Regression: preserved', $message->header);
    }

    public function test_later_wp_mail_filter_can_override_sender()
    {
        putenv('WP_MAIL_FROM=environment@example.org');
        add_filter('wp_mail', static function ($args) {
            $args['headers'][] = 'From: Later Sender <later@example.org>';
            return $args;
        }, 10);
        $this->assertSender('later@example.org', 'Later Sender');
    }

    public function test_sender_filters_can_override_address_and_name()
    {
        define('WP_MAIL_FROM', '"Configured Sender" <constant@example.org>');
        add_filter('wp_mail_from', static function ($from) {
            return 'filtered@example.org';
        });
        add_filter('wp_mail_from_name', static function ($name) {
            return 'Filtered Sender';
        });
        $this->assertSender('filtered@example.org', 'Filtered Sender');
    }

    public function test_invalid_sender_reports_failure_without_sending()
    {
        define('WP_MAIL_FROM', 'not-an-email');
        $error = null;
        add_action('wp_mail_failed', static function ($failure) use (&$error) {
            $error = $failure;
        });
        $this->assertFalse(wp_mail('recipient@example.org', 'Invalid sender', 'Body'));
        $this->assertInstanceOf(WP_Error::class, $error);
        $this->assertSame('wp_mail_failed', $error->get_error_code());
        $this->assertNotSame('', $error->get_error_message());
        $this->assertFalse(tests_retrieve_phpmailer_instance()->get_sent());
    }
}
