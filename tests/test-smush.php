<?php
/**
 * Smush tests
 *
 * @package WP_Smush
 */

use Helpers\Helper;
use Smush\Core\Installer;
use Smush\Core\Modules\Smush;
use Smush\Core\Settings;
use Smush\WP_Smush;

/**
 * Class SmushTest
 */
class SmushTest extends WP_UnitTestCase {

	/**
	 * WpunitTester tester.
	 *
	 * @var Helper $tester
	 */
	protected $tester;

	/**
	 * Setup method.
	 */
	public function setUp() {
		require_once 'helpers/class-helper.php';
		$this->tester = new Helper();

		Installer::smush_activated();
	}

	/**
	 * Cleanup.
	 *
	 * @since 3.4.0
	 */
	public function tearDown() {

		delete_option( 'wp-smush-settings' );
		delete_option( 'wp-smush-install-type' );
		delete_option( 'wp-smush-version' );

	}

	/**
	 * Test Smush single image.
	 *
	 * @group single
	 */
	public function testSmushSingle() {
		// Make sure it is not auto smushed.
		Settings::get_instance()->set( 'auto', false );

		// Upload image.
		$id = $this->tester->upload_image();

		// Smush the image.
		WP_Smush::get_instance()->core()->mod->smush->smush_single( $id, true );

		// Try to get the smushed meta.
		$smush_meta = get_post_meta( $id, Smush::$smushed_meta_key );

		// We don't need the attachment anymore. Delete.
		wp_delete_attachment( $id, true );

		// Make sure meta is set.
		$this->assertTrue( ! empty( $smush_meta ) );
	}

}
