<?php

/**
 * Read-only sender configuration diagnostics.
 * @testCase
 */

use JakubBoucek\WpPlugin\MailSenderConfig\StaticMailSenderConfigurator;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';

class ConfigurationTest extends Tester\TestCase
{
    /** @var string|false */
    private $originalEnvironment;

    protected function setUp()
    {
        $this->originalEnvironment = getenv('WP_MAIL_FROM');
        putenv('WP_MAIL_FROM');
    }

    protected function tearDown()
    {
        putenv($this->originalEnvironment === false ? 'WP_MAIL_FROM' : 'WP_MAIL_FROM=' . $this->originalEnvironment);
    }

    public function testMissing(): void
    {

        Assert::same(
            ['source' => 'none', 'value' => null, 'status' => 'missing'],
            StaticMailSenderConfigurator::get_configuration()
        );
    }

    public function testEnvironment(): void
    {
        putenv('WP_MAIL_FROM=env@example.org');
        Assert::same(
            ['source' => 'environment', 'value' => 'env@example.org', 'status' => 'valid'],
            StaticMailSenderConfigurator::get_configuration()
        );
    }

    public function testConstant(): void
    {
        define('WP_MAIL_FROM', 'constant@example.org');
        Assert::same(
            ['source' => 'constant', 'value' => 'constant@example.org', 'status' => 'valid'],
            StaticMailSenderConfigurator::get_configuration()
        );
    }

    public function testConstantPrecedence(): void
    {
        putenv('WP_MAIL_FROM=env@example.org');
        define('WP_MAIL_FROM', 'constant@example.org');
        Assert::same(
            ['source' => 'constant', 'value' => 'constant@example.org', 'status' => 'valid'],
            StaticMailSenderConfigurator::get_configuration()
        );
    }

    public function testEmptyConstant(): void
    {
        putenv('WP_MAIL_FROM=env@example.org');
        define('WP_MAIL_FROM', '');
        Assert::same(
            ['source' => 'constant', 'value' => '', 'status' => 'empty'],
            StaticMailSenderConfigurator::get_configuration()
        );
    }

    public function testEmptyEnvironment(): void
    {
        putenv('WP_MAIL_FROM=');
        Assert::same(
            ['source' => 'environment', 'value' => '', 'status' => 'empty'],
            StaticMailSenderConfigurator::get_configuration()
        );
    }

    public function testWhitespace(): void
    {
        putenv('WP_MAIL_FROM=   ');
        Assert::same(
            ['source' => 'environment', 'value' => '   ', 'status' => 'empty'],
            StaticMailSenderConfigurator::get_configuration()
        );
    }

    public function testMalformed(): void
    {
        putenv('WP_MAIL_FROM=valid@example.org');
        define('WP_MAIL_FROM', 'not-an-email');
        Assert::same(
            ['source' => 'constant', 'value' => 'not-an-email', 'status' => 'invalid'],
            StaticMailSenderConfigurator::get_configuration()
        );
    }

    public function testNamedSender(): void
    {
        define('WP_MAIL_FROM', '"Site Name" <sender@example.org>');
        Assert::same(
            ['source' => 'constant', 'value' => '"Site Name" <sender@example.org>', 'status' => 'valid'],
            StaticMailSenderConfigurator::get_configuration()
        );
    }

    public function testHeaderInjection(): void
    {
        define('WP_MAIL_FROM', "sender@example.org\r\nBcc: other@example.org");
        Assert::same(
            ['source' => 'constant', 'value' => "sender@example.org\r\nBcc: other@example.org", 'status' => 'invalid'],
            StaticMailSenderConfigurator::get_configuration()
        );
    }

    public function testNonString(): void
    {
        define('WP_MAIL_FROM', ['sender@example.org']);
        Assert::same(
            ['source' => 'constant', 'value' => ['sender@example.org'], 'status' => 'invalid'],
            StaticMailSenderConfigurator::get_configuration()
        );
    }

    public function testEscapedConfiguration(): void
    {
        define('WP_MAIL_FROM', '<script>alert("sender")</script>');
        wp_set_current_user(1);
        ob_start();
        try {
            StaticMailSenderConfigurator::render_admin_page();
            $html = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        if ($html === false) {
            throw new RuntimeException('Cannot read rendered configuration.');
        }
        $document = new DOMDocument();
        $document->loadHTML($html);
        Assert::same(0, $document->getElementsByTagName('script')->length);
        $heading = $document->getElementsByTagName('h1')->item(0);
        $value = $document->getElementsByTagName('code')->item(0);
        if ($heading === null || $value === null) {
            throw new RuntimeException('The configuration heading or value is missing.');
        }
        Assert::same('Mail Config', $heading->textContent);
        Assert::same('<script>alert("sender")</script>', $value->textContent);
    }
}

(new ConfigurationTest())->run();
