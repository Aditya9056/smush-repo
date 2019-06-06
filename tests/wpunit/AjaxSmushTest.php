<?php

/**
 * Class AjaxSmushTest
 */
class AjaxSmushTest extends \Codeception\TestCase\WPAjaxTestCase {

	/**
	 * WpunitTester tester.
	 *
	 * @var \WpunitTester $tester
	 */
	protected $tester;

	/**
	 * Setup method.
	 */
	public function setUp(): void {
		parent::setUp();

		wp_set_current_user( 1 );
		new WP_Smush_Ajax();
	}

	/**
	 * Tear down method.
	 */
	public function tearDown(): void {
		// your tear down methods here.
		parent::tearDown();
	}

	/**
	 * Trigger wp_ajax_wp_smushit_manual ajax request.
	 *
	 * @param int $id  Image ID.
	 *
	 * @return array|mixed|object  Response from smush_manual()
	 */
	private function ajaxSmushitManual( $id ) {
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
	 */
	public function testSmushSingle() {
		WP_Smush_Settings::get_instance()->set( 'auto', false );

		$id = $this->tester->createImgPost();

		$response = $this->ajaxSmushitManual( $id );

		$this->assertTrue( $response['success'] );
		$this->assertInternalType( 'array', $response['data'] );
		$this->assertEquals( '<p class="smush-status">Smushing in progress..</p>', $response['data']['status'] );
	}

	/**
	 * Test wp_smush_image filter.
	 */
	public function testSmushImageFilter() {
		$id = $this->tester->uploadImage();

		add_filter( 'wp_smush_image', function( $status, $img_id ) use ( &$id ) {
			if ( $id === $img_id ) {
				$status = false;
			}

			return $status;
		}, 10, 2 );

		$response = $this->ajaxSmushitManual( $id );

		$this->assertFalse( $response['success'] );
		$this->assertInternalType( 'array', $response['data'] );

		$error_message = '<p class="wp-smush-error-message">Attachment Skipped - Check `wp_smush_image` filter.</p>';
		$this->assertEquals( $error_message, $response['data']['error_msg'] );
	}

}
