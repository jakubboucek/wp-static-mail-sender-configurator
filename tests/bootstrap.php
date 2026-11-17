<?php

require_once '/opt/test-tools/vendor/autoload.php';
Tester\Environment::setup();

require '/opt/wordpress/wp-load.php';
// Tester owns its output buffer; WordPress must not flush it during shutdown.
remove_action('shutdown', 'wp_ob_end_flush_all', 1);
require dirname(__DIR__) . '/plugin/static-mail-sender-configurator.php';
require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';

// Keep real WordPress filtering and MIME construction; replace only delivery.
class CapturedMailer extends PHPMailer\PHPMailer\PHPMailer
{
    private $sent = false;

    public function postSend()
    {
        $this->sent = (object) [
            'headers' => preg_split('/\r?\n/', trim($this->MIMEHeader . $this->mailHeader)),
            'subject' => $this->Subject,
            'body' => $this->MIMEBody,
        ];
        return true;
    }

    public function getSent()
    {
        return $this->sent;
    }
}

function get_test_mailer(): CapturedMailer
{
    return $GLOBALS['phpmailer'];
}

function reset_test_mailer(): void
{
    $GLOBALS['phpmailer'] = new CapturedMailer(true);
    CapturedMailer::$validator = static function ($email) {
        return (bool) is_email($email);
    };
}
