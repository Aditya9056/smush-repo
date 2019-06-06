<?php
/**
 * Settings tests
 *
 * @package UnitTests
 */

use Codeception\TestCase\WPTestCase;
use WP_Smush\Core\Settings;

/**
 * Class NewSettingsTest
 */
class NewSettingsTest extends WPTestCase {

	/**
	 * WpunitTester tester.
	 *
	 * @var WpunitTester $tester
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
	public function setUp(): void {
		parent::setUp();

		/* @noinspection PhpIncludeInspection */
		require_once WP_SMUSH_DIR . '/core/class-settings.php';
		$this->settings = new Settings();
	}

	/**
	 * Tear down method.
	 */
	public function tearDown(): void {
		// Your tear down methods here.

		parent::tearDown();
	}

	/**
	 * Test settings if no module is selected.
	 */
	//public function test_empty_get() {
	//	$this->assertFalse( $this->settings->get( '' ) );
	//}

	//public function test_default_settings() {
	//	$defaults = $this->settings->get_defaults();
	//	codecept_debug( $defaults );
	//}

	/**
	 * Test bulk smush settings in a MU network on a subsite.
	 *
	 * @env multisite
	 */
	//public function test_bulk_subsite() {
	//	$this->assertTrue( $this->settings->is_multisite() );
	//}

	/*
	public function test_it_works() {
		$post = static::factory()->post->create_and_get();

		$this->assertInstanceOf(\WP_Post::class, $post);
	}
	*/

}
