# Local development

All application runtimes and PHP tooling run in Docker. The host needs Docker with Compose and Bash; it does not need PHP, Apache, MySQL, Composer, or Node.js. The images target `linux/amd64` (ARM hosts require emulation).

## Environments

| Target | WordPress | PHP | Site | MailDev |
| --- | --- | --- | --- | --- |
| `modern` | 7.1.4 | 8.4.27 | http://localhost:8180 | http://localhost:8181 |
| `legacy` | 6.0 | 7.4.33 | http://localhost:8190 | http://localhost:8191 |

Both use `jakubboucek/lamp-devstack-php`, MariaDB 10.11.19 from `jakubboucek/lamp-devstack-mysql`, WP-CLI 2.12.0, and MailDev 3.0.0. The legacy PHP image is pinned by digest. Target versions and ports are configured in `docker/modern.env` and `docker/legacy.env`. PHP 8.4 is the modern baseline for the current tooling; PHP 8.5 is not part of this environment matrix.

`bin/dev` selects a Compose project (`static-mail-modern` or `static-mail-legacy`). Each has separate WordPress and database volumes and its own MailDev inbox. The database and SMTP ports are internal; HTTP ports bind only to localhost. Do not run unrelated projects under these project names.

## Start and stop

From the repository root:

```sh
bin/dev modern up -d --build --wait --wait-timeout 180
bin/dev legacy up -d --build --wait --wait-timeout 180
```

The first start downloads the pinned WordPress version, creates its configuration and database tables, creates local accounts, and activates the plugin. Subsequent starts preserve site data. Bootstrap refuses to run a different WordPress version against an existing core volume; use a separate project and volumes when changing core versions.

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

Use the corresponding `legacy` commands for the minimum-version site. `down` removes a target's containers and network but preserves its volumes; `down -v` also deletes that target's site and database data. MailDev has no persistent storage, so recreating its container clears its inbox. No commands affect the SVN checkout.

## Source files and configuration

`plugin/` is mounted read-only at `/var/www/html/www/wp-content/plugins/static-mail-sender-configurator`. Edit files in Git on the host; no copying or ZIP upload is needed. WordPress core, generated `wp-config.php`, and logs reside in the target's WordPress volume. Development tooling and MU plugins are outside the release directory.

The example environment sets `WP_MAIL_FROM=dev-sender@example.test` for both HTTP and CLI. The plugin still supports a constant in `wp-config.php`, which takes precedence. Change the Compose environment and recreate the web service to test environment configuration; remove the variable entirely to test absence (an empty variable is still present).

Debug logging is enabled, display is disabled, and automatic updates/file modifications are disabled to preserve the selected test versions. Read `wp-content/debug.log` inside the container when diagnosing a failure.

## Container commands

```sh
bin/dev modern exec -T web php -v
bin/dev modern exec -T web php -l wp-content/plugins/static-mail-sender-configurator/static-mail-sender-configurator.php
bin/dev modern exec -T web wp --allow-root core version
bin/dev modern exec -T web wp --allow-root plugin status static-mail-sender-configurator
```

WP-CLI is installed in the PHP image by `docker/Dockerfile`; the base LAMP image provides Composer. PHPUnit and the WordPress test suite are not installed yet and belong to the next implementation stage. The PHP image does not provide a database client for `wp db` commands; use the database container's `mariadb` command instead.

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

`docker/init-tests.sql` creates the test account on initial database-volume creation. It is not reapplied to an already initialized volume. Modern and legacy databases also remain isolated from each other by separate containers and volumes.
