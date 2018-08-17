<?php
/**
 * Smush tests
 *
 * @package UnitTests
 */

/**
 * Class SmushTest
 */
class SmushTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * Setup method.
	 */
	public function setUp() {
		parent::setUp();

		WP_Smush_Installer::smush_activated();
	}

	/**
	 * Make auto smushing disabled.
	 *
	 * @param int $status Auto smush setting.
	 */
	private function setAutoSmush( $status = true ) {
		global $wpsmush_settings;

		// Set smush original image setting to true.
		$wpsmush_settings->settings['auto'] = $status;
	}

	/**
	 * Tear down method.
	 */
	public function tearDown() {
		// your tear down methods here

		parent::tearDown();
	}

	/**
	 * Test update settings.
	 */
	public function testSmushSingle() {
		/**
		 * WpSmushitAdmin global
		 *
		 * @var WpSmushitAdmin $wpsmushit_admin
		 */
		global $wpsmushit_admin;

		// Make sure it is not auto smushed.
		$this->setAutoSmush( false );

		$file = dirname( dirname( __FILE__ ) ) . '/_data/images/image1.jpeg';

		$id = $this->factory()->attachment->create_upload_object( $file );

		// Smush the image.
		$wpsmushit_admin->smush_single( $id, true );
		// Try to get the smushed meta.
		$smush_meta = get_post_meta( $id, $wpsmushit_admin->smushed_meta_key, true );
		// Check if meta is empty.
		$smushed = empty( $smush_meta ) ? false : true;

		// Make sure meta is set.
		$this->assertTrue( $smushed );
	}

	//public function testRestoreSingle() {}

}
