=== Static Mail Sender Configurator ===
Contributors: jakubboucek
Tags: wp_mail, sender, mail from, mail sender
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 0.10.1
Requires PHP: 7.4.0
License: MIT
License URI: https://github.com/jakubboucek/wp-static-mail-sender-configurator/blob/master/LICENSE

Simple & static configure WordPress internal mailer sender's (`From:`) address.

This plugin is very simple, it doesn't using database or another storage - you just add the constant `WP_MAIL_FROM` to `wp-config.php` or to Environment variable.

Plugin is define this configuration with major priority to set configuration as the **default value**, that means any orher plugin with lower priority applied afterwards can simple rewrite that value.

== Administration ==

Open Tools > Mail Config to inspect the active configuration source, value, and sender format, and view setup instructions. Access requires the `manage_options` capability.

Registered `wp_mail_from` and `wp_mail_from_name` filters trigger warnings about possible sender overrides. The page does not execute these filters or determine their results.

The page is read-only. The PHP constant takes precedence over the environment variable, even if it is empty or invalid. Diagnostic checks do not change mail handling or verify delivery or the final sender.

== Example ==

= Using constant in `wp-config.php` file: =

Add this row to `wp-config.php` file:

    const WP_MAIL_FROM = 'noreply@wordpress.domain.tld';


You can define sender's name too by format:

    "Name Lastname" <noreply@wordpress.domain.tld>

= Using Environment variable: =

Same principe is available by define Environment variable `WP_MAIL_FROM`.

== Changelog ==

= 0.10.1 =

- Verified mail configuration compatibility with WordPress 7.1.4 on PHP 8.5.12 using isolated tests of WordPress mail functions and PHPMailer.
- Updated the tested WordPress version to 7.1. No runtime behavior changes.

= 0.10.0 =

- Plugin now supports WordPress version up to 6.3
- Plugin now requires WordPress version at least 6.0
- Plugin now requires minimal PHP version 7.4

= 0.9.3 =

- Plugin now supports WordPress version 5.8

= 0.9.2 =

- Fixes plugin localization requirements
- Plugin now requires WordPress version at least 4.9.6

= 0.9.1 =

- Fixes readme documentation only

= 0.9.0 =

- First release to public WordPress Plugin Directory
