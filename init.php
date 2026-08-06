<?php
/**
 * Gitvise loader: single entry point and "latest version wins" when used by multiple plugins.
 *
 * @package Nilambar\Gitvise
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! defined( 'GITVISE_LOADED' ) ) {
	define( 'GITVISE_LOADED', true );

	// Fallback when this package is the root project (e.g. development). Otherwise version is read from Composer's installed.json.
	if ( ! defined( 'GITVISE_VERSION' ) ) {
		define( 'GITVISE_VERSION', '1.0.0' );
	}

	/**
	 * Get the version of a copy of the library at the given package root.
	 *
	 * Reads from vendor/composer/installed.json when the package is installed via Composer;
	 * otherwise returns the GITVISE_VERSION constant (e.g. when the package is the root project).
	 *
	 * @since 1.0.0
	 *
	 * @param string $package_root Absolute path to the package root (directory containing init.php).
	 * @return string Version string.
	 */
	function gitvise_get_package_version( $package_root ) {
		$installed_file = $package_root . '/../composer/installed.json';
		if ( is_readable( $installed_file ) ) {
			$json = file_get_contents( $installed_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$data = json_decode( $json, true );
			if ( isset( $data['packages'] ) && is_array( $data['packages'] ) ) {
				foreach ( $data['packages'] as $pkg ) {
					if ( isset( $pkg['name'] ) && 'ernilambar/gitvise' === $pkg['name'] && isset( $pkg['version'] ) ) {
						return $pkg['version'];
					}
				}
			}
			// Composer 2.2+ uses "installed" key for installed packages.
			if ( isset( $data['installed'] ) && is_array( $data['installed'] ) ) {
				foreach ( $data['installed'] as $pkg ) {
					if ( isset( $pkg['name'] ) && 'ernilambar/gitvise' === $pkg['name'] && isset( $pkg['version'] ) ) {
						return $pkg['version'];
					}
				}
			}
		}
		return GITVISE_VERSION;
	}

	/**
	 * Resolve the path to the copy of the library with the highest version.
	 *
	 * Scans plugin directories for vendor/ernilambar/gitvise and the current package;
	 * returns the package root path for the copy with the greatest version number.
	 * Version is read from Composer's installed.json when available.
	 *
	 * @since 1.0.0
	 *
	 * @return string Absolute path to the winning package root (no trailing slash).
	 */
	function gitvise_resolve_winner_root() {
		static $root = null;

		if ( null !== $root ) {
			return $root;
		}

		$candidates = array();

		// Current package (this init.php lives at package root).
		$candidates[ __DIR__ ] = gitvise_get_package_version( __DIR__ );

		// Other copies under wp-content/plugins/*/vendor/ernilambar/gitvise.
		if ( defined( 'WP_PLUGIN_DIR' ) && is_dir( WP_PLUGIN_DIR ) ) {
			$pattern = WP_PLUGIN_DIR . '/*/vendor/ernilambar/gitvise/init.php';
			$files   = glob( $pattern );
			if ( is_array( $files ) ) {
				foreach ( $files as $path ) {
					$package_root = dirname( $path );
					if ( isset( $candidates[ $package_root ] ) ) {
						continue;
					}
					$candidates[ $package_root ] = gitvise_get_package_version( $package_root );
				}
			}
		}

		$best_root = array_key_first( $candidates );
		$best_ver  = $candidates[ $best_root ];

		foreach ( $candidates as $dir => $ver ) {
			if ( version_compare( $ver, $best_ver, '>' ) ) {
				$best_ver  = $ver;
				$best_root = $dir;
			}
		}

		$root = $best_root;
		return $root;
	}

	/**
	 * PSR-4 autoloader for Nilambar\Gitvise namespace; loads from the resolved winner path.
	 *
	 * @since 1.0.0
	 *
	 * @param string $class_name Fully qualified class name.
	 * @return void
	 */
	function gitvise_autoload( $class_name ) {
		$prefix = 'Nilambar\\Gitvise\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$file     = str_replace( '\\', '/', $relative ) . '.php';
		$root     = gitvise_resolve_winner_root();
		$path     = $root . '/src/' . $file;

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}

	spl_autoload_register( 'gitvise_autoload', true, true );
}
