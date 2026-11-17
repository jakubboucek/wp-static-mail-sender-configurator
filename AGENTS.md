# Project guidelines

## Development and releases

- This Git repository is the development source of truth. Make code and documentation changes here.
- WordPress Plugin Directory releases use a separate SVN working copy. Regular development does not include synchronizing or publishing releases; perform those operations only when explicitly requested.
- See [SVN working copy setup](docs/releases.md) for repository access and directory layout. Local checkout paths are developer-specific and are not part of the project configuration.
- See [Local development](docs/development.md) for Docker setup, accounts, mail capture, MCP endpoints, and database isolation.
- See the [admin diagnostics development plan](docs/admin-diagnostics-plan.md) for the staged feature work and test requirements. This is a temporary WIP plan; consolidate it into current-state documentation when the work is complete.

## Language and documentation

- Write all repository-related content exclusively in English, including code, comments, documentation, commit messages, issues, pull requests, and release notes. Chat communication may use the user's preferred language.
- Keep documentation factual and focused on the current code and project. Update or remove obsolete descriptions and abandoned plans instead of accumulating historical notes.
- Record change history, implementation events, and decision or analysis notes in commit messages, pull requests, or release notes as appropriate, rather than in project reference documentation.
- For substantial work spanning multiple stages, temporary work-in-progress notes may document outstanding steps. Consolidate them into current-state documentation and remove obsolete notes when the work is complete.
- Store lasting code and project knowledge in this file or linked files under `docs/`. User memory is reserved for personal preferences and feedback, never project-specific knowledge.

## Structure and behavior

- `plugin/static-mail-sender-configurator.php` contains the entire plugin: metadata, the `JakubBoucek\WpPlugin\MailSenderConfig\StaticMailSenderConfigurator` class, and registration when the file is loaded.
- `plugin/readme.txt` contains the description, configuration examples, compatibility metadata, and changelog in WordPress Plugin Directory format.
- `LICENSE` contains the repository MIT license; `plugin/LICENSE` is its identical distribution copy. Keep both copies in sync.
- `plugin/` is the distribution root. Mount it as `wp-content/plugins/static-mail-sender-configurator`; package its contents under `static-mail-sender-configurator/` in the ZIP and directly under SVN `trunk/`.
- The plugin registers a `wp_mail` filter at priority `1`, allowing later filters to override the default sender. Preserve this early priority.
- `WP_MAIL_FROM` is read from a PHP constant or an environment variable. The constant takes precedence. If neither is defined, the filter returns its input unchanged.
- The filter converts string headers into an array and appends a `From:` header. The configured value may include both a sender name and an email address.
- Tools > Mail Config is registered through `admin_menu` and requires `manage_options`. The rendering callback also checks the capability. The page currently displays only its translated, escaped heading. It has no configuration display or test submission yet.
- The plugin has no database storage or external runtime dependencies.

## Compatibility and validation

- The declared minimum versions are PHP 7.4.0 and WordPress 6.0. Do not introduce newer PHP syntax without an intentional change to supported versions.
- When changing the release version, keep the PHP header's `Version`, the `Stable tag` in `plugin/readme.txt`, and the changelog consistent. PHP and WordPress requirements appear in both files and must remain consistent as well.
- The root Composer files and `tests/tools/legacy/` contain development-only test dependencies for modern PHP and PHP 7.4 respectively. The root dependencies also include DressCode and PHPStan with WordPress support; run `bin/check` for read-only style and level-8 static analysis with an explicit PHP 7.4 target. Run `bin/test legacy` and `bin/test modern` for the WordPress integration suite. There is no frontend build. Check syntax with `bin/dev modern exec -T web php -l wp-content/plugins/static-mail-sender-configurator/static-mail-sender-configurator.php` (also run with `legacy`).
- For behavior changes, verify missing configuration, constant-only configuration, environment-only configuration, and both sources together; string and array headers; and subsequent sender overrides. Verify integration in WordPress: syntax checks alone do not establish correct behavior.
- Run the development site, database, PHP checks, and test tooling in Docker. Do not use host PHP, Apache, or MySQL for this development environment. Prefer the `jakubboucek/docker-lamp-devstack` images. WordPress core and generated environment data must remain outside tracked plugin source files.
