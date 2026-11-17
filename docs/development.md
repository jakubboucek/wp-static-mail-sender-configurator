# Local development

All application runtimes and PHP tooling run in Docker. The host needs Docker with Compose and Bash; it does not need PHP, Apache, MySQL, Composer, or Node.js. The images target `linux/amd64` (ARM hosts require emulation).

## Environments

| Target | WordPress | PHP | Site | MailDev |
| --- | --- | --- | --- | --- |
| `modern` | 7.1.4 | 8.4.27 | http://localhost:8180 | http://localhost:8181 |
| `legacy` | 6.0 | 7.4.33 | http://localhost:8190 | http://localhost:8191 |

Both use `jakubboucek/lamp-devstack-php`, MariaDB 10.11.19 from `jakubboucek/lamp-devstack-mysql`, WP-CLI 2.12.0, and MailDev 3.0.0. The legacy PHP image is pinned by digest. Target versions and ports are configured in `docker/modern.env` and `docker/legacy.env`. PHP 8.4 is the modern baseline for the current tooling; PHP 8.5 is not part of this environment matrix.

`bin/dev` uses `docker-compose.yml` and selects a Compose project (`static-mail-modern` or `static-mail-legacy`). Each has separate WordPress and database bind mounts under `docker/data/<target>/` and its own MailDev inbox. The database and SMTP ports are internal; HTTP ports bind only to localhost. Do not run unrelated projects under these project names.

## Start and stop

From the repository root:

```sh
bin/dev modern up -d --build --wait --wait-timeout 180
bin/dev legacy up -d --build --wait --wait-timeout 180
```

The first start downloads the pinned WordPress version, creates its configuration and database tables, creates local accounts, and activates the plugin. Subsequent starts preserve site data. Bootstrap refuses to run a different WordPress version against an existing core directory; use a separate project and data directory when changing core versions.

Local-only accounts (identical on both sites):

| Username | Password | Role |
| --- | --- | --- |
| `admin` | `local-admin-password` | Administrator |
| `subscriber` | `local-subscriber-password` | Subscriber |

Open `/wp-admin/` on the selected site to log in. These credentials are exclusively for this disposable local environment.

```sh
bin/dev modern ps
bin/dev modern logs --tail=50 web
bin/dev modern stop
bin/dev modern start
```

Use the corresponding `legacy` commands for the minimum-version site. `down` removes a target's containers and network but preserves its bind-mounted data. `down -v` does not delete these host directories. To reset a target, stop and remove its containers before explicitly removing its `docker/data/<target>/` directory. MailDev has no persistent storage, so recreating its container clears its inbox. No commands affect the SVN checkout.

## Persistent data and backups

`docker/data/` is excluded from Git and Docker build contexts. Each target stores WordPress files in `wordpress/` and MariaDB files in `mysql/`. Stop the target with `bin/dev <target> stop` before copying its whole data directory for a consistent filesystem backup; preserve file ownership and permissions. Start it again after copying. Restore backups while containers are stopped. MailDev inboxes are ephemeral.

## Source files and configuration

`plugin/` is mounted read-only at `/var/www/html/www/wp-content/plugins/static-mail-sender-configurator`. Edit files in Git on the host; no copying or ZIP upload is needed. WordPress core, generated `wp-config.php`, and logs reside in `docker/data/<target>/wordpress/`. Development tooling and MU plugins are outside the release directory.

The example environment sets `WP_MAIL_FROM=dev-sender@example.test` for both HTTP and CLI. The plugin still supports a constant in `wp-config.php`, which takes precedence. Change the Compose environment and recreate the web service to test environment configuration; remove the variable entirely to test absence (an empty variable is still present).

Debug logging is enabled, display is disabled, and automatic updates/file modifications are disabled to preserve the selected test versions. Read `wp-content/debug.log` inside the container when diagnosing a failure.

## Container commands

```sh
bin/dev modern exec -T web php -v
bin/dev modern exec -T web php -l wp-content/plugins/static-mail-sender-configurator/static-mail-sender-configurator.php
bin/dev modern exec -T web wp --allow-root core version
bin/dev modern exec -T web wp --allow-root plugin status static-mail-sender-configurator
```

WP-CLI is installed in the PHP image by `docker/Dockerfile`; the base LAMP image provides Composer. Nette Tester and a separate WordPress installation are installed in a separate, disposable `tests` service; see the regression test commands below. The PHP image does not provide a database client for `wp db` commands; use the database container's `mariadb` command instead.

## Mail capture and smoke checks

`docker/mail-transport.php` is mounted as a development-only MU plugin. It directs PHPMailer to `maildev:1025` without authentication, TLS, or changes to the sender. No external relay is configured. This transport applies to web and CLI requests and must never enter the release ZIP.

Send a local smoke-test message:

```sh
bin/dev modern exec -T web wp --allow-root eval 'if (!wp_mail("recipient@example.test", "CLI smoke test", "Local capture test.")) { exit(1); }'
```

Inspect the message in MailDev: recipient `recipient@example.test`, sender `dev-sender@example.test`, subject `CLI smoke test`, and body `Local capture test.`. For an HTTP-path check, open the site's login page and request a password reset for `admin`; its message should arrive in the same local inbox. This does not change the password unless the reset link is followed and submitted. Repeat with `legacy` to check the minimum-version environment.

MailDev exposes its REST API at `/api/email` and its MCP endpoint at `/mcp`. MCP is explicitly enabled through `MAILDEV_MCP`. Client URLs are:

- Modern: `http://localhost:8181/mcp`
- Legacy: `http://localhost:8191/mcp`

