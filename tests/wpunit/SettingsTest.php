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
	 * Test bulk limit for free users.
	 */
	public function testBulkLimit() {
		/**
		 * WpSmushitAdmin global.
		 *
		 * @var WpSmushitAdmin $wpsmushit_admin
		 */
		global $wpsmushit_admin;

		$i         = 0;
		$condition = true;

		while ( $i <= 50 ) {
			set_transient( 'wp-smush-bulk_sent_count', $i, 60 );

			if ( 50 === $i ) {
				$condition = false;
			}

			$this->assertEquals( $condition, $wpsmushit_admin->check_bulk_limit() );
			$i++;
		}
	}

	/**
	 * Test update settings.
	 */
	public function testUpdateSettings() {
		/* @var WpSmushSettings $wpsmush_settings */
		global $wpsmush_settings;

		$expected = new WpSmushSettings();
		$this->assertEquals( $expected, $wpsmush_settings );
	}

	/**
	 * Test smushing original image.
	 */
	public function testSmushOriginal() {
		global $wpsmushit_admin, $wpsmush_settings;

		// Set Smush to Pro.
		$this->setPro();

		$file = dirname( dirname( __FILE__ ) ) . '/_data/images/image1.jpeg';

		// Set smush original image setting to true.
		$wpsmush_settings->settings['original'] = 1;

		// Upload image and set meta data.
		$id = $this->factory()->attachment->create_upload_object( $file );

		// Get smush meta data for attachment.
		$meta = get_post_meta( $id, $wpsmushit_admin->smushed_meta_key, true );

		// Full size should be there in smushed sizes.
		$this->assertTrue( isset( $meta['sizes']['full'] ) );
	}

	/**
	 * Test skipping original image.
	 */
	public function testSkipOriginal() {
		global $wpsmushit_admin, $wpsmush_settings;

		// Set Smush to Pro.
		$this->setPro();

		$file = dirname( dirname( __FILE__ ) ) . '/_data/images/image1.jpeg';

		// Set smush original image setting to false.
		$wpsmush_settings->settings['original'] = 0;

		// Upload image and set meta data.
		$id = $this->factory()->attachment->create_upload_object( $file );

		// Get smush meta data for attachment.
		$meta = get_post_meta( $id, $wpsmushit_admin->smushed_meta_key, true );

		// Full size should not be there in smushed sizes.
		$this->assertFalse( isset( $meta['sizes']['full'] ) );
	}
}
