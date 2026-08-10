<?php
/**
 * Updater class
 *
 * @package Nilambar\Gitvise
 */

namespace Nilambar\Gitvise;

use Nilambar\Gitvise\Readme\Parser;

if ( ! class_exists( \Nilambar\Gitvise\Updater::class ) ) {

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
		 * GitHub repository slug in "username/repository" format.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		private $repo_slug;

		/**
		 * Absolute path to the plugin main file.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		private $plugin_file;

		/**
		 * Plugin slug (e.g. "my-plugin/my-plugin.php").
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		private $plugin_slug;

		/**
		 * Optional update slug. When set, used in update checks and plugin info; otherwise derived from plugin path.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		private $slug = '';

		/**
		 * HTTP request timeout in seconds.
		 *
		 * @since 1.0.0
		 *
		 * @var int
		 */
		const REQUEST_TIMEOUT = 15;

		/**
		 * Optional GitHub personal access token for authenticated requests.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		private $access_token;

		/**
		 * Cached release data from GitHub API.
		 *
		 * @since 1.0.0
		 *
		 * @var array|null
		 */
		private $release_data = null;

		/**
		 * Constructor.
		 *
		 * @since 1.0.0
		 *
		 * @param string $repo_slug    GitHub repository slug in "username/repository" format.
		 * @param string $plugin_file  Absolute path to the plugin main file.
		 * @param string $slug         Optional update slug. Use when plugin directory name differs from desired slug (e.g. repo name).
		 * @param string $access_token Optional GitHub personal access token.
		 */
		public function __construct( $repo_slug, $plugin_file, $slug = '', $access_token = '' ) {
			$this->repo_slug    = $repo_slug;
			$this->plugin_file  = $plugin_file;
			$this->slug         = $slug;
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
			return 'https://api.github.com/repos/' . $this->repo_slug . '/releases/latest';
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
		 * Fetch and decode readme.txt contents from the repository at a given tag.
		 *
		 * @since 1.0.2
		 *
		 * @param string $tag_name Git tag name.
		 * @return string|false Raw readme.txt contents, or false when unavailable.
		 */
		private function get_readme_contents( $tag_name ) {
			$url = 'https://api.github.com/repos/' . $this->repo_slug . '/contents/readme.txt?ref=' . rawurlencode( $tag_name );

			$response = wp_remote_get( $url, $this->get_request_args() );

			if ( is_wp_error( $response ) ) {
				return false;
			}

			if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
				return false;
			}

			$data = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( ! is_array( $data ) || empty( $data['content'] ) ) {
				return false;
			}

			$contents = base64_decode( $data['content'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

			return false !== $contents ? $contents : false;
		}

		/**
		 * Determine the download URL from a release.
		 *
		 * Matches asset filenames ending in ".zip" (covers versioned names like
		 * "slug-1.2.3.zip"). Prefers an asset named after the update slug when
		 * multiple ZIP assets are present; otherwise uses the first ZIP asset found.
		 *
		 * @since 1.0.0
		 *
		 * @param array $release Release data from the GitHub API.
		 * @return string|false Download URL, or false when no ZIP asset is available.
		 */
		private function get_download_url( $release ) {
			if ( empty( $release['assets'] ) || ! is_array( $release['assets'] ) ) {
				return false;
			}

			$slug      = $this->get_update_slug();
			$first_zip = false;

			foreach ( $release['assets'] as $asset ) {
				if ( empty( $asset['browser_download_url'] ) || empty( $asset['name'] ) ) {
					continue;
				}

				if ( 1 !== preg_match( '/\.zip$/i', $asset['name'] ) ) {
					continue;
				}

				if ( false === $first_zip ) {
					$first_zip = $asset['browser_download_url'];
				}

				if ( $asset['name'] === $slug . '.zip' || 0 === stripos( $asset['name'], $slug . '-' ) ) {
					return $asset['browser_download_url'];
				}
			}

			return $first_zip;
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
		 * Get plugin header data from the main plugin file.
		 *
		 * Used to populate name, description, author, and version requirements in
		 * the "View details" popup. Includes the "Tested up to" custom header
		 * under the "TestedUpTo" key, since get_plugin_data() does not recognize it.
		 *
		 * @since 1.0.0
		 *
		 * @return array Plugin header data, or empty array if not available.
		 */
		private function get_plugin_header_data() {
			if ( ! function_exists( 'get_plugin_data' ) ) {
				if ( ! defined( 'ABSPATH' ) || ! is_file( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
					return array();
				}
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			if ( ! is_readable( $this->plugin_file ) ) {
				return array();
			}

			$data = get_plugin_data( $this->plugin_file, false, false );

			if ( ! is_array( $data ) ) {
				$data = array();
			}

			$data['TestedUpTo'] = '';

			if ( function_exists( 'get_file_data' ) ) {
				$custom_headers = get_file_data( $this->plugin_file, array( 'TestedUpTo' => 'Tested up to' ) );

				if ( isset( $custom_headers['TestedUpTo'] ) ) {
					$data['TestedUpTo'] = $custom_headers['TestedUpTo'];
				}
			}

			return $data;
		}

		/**
		 * Return the update slug. Uses optional slug when set; otherwise derived from plugin path (subdirectory name).
		 *
		 * @since 1.0.0
		 *
		 * @return string Update slug.
		 */
		private function get_update_slug() {
			if ( '' !== $this->slug ) {
				return $this->slug;
			}
			return dirname( $this->plugin_slug );
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

			$installed_version = isset( $transient->checked[ $this->plugin_slug ] )
			? $transient->checked[ $this->plugin_slug ]
			: '';

			$release = $this->get_release_data();

			if ( false === $release ) {
				return $transient;
			}

			$remote_version = $this->get_version_from_tag( $release['tag_name'] );
			$download_url   = $this->get_download_url( $release );

			if ( false === $download_url ) {
				return $transient;
			}

			if ( version_compare( $installed_version, $remote_version, '<' ) ) {
				$update = array(
					'slug'        => $this->get_update_slug(),
					'plugin'      => $this->plugin_slug,
					'new_version' => $remote_version,
					'url'         => 'https://github.com/' . $this->repo_slug,
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

			$plugin_data = $this->get_plugin_header_data();
			$github_url  = 'https://github.com/' . $this->repo_slug;

			$sections = array();

			$readme_requires     = '';
			$readme_tested       = '';
			$readme_requires_php = '';

			$readme_contents = $this->get_readme_contents( $release['tag_name'] );

			if ( false !== $readme_contents ) {
				$readme = new Parser( $readme_contents );

				foreach ( $readme->sections as $section_name => $section_content ) {
					if ( '' !== trim( (string) $section_content ) ) {
						$sections[ $section_name ] = $section_content;
					}
				}

				$readme_requires     = $readme->requires;
				$readme_tested       = $readme->tested;
				$readme_requires_php = $readme->requires_php;
			}

			if ( empty( $sections['description'] ) ) {
				$sections['description'] = isset( $plugin_data['Description'] ) ? $plugin_data['Description'] : '';
			}

			if ( empty( $sections['changelog'] ) && isset( $release['body'] ) && '' !== trim( (string) $release['body'] ) ) {
				$sections['changelog'] = Parser::markdown_to_html( $release['body'] );
			}

			$requires     = ! empty( $plugin_data['RequiresWP'] ) ? $plugin_data['RequiresWP'] : $readme_requires;
			$tested       = ! empty( $plugin_data['TestedUpTo'] ) ? $plugin_data['TestedUpTo'] : $readme_tested;
			$requires_php = ! empty( $plugin_data['RequiresPHP'] ) ? $plugin_data['RequiresPHP'] : $readme_requires_php;
			$last_updated = '';

			if ( ! empty( $release['published_at'] ) ) {
				$last_updated = (string) $release['published_at'];
			} elseif ( ! empty( $release['created_at'] ) ) {
				$last_updated = (string) $release['created_at'];
			}

			$info = array(
				'name'          => isset( $plugin_data['Name'] ) ? $plugin_data['Name'] : $args->slug,
				'slug'          => $args->slug,
				'version'       => $remote_version,
				'author'        => isset( $plugin_data['Author'] ) ? $plugin_data['Author'] : explode( '/', $this->repo_slug )[0],
				'homepage'      => isset( $plugin_data['PluginURI'] ) && '' !== $plugin_data['PluginURI'] ? $plugin_data['PluginURI'] : $github_url,
				'download_link' => $download_url,
				'sections'      => $sections,
			);

			if ( ! empty( $plugin_data['Description'] ) ) {
				$info['short_description'] = $plugin_data['Description'];
			}

			if ( '' !== $requires ) {
				$info['requires'] = $requires;
			}

			if ( '' !== $tested ) {
				$info['tested'] = $tested;
			}

			if ( '' !== $requires_php ) {
				$info['requires_php'] = $requires_php;
			}

			if ( '' !== $last_updated ) {
				$info['last_updated'] = $last_updated;
			}

			return (object) $info;
		}
	}

}