The endpoint is available for a local MCP client, but starting Docker does not register it with an agent automatically. Agent connection settings are separate from this repository. A remotely hosted client cannot access these localhost endpoints. REST API inspection remains available if an MCP client is not connected.

## Database isolation

Inside each project's network, host `db` provides:

| Purpose | Database and username | Password |
| --- | --- | --- |
| Interactive site | `wordpress` | `local-wordpress` |
| Automated tests | `wordpress_tests` | `local-tests` |

Each account has privileges only on its own database. Use the test account for destructive test-suite setup, never the site or root account. The root password `local-root` is only for administering the disposable container.

To reset only the modern test database:

```sh
bin/dev modern exec -T db mariadb -uwordpress_tests -plocal-tests \
    -e 'DROP DATABASE wordpress_tests; CREATE DATABASE wordpress_tests;'
```

`docker/init-tests.sql` creates the test account on initial database-directory creation. It is not reapplied to an already initialized database directory. Modern and legacy databases remain isolated by separate containers and bind mounts.

## Automated regression tests

Run either target from the repository root:

```sh
bin/test legacy
bin/test modern
```

`bin/test` builds the test image using Docker's cache and runs Nette Tester in a disposable container. It starts the target's database if needed, but does not start or recreate the web service or MailDev. The `tests` Compose service belongs to an opt-in profile, so normal `up` commands do not launch tests.

The modern image uses Nette Tester 2.6.3, pinned in the root Composer files. The legacy image uses Tester 2.4.3, pinned in `tests/tools/legacy/`, to support PHP 7.4. Both run the same suite. These development dependencies remain outside `plugin/`. WP-CLI downloads the target's WordPress release and installs it into `wordpress_tests` using only the restricted test account, with the `tester_` table prefix. The bootstrap loads real WordPress through `wp-load.php`, then loads the plugin.

Core files are inside the disposable test container, separate from the interactive site. Tests run sequentially (`-j 1`). Do not run two suites for the same target simultaneously: they share its test database. Modern and legacy can run independently. The test database persists across runs; tests that modify database state must clean up their fixtures.

The `@testCase` annotation runs every method in a separate PHP process, allowing each scenario to define `WP_MAIL_FROM` independently. The test fixture restores environment variables and hooks and resets the mailer after each test.

The bootstrap disables WordPress's shutdown buffer flush so Tester can manage its own output buffer. Failure artifacts are written to the ignored `tests/output/` directory, mounted writable while test sources remain read-only.

The suite covers:

- Early filter registration and unchanged arguments when configuration is absent.
- Default WordPress sender, constant/environment configuration, precedence, and empty values.
- Named senders, array headers, LF/CRLF strings, custom headers, and Reply-To preservation.
- Overrides by later `wp_mail`, `wp_mail_from`, and `wp_mail_from_name` callbacks.
- Invalid sender failure and the `wp_mail_failed` event.

Mail tests exercise real WordPress filtering and PHPMailer message construction. The test `CapturedMailer` subclass replaces only PHPMailer transport, capturing MIME headers and body for assertions. They do not deliver messages to MailDev or external addresses; use the smoke checks above to test SMTP transport. A passing suite does not prove external deliverability.

To list runner options:

```sh
bin/test modern --help
```

PHP and WordPress test dependencies live in containers. An IDE without those libraries configured as external sources may report unresolved WordPress/Tester symbols; the container test run is the executable check.

## Code quality checks

Run `bin/check` from the repository root. It builds the modern `checks` image and runs DressCode followed by PHPStan without starting WordPress, MariaDB, or MailDev. Sources are mounted read-only; checks do not rewrite files. Dependencies are pinned in the root Composer lock file and remain outside the plugin distribution.

`dresscode.neon` applies PSR-12 to `plugin/`, PHP and PHPT files in `tests/`, and the PHP helpers in `docker/` listed in the configuration. Plugin methods use snake_case to preserve the public `wp_mail` callback name. The target is explicitly PHP 7.4, independently of the modern runtime used by the tool. Modernization sets and risky fixes are not enabled. DressCode reports that its own tested target range starts at PHP 8.0; integration tests on PHP 7.4 remain required after code changes.

`phpstan.neon` checks the same source paths at level 8 with `phpVersion: 70400`, without a baseline. It uses `szepeviktor/phpstan-wordpress` and its WordPress stubs for hooks and API symbols. PHPMailer is a development-only dependency for static analysis of the captured test transport; integration tests explicitly load the PHPMailer bundled with their WordPress release. Static checks do not execute the integration-test bootstrap or connect to a database. `docker/phpstan-bootstrap.php` loads the legacy Tester autoloader so PHPStan sees PHP 7.4-compatible signatures and defines the WordPress include-directory constant. One narrowly scoped exception permits the reference to `/opt/wordpress/wp-load.php`, which exists only in the integration-test image; other missing includes remain errors.

The configuration uses dependency paths inside the checks image. Current WordPress stubs describe a modern API, so a clean PHPStan result does not establish WordPress 6.0 compatibility. Run `bin/test legacy` and `bin/test modern` after code changes. The checks command returns a nonzero exit code on failure and can be used in CI.

## Admin page

Tools > Mail Sender opens `wp-admin/tools.php?page=static-mail-sender-configurator`. The menu and rendering callback require `manage_options`. The page currently contains only the translated, escaped heading in WordPress's native wrapper. It does not display configuration or send test messages yet.

For browser access checks, use both development targets: the local administrator can see the menu and open the page, while the subscriber has no menu entry and receives HTTP 403 at the direct URL. Use separate browser contexts to preserve the administrator's session.
