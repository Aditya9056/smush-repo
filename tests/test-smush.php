<?php
/**
 * Smush tests
 *
 * @package WP_Smush
 */

use Helpers\Helper;
use Smush\Core\Installer;
use Smush\Core\Modules\Smush;
use Smush\Core\Settings;

/**
 * Class SmushTest
 */
class SmushTest extends WP_UnitTestCase {

	/**
	 * Uploaded image ID.
	 *
	 * @var int $id
	 */
	private static $id;

	/**
	 * Setup method.
	 */
	public function setUp() {
		Installer::smush_activated();
	}

	/**
	 * Runs before the test case class’ first test.
	 */
	public static function setUpBeforeClass() {
		require_once 'helpers/class-helper.php';
		$tester = new Helper();

		// Make sure it is not auto smushed.
		Settings::get_instance()->set( 'auto', false );

		// Upload image.
		self::$id = $tester->upload_image_large();
	}

	/**
	 * Runs after the test case class' last test.
	 */
	public static function tearDownAfterClass() {
		// We don't need the attachment anymore. Delete.
		wp_delete_attachment( self::$id, true );
	}

	/**
	 * If this test fails, WordPress will have added new image sizes.
	 */
	public function testRegisterAttachmentSizes() {
		global $wp_version;

		$default_sizes = [ 'thumbnail', 'medium', 'medium_large', 'large' ];

		if ( version_compare( $wp_version, '5.3', '>' ) ) {
			// WordPress 5.3 adds two ned additional image sizes.
			array_push( $default_sizes, '1536x1536', '2048x2048' );
		}

		$this->assertEquals( $default_sizes, get_intermediate_image_sizes() );
	}

	/**
	 * The original do_smushit() method will receive a file path. Because we need a way to identify the file size
	 * based only on the file path (the actual file name can change), we're using regex to match the file ending. For
	 * example, a file '/tmp/wordpress/wp-content/uploads/2019/11/image-large-29-150x150.jpg' will be matched against
	 * '150x150.jpg'. Hope that makes sense.
	 *
	 * TODO: make sure that all attachment sizes are processed. Needs future proofing.
	 *
	 * @param string $attachment_file_path_size  Image.
	 *
	 * @return array|\WP_Error
	 */
	public function fake_do_smushit( $attachment_file_path_size ) {
		$data = new stdClass();

		$data->api_version = '1.0';
		$data->is_premium  = false;
		$data->lossy       = false;
		$data->keep_exif   = false;
		$data->time        = 0.02;

		if ( preg_match( '/150x150\.jpg$/i', $attachment_file_path_size ) ) {
			$data->before_size = 8770;
			$data->after_size  = 5692;
			$data->bytes_saved = 3078;
			$data->compression = 35.1;
			$data->image_md5   = '1370fbbb309892abf154124ce7432df6';
		}

		if ( preg_match( '/240x300\.jpg$/i', $attachment_file_path_size ) ) {
			$data->before_size = 17966;
			$data->after_size  = 14533;
			$data->bytes_saved = 3433;
			$data->compression = 19.11;
			$data->image_md5   = 'ebd1666383fb790d25c5ba517c9db79e';
		}

		if ( preg_match( '/768x960\.jpg$/i', $attachment_file_path_size ) ) {
			$data->before_size = 144433;
			$data->after_size  = 136672;
			$data->bytes_saved = 7761;
			$data->compression = 5.37;
			$data->image_md5   = '4a238e9df5c0b0426b5ae0e4b8120e11';
		}

		if ( preg_match( '/819x1024\.jpg$/i', $attachment_file_path_size ) ) {
			$data->before_size = 164017;
			$data->after_size  = 155780;
			$data->bytes_saved = 8237;
			$data->compression = 5.02;
			$data->image_md5   = 'da0346fb2d13aa6f229ee34f6b06ff66';
		}

		if ( isset( $data->image_md5 ) ) {
			return [
				'success' => true,
				'data'    => $data,
			];
		}

		return new \WP_Error( 'unkonwn_attachment_size', 'Unkown attachment size' );
	}

	/**
	 * Test Smush single image.
	 *
	 * @group single
	 */
	public function testSmushSingle() {
		$smush = $this->getMockBuilder( Smush::class )
					->setMethods( null )
					->setMethods( [ 'do_smushit' ] )
					->getMock();

		$smush->method( 'do_smushit' )
			->will( $this->returnCallback( [ $this, 'fake_do_smushit' ] ) );

		/**
		 * Smush the image.
		 *
		 * @var Smush $smush
		 */
		$smush->smush_single( self::$id, true );

		// Try to get the smushed meta.
		$smush_meta = get_post_meta( self::$id, Smush::$smushed_meta_key );

		// Make sure meta is set.
		$this->assertNotEmpty( $smush_meta );
	}

}
