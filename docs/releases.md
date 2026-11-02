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

The plugin distribution consists of `static-mail-sender-configurator.php`, `readme.txt`, and `LICENSE`, placed directly in `trunk/`. Keep Git metadata, IDE settings, and contributor documentation out of the distribution.

Prepare and validate changes in Git before copying release files into SVN. Keep the plugin header version, readme stable tag, and release tag consistent. Review SVN changes before committing; SVN commits publish changes to WordPress.org.

For the publishing procedure, see the official [WordPress Subversion guide](https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/).
