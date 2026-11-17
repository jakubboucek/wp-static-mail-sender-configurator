# Admin diagnostics development plan

This temporary WIP plan describes agreed feature behavior and pending implementation stages. The plugin currently provides a Tools > Mail Config page with read-only configuration, sender-filter warnings, setup guidance, and test email submission, restricted to users with `manage_options`. Its regression suite protects existing mail behavior. The Docker development environment is documented in [Local development](development.md). Complete, verify, and commit each stage separately before proceeding. Replace this plan with documentation of the implemented behavior and executable development instructions when the work is complete.

## Agreed scope

- Preserve PHP 7.4 and WordPress 6.0 compatibility, the priority-1 `wp_mail` filter, and constant-over-environment precedence.
- Add Tools > Mail Config using `manage_options` for both page access and test submission. Use native WordPress UI and translatable English strings.
- Show configuration read-only: active source, value, validity, and setup guidance. Do not add database settings or change existing mail behavior through diagnostic validation.
- Detect callbacks on `wp_mail_from` and `wp_mail_from_name` using `has_filter()`. Explain that they may override the configured sender. Do not execute callbacks for preview, infer their return values, or identify their originating plugins.
- Accept one recipient address, initially populated from the current user's email. Use a fixed plain-text test message and the normal `wp_mail()` pipeline, including when plugin configuration is missing or invalid.
- Process POST and display its result in the same request. Check capability, nonce, and recipient before sending. Do not redirect or persist results. Refresh may repeat a submission; strict duplicate prevention is out of scope. Disabling the submit button with JavaScript is optional.
- Temporarily observe `wp_mail_succeeded` and `wp_mail_failed` during the test and remove observers afterward, including on exceptional exits. Combine observed events with the return value of `wp_mail()`; display relevant escaped error messages without dumping full error objects.
- Distinguish reported success, reported failure, and incomplete or inconsistent diagnostics. Missing events do not prove which code intercepted sending. Success does not prove delivery.
- Do not inspect PHPMailer or compare the final sender. The `headers` passed to `wp_mail_succeeded` do not contain the parsed `From` address or name. The recipient can inspect the received message.
- Do not add persistent history, global admin notices, a callback inventory, or a network admin page. Verify the page within an individual Multisite site's admin context.

## Test requirements for remaining work

Use the Docker environments described in [Local development](development.md). Run all PHP checks inside containers. Load the matching WordPress release in Nette Tester, pin compatible test dependencies for each PHP target, and isolate constant configuration scenarios in separate PHP processes. Use only the dedicated test database credentials for destructive test setup. Keep test dependencies outside `plugin/`.

## Repository layout

`plugin/` contains the distributable PHP file, WordPress readme, and license. The root license also covers the repository. Packaging and installed paths are described in [release documentation](releases.md).

`docker/`, `docker-compose.yml`, and `bin/dev` provide local development infrastructure. `tests/`, the root Composer files, and `bin/test` provide regression testing. These development files remain outside `plugin/`.

## Pending implementation stages

### 6. Verify compatibility and prepare release

Run the relevant regression and browser checks on baseline and modern targets, including site-level Multisite access, keyboard operation, translated-string readiness, and debug logs. Run Plugin Check on an environment supported by that tool. Test installation of the distribution ZIP in a clean local site as a final packaging check.

Acceptance: all agreed scenarios pass; documentation describes the implemented state; temporary planning notes are consolidated; distribution files exclude development tooling; plugin version, stable tag, and changelog are consistent. Publishing remains a separate explicit operation.

## WordPress testing references

- [WP-CLI plugin test scaffolding](https://developer.wordpress.org/cli/commands/scaffold/plugin-tests/) generates a PHPUnit bootstrap and WordPress test-suite installation script; review generated files rather than adopting unrelated tooling automatically.
- [wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) is an official local WordPress environment tool with Docker support and matching WordPress PHPUnit files. Custom Compose is used here to use the preferred LAMP images directly.
- [Plugin Check](https://wordpress.org/plugins/plugin-check/) checks WordPress.org requirements and common implementation issues. It complements functional tests; it does not establish correct mail behavior or compatibility with every supported version.
