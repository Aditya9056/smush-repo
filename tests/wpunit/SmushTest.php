<?php
/**
 * Smush tests
 *
 * @package UnitTests
 */

/**
 * Class SmushTest
 */
class SmushTest extends \Codeception\TestCase\WPTestCase {

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
	public function testSmushSingle() {
		$file = dirname( dirname( __FILE__ ) ) . '/_data/images/image1.jpeg';

		$id = $this->factory()->attachment->create( array(
			'post_title'   => basename( $file ),
			'post_content' => $file,
		) );

		WP_Smush::get_instance()->core()->initialise();

		update_option( "smush-in-progress-{$id}", true );
	}

	//public function testRestoreSingle() {}

}
