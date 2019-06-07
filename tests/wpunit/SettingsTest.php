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
	 * Test defaults.
	 *
	 * @throws ReflectionException
	 */
	public function testDefaults( \Codeception\Scenario $scenario ) {
		codecept_debug( $scenario->current('env') );
		// Remove all the settings.
		$this->settings->delete_setting( WP_SMUSH_PREFIX . 'settings' );

		$settings = $this->settings->get_setting( WP_SMUSH_PREFIX . 'settings' );
		$this->assertFalse( $settings );

		$this->settings->init();
		$settings = $this->settings->get_setting( WP_SMUSH_PREFIX . 'settings' );
		$defaults = $this->tester->readPrivateProperty( $this->settings, 'defaults' );
		$this->assertEquals( $defaults, $settings );
	}

	/**
	 * Test is_network_enabled on single site.
	 *
	 * @env single
	 */
	public function testIs_network_enabledSingle() {
		$this->assertFalse( $this->settings->is_network_enabled() );
	}

	/**
	 * Test is_network_enabled on multisite.
	 *
	 * @env multisite
	 */
	public function testIs_network_enabledMultisite() {
		$this->assertTrue( $this->settings->is_network_enabled() );
	}

}
