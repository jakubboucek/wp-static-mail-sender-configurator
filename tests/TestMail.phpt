<?php

/**
 * Test email authorization, validation, outcomes, and observer cleanup.
 * @testCase
 */

use JakubBoucek\WpPlugin\MailSenderConfig\StaticMailSenderConfigurator as Plugin;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';

class TestMailTest extends Tester\TestCase
{
    protected function setUp()
    {
        wp_set_current_user(1);
        putenv('WP_MAIL_FROM=sender@example.org');
        reset_test_mailer();
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    /** @return array<string, mixed> */
    private function input(): array
    {
        return [
            'mail_config_nonce' => wp_create_nonce('mail_config_send_test'),
            'recipient' => 'recipient@example.org',
        ];
    }

    private function assertClean(): void
    {
        Assert::false(has_filter('wp_mail_succeeded'));
        Assert::false(has_filter('wp_mail_failed'));
    }

    private function render(): DOMDocument
    {
        ob_start();
        try {
            Plugin::render_admin_page();
            $html = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        if ($html === false) {
            throw new RuntimeException('Cannot read admin page output.');
        }
        $document = new DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">' . $html);
        return $document;
    }

    public function testSuccessAndMessage(): void
    {
        $result = Plugin::send_test_mail($this->input());
        Assert::same('success', $result['status']);
        Assert::same([], $result['errors']);
        $message = get_test_mailer()->getSent();
        if ($message === false) {
            throw new RuntimeException('No test message captured.');
        }
        Assert::same('Mail Config test email', $message->subject);
        Assert::same(
            'This is a test email from the Static Mail Sender Configurator plugin.'
            . ' Check the sender shown by your email client.',
            trim($message->body)
        );
        Assert::same('recipient@example.org', get_test_mailer()->getToAddresses()[0][0]);
        Assert::same('sender@example.org', get_test_mailer()->From);
        Assert::same('text/plain', get_test_mailer()->ContentType);
        $this->assertClean();
        Assert::true(wp_mail('later@example.org', 'Later message', 'Later body'));
        $this->assertClean();
    }

    public function testAbsentConfigurationStillSends(): void
    {
        putenv('WP_MAIL_FROM');
        Assert::same('success', Plugin::send_test_mail($this->input())['status']);
        Assert::same('wordpress@example.org', get_test_mailer()->From);
    }

    public function testInvalidSenderReportsFailure(): void
    {
        define('WP_MAIL_FROM', 'invalid');
        $result = Plugin::send_test_mail($this->input());
        Assert::same('error', $result['status']);
        Assert::same(1, count($result['errors']));
        Assert::notSame('', $result['errors'][0]);
        Assert::false(get_test_mailer()->getSent());
        $this->assertClean();
    }

    public function testInvalidRecipientsNeverSend(): void
    {
        $recipients = [
            '',
            'invalid',
            'one@example.org,two@example.org',
            "a@example.org\r\nBcc: b@example.org",
            ['a@example.org'],
        ];
        foreach ($recipients as $recipient) {
            $input = $this->input();
            $input['recipient'] = $recipient;
            Assert::same('error', Plugin::send_test_mail($input)['status']);
            Assert::false(get_test_mailer()->getSent());
            $this->assertClean();
        }
    }

    public function testInvalidNoncesNeverSend(): void
    {
        foreach (['invalid', null, []] as $nonce) {
            $input = $this->input();
            $input['mail_config_nonce'] = $nonce;
            Assert::same('error', Plugin::send_test_mail($input)['status']);
            Assert::false(get_test_mailer()->getSent());
            $this->assertClean();
        }
    }

    public function testUnauthorizedSubmission(): void
    {
        $input = $this->input();
        wp_set_current_user(0);
        Assert::same('error', Plugin::send_test_mail($input)['status']);
        Assert::false(get_test_mailer()->getSent());
        $this->assertClean();
    }

    public function testShortCircuitedSuccess(): void
    {
        add_filter('pre_wp_mail', static function () {
            return true;
        });
        Assert::same('warning', Plugin::send_test_mail($this->input())['status']);
        Assert::false(get_test_mailer()->getSent());
        $this->assertClean();
    }

    public function testShortCircuitedFailure(): void
    {
        add_filter('pre_wp_mail', static function () {
            return false;
        });
        Assert::same('warning', Plugin::send_test_mail($this->input())['status']);
        Assert::false(get_test_mailer()->getSent());
        $this->assertClean();
    }

    public function testConflictingFailureAndReturnValue(): void
    {
        add_filter('pre_wp_mail', static function () {
            do_action('wp_mail_failed', new WP_Error('test', 'Reported failure'));
            return true;
        });
        $result = Plugin::send_test_mail($this->input());
        Assert::same('warning', $result['status']);
        Assert::same(['Reported failure'], $result['errors']);
        $this->assertClean();
    }

    public function testConflictingSuccessAndReturnValue(): void
    {
        add_filter('pre_wp_mail', static function () {
            do_action('wp_mail_succeeded', []);
            return false;
        });
        Assert::same('warning', Plugin::send_test_mail($this->input())['status']);
        $this->assertClean();
    }

    public function testBothEvents(): void
    {
        add_filter('pre_wp_mail', static function () {
            do_action('wp_mail_succeeded', []);
            do_action('wp_mail_failed', new WP_Error('test', 'Conflicting failure'));
            return true;
        });
        Assert::same('warning', Plugin::send_test_mail($this->input())['status']);
        $this->assertClean();
    }

    public function testExceptionAndCleanup(): void
    {
        $callback = static function (): void {
            throw new RuntimeException('Transport exception');
        };
        add_action('phpmailer_init', $callback);
        $result = Plugin::send_test_mail($this->input());
        Assert::same('warning', $result['status']);
        Assert::same(['Transport exception'], $result['errors']);
        $this->assertClean();
        remove_action('phpmailer_init', $callback);
        Assert::same('success', Plugin::send_test_mail($this->input())['status']);
        $this->assertClean();
    }

    public function testOtherObserversRemainRegistered(): void
    {
        $calls = 0;
        $callback = static function () use (&$calls): void {
            $calls++;
        };
        add_action('wp_mail_succeeded', $callback, 20);
        Assert::same('success', Plugin::send_test_mail($this->input())['status']);
        Assert::same(20, has_filter('wp_mail_succeeded', $callback));
        Assert::same(1, $calls);
        remove_action('wp_mail_succeeded', $callback, 20);
        $this->assertClean();
    }

    public function testGetPrefillsCurrentUserWithoutSending(): void
    {
        $input = $this->render()->getElementById('mail-config-recipient');
        if (!$input instanceof DOMElement) {
            throw new RuntimeException('Recipient input missing.');
        }
        Assert::same(wp_get_current_user()->user_email, $input->getAttribute('value'));
        Assert::false(get_test_mailer()->getSent());
    }

    public function testPostDoesNotRequireSubmitButton(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $this->input();
        $notice = $this->render()->getElementById('mail-config-result');
        if (!$notice instanceof DOMElement) {
            throw new RuntimeException('Result notice missing.');
        }
        Assert::same('notice notice-success inline', $notice->getAttribute('class'));
        Assert::notSame(false, get_test_mailer()->getSent());
        $this->assertClean();
    }

    public function testErrorMessagesAreEscaped(): void
    {
        add_action('phpmailer_init', static function (): void {
            throw new RuntimeException('<script>alert("error")</script>');
        });
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $this->input();
        $document = $this->render();
        Assert::same(0, $document->getElementsByTagName('script')->length);
        $error = $document->getElementsByTagName('li')->item(0);
        if (!$error instanceof DOMElement) {
            throw new RuntimeException('Error detail missing.');
        }
        Assert::same('<script>alert("error")</script>', $error->textContent);
        $this->assertClean();
    }

    public function testInvalidRecipientIsEscapedAndPreserved(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $this->input();
        $_POST['recipient'] = '<script>alert("recipient")</script>';
        $document = $this->render();
        Assert::same(0, $document->getElementsByTagName('script')->length);
        $input = $document->getElementById('mail-config-recipient');
        if (!$input instanceof DOMElement) {
            throw new RuntimeException('Recipient input missing.');
        }
        Assert::same($_POST['recipient'], $input->getAttribute('value'));
        Assert::false(get_test_mailer()->getSent());
    }

    public function testUnauthorizedPostCannotRenderOrSend(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $this->input();
        wp_set_current_user(0);
        add_filter('wp_die_handler', static function () {
            return static function (): void {
                throw new RuntimeException('Access denied');
            };
        });
        Assert::exception(static function (): void {
            Plugin::render_admin_page();
        }, RuntimeException::class, 'Access denied');
        Assert::false(get_test_mailer()->getSent());
    }

    public function testRepeatedPostCanSendAgain(): void
    {
        $sent = 0;
        $observer = static function () use (&$sent): void {
            $sent++;
        };
        add_action('wp_mail_succeeded', $observer);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $this->input();
        $this->render();
        $this->render();
        Assert::same(2, $sent);
        remove_action('wp_mail_succeeded', $observer);
        $this->assertClean();
    }
}

(new TestMailTest())->run();
