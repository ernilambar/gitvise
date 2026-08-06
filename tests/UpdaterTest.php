<?php
/**
 * Tests for the Updater class.
 *
 * @package Nilambar\Gitvise
 */

use Nilambar\Gitvise\Updater;
use PHPUnit\Framework\TestCase;

/**
 * Updater test case.
 */
class UpdaterTest extends TestCase {

	/**
	 * Absolute plugin file path used across tests.
	 *
	 * plugin_basename() stub turns this into "my-plugin/my-plugin.php".
	 *
	 * @var string
	 */
	private $plugin_file = '/wp-content/plugins/my-plugin/my-plugin.php';

	/**
	 * GitHub repository slug used across tests.
	 *
	 * @var string
	 */
	private $repo_slug = 'acme/my-plugin';

	/**
	 * Reset the global HTTP response stub before every test.
	 */
	protected function setUp(): void {
		$GLOBALS['wp_remote_get_response'] = null;
	}

	// -------------------------------------------------------------------------
	// Helpers.
	// -------------------------------------------------------------------------

	/**
	 * Build a fake 200 OK GitHub API response.
	 *
	 * @param string $tag_name Release tag (e.g. "v2.0.0").
	 * @param array  $assets   Optional release assets array.
	 * @return array
	 */
	private function make_release_response( $tag_name, $assets = array() ) {
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => json_encode(
				array(
					'tag_name' => $tag_name,
					'body'     => 'Release notes for ' . $tag_name,
					'assets'   => $assets,
				)
			),
		);
	}

	/**
	 * Build a release asset entry for a ZIP file.
	 *
	 * @param string $name Asset filename (e.g. "my-plugin-2.0.0.zip").
	 * @param string $url  Optional download URL; derived from the name when omitted.
	 * @return array
	 */
	private function zip_asset( $name, $url = '' ) {
		return array(
			'name'                 => $name,
			'browser_download_url' => '' !== $url ? $url : 'https://github.com/acme/my-plugin/releases/download/v2.0.0/' . $name,
		);
	}

	// -------------------------------------------------------------------------
	// check_update() – early-return paths.
	// -------------------------------------------------------------------------

	/**
	 * @test
	 */
	public function check_update_returns_transient_unchanged_when_checked_is_empty() {
		$updater   = new Updater( $this->repo_slug, $this->plugin_file );
		$transient = (object) array( 'checked' => array() );

		$result = $updater->check_update( $transient );

		$this->assertSame( $transient, $result );
		$this->assertFalse( isset( $result->response ) );
	}

	/**
	 * @test
	 */
	public function check_update_returns_transient_unchanged_on_wp_error() {
		$GLOBALS['wp_remote_get_response'] = new WP_Error();

		$updater   = new Updater( $this->repo_slug, $this->plugin_file );
		$transient = (object) array(
			'checked'  => array( 'my-plugin/my-plugin.php' => '1.0.0' ),
			'response' => array(),
		);

		$result = $updater->check_update( $transient );

		$this->assertArrayNotHasKey( 'my-plugin/my-plugin.php', $result->response );
	}

	/**
	 * @test
	 */
	public function check_update_returns_transient_unchanged_on_non_200_response() {
		$GLOBALS['wp_remote_get_response'] = array(
			'response' => array( 'code' => 404 ),
			'body'     => '',
		);

		$updater   = new Updater( $this->repo_slug, $this->plugin_file );
		$transient = (object) array(
			'checked'  => array( 'my-plugin/my-plugin.php' => '1.0.0' ),
			'response' => array(),
		);

		$result = $updater->check_update( $transient );

		$this->assertArrayNotHasKey( 'my-plugin/my-plugin.php', $result->response );
	}

	/**
	 * @test
	 */
	public function check_update_returns_transient_unchanged_when_same_version() {
		$GLOBALS['wp_remote_get_response'] = $this->make_release_response(
			'v1.0.0',
			array( $this->zip_asset( 'my-plugin.zip' ) )
		);

		$updater   = new Updater( $this->repo_slug, $this->plugin_file );
		$transient = (object) array(
			'checked'  => array( 'my-plugin/my-plugin.php' => '1.0.0' ),
			'response' => array(),
		);

		$result = $updater->check_update( $transient );

		$this->assertArrayNotHasKey( 'my-plugin/my-plugin.php', $result->response );
	}

	/**
	 * @test
	 */
	public function check_update_returns_transient_unchanged_when_installed_is_newer() {
		$GLOBALS['wp_remote_get_response'] = $this->make_release_response(
			'v1.0.0',
			array( $this->zip_asset( 'my-plugin.zip' ) )
		);

		$updater   = new Updater( $this->repo_slug, $this->plugin_file );
		$transient = (object) array(
			'checked'  => array( 'my-plugin/my-plugin.php' => '2.0.0' ),
			'response' => array(),
		);

		$result = $updater->check_update( $transient );

		$this->assertArrayNotHasKey( 'my-plugin/my-plugin.php', $result->response );
	}

	// -------------------------------------------------------------------------
	// check_update() – update injected.
	// -------------------------------------------------------------------------

	/**
	 * @test
	 */
	public function check_update_injects_update_when_newer_version_available() {
		$GLOBALS['wp_remote_get_response'] = $this->make_release_response(
			'v2.0.0',
			array( $this->zip_asset( 'my-plugin-2.0.0.zip' ) )
		);

		$updater   = new Updater( $this->repo_slug, $this->plugin_file );
		$transient = (object) array(
			'checked'  => array( 'my-plugin/my-plugin.php' => '1.0.0' ),
			'response' => array(),
		);

		$result = $updater->check_update( $transient );

		$this->assertArrayHasKey( 'my-plugin/my-plugin.php', $result->response );
		$update = $result->response['my-plugin/my-plugin.php'];
		$this->assertSame( '2.0.0', $update->new_version );
		$this->assertSame( 'my-plugin', $update->slug );
		$this->assertSame( 'my-plugin/my-plugin.php', $update->plugin );
		$this->assertSame( 'https://github.com/acme/my-plugin', $update->url );
	}

	/**
	 * @test
	 */
	public function check_update_strips_leading_v_from_tag_name() {
		$GLOBALS['wp_remote_get_response'] = $this->make_release_response(
			'v1.5.3',
			array( $this->zip_asset( 'my-plugin-1.5.3.zip' ) )
		);

		$updater   = new Updater( $this->repo_slug, $this->plugin_file );
		$transient = (object) array(
			'checked'  => array( 'my-plugin/my-plugin.php' => '1.0.0' ),
			'response' => array(),
		);

		$result = $updater->check_update( $transient );

		$this->assertSame( '1.5.3', $result->response['my-plugin/my-plugin.php']->new_version );
	}

	/**
	 * @test
	 */
	public function check_update_uses_zip_asset_url_when_asset_present() {
		$asset     = $this->zip_asset( 'my-plugin.zip' );
		$asset_url = $asset['browser_download_url'];

		$GLOBALS['wp_remote_get_response'] = $this->make_release_response(
			'v2.0.0',
			array( $asset )
		);

		$updater   = new Updater( $this->repo_slug, $this->plugin_file );
		$transient = (object) array(
			'checked'  => array( 'my-plugin/my-plugin.php' => '1.0.0' ),
			'response' => array(),
		);

		$result = $updater->check_update( $transient );

		$this->assertSame( $asset_url, $result->response['my-plugin/my-plugin.php']->package );
	}

	/**
	 * @test
	 */
	public function check_update_returns_transient_unchanged_when_no_zip_asset() {
		$GLOBALS['wp_remote_get_response'] = $this->make_release_response( 'v2.0.0', array() );

		$updater   = new Updater( $this->repo_slug, $this->plugin_file );
		$transient = (object) array(
			'checked'  => array( 'my-plugin/my-plugin.php' => '1.0.0' ),
			'response' => array(),
		);

		$result = $updater->check_update( $transient );

		$this->assertArrayNotHasKey( 'my-plugin/my-plugin.php', $result->response );
	}

	/**
	 * @test
	 */
	public function check_update_ignores_non_zip_assets() {
		$non_zip_asset = array(
			'name'                 => 'my-plugin.tar.gz',
			'browser_download_url' => 'https://github.com/acme/my-plugin/releases/download/v2.0.0/my-plugin.tar.gz',
		);
		$zip_asset      = $this->zip_asset( 'my-plugin-2.0.0.zip' );

		$GLOBALS['wp_remote_get_response'] = $this->make_release_response(
			'v2.0.0',
			array( $non_zip_asset, $zip_asset )
		);

		$updater   = new Updater( $this->repo_slug, $this->plugin_file );
		$transient = (object) array(
			'checked'  => array( 'my-plugin/my-plugin.php' => '1.0.0' ),
			'response' => array(),
		);

		$result = $updater->check_update( $transient );

		$this->assertSame( $zip_asset['browser_download_url'], $result->response['my-plugin/my-plugin.php']->package );
	}

	/**
	 * @test
	 */
	public function check_update_prefers_slug_prefixed_zip_over_other_zip_assets() {
		$other_zip = $this->zip_asset( 'changelog.zip' );
		$slug_zip  = $this->zip_asset( 'my-plugin-2.0.0.zip' );

		$GLOBALS['wp_remote_get_response'] = $this->make_release_response(
			'v2.0.0',
			array( $other_zip, $slug_zip )
		);

		$updater   = new Updater( $this->repo_slug, $this->plugin_file );
		$transient = (object) array(
			'checked'  => array( 'my-plugin/my-plugin.php' => '1.0.0' ),
			'response' => array(),
		);

		$result = $updater->check_update( $transient );

		$this->assertSame( $slug_zip['browser_download_url'], $result->response['my-plugin/my-plugin.php']->package );
	}

	/**
	 * @test
	 */
	public function check_update_falls_back_to_first_zip_when_no_slug_match() {
		$zip_asset = $this->zip_asset( 'release-bundle.zip' );

		$GLOBALS['wp_remote_get_response'] = $this->make_release_response(
			'v2.0.0',
			array( $zip_asset )
		);

		$updater   = new Updater( $this->repo_slug, $this->plugin_file );
		$transient = (object) array(
			'checked'  => array( 'my-plugin/my-plugin.php' => '1.0.0' ),
			'response' => array(),
		);

		$result = $updater->check_update( $transient );

		$this->assertSame( $zip_asset['browser_download_url'], $result->response['my-plugin/my-plugin.php']->package );
	}

	/**
	 * @test
	 */
	public function check_update_uses_access_token_in_request() {
		// Intercept the global response; verify the update still succeeds with a token set.
		$GLOBALS['wp_remote_get_response'] = $this->make_release_response(
			'v2.0.0',
			array( $this->zip_asset( 'my-plugin-2.0.0.zip' ) )
		);

		$updater   = new Updater( $this->repo_slug, $this->plugin_file, '', 'test-token-123' );
		$transient = (object) array(
			'checked'  => array( 'my-plugin/my-plugin.php' => '1.0.0' ),
			'response' => array(),
		);

		// The token is used internally; we verify the update still succeeds.
		$result = $updater->check_update( $transient );

		$this->assertArrayHasKey( 'my-plugin/my-plugin.php', $result->response );
	}

	// -------------------------------------------------------------------------
	// plugin_info() tests.
	// -------------------------------------------------------------------------

	/**
	 * @test
	 */
	public function plugin_info_returns_result_unchanged_for_wrong_action() {
		$updater  = new Updater( $this->repo_slug, $this->plugin_file );
		$original = new stdClass();
		$args     = (object) array( 'slug' => 'my-plugin' );

		$result = $updater->plugin_info( $original, 'query_plugins', $args );

		$this->assertSame( $original, $result );
	}

	/**
	 * @test
	 */
	public function plugin_info_returns_result_unchanged_for_wrong_slug() {
		$updater  = new Updater( $this->repo_slug, $this->plugin_file );
		$original = new stdClass();
		$args     = (object) array( 'slug' => 'completely-different-plugin' );

		$result = $updater->plugin_info( $original, 'plugin_information', $args );

		$this->assertSame( $original, $result );
	}

	/**
	 * @test
	 */
	public function plugin_info_returns_result_unchanged_when_api_fails() {
		$GLOBALS['wp_remote_get_response'] = new WP_Error();

		$updater  = new Updater( $this->repo_slug, $this->plugin_file );
		$args     = (object) array( 'slug' => 'my-plugin' );

		$result = $updater->plugin_info( false, 'plugin_information', $args );

		$this->assertFalse( $result );
	}

	/**
	 * @test
	 */
	public function plugin_info_returns_plugin_info_object_for_matching_slug() {
		$asset = $this->zip_asset( 'my-plugin-2.0.0.zip' );

		$GLOBALS['wp_remote_get_response'] = $this->make_release_response(
			'v2.0.0',
			array( $asset )
		);

		$updater = new Updater( $this->repo_slug, $this->plugin_file );
		$args    = (object) array( 'slug' => 'my-plugin' );

		$result = $updater->plugin_info( false, 'plugin_information', $args );

		$this->assertIsObject( $result );
		$this->assertSame( 'my-plugin', $result->slug );
		$this->assertSame( '2.0.0', $result->version );
		$this->assertSame( 'acme', $result->author );
		$this->assertSame( 'https://github.com/acme/my-plugin', $result->homepage );
		$this->assertSame( $asset['browser_download_url'], $result->download_link );
		$this->assertSame( 'Release notes for v2.0.0', $result->sections['description'] );
	}

	/**
	 * @test
	 */
	public function plugin_info_prefers_zip_asset_for_download_link() {
		$asset = $this->zip_asset( 'my-plugin.zip' );

		$GLOBALS['wp_remote_get_response'] = $this->make_release_response(
			'v2.0.0',
			array( $asset )
		);

		$updater = new Updater( $this->repo_slug, $this->plugin_file );
		$args    = (object) array( 'slug' => 'my-plugin' );

		$result = $updater->plugin_info( false, 'plugin_information', $args );

		$this->assertSame( $asset['browser_download_url'], $result->download_link );
	}

	// -------------------------------------------------------------------------
	// Optional slug (4th constructor argument).
	// -------------------------------------------------------------------------

	/**
	 * @test
	 */
	public function check_update_uses_custom_slug_in_response_when_provided() {
		$GLOBALS['wp_remote_get_response'] = $this->make_release_response(
			'v2.0.0',
			array( $this->zip_asset( 'my-plugin-2.0.0.zip' ) )
		);

		$updater   = new Updater( $this->repo_slug, $this->plugin_file, 'custom-update-slug' );
		$transient = (object) array(
			'checked'  => array( 'my-plugin/my-plugin.php' => '1.0.0' ),
			'response' => array(),
		);

		$result = $updater->check_update( $transient );

		$this->assertArrayHasKey( 'my-plugin/my-plugin.php', $result->response );
		$this->assertSame( 'custom-update-slug', $result->response['my-plugin/my-plugin.php']->slug );
		$this->assertSame( 'my-plugin/my-plugin.php', $result->response['my-plugin/my-plugin.php']->plugin );
	}

	/**
	 * @test
	 */
	public function plugin_info_matches_custom_slug_when_provided() {
		$GLOBALS['wp_remote_get_response'] = $this->make_release_response(
			'v2.0.0',
			array( $this->zip_asset( 'my-plugin-2.0.0.zip' ) )
		);

		$updater = new Updater( $this->repo_slug, $this->plugin_file, 'custom-update-slug' );
		$args    = (object) array( 'slug' => 'custom-update-slug' );

		$result = $updater->plugin_info( false, 'plugin_information', $args );

		$this->assertIsObject( $result );
		$this->assertSame( 'custom-update-slug', $result->slug );
		$this->assertSame( '2.0.0', $result->version );
	}

	/**
	 * @test
	 */
	public function plugin_info_does_not_match_derived_slug_when_custom_slug_is_set() {
		$updater  = new Updater( $this->repo_slug, $this->plugin_file, 'custom-update-slug' );
		$original = new stdClass();
		$args     = (object) array( 'slug' => 'my-plugin' );

		$result = $updater->plugin_info( $original, 'plugin_information', $args );

		$this->assertSame( $original, $result );
	}

	/**
	 * @test
	 */
	public function constructor_accepts_token_and_slug_together() {
		$GLOBALS['wp_remote_get_response'] = $this->make_release_response(
			'v2.0.0',
			array( $this->zip_asset( 'my-plugin-2.0.0.zip' ) )
		);

		$updater   = new Updater( $this->repo_slug, $this->plugin_file, 'my-custom-slug', 'test-token' );
		$transient = (object) array(
			'checked'  => array( 'my-plugin/my-plugin.php' => '1.0.0' ),
			'response' => array(),
		);

		$result = $updater->check_update( $transient );

		$this->assertArrayHasKey( 'my-plugin/my-plugin.php', $result->response );
		$this->assertSame( 'my-custom-slug', $result->response['my-plugin/my-plugin.php']->slug );
	}
}
