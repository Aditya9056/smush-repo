<?php
/**
 * Smush tests
 *
 * @package WP_Smush
 */

use Helpers\Helper;

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

		WP_Smush_Installer::smush_activated();
	}

	/**
	 * Set setting value temporarily.
	 *
	 * @param string $key Setting name.
	 * @param bool   $value True or false.
	 */
	private function setSetting( $key, $value = true ) {
		$settings = WP_Smush_Settings::get_instance();
		$settings->set( $key, $value );
	}

	/**
	 * Test Smush single image.
	 *
	 * @group single
	 */
	public function testSmushSingle() {
		$smush = WP_Smush::get_instance();
		// Make sure it is not auto smushed.
		$smush->core()->mod->settings->set( 'auto', false );

		// Upload image.
		$id = $this->tester->upload_image();

		// Smush the image.
		WP_Smush::get_instance()->core()->mod->smush->smush_single( $id, true );

		// Try to get the smushed meta.
		$smush_meta = get_post_meta( $id, WP_Smushit::$smushed_meta_key, true );

		// We don't need the attachment anymore. Delete.
		wp_delete_attachment( $id, true );

		// Make sure meta is set.
		$this->assertTrue( ! empty( $smush_meta ) );
	}

}
