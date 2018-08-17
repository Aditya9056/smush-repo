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

		//wp_set_current_user( 1 );
		//$_GET['attachment_id'] = 64;
		//$wpsmushit_admin->smush_manual();

		WP_Smush::get_instance()->core()->initialise();

		update_option( "smush-in-progress-{$id}", true );

		//$wpsmushit_admin->smush_single( $id );
	}

	//public function testRestoreSingle() {}

}
