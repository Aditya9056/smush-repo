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
	 * Set setting value temporarily.
	 *
	 * @param string $key Setting name.
	 * @param bool   $value True or false.
	 */
	private function setSetting( $key, $value = true ) {
		global $wpsmush_settings;

		// Set smush original image setting to true.
		$wpsmush_settings->settings[ $key ] = $value;
	}

	/**
	 * Set Smush to Smush Pro.
	 */
	private function setPro() {
		// Define test api key.
		if ( ! defined( 'WPMUDEV_APIKEY' ) ) {
			define( 'WPMUDEV_APIKEY', 'test_api_key' );
		}

		// Flag that api key is valid.
		update_site_option( 'wp_smush_api_auth', array(
			'test_api_key' => array(
				'timestamp' => current_time( 'timestamp' ),
				'validity'  => 'valid',
			),
		) );
	}

	/**
	 * Set Smush to free version.
	 */
	private function setFree() {
		// Delete api key.
		delete_site_option( 'wp_smush_api_auth' );
	}

	/**
	 * Make auto smushing disabled.
	 */
	private function uploadImage() {
		$file = dirname( dirname( __FILE__ ) ) . '/_data/images/image1.jpeg';

		return $this->factory()->attachment->create_upload_object( $file );
	}

	/**
	 * Tear down method.
	 */
	public function tearDown() {
		// your tear down methods here

		parent::tearDown();
	}

	/**
	 * Test smush single image.
	 */
	public function testSmushSingle() {
		/**
		 * WpSmushitAdmin global
		 *
		 * @var WpSmushitAdmin $wpsmushit_admin
		 */
		global $wpsmushit_admin;

		// Make sure it is not auto smushed.
		$this->setSetting( 'auto', false );

		// Upload image.
		$id = $this->uploadImage();

		// Smush the image.
		$wpsmushit_admin->smush_single( $id, true );
		// Try to get the smushed meta.
		$smush_meta = get_post_meta( $id, $wpsmushit_admin->smushed_meta_key, true );
		// Check if meta is empty.
		$smushed = empty( $smush_meta ) ? false : true;

		// Make sure meta is set.
		$this->assertTrue( $smushed );
	}

	/**
	 * Test restore image after smushing.
	 */
	public function testRestoreSingle() {
		global $wpsmush_backup, $wpsmushit_admin;

		// Set smush pro.
		$this->setPro();
		// Make sure it is auto smushed.
		$this->setSetting( 'auto', 1 );
		// Make sure smush original enabled.
		$this->setSetting( 'original', 1 );
		// Make sure backup original enabled.
		$this->setSetting( 'backup', 1 );
		// Enable backup image.
		$wpsmush_backup->backup_enabled = true;

		// Upload an image.
		$id = $this->uploadImage();

		// Restore image.
		$wpsmush_backup->restore_image( $id, false );
		// Try to get smushed meta.
		$restored_meta = get_post_meta( $id, $wpsmushit_admin->smushed_meta_key, true );

		// We don't need the attachment anymore. Delete.
		wp_delete_attachment( $id, true );

		// Make sure meta is empty.
		$this->assertEmpty( $restored_meta );
	}
}
