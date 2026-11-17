# Admin diagnostics development plan

This temporary WIP plan describes agreed feature behavior and pending implementation stages. The plugin currently has no admin page or automated test environment. Complete, verify, and commit each stage separately before proceeding. Replace this plan with documentation of the implemented behavior and executable development instructions when the work is complete.

## Agreed scope

- Preserve PHP 7.4 and WordPress 6.0 compatibility, the priority-1 `wp_mail` filter, and constant-over-environment precedence.
- Add Tools > Mail Sender using `manage_options` for both page access and test submission. Use native WordPress UI and translatable English strings.
- Show configuration read-only: active source, value, validity, and setup guidance. Do not add database settings or change existing mail behavior through diagnostic validation.
- Detect callbacks on `wp_mail_from` and `wp_mail_from_name` using `has_filter()`. Explain that they may override the configured sender. Do not execute callbacks for preview, infer their return values, or identify their originating plugins.
- Accept one recipient address, initially populated from the current user's email. Use a fixed plain-text test message and the normal `wp_mail()` pipeline, including when plugin configuration is missing or invalid.
- Process POST and display its result in the same request. Check capability, nonce, and recipient before sending. Do not redirect or persist results. Refresh may repeat a submission; strict duplicate prevention is out of scope. Disabling the submit button with JavaScript is optional.
- Temporarily observe `wp_mail_succeeded` and `wp_mail_failed` during the test and remove observers afterward, including on exceptional exits. Combine observed events with the return value of `wp_mail()`; display relevant escaped error messages without dumping full error objects.
- Distinguish reported success, reported failure, and incomplete or inconsistent diagnostics. Missing events do not prove which code intercepted sending. Success does not prove delivery.
- Do not inspect PHPMailer or compare the final sender. The `headers` passed to `wp_mail_succeeded` do not contain the parsed `From` address or name. The recipient can inspect the received message.
- Do not add persistent history, global admin notices, a callback inventory, or a network admin page. Verify the page within an individual Multisite site's admin context.

## Proposed test environment (not yet implemented)

