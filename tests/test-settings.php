<?php
/**
 * Settings tests
 *
 * @package WP_Smush
 */

use Helpers\Helper;
use Smush\Core\Core;
use Smush\Core\Installer;
use Smush\Core\Settings;

/**
 * Class SettingsTest
 */
class SettingsTest extends WP_UnitTestCase {

	/**
	 * WpunitTester tester.
	 *
	 * @var Helper $tester
	 */
	protected $tester;

	/**
	 * Settings instance.
	 *
	 * @var Settings $settings
	 */
	private $settings;

	/**
	 * Setup method.
	 */
	public function setUp() {
		require_once 'helpers/class-helper.php';
		$this->tester = new Helper();

		Installer::smush_activated();
		$this->settings = Settings::get_instance();
	}

	/**
	 * Cleanup.
	 *
	 * @since 3.4.0
	 */
	public function tearDown() {

		delete_option( 'wp-smush-install-type' );
		delete_option( 'wp-smush-version' );
		delete_option( 'wp-smush-settings' );

		delete_transient( 'timeout_wp-smush-bulk_sent_count' );
		delete_transient( 'wp-smush-bulk_sent_count' );

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

			$this->assertEquals( $condition, Core::check_bulk_limit() );
			$i++;
		}
	}

	/**
	 * Test defaults.
	 *
	 * @throws ReflectionException  Exception.
	 */
	public function testDefaults() {
		// Remove all the settings.
		$this->settings->delete_setting( WP_SMUSH_PREFIX . 'settings' );

		$settings = $this->settings->get_setting( WP_SMUSH_PREFIX . 'settings' );
		$this->assertFalse( $settings );

		$this->settings->init();
		$settings = $this->settings->get_setting( WP_SMUSH_PREFIX . 'settings' );
		$defaults = $this->tester->read_private_property( $this->settings, 'defaults' );
		$this->assertEquals( $defaults, $settings );
	}

	/**
	 * Test is_network_enabled on single site.
	 *
	 * @group single
	 */
	public function testIs_network_enabledSingle() {
		$this->assertFalse( $this->settings->is_network_enabled() );
	}

	/**
	 * Test is_network_enabled on multisite.
	 *
	 * @group multisite
	 */
	public function testIs_network_enabledMultisite() {
		$this->assertTrue( $this->settings->is_network_enabled() );
	}

	/**
	 * Test what the function returns on various installs.
	 *
	 * @group multisite
	 */
	public function testIs_network_enabledVaritations() {
		$this->assertTrue( $this->settings->is_network_enabled() );
		$this->settings->set_setting( WP_SMUSH_PREFIX . 'networkwide', true );
		$this->assertFalse( $this->settings->is_network_enabled() );
		$this->settings->set_setting( WP_SMUSH_PREFIX . 'networkwide', array( 'bulk' ) );
		$this->assertFalse( $this->settings->is_network_enabled() );
	}

	/**
	 * Test global network settings.
	 *
	 * @group multisite
	 */
	//public function testGlobalNetworkSettings() {
		/*
		if ( ! is_multisite() ) {
			return;
		}

		$blog_id_1 = $this->factory->blog->create_object( $this->factory->blog->generate_args() );
		$blog_id_2 = $this->factory->blog->create_object( $this->factory->blog->generate_args() );

		switch_to_blog( $blog_id_1 );
		$this->settings->init();

		$settings = $this->settings->get_setting( WP_SMUSH_PREFIX . 'settings' );

		// Turn off all settings.
		$disabled_settings = array_fill_keys( array_keys( $settings ), false );
		$this->settings->set_setting( WP_SMUSH_PREFIX . 'settings', $disabled_settings );

		$this->settings->init();

		// Check one setting to make sure it's off.
		$auto = $this->settings->get( 'auto' );
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
	//}

}
