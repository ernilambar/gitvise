<?php
/**
 * Updater class
 *
 * @package Nilambar\Gitvise
 */

namespace Nilambar\Gitvise;

/**
 * Updater class.
 *
 * Hooks into the WordPress plugin update mechanism to deliver updates
 * from a GitHub repository's latest release instead of the WordPress
 * plugin directory.
 *
 * @since 1.0.0
 */
class Updater {

	/**
	 * GitHub username (or organization).
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $username;

	/**
	 * GitHub repository name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $repository;

	/**
	 * Absolute path to the plugin main file.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Plugin slug (e.g. "my-plugin/my-plugin.php").
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $plugin_slug;

	/**
	 * Currently installed plugin version.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $version;

	/**
	 * HTTP request timeout in seconds.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const REQUEST_TIMEOUT = 15;

	/**
	 * Optional GitHub personal access token for authenticated requests.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $access_token;

	/**
	 * Cached release data from GitHub API.
	 *
	 * @since 1.0.0
	 * @var array|null
	 */
	private $release_data = null;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string $plugin_file  Absolute path to the plugin main file.
	 * @param string $username     GitHub username or organization.
	 * @param string $repository   GitHub repository name.
	 * @param string $version      Currently installed plugin version.
	 * @param string $access_token Optional GitHub personal access token.
	 */
	public function __construct( $plugin_file, $username, $repository, $version, $access_token = '' ) {
		$this->plugin_file  = $plugin_file;
		$this->username     = $username;
		$this->repository   = $repository;
		$this->version      = $version;
		$this->access_token = $access_token;
		$this->plugin_slug  = plugin_basename( $plugin_file );
	}

	/**
	 * Register WordPress hooks.
	 *
	 * Call this method after instantiation to activate the updater.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 10, 3 );
	}

	/**
	 * Build the GitHub API URL for the latest release endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @return string GitHub REST API URL.
	 */
	private function get_api_url() {
		return sprintf(
			'https://api.github.com/repos/%s/%s/releases/latest',
			rawurlencode( $this->username ),
			rawurlencode( $this->repository )
		);
	}

	/**
	 * Build request arguments for wp_remote_get().
	 *
	 * @since 1.0.0
	 *
	 * @return array Request arguments.
	 */
	private function get_request_args() {
		$headers = array(
			'Accept' => 'application/vnd.github.v3+json',
		);

		if ( ! empty( $this->access_token ) ) {
			$headers['Authorization'] = 'Bearer ' . $this->access_token;
		}

		return array(
			'headers' => $headers,
			'timeout' => self::REQUEST_TIMEOUT,
		);
	}

	/**
	 * Fetch and cache release data from the GitHub API.
	 *
	 * @since 1.0.0
	 *
	 * @return array|false Associative release data array, or false on failure.
	 */
	private function get_release_data() {
		if ( null !== $this->release_data ) {
			return $this->release_data;
		}

		$response = wp_remote_get( $this->get_api_url(), $this->get_request_args() );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
			return false;
		}

		$this->release_data = $data;

		return $this->release_data;
	}

	/**
	 * Determine the download URL from a release.
	 *
	 * Prefers the first ZIP release asset; falls back to the GitHub-generated
	 * zipball URL when no ZIP asset is attached.
	 *
	 * @since 1.0.0
	 *
	 * @param array $release Release data from the GitHub API.
	 * @return string|false Download URL, or false when none is available.
	 */
	private function get_download_url( $release ) {
		if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
			foreach ( $release['assets'] as $asset ) {
				if ( isset( $asset['content_type'] ) && 'application/zip' === $asset['content_type'] ) {
					return $asset['browser_download_url'];
				}
			}
		}

		// Fall back to the auto-generated zipball when no asset is present.
		return ! empty( $release['zipball_url'] ) ? $release['zipball_url'] : false;
	}

	/**
	 * Strip a leading "v" from a tag name to produce a version string.
	 *
	 * @since 1.0.0
	 *
	 * @param string $tag_name Git tag name (e.g. "v1.2.3" or "1.2.3").
	 * @return string Version string (e.g. "1.2.3").
	 */
	private function get_version_from_tag( $tag_name ) {
		return ltrim( $tag_name, 'v' );
	}

	/**
	 * Derive the update slug from the plugin slug.
	 *
	 * For plugins that live in their own subdirectory (the normal case) this is
	 * just the directory name.  For single-file plugins installed directly in
	 * wp-content/plugins/ the directory component would be ".", so we fall back
	 * to the filename without its extension.
	 *
	 * @since 1.0.0
	 *
	 * @return string Update slug.
	 */
	private function get_update_slug() {
		$dir = dirname( $this->plugin_slug );

		if ( '.' === $dir ) {
			return basename( $this->plugin_slug, '.php' );
		}

		return $dir;
	}

	/**
	 * Check whether a newer release is available and populate the update transient.
	 *
	 * Hooked to `pre_set_site_transient_update_plugins`.
	 *
	 * @since 1.0.0
	 *
	 * @param object $transient WordPress update transient object.
	 * @return object Possibly modified transient object.
	 */
	public function check_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_release_data();

		if ( false === $release ) {
			return $transient;
		}

		$remote_version = $this->get_version_from_tag( $release['tag_name'] );
		$download_url   = $this->get_download_url( $release );

		if ( false === $download_url ) {
			return $transient;
		}

		if ( version_compare( $this->version, $remote_version, '<' ) ) {
			$update = array(
				'slug'        => $this->get_update_slug(),
				'plugin'      => $this->plugin_slug,
				'new_version' => $remote_version,
				'url'         => sprintf( 'https://github.com/%s/%s', $this->username, $this->repository ),
				'package'     => $download_url,
			);

			$transient->response[ $this->plugin_slug ] = (object) $update;
		}

		return $transient;
	}

	/**
	 * Return plugin information for the "View details" overlay.
	 *
	 * Hooked to `plugins_api`.
	 *
	 * @since 1.0.0
	 *
	 * @param false|object|array $result The result object or array.
	 * @param string             $action The type of information being requested.
	 * @param object             $args   Plugin API arguments.
	 * @return false|object Plugin information object, or the original result.
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( $this->get_update_slug() !== $args->slug ) {
			return $result;
		}

		$release = $this->get_release_data();

		if ( false === $release ) {
			return $result;
		}

		$remote_version = $this->get_version_from_tag( $release['tag_name'] );
		$download_url   = $this->get_download_url( $release );

		if ( false === $download_url ) {
			return $result;
		}

		$info = array(
			'name'          => $args->slug,
			'slug'          => $args->slug,
			'version'       => $remote_version,
			'author'        => $this->username,
			'homepage'      => sprintf( 'https://github.com/%s/%s', $this->username, $this->repository ),
			'download_link' => $download_url,
			'sections'      => array(
				'description' => isset( $release['body'] ) ? $release['body'] : '',
			),
		);

		return (object) $info;
	}
}
