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
	 * Test bulk limit for free users.
	 */
	public function testBulkLimit() {
		/* @var WpSmushitAdmin $wpsmushit_admin */
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

}
