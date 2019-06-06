<?php
/**
 * Settings tests
 *
 * @package UnitTests
 */

use Codeception\TestCase\WPTestCase;

/**
 * Class SettingsTest
 */
class SettingsTest extends WPTestCase {

	/**
	 * WpunitTester tester.
	 *
	 * @var WpunitTester $tester
	 */
	protected $tester;

	/**
	 * Settings instance.
	 *
	 * @var WP_Smush_Settings $settings
	 */
	private $settings;

	/**
	 * Setup method.
	 */
	public function setUp(): void {
		parent::setUp();

		WP_Smush_Installer::smush_activated();
		$this->settings = WP_Smush_Settings::get_instance();
	}

	/**
	 * Tear down method.
	 */
	public function tearDown(): void {
		// your tear down methods here.

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
	 * Test settings if no module is selected.
	 */
	//public function test_empty_get() {}

	/**
	 * Test bulk smush settings in a MU network on a subsite.
	 *
	 * @env multisite
	 */

}
