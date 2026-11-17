# Project guidelines

## Development and releases

- This Git repository is the development source of truth. Make code and documentation changes here.
- WordPress Plugin Directory releases use a separate SVN working copy. Regular development does not include synchronizing or publishing releases; perform those operations only when explicitly requested.
- See [SVN working copy setup](docs/releases.md) for repository access and directory layout. Local checkout paths are developer-specific and are not part of the project configuration.

## Language and documentation

- Write all repository-related content exclusively in English, including code, comments, documentation, commit messages, issues, pull requests, and release notes. Chat communication may use the user's preferred language.
- Keep documentation factual and focused on the current code and project. Update or remove obsolete descriptions and abandoned plans instead of accumulating historical notes.
- Record change history, implementation events, and decision or analysis notes in commit messages, pull requests, or release notes as appropriate, rather than in project reference documentation.
- For substantial work spanning multiple stages, temporary work-in-progress notes may document outstanding steps. Consolidate them into current-state documentation and remove obsolete notes when the work is complete.
- Store lasting code and project knowledge in this file or linked files under `docs/`. User memory is reserved for personal preferences and feedback, never project-specific knowledge.

## Structure and behavior

- `static-mail-sender-configurator.php` contains the entire plugin: metadata, the `JakubBoucek\WpPlugin\MailSenderConfig\StaticMailSenderConfigurator` class, and registration when the file is loaded.
- `readme.txt` contains the description, configuration examples, compatibility metadata, and changelog in WordPress Plugin Directory format.
- `LICENSE` contains the MIT license.
- The plugin registers a `wp_mail` filter at priority `1`, allowing later filters to override the default sender. Preserve this early priority.
- `WP_MAIL_FROM` is read from a PHP constant or an environment variable. The constant takes precedence. If neither is defined, the filter returns its input unchanged.
- The filter converts string headers into an array and appends a `From:` header. The configured value may include both a sender name and an email address.
- The plugin has no administration interface, database storage, or external dependencies.

## Compatibility and validation

- The declared minimum versions are PHP 7.4.0 and WordPress 6.0. Do not introduce newer PHP syntax without an intentional change to supported versions.
- When changing the release version, keep the PHP header's `Version`, the `Stable tag` in `readme.txt`, and the changelog consistent. PHP and WordPress requirements appear in both files and must remain consistent as well.
- The repository has no Composer setup, frontend build, or automated test suite. Check PHP syntax with `php -l static-mail-sender-configurator.php`.
- For behavior changes, verify missing configuration, constant-only configuration, environment-only configuration, and both sources together; string and array headers; and subsequent sender overrides. Verify integration in WordPress: syntax checks alone do not establish correct behavior.
