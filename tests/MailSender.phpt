<?php

/**
 * Mail sender integration with real WordPress; each method runs in its own process.
 * @testCase
 */

use JakubBoucek\WpPlugin\MailSenderConfig\StaticMailSenderConfigurator;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';

class MailSenderTest extends Tester\TestCase
{
    private $originalEnvironment;
    private $originalHooks;

    protected function setUp()
    {
        $this->originalHooks = array_map(static function ($hook) {
            return clone $hook;
        }, $GLOBALS['wp_filter']);
        $this->originalEnvironment = getenv('WP_MAIL_FROM');
        putenv('WP_MAIL_FROM');
        reset_test_mailer();
    }

    protected function tearDown()
    {
        if ($this->originalEnvironment === false) {
            putenv('WP_MAIL_FROM');
        } else {
            putenv('WP_MAIL_FROM=' . $this->originalEnvironment);
        }
        reset_test_mailer();
        $GLOBALS['wp_filter'] = $this->originalHooks;
    }

    private function send($headers = '')
    {
        Assert::true(wp_mail('recipient@example.org', 'Regression test', 'Message body.', $headers));
        $mailer = get_test_mailer();
        $message = $mailer->getSent();
        Assert::notSame(false, $message);
        Assert::same('recipient@example.org', $mailer->getToAddresses()[0][0]);
        Assert::same('Regression test', $message->subject);
        Assert::same('Message body.', trim($message->body));
        return $message;
    }

    private function assertSender($email, $name = 'WordPress', $headers = '')
    {
        $message = $this->send($headers);
        $mailer = get_test_mailer();
        Assert::same($email, $mailer->From);
        Assert::same($name, $mailer->FromName);
        Assert::contains('From: ' . $name . ' <' . $email . '>', $message->headers);
        return $message;
    }

    public function testRegistrationUsesEarlyPriority()
    {
        Assert::same(1, has_filter('wp_mail', [StaticMailSenderConfigurator::class, 'wp_mail']));
    }

    public function testAbsentConfigurationPreservesAllArguments()
    {
        $args = [
            'to' => 'recipient@example.org',
            'subject' => 'Subject',
            'message' => 'Body',
            'headers' => "From: Existing <existing@example.org>\r\nX-Test: unchanged",
            'attachments' => [],
            'custom' => ['keep' => true],
        ];
        Assert::same($args, apply_filters('wp_mail', $args));
        $this->assertSender('existing@example.org', 'Existing', $args['headers']);
    }

    public function testAbsentConfigurationUsesWordpressDefaultSender()
    {
        $this->assertSender('wordpress@example.org');
    }

    public function testEnvironmentAddress()
    {
        putenv('WP_MAIL_FROM=environment@example.org');
        $this->assertSender('environment@example.org');
    }

    public function testConstantAddress()
    {
        define('WP_MAIL_FROM', 'constant@example.org');
        $this->assertSender('constant@example.org');
    }

    public function testConstantTakesPrecedenceOverEnvironment()
    {
        putenv('WP_MAIL_FROM=environment@example.org');
        define('WP_MAIL_FROM', 'constant@example.org');
        $this->assertSender('constant@example.org');
    }

    public function testEmptyConstantDoesNotFallBackToEnvironment()
    {
        putenv('WP_MAIL_FROM=environment@example.org');
        define('WP_MAIL_FROM', '');
        $args = apply_filters('wp_mail', ['headers' => []]);
        Assert::same(['From:'], $args['headers']);
        // WordPress ignores an empty From value and supplies its own default.
        $this->assertSender('wordpress@example.org');
    }

    public function testEmptyEnvironmentIsDistinctFromMissingConfiguration()
    {
        putenv('WP_MAIL_FROM=');
        $args = apply_filters('wp_mail', ['headers' => []]);
        Assert::same(['From:'], $args['headers']);
        $this->assertSender('wordpress@example.org');
    }

    public function testNamedEnvironmentSender()
    {
        putenv('WP_MAIL_FROM="Environment Sender" <environment@example.org>');
        $this->assertSender('environment@example.org', 'Environment Sender');
    }

    public function testNamedConstantSender()
    {
        define('WP_MAIL_FROM', '"Constant Sender" <constant@example.org>');
        $this->assertSender('constant@example.org', 'Constant Sender');
    }

    public function testArrayHeadersPreserveCustomHeaderAndReplyTo()
    {
        putenv('WP_MAIL_FROM=environment@example.org');
        $message = $this->assertSender('environment@example.org', 'WordPress', [
            'X-Regression: preserved',
            'Reply-To: reply@example.org',
        ]);
        Assert::contains('X-Regression: preserved', $message->headers);
        Assert::contains('Reply-To: reply@example.org', $message->headers);
    }

    public function testCrlfStringHeadersPreserveCustomHeaderAndReplyTo()
    {
        putenv('WP_MAIL_FROM=environment@example.org');
        $message = $this->assertSender(
            'environment@example.org',
            'WordPress',
            "X-Regression: preserved\r\nReply-To: reply@example.org"
        );
        Assert::contains('X-Regression: preserved', $message->headers);
        Assert::contains('Reply-To: reply@example.org', $message->headers);
    }

    public function testLfStringHeadersAndExistingSender()
    {
        putenv('WP_MAIL_FROM=environment@example.org');
        $message = $this->assertSender(
            'environment@example.org',
            'WordPress',
            "From: original@example.org\nX-Regression: preserved"
        );
        Assert::contains('X-Regression: preserved', $message->headers);
    }

    public function testLaterWpMailFilterCanOverrideSender()
    {
        putenv('WP_MAIL_FROM=environment@example.org');
        add_filter('wp_mail', static function ($args) {
            $args['headers'][] = 'From: Later Sender <later@example.org>';
            return $args;
        }, 10);
        $this->assertSender('later@example.org', 'Later Sender');
    }

    public function testSenderFiltersCanOverrideAddressAndName()
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

    public function testInvalidSenderReportsFailureWithoutSending()
    {
        define('WP_MAIL_FROM', 'not-an-email');
        $error = null;
        add_action('wp_mail_failed', static function ($failure) use (&$error) {
            $error = $failure;
        });
        Assert::false(wp_mail('recipient@example.org', 'Invalid sender', 'Body'));
        Assert::type(WP_Error::class, $error);
        Assert::same('wp_mail_failed', $error->get_error_code());
        Assert::notSame('', $error->get_error_message());
        Assert::false(get_test_mailer()->getSent());
    }
}

(new MailSenderTest())->run();
