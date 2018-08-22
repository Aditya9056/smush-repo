<?php
/**
 * Settings tests
 *
 * @package UnitTests
 */

/**
 * Class SettingsTest
 */
class SettingsTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * Setup method.
	 */
	public function setUp() {
		parent::setUp();

		WP_Smush_Installer::smush_activated();
	}

	/**
	 * Tear down method.
	 */
	public function tearDown() {
		// your tear down methods here

		parent::tearDown();
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
	 * Test bulk limit for free users.
	 */
	public function testBulkLimit() {
		$i         = 0;
		$condition = true;

		while ( $i <= 50 ) {
			set_transient( 'wp-smush-bulk_sent_count', $i, 60 );

			if ( 50 === $i ) {
				$condition = false;
			}

			$this->assertEquals( $condition, WP_Smush_Core::check_bulk_limit() );
			$i++;
		}
	}

	/**
	 * Test smushing original image.
	 */
	public function testSmushOriginalSetting() {
		// Set Smush to Pro.
		$this->setPro();

		// Image path.
		$file = dirname( dirname( __FILE__ ) ) . '/_data/images/image2.jpeg';

		// Set smush original image setting to true.
		WP_Smush_Settings::$settings['original'] = 1;
		// Upload image and get meta.
		$id   = $this->factory()->attachment->create_upload_object( $file );
		$meta = get_post_meta( $id, WP_Smushit::$smushed_meta_key, true );

		// Now delete the uploaded file.
		wp_delete_attachment( $id );

		// Full size should be there in smushed sizes.
		$this->assertTrue( isset( $meta['sizes']['full'] ) );

		// Set smush original image setting to false.
		WP_Smush_Settings::$settings['original'] = 0;
		// Upload image and get meta.
		$id   = $this->factory()->attachment->create_upload_object( $file );
		$meta = get_post_meta( $id, WP_Smushit::$smushed_meta_key, true );

		// Now delete the uploaded file.
		wp_delete_attachment( $id );

		// Full size should not be there in smushed sizes.
		$this->assertFalse( isset( $meta['sizes']['full'] ) );

		// Remove temp API key.
		$this->setFree();
	}

}
