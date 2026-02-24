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
 * Returns whatever is stored in $GLOBALS['wp_remote_get_response'].
 *
 * @param string $url  Request URL.
 * @param array  $args Request arguments.
 * @return mixed
 */
function wp_remote_get( $url, $args = array() ) {
	return $GLOBALS['wp_remote_get_response'] ?? null;
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

