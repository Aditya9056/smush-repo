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

		/**
		 * This is a patch for Codeception not seeing the freaking actor in a local install...
		 */
		if ( is_null( $this->tester ) ) {
			$di = $this->getMetadata()->getService( 'di' );
			$di->set( new \Codeception\Scenario( $this ) );
			$this->tester = $di->instantiate( $this->getMetadata()->getCurrent( 'actor' ) );
		}

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
	public function testDefaults() {
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

	/**
	 * Test what the function returns on various installs.
	 *
	 * @env multisite
	 */
	public function testIs_network_enabledVaritations() {
		$this->assertTrue( $this->settings->is_network_enabled() );
		$this->settings->set_setting( WP_SMUSH_PREFIX . 'networkwide', true );
		$this->assertFalse( $this->settings->is_network_enabled() );
		$this->settings->set_setting( WP_SMUSH_PREFIX . 'networkwide', array( 'bulk' ) );
		$this->assertFalse( $this->settings->is_network_enabled() );
	}

	/**
	 * @env multisite
	 */
	public function testGlobalNetworkSettings() {
		/*
		$smush = WP_Smush::get_instance();

		$settings = $smush->core()->mod->settings->get_setting( WP_SMUSH_PREFIX . 'settings' );

		// Turn off all settings.
		$disabled_settings = array_fill_keys( array_keys( $settings ), false );
		$smush->core()->mod->settings->set_setting( WP_SMUSH_PREFIX . 'settings', $disabled_settings );

		$smush->core()->mod->settings->init();
		codecept_debug( $this->tester->readPrivateProperty( $smush->core()->mod->settings, 'settings' ) );

		// Check one setting to make sure it's off.
		$auto = $smush->core()->mod->settings->get( 'auto' );
		codecept_debug( 'auto: ' . $auto );
		*/

		/*
		codecept_debug( $this->tester->readPrivateProperty( $this->settings, 'settings' ) );
		$this->settings->init();

		$settings = $this->settings->get_setting( WP_SMUSH_PREFIX . 'settings' );

		// Turn off all settings.
		$disabled_settings = array_fill_keys( array_keys( $settings ), false );
		$this->settings->set_setting( WP_SMUSH_PREFIX . 'settings', $disabled_settings );

		$this->settings->init();

		// Check one setting to make sure it's off.
		$auto = $this->settings->get( 'auto' );
		codecept_debug( 'auto: ' . $auto );

		$this->settings->set( 'auto', false );
		codecept_debug( $this->settings->get( 'auto' ) );
		*/

		/**
		 * Disable all settings and make sure they are reflected on subsites.
		 */
		/*
		switch_to_blog( 1 );
		$settings = $this->settings->get_setting( WP_SMUSH_PREFIX . 'settings' );
		// Turn off all settings.
		$disabled_settings = array_fill_keys( array_keys( $settings ), false );
		$this->settings->set_setting( WP_SMUSH_PREFIX . 'settings', $disabled_settings );

		// Check one setting to make sure it's off.
		$auto = $this->settings->get( 'auto' );
		$this->assertFalse( $auto );

		$settings = $this->settings->get_setting( WP_SMUSH_PREFIX . 'settings' );
		$this->assertEquals( $disabled_settings, $settings );

		switch_to_blog( 2 );
		$settings = $this->settings->get_setting( WP_SMUSH_PREFIX . 'settings' );
		$this->assertEquals( $disabled_settings, $settings );
		*/

		/**
		 * Enable all settings and make sure they are reflected on subsites.
		 */
		/*
		switch_to_blog( 1 );
		$settings = $this->settings->get_setting( WP_SMUSH_PREFIX . 'settings' );
		// Turn on all settings.
		$enabled_settings = array_fill_keys( array_keys( $settings ), true );
		$this->settings->set_setting( WP_SMUSH_PREFIX . 'settings', $enabled_settings );

		// Check one setting to make sure it's on.
		$auto = $this->settings->get( 'auto' );
		$this->assertTrue( $auto );

		$settings = $this->settings->get_setting( WP_SMUSH_PREFIX . 'settings' );
		$this->assertEquals( $enabled_settings, $settings );

		switch_to_blog( 2 );
		$settings = $this->settings->get_setting( WP_SMUSH_PREFIX . 'settings' );
		$this->assertEquals( $enabled_settings, $settings );
		*/
	}

}
