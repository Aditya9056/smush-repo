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
	 * WpunitTester tester.
	 *
	 * @var \WpunitTester $tester
	 */
	protected $tester;

	/**
	 * Setup method.
	 */
	public function setUp() {
		parent::setUp();

		WP_Smush_Installer::smush_activated();
		WP_Smush_Settings::get_instance()->init();
	}

	/**
	 * Tear down method.
	 */
	public function tearDown() {
		// your tear down methods here

		parent::tearDown();
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
	/*
	public function testSmushOriginals() {
		// Set Smush to Pro.
		$this->tester->setPro();

		$this->assertTrue( WP_Smush::is_pro() );

		// Set smush original image setting to true.
		WP_Smush_Settings::get_instance()->set( 'original', true );

		// Upload image and get meta.
		$id = $this->tester->uploadImage();

		$meta = get_post_meta( $id, WP_Smushit::$smushed_meta_key, true );

		codecept_debug( $meta );

		// Now delete the uploaded file.
		wp_delete_attachment( $id );

		// Full size should be there in smushed sizes.
		$this->assertTrue( isset( $meta['sizes']['full'] ) );

		// Set smush original image setting to false.
		WP_Smush_Settings::get_instance()->set( 'original', false );

		// Upload image and get meta.
		$id   = $this->tester->uploadImage();
		$meta = get_post_meta( $id, WP_Smushit::$smushed_meta_key, true );

		// Now delete the uploaded file.
		wp_delete_attachment( $id );

		// Full size should not be there in smushed sizes.
		$this->assertFalse( isset( $meta['sizes']['full'] ) );

		// Remove temp API key.
		$this->tester->setFree();

		$this->assertFalse( WP_Smush::is_pro() );
	}
	*/

}
