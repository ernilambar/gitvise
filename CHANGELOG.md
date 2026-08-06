# gitvise Changelog

## 1.0.3 - 2026-08-06
- Fixed: fatal "Cannot redeclare" error when multiple plugins bundle the library

## 1.0.2 - 2026-08-06
- Added: release notes now populate their own changelog section in the "View details" popup
- Added: `readme.txt`, when present in the repository, is parsed and merged into the "View details" popup

## 1.0.1 - 2026-08-06
- Changed: release ZIP asset is now resolved by matching the asset filename

## 1.0.0 - 2025-02-24
- Added: `Updater` class for WordPress plugin updates from GitHub releases
- Added: release ZIP asset used as the update package
- Added: optional custom update slug and GitHub personal access token
- Added: `plugins_api` integration for "View details" overlay
