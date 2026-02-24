# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-02-24

### Added

- Initial release.
- `Updater` class for WordPress plugin updates from GitHub releases.
- Support for latest release tag as version and ZIP asset or zipball as package.
- Optional custom update slug and GitHub personal access token.
- `plugins_api` integration for "View details" overlay.
- Single entry point (`init.php`) with "latest version wins" when multiple plugins use the library.