Use a project-specific Docker Compose environment based on [docker-lamp-devstack](https://github.com/jakubboucek/docker-lamp-devstack). Run PHP, Apache, MariaDB, WP-CLI, Composer, and PHPUnit inside containers; do not use host runtime services or host PHP for checks.

- Web: `jakubboucek/lamp-devstack-php`, with explicitly selected PHP versions. The PHP 7.4 variant uses the `7.4-legacy` tag. Images target `linux/amd64`; account for emulation on ARM hosts.
- Database: `jakubboucek/lamp-devstack-mysql`, with an explicitly selected MariaDB version. Use separate databases and credentials for interactive development and destructive automated tests.
- Mail capture: [MailDev](https://maildev.github.io/maildev/), reachable via SMTP only within the Docker network, with its browser UI and MCP endpoint bound to localhost. Pin a release with built-in MCP support and enable it explicitly. Configure the agent connection separately; starting the service does not automatically connect an MCP client. Do not configure external forwarding.
- Store WordPress core, databases, and generated files in dedicated volumes or ignored development directories. Mount the distribution directory `plugin/` at `wp-content/plugins/static-mail-sender-configurator` so edits appear immediately without ZIP uploads. Prefer a read-only plugin mount for the web service.
- Configure Apache's document root explicitly using `APACHE_DOCUMENT_ROOT`; the image default is `/var/www/html/www`.
- Bootstrap WordPress and local test accounts using a pinned compatible WP-CLI version inside the PHP container. Do not assume that the LAMP image already contains WP-CLI.
- Route local mail to MailDev using a development-only MU plugin that configures SMTP transport through `phpmailer_init`, without changing the sender. This is test infrastructure, not production plugin diagnostics, and must not enter the release package. Verify both HTTP and CLI test paths use this transport.
- Enable WordPress debug logging in development, with logs excluded from Git and release artifacts.
- Start with WordPress 6.0 / PHP 7.4 and the current stable WordPress / a supported modern PHP version. Pin exact versions when implementing the environment. Use separate volumes for each matrix target; do not downgrade a shared WordPress database.
- Use the WordPress PHPUnit test framework matching the selected core version. Pin compatible PHPUnit and PHPUnit Polyfills dependencies per target if necessary. Run constant configuration scenarios in isolated PHP processes because constants cannot be undefined.
- Keep WordPress core and development dependencies out of the distributed plugin. Add release exclusions or an explicit packaging allowlist when introducing development files.

## Repository layout

`plugin/` contains the distributable PHP file, WordPress readme, and license. The root license also covers the repository. Packaging and installed paths are described in [release documentation](releases.md).

The planned environment will add `tests/`, `docker/`, and `compose.yaml` at the repository root. These development files must remain outside `plugin/`.

## Pending implementation stages

### 0. Establish the Docker test environment

Create the Compose configuration, reproducible setup commands, isolated databases, WordPress installations, and local mail capture described above. Add executable setup/test instructions only after verifying them.

Acceptance: a clean setup starts successfully; the plugin is activated directly from the source mount; HTTP and CLI use the selected container PHP; test mail reaches MailDev; database reset does not affect the interactive site or other projects; no host runtime services are used.

### 1. Protect existing behavior with tests

Add focused regression tests using actual WordPress hooks and mail processing where appropriate. Cover absent configuration, environment-only, constant-only, both sources, empty constants, string and array headers, sender names, and later sender overrides. Do not replace WordPress integration with mocks alone.

Acceptance: tests pass on the baseline and modern targets before changing runtime behavior. Test fixtures restore environment variables and hooks; constant scenarios remain isolated.

### 2. Add the admin page and access controls

Register Tools > Mail Sender with `manage_options`. Keep the initial page minimal and use native WordPress markup.

Acceptance: authorized users can open it; unauthorized users cannot see the menu or access the URL directly; existing mail tests still pass.

### 3. Display configuration status

Show the effective configuration source and value, diagnostic validity, precedence, and setup instructions. Keep diagnostics separate from changes to the existing runtime contract.

Acceptance: verify absent, constant-only, environment-only, both-source, empty, malformed, and named-address cases. Escape HTML-sensitive values. A defined empty constant must not silently fall back to the environment.

### 4. Explain registered sender filters

Detect `wp_mail_from` and `wp_mail_from_name` callbacks without invoking them. Warn about possible overrides rather than claiming a conflict or a known resulting value.

Acceptance: verify neither, either, and both hooks. Opening the page must not execute these callbacks.

### 5. Implement test submission and result reporting

Add the recipient form, POST validation, test message, temporary outcome observers, and immediate result display. Keep execution independent of whether a submit button's value is present.

Acceptance: verify success with captured mail, detailed failure, invalid recipient, rejected nonce, unauthorized POST, and `pre_wp_mail` returning either true or false without standard outcome events. Handle inconsistent observations as incomplete diagnostics. Ensure observers are removed and later emails are unaffected. Inspect the captured recipient, sender, subject, and body in MailDev. Check browser behavior after refresh and resubmission.

### 6. Verify compatibility and prepare release

Run the relevant regression and browser checks on baseline and modern targets, including site-level Multisite access, keyboard operation, translated-string readiness, and debug logs. Run Plugin Check on an environment supported by that tool. Test installation of the distribution ZIP in a clean local site as a final packaging check.

Acceptance: all agreed scenarios pass; documentation describes the implemented state; temporary planning notes are consolidated; distribution files exclude development tooling; plugin version, stable tag, and changelog are consistent. Publishing remains a separate explicit operation.

## WordPress testing references

- [WP-CLI plugin test scaffolding](https://developer.wordpress.org/cli/commands/scaffold/plugin-tests/) generates a PHPUnit bootstrap and WordPress test-suite installation script; review generated files rather than adopting unrelated tooling automatically.
- [wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) is an official local WordPress environment tool with Docker support and matching WordPress PHPUnit files. Custom Compose is proposed here to use the preferred LAMP images directly.
- [Plugin Check](https://wordpress.org/plugins/plugin-check/) checks WordPress.org requirements and common implementation issues. It complements functional tests; it does not establish correct mail behavior or compatibility with every supported version.
