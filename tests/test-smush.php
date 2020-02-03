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
	 * Smush mock.
	 *
	 * @var Smush $smush
	 */
	private $smush;

	/**
	 * Setup method.
	 */
	public function setUp() {
		Installer::smush_activated();

		$this->smush = $this->getMockBuilder( Smush::class )
							->setMethods( [ 'do_smushit' ] )
							->getMock();

		$this->smush->method( 'do_smushit' )
					->will( $this->returnCallback( [ $this, 'fake_do_smushit' ] ) );
	}

	/**
	 * Cleanup.
	 *
	 * @since 3.4.0
	 */
	public function tearDown() {
		delete_option( 'wp-smush-settings' );
		delete_option( 'wp-smush-install-type' );
		delete_option( 'wp-smush-version' );
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

		/**
		 * Pre-release versions, such as 5.3-RC4, are considered lower than their final release counterparts (like 5.3.0).
		 *
		 * @see https://www.php.net/manual/en/function.version-compare.php#refsect1-function.version-compare-notes
		 */
		if ( version_compare( $wp_version, '5.2.999', '>' ) ) {
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
	 * @param string $attachment_file_path_size  Image.
	 *
	 * @return array|WP_Error
	 */
	public function fake_do_smushit( $attachment_file_path_size ) {
		$data = new stdClass();

		$settings = get_option( 'wp-smush-settings' );

		$data->api_version = '1.0';
		$data->is_premium  = WP_Smush::is_pro();
		$data->lossy       = $settings['lossy'];
		$data->keep_exif   = ! $settings['strip_exif'];
		$data->time        = 0.02;
		$data->before_size = 0;
		$data->after_size  = 0;

		if ( preg_match( '/150x150\.jpg$/i', $attachment_file_path_size ) ) {
			$data->before_size = 8770;

			if ( $settings['lossy'] ) {
				// Super Smush variations.
				$data->after_size = $settings['strip_exif'] ? 5021 : 8183;
			} else {
				// Regular Smush variations.
				$data->after_size = $settings['strip_exif'] ? 5692 : 8770;
			}
		}

		if ( preg_match( '/240x300\.jpg$/i', $attachment_file_path_size ) ) {
			$data->before_size = 17966;

			if ( $settings['lossy'] ) {
				// Super Smush variations.
				$data->after_size = $settings['strip_exif'] ? 12976 : 16138;
			} else {
				// Regular Smush variations.
				$data->after_size = $settings['strip_exif'] ? 14533 : 17695;
			}
		}

		if ( preg_match( '/768x960\.jpg$/i', $attachment_file_path_size ) ) {
			$data->before_size = 144433;

			if ( $settings['lossy'] ) {
				// Super Smush variations.
				$data->after_size = $settings['strip_exif'] ? 120600 : 123762;
			} else {
				// Regular Smush variations.
				$data->after_size = $settings['strip_exif'] ? 136672 : 139834;
			}
		}

		if ( preg_match( '/819x1024\.jpg$/i', $attachment_file_path_size ) ) {
			$data->before_size = 164017;

			if ( $settings['lossy'] ) {
				// Super Smush variations.
				$data->after_size = $settings['strip_exif'] ? 137291 : 140453;
			} else {
				// Regular Smush variations.
				$data->after_size = $settings['strip_exif'] ? 155780 : 158942;
			}
		}

		if ( preg_match( '/1229x1536\.jpg$/i', $attachment_file_path_size ) ) {
			$data->before_size = 353148;

			if ( $settings['lossy'] ) {
				// Super Smush variations.
				$data->after_size = $settings['strip_exif'] ? 299022 : 302184;
			} else {
				// Regular Smush variations.
				$data->after_size = $settings['strip_exif'] ? 338570 : 341732;
			}
		}

		if ( preg_match( '/1639x2048\.jpg$/i', $attachment_file_path_size ) ) {
			$data->before_size = 606061;

			if ( $settings['lossy'] ) {
				// Super Smush variations.
				$data->after_size = $settings['strip_exif'] ? 513710 : 516872;
			} else {
				// Regular Smush variations.
				$data->after_size = $settings['strip_exif'] ? 581810 : 584972;
			}
		}

		$data->bytes_saved = $data->before_size - $data->after_size;
		$data->compression = round( $data->bytes_saved / $data->after_size * 100, 2 );
		$data->image_md5   = '1370fbbb309892abf154124ce7432df6';

		if ( isset( $data->image_md5 ) ) {
			return [
				'success' => true,
				'data'    => $data,
			];
		}

		return new WP_Error( 'unkonwn_attachment_size', 'Unkown attachment size' );
	}

	/**
	 * Get savings percentage.
	 *
	 * @return double
	 */
	private function get_percent() {
		return $this->get_bytes_saved() / $this->get_size_before() * 100;
	}

	/**
	 * Get saved bytes.
	 *
	 * @return int
	 */
	private function get_bytes_saved() {
		global $wp_version;

		$settings = get_option( 'wp-smush-settings' );

		if ( version_compare( $wp_version, '5.2.999', '>' ) ) {
			if ( $settings['lossy'] ) {
				return $settings['strip_exif'] ? 205775 : 186803;
			}

			return $settings['strip_exif'] ? 61338 : 42450;
		}

		if ( $settings['lossy'] ) {
			return $settings['strip_exif'] ? 59298 : 46650;
		}

		return $settings['strip_exif'] ? 22509 : 9945;
	}

	/**
	 * Get size before.
	 *
	 * @return int
	 */
	private function get_size_before() {
		global $wp_version;
		return version_compare( $wp_version, '5.2.999', '>' ) ? 1294395 : 335186;
	}

	/**
	 * Get size after.
	 *
	 * @return int
	 */
	private function get_size_after() {
		global $wp_version;

		$settings = get_option( 'wp-smush-settings' );

		if ( version_compare( $wp_version, '5.2.999', '>' ) ) {
			if ( $settings['lossy'] ) {
				return $settings['strip_exif'] ? 1088620 : 1107592;
			}

			return $settings['strip_exif'] ? 1233057 : 1251945;
		}

		if ( $settings['lossy'] ) {
			return $settings['strip_exif'] ? 275888 : 288536;
		}

		return $settings['strip_exif'] ? 312677 : 325241;
	}

	/**
	 * Test Smush single image + keep EXIF.
	 *
	 * @group single
	 */
	public function testSmushKeepExif() {
		Settings::get_instance()->set( 'lossy', false );
		Settings::get_instance()->set( 'strip_exif', false );

		$this->smush->smush_single( self::$id, true );

		// Try to get the smushed meta.
		$smush_meta = get_post_meta( self::$id, Smush::$smushed_meta_key, true );

		// Make sure meta is set.
		$this->assertNotEmpty( $smush_meta );

		$this->assertFalse( $smush_meta['stats']['lossy'] );
		$this->assertTrue( $smush_meta['stats']['keep_exif'] );

		$this->assertEquals( $this->get_percent(), $smush_meta['stats']['percent'] );
		$this->assertEquals( $this->get_bytes_saved(), $smush_meta['stats']['bytes'] );
		$this->assertEquals( $this->get_size_before(), $smush_meta['stats']['size_before'] );
		$this->assertEquals( $this->get_size_after(), $smush_meta['stats']['size_after'] );
	}

	/**
	 * Test Smush single image + strip EXIF.
	 *
	 * @group single
	 */
	public function testSmushStripExif() {
		Settings::get_instance()->set( 'lossy', false );
		Settings::get_instance()->set( 'strip_exif', true );

		$this->smush->smush_single( self::$id, true );

		// Try to get the smushed meta.
		$smush_meta = get_post_meta( self::$id, Smush::$smushed_meta_key, true );

		// Make sure meta is set.
		$this->assertNotEmpty( $smush_meta );

		$this->assertFalse( $smush_meta['stats']['lossy'] );
		$this->assertEquals( 0, $smush_meta['stats']['keep_exif'] );

		$this->assertEquals( $this->get_percent(), $smush_meta['stats']['percent'] );
		$this->assertEquals( $this->get_bytes_saved(), $smush_meta['stats']['bytes'] );
		$this->assertEquals( $this->get_size_before(), $smush_meta['stats']['size_before'] );
		$this->assertEquals( $this->get_size_after(), $smush_meta['stats']['size_after'] );
	}

	/**
	 * Test super Smush single imag + keep EXIF.
	 *
	 * @group single
	 */
	public function testSuperSmushKeepExif() {
		Settings::get_instance()->set( 'lossy', true );
		Settings::get_instance()->set( 'strip_exif', false );

		$this->smush->smush_single( self::$id, true );

		// Try to get the smushed meta.
		$smush_meta = get_post_meta( self::$id, Smush::$smushed_meta_key, true );

		// Make sure meta is set.
		$this->assertNotEmpty( $smush_meta );

		$this->assertTrue( $smush_meta['stats']['lossy'] );
		$this->assertTrue( $smush_meta['stats']['keep_exif'] );

		$this->assertEquals( $this->get_percent(), $smush_meta['stats']['percent'] );
		$this->assertEquals( $this->get_bytes_saved(), $smush_meta['stats']['bytes'] );
		$this->assertEquals( $this->get_size_before(), $smush_meta['stats']['size_before'] );
		$this->assertEquals( $this->get_size_after(), $smush_meta['stats']['size_after'] );
	}

	/**
	 * Test Smush single image + strip EXIF.
	 *
	 * @group single
	 */
	public function testSuperSmushStripExif() {
		Settings::get_instance()->set( 'lossy', true );
		Settings::get_instance()->set( 'strip_exif', true );

		$this->smush->smush_single( self::$id, true );

		// Try to get the smushed meta.
		$smush_meta = get_post_meta( self::$id, Smush::$smushed_meta_key, true );

		// Make sure meta is set.
		$this->assertNotEmpty( $smush_meta );

		$this->assertTrue( $smush_meta['stats']['lossy'] );
		$this->assertEquals( 0, $smush_meta['stats']['keep_exif'] );

		$this->assertEquals( $this->get_percent(), $smush_meta['stats']['percent'] );
		$this->assertEquals( $this->get_bytes_saved(), $smush_meta['stats']['bytes'] );
		$this->assertEquals( $this->get_size_before(), $smush_meta['stats']['size_before'] );
		$this->assertEquals( $this->get_size_after(), $smush_meta['stats']['size_after'] );
	}

}
