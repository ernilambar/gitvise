# AGENTS

## Overview

WordPress PHP library that delivers plugin updates from **GitHub releases**. Uses Composer for autoloading and ships an `init.php` single entry point. Targets PHP 7.4+, WordPress 6.0+, GPL-2.0-or-later.

## Setup

```bash
composer install
```

## Commands

```bash
composer install        # Install dependencies
composer test           # PHPUnit (bootstrap: tests/bootstrap.php)
composer lint           # Runs lint-php then phpcs
composer lint-php       # PHP syntax check only (parallel-lint)
composer phpcs          # PHPCS scan only (WordPress + NilambarCodingStandard)
composer format         # PHPCBF auto-fix (run BEFORE lint)
```

## Conventions

- **Namespace `Nilambar\Gitvise`** maps to `src/` via PSR-4. Autoloading is registered in `init.php` through `gitvise_autoload()`; do not add a separate Composer PSR-4 entry — the manual loader resolves the "latest version wins" copy when multiple plugins bundle this library.
- **`init.php` is the single entry point.** All runtime behavior (version resolution, winner-root selection, autoloader registration) lives there. `src/` contains only classes.
- **WordPress core API only.** No external HTTP client — use `wp_remote_get()`, `wp_remote_retrieve_body()`, etc. for GitHub API calls.
- **Sanitize, escape, validate.** Follow WordPress coding standards for all output and input handling (enforced by PHPCS `WordPress` rule set).
- **Parsedown is the only runtime dependency** (`erusev/parsedown`). Do not add new runtime deps.

## Quality Gate

Run in this order; **all must exit 0** before declaring a task complete:

```bash
composer format
composer lint
composer test
```

If any step fails, fix the issue and re-run from that step.
