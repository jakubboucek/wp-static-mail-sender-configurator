# SVN working copy setup

Development takes place in Git. Publishing to the WordPress Plugin Directory uses the plugin's separate [SVN repository](https://plugins.svn.wordpress.org/static-mail-sender-configurator/).

## Create a working copy

Install a Subversion command-line client providing `svn`. From the root of your Git clone, run:

```sh
svn checkout https://plugins.svn.wordpress.org/static-mail-sender-configurator/ ../static-mail-sender-configurator-svn
```

The destination is an example sibling directory outside the Git clone; choose any unused path outside the repository. Checkout creates the directory and its SVN metadata, which records the remote URL. No Git remote or Git-to-SVN bridge is required.

Inspect the connection and working copy with:

```sh
svn info ../static-mail-sender-configurator-svn
svn status ../static-mail-sender-configurator-svn
```

Public checkout does not require publishing permissions. Committing a release requires a WordPress.org account with commit access to this plugin.

## Release layout

- `trunk/` holds the plugin files prepared for release.
- `tags/<version>/` holds versioned releases.
- `assets/` holds WordPress.org listing images such as banners and icons.

The `plugin/` directory is the distribution root. It contains `static-mail-sender-configurator.php`, `readme.txt`, and `LICENSE`, which must be placed directly in `trunk/` without an extra `plugin/` directory. Keep `plugin/LICENSE` identical to the repository-level `LICENSE`. Keep Git metadata, IDE settings, and contributor documentation out of the distribution.

Prepare and validate changes in Git before copying release files into SVN. Keep the plugin header version, readme stable tag, and release tag consistent. Review SVN changes before committing; SVN commits publish changes to WordPress.org.

For the publishing procedure, see the official [WordPress Subversion guide](https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/).

## Publish a release

Run the following from the Git repository root, using a clean SVN working copy. Adjust `svn_dir` to your checkout location. Check `svn status` before proceeding and resolve any existing changes separately.

```sh
svn_dir=../static-mail-sender-configurator-svn
svn update "$svn_dir"
svn status "$svn_dir"
```

Before preparing a release, run the relevant validation, including PHP syntax checks, inside Docker. Prepare the release files and create a new tag. The version is read from the plugin header; the tag must not already exist. Copy only distribution files, keeping listing assets and previous tags intact.

```sh
(
    set -eu
    svn_dir=../static-mail-sender-configurator-svn
    version=$(sed -n 's/^ \* Version: //p' plugin/static-mail-sender-configurator.php)
    test -n "$version"
    test "$(sed -n 's/^Stable tag: //p' plugin/readme.txt)" = "$version"
    test -z "$(svn status "$svn_dir")"
    test ! -e "$svn_dir/tags/$version"
    cp plugin/static-mail-sender-configurator.php plugin/readme.txt plugin/LICENSE "$svn_dir/trunk/"
    svn copy "$svn_dir/trunk" "$svn_dir/tags/$version"
    svn status "$svn_dir"
    svn diff "$svn_dir"
)
```

Review the output: it should contain only the intended `trunk/` changes and the new release tag. Then publish both together:

```sh
svn_dir=../static-mail-sender-configurator-svn
version=$(sed -n 's/^ \* Version: //p' plugin/static-mail-sender-configurator.php)
svn commit "$svn_dir/trunk" "$svn_dir/tags/$version" \
    -m "Release $version: update tested WordPress compatibility"
svn status "$svn_dir"
```

These commands cover releases with the current distribution file set. If files are added or removed in a future release, schedule those changes with `svn add` or `svn delete` before creating the tag. Do not modify an existing release tag to publish a new version.

## ZIP layout

Build the installable ZIP from the contents of `plugin/`, under a single top-level directory named `static-mail-sender-configurator/`:

```text
static-mail-sender-configurator/
    static-mail-sender-configurator.php
    readme.txt
    LICENSE
```

Do not ZIP the repository root or include `plugin/` as an additional directory level. The installed plugin basename must remain `static-mail-sender-configurator/static-mail-sender-configurator.php`. Tests, Docker configuration, and contributor documentation are not part of the distribution.
