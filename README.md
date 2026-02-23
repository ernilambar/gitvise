# Gitvise

A WordPress PHP library that enables a plugin to receive automatic updates
directly from a **GitHub repository** instead of the WordPress.org plugin
directory. It uses the latest GitHub release as the new version number and the
release's ZIP asset (or the auto-generated zipball) as the downloadable
package.

---

## Requirements

- PHP 7.4 or later
- WordPress 5.0 or later

---

## Installation

Install via Composer:

```bash
composer require nilambar/gitvise
```

Make sure Composer's autoloader is included in your plugin:

```php
require_once __DIR__ . '/vendor/autoload.php';
```

---

## Usage

```php
use Nilambar\Gitvise\Updater;

$updater = new Updater(
    'your-github-user/your-repo-name', // GitHub repository slug ("username/repository").
    __FILE__                           // Absolute path to the plugin main file.
);
$updater->init();
```

### Optional: Custom update slug

When your plugin’s directory name is not the same as the slug you want for
updates (e.g. you want the slug to match the repo name), pass it as the
third argument:

```php
$updater = new Updater(
    'your-github-user/your-repo-name',
    __FILE__,
    'your-repo-name'    // Update slug used in update checks and "View details".
);
$updater->init();
```

### Optional: GitHub personal access token

Pass a personal access token as the fourth argument to raise the GitHub API
rate limit (useful on high-traffic sites):

```php
$updater = new Updater(
    'your-github-user/your-repo-name',
    __FILE__,
    '',                             // No custom slug.
    'ghp_your_personal_access_token'
);
$updater->init();
```

With a custom slug and a token:

```php
$updater = new Updater(
    'your-github-user/your-repo-name',
    __FILE__,
    'your-repo-name',
    'ghp_your_personal_access_token'
);
$updater->init();
```

---

## How it works

1. On every WordPress update check (`pre_set_site_transient_update_plugins`)
   the library queries the GitHub REST API for the latest release of the
   configured repository.
2. The release tag (e.g. `v1.2.3` → `1.2.3`) is compared against the
   currently installed version, which is read automatically from the WordPress
   update transient (no need to pass it manually).
3. When a newer version is found, the update transient is populated with the
   download URL, which WordPress uses to install the update.
4. The download URL is resolved in the following order:
   - The first ZIP file attached as a **release asset**.
   - The auto-generated **zipball** URL provided by GitHub.
5. The `plugins_api` filter is also handled so that the "View details"
   overlay in the WordPress admin shows release information.

---

## License

GPL-2.0-or-later
