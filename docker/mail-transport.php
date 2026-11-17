<?php
/**
 * Development-only transport. Never include this file in a release.
 */
add_action('phpmailer_init', static function ($mailer) {
    $mailer->isSMTP();
    $mailer->Host = 'maildev';
    $mailer->Port = 1025;
    $mailer->SMTPAuth = false;
    $mailer->SMTPSecure = '';
    $mailer->SMTPAutoTLS = false;
});
