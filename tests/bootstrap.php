<?php
/**
 * PHPUnit bootstrap: WordPress function stubs.
 *
 * @package Nilambar\Gitvise
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// ---------------------------------------------------------------------------
// WordPress stub: WP_Error class.
// ---------------------------------------------------------------------------

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WP_Error stub for unit tests.
	 */
	class WP_Error {} // phpcs:ignore
}

// ---------------------------------------------------------------------------
// WordPress function stubs.
// ---------------------------------------------------------------------------

/**
 * Stub for plugin_basename().
 *
 * Returns the last two path segments (dir/file.php), which mirrors what
 * WordPress returns for a normal subdirectory plugin.
 *
 * @param string $file Absolute path to the plugin file.
 * @return string Plugin basename.
 */
function plugin_basename( $file ) {
	$normalized = str_replace( '\\', '/', $file );
	$parts      = array_values( array_filter( explode( '/', $normalized ) ) );
	$count      = count( $parts );

	if ( $count >= 2 ) {
		return $parts[ $count - 2 ] . '/' . $parts[ $count - 1 ];
	}

	return $parts[ $count - 1 ] ?? '';
}

/**
 * Stub for add_filter() — no-op in tests.
 *
 * @param string   $tag           Filter tag.
 * @param callable $callback      Callback.
 * @param int      $priority      Priority.
 * @param int      $accepted_args Accepted args.
 */
function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {}

/**
 * Stub for wp_remote_get().
 *
 * Routes readme.txt Contents API requests to $GLOBALS['wp_remote_get_readme_response'],
 * everything else to $GLOBALS['wp_remote_get_response'].
 *
 * @param string $url  Request URL.
 * @param array  $args Request arguments.
 * @return mixed
 */
function wp_remote_get( $url, $args = array() ) {
	if ( false !== strpos( $url, '/contents/readme.txt' ) ) {
		return $GLOBALS['wp_remote_get_readme_response'] ?? null;
	}

	return $GLOBALS['wp_remote_get_response'] ?? null;
}

/**
 * Stub for get_plugin_data().
 *
 * Returns whatever is stored in $GLOBALS['get_plugin_data_response'].
 *
 * @param string $plugin_file Absolute path to the plugin main file.
 * @param bool   $markup      Unused.
 * @param bool   $translate   Unused.
 * @return array Plugin header data.
 */
function get_plugin_data( $plugin_file, $markup = true, $translate = true ) {
	return $GLOBALS['get_plugin_data_response'] ?? array();
}

/**
 * Stub for esc_html().
 *
 * @param string $text Text to escape.
 * @return string
 */
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

/**
 * Stub for wp_strip_all_tags().
 *
 * @param string $text          Text to strip.
 * @param bool   $remove_breaks Whether to remove line breaks too.
 * @return string
 */
function wp_strip_all_tags( $text, $remove_breaks = false ) {
	$text = strip_tags( (string) $text );

	if ( $remove_breaks ) {
		$text = preg_replace( '/[\r\n\t ]+/', ' ', $text );
	}

	return trim( $text );
}

/**
 * Stub for force_balance_tags().
 *
 * Not a faithful reimplementation — just returns the text unchanged, which is
 * sufficient for well-formed test fixtures.
 *
 * @param string $text HTML to balance.
 * @return string
 */
function force_balance_tags( $text ) {
	return $text;
}

/**
 * Stub for wp_kses().
 *
 * Not a faithful reimplementation of WordPress' KSES filtering — just returns
 * the text unchanged, which is sufficient for well-formed test fixtures.
 *
 * @param string $text    Text to filter.
 * @param array  $allowed Allowed HTML.
 * @return string
 */
function wp_kses( $text, $allowed ) {
	return $text;
}

/**
 * Stub for get_user_by().
 *
 * Always returns false — readme contributors are wp.org usernames, not local
 * WordPress users, so lookups never match in this context.
 *
 * @param string $field Field to look up by.
 * @param string $value Value to look up.
 * @return false
 */
function get_user_by( $field, $value ) {
	return false;
}

/**
 * Stub for is_wp_error().
 *
 * @param mixed $thing Value to check.
 * @return bool
 */
function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

/**
 * Stub for wp_remote_retrieve_response_code().
 *
 * @param array $response HTTP response array.
 * @return int HTTP status code.
 */
function wp_remote_retrieve_response_code( $response ) {
	return isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
}

/**
 * Stub for wp_remote_retrieve_body().
 *
 * @param array $response HTTP response array.
 * @return string Response body.
 */
function wp_remote_retrieve_body( $response ) {
	return isset( $response['body'] ) ? (string) $response['body'] : '';
}

