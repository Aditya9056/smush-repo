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
	 * Test update settings.
	 */
	public function testUpdateSettings() {
		/* @var WpSmushSettings $wpsmush_settings */
		global $wpsmush_settings;

		$expected = new WpSmushSettings();
		$this->assertEquals( $expected, $wpsmush_settings );
	}

}