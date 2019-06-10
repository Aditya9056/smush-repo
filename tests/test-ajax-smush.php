<?php
/**
 * Class for testing ajax smushing.
 *
 * @package WP_Smush
 */

use Helpers\Helper;

/**
 * Class AjaxSmushTest
 */
class AjaxSmushTest extends WP_Ajax_UnitTestCase {

	/**
	 * Unit tester.
	 *
	 * @var Helper $tester
	 */
	protected $tester;

	/**
	 * Run before actions.
	 */
	public function setUp() {
		parent::setup();

		require_once 'helpers/class-helper.php';
		$this->tester = new Helper();

		wp_set_current_user( 1 );
		new WP_Smush_Ajax();
	}

	/**
	 * Trigger wp_ajax_wp_smushit_manual ajax request.
	 *
	 * @param int $id  Image ID.
	 *
	 * @return array|mixed|object  Response from smush_manual()
	 */
	private function ajax_smushit_manual( $id ) {
		try {
			$_GET['attachment_id'] = $id;
			$this->_handleAjax( 'wp_smushit_manual' );
			$this->fail( 'Expected exception: WPAjaxDieContinueException' );
		} catch ( WPAjaxDieContinueException $e ) {
			// We expected this, do nothing.
		} // End try.

		return json_decode( $this->_last_response, true );
	}

	/**
	 * Test single image manual Smush (from media library).
	 *
	 * @group ajax
	 */
	public function testSmushSingle() {
		WP_Smush_Settings::get_instance()->set( 'auto', false );

		$id = $this->tester->create_img_post();

		$response = $this->ajax_smushit_manual( $id );

		$this->assertTrue( $response['success'] );
		$this->assertInternalType( 'array', $response['data'] );
		$this->assertEquals( '<p class="smush-status">Smushing in progress..</p>', $response['data']['status'] );
	}

	/**
	 * Test wp_smush_image filter.
	 *
	 * @group ajax
	 */
	public function testSmushImageFilter() {
		$id = $this->tester->upload_image();

		add_filter(
			'wp_smush_image',
			function( $status, $img_id ) use ( &$id ) {
				if ( $id === $img_id ) {
					$status = false;
				}

				return $status;
			},
			10,
			2
		);

		$response = $this->ajax_smushit_manual( $id );

		$this->assertFalse( $response['success'] );
		$this->assertInternalType( 'array', $response['data'] );

		$error_message = '<p class="wp-smush-error-message">Attachment Skipped - Check `wp_smush_image` filter.</p>';
		$this->assertEquals( $error_message, $response['data']['error_msg'] );
	}

}
