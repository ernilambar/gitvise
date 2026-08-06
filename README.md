# Gitvise

WordPress PHP library for plugin updates from **GitHub releases**: uses the latest release tag as the version and the release ZIP as the package.

**Requirements:** PHP 7.4+, WordPress 6.0+

---

## Installation

```bash
composer require ernilambar/gitvise
```

In your plugin, load the library via its entry point:

```php
require_once __DIR__ . '/vendor/ernilambar/gitvise/init.php';
```

---

## Usage

```php
use Nilambar\Gitvise\Updater;

$updater = new Updater( 'your-github-user/your-repo-name', __FILE__ );
$updater->init();
```

**Optional:** Third argument = custom update slug (when plugin dir name differs from desired slug). Fourth argument = GitHub personal access token (for higher API rate limit).

```php
$updater = new Updater( 'user/repo', __FILE__, 'repo-slug', 'ghp_your_token' );
$updater->init();
```

---

## How it works

On each update check, the library fetches the latest release from the GitHub API, compares the tag (e.g. `v1.2.3` → `1.2.3`) with the installed version from WordPress’s transient, and populates the update with a ZIP asset from the release. The `plugins_api` filter is handled so “View details” shows release info.

---

## License

[GPL-2.0-or-later](LICENSE)
