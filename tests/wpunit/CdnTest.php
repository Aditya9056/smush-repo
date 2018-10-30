<?php
/**
 * Test the Smush CDN: CdnTest
 *
 * @since 3.0
 */

/**
 * Class CdnTest
 *
 * @covers WP_Smush_CDN
 */
class CdnTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * WpunitTester tester.
	 *
	 * @var \WpunitTester $tester
	 */
	protected $tester;

	/**
	 * Global variables.
	 *
	 * @var array $_globals
	 */
	protected $_globals;

	/**
	 * Setup method.
	 */
	public function setUp() {
		parent::setUp();

		// Preserve global variables.
		global $content_width;
		$this->_globals['content_width'] = $content_width;
	}

	/**
	 * Tear down method.
	 */
	public function tearDown() {
		// Restore global variables.
		global $content_width;
		$content_width = $this->_globals['content_width'];

		parent::tearDown();
	}

	/**
	 * Prepare HTML content from a test file.
	 *
	 * @param string $file  File name in tests/_data/cdn folder.
	 *
	 * @return string
	 */
	private function get_content( $file ) {
		$path    = dirname( dirname( __FILE__ ) ) . '/_data/cdn/';
		$content = file_get_contents( $path . $file );

		$document = new \DOMDocument();
		$document->loadHTML( utf8_decode( $content ) );

		return $document->saveHTML();
	}

	/**
	 * @param WP_Smush_CDN $cdn
	 *
	 * @throws ReflectionException
	 */
	private function enableCDN( $cdn ) {
		$this->tester->setPro();
		$this->tester->setPrivateProperty( $cdn, 'cdn_active', true );

		$status = new stdClass();

		$status->site_id      = 100;
		$status->endpoint_url = 'sid.smushcdn.com';

		$this->tester->setPrivateProperty( $cdn, 'status', $status );

		$cdn->set_cdn_url();
	}

	/**
	 * Check that the CDN instance is properly initialized.
	 */
	public function testCdnInstance() {
		$cdn = new WP_Smush_CDN();

		$this->assertInstanceOf( 'WP_Smush_CDN', $cdn );
	}

	/**
	 * Test init method.
	 *
	 * @throws ReflectionException
	 */
	public function testCdnInitMethod() {
		$cdn = new WP_Smush_CDN();

		$cdn->init();

		$this->assertFalse( $cdn->get_status() );

		$this->tester->setPrivateProperty( $cdn, 'cdn_active', true );

		$this->assertTrue( $cdn->get_status() );
	}


	/**
	 * Check that CDN does not fail when process_buffer() sends empty content.
	 *
	 * @covers WP_Smush_CDN::process_img_tags
	 */
	public function testCdnParseImagesFromEmptyHTML() {
		$cdn = new WP_Smush_CDN();

		$this->assertEmpty( $cdn->process_img_tags( '' ) );
	}

	/**
	 * Verify external images are not parsed by the CDN.
	 *
	 * @covers WP_Smush_CDN::process_img_tags
	 */
	public function testCdnSkipImagesFromExternalSources() {
		$cdn = new WP_Smush_CDN();

		$content = $this->get_content( 'external-images.html' );

		$this->assertEquals( $content, $cdn->process_img_tags( $content ) );
	}

	/**
	 * Try to get dimensions from image name.
	 *
	 * @covers WP_Smush_CDN::get_size_from_file_name
	 * @throws ReflectionException
	 */
	public function testCdnGet_size_from_file_nameMethod() {
		$cdn = new WP_Smush_CDN();

		// Test image without dimensions.
		$image = 'http://' . WP_TESTS_DOMAIN . '/wp-content/plugins/wp-smushit/tests/_data/images/image1.jpeg';
		$sizes = $this->tester->callPrivateMethod( $cdn, 'get_size_from_file_name', array( $image ) );

		$this->assertEquals( array( false, false ), $sizes );

		// Test image with dimensions.
		$image = 'http://' . WP_TESTS_DOMAIN . '/wp-content/plugins/wp-smushit/tests/_data/images/image-1920x1280.jpeg';
		$sizes = $this->tester->callPrivateMethod( $cdn, 'get_size_from_file_name', array( $image ) );

		$this->assertEquals( array( 1920, 1280 ), $sizes );
	}

	/**
	 * Test that image src is replaced, srcset and sizes attributes are added when CDN is active.
	 *
	 * We don't really need this test... But getting that 100% code coverage is awesome.
	 *
	 * @throws ReflectionException
	 */
	public function testCdnGeneralFunctionality() {
		$smush   = WP_Smush::get_instance();
		$cdn     = $smush->core()->mod->cdn;
		$content = $this->get_content( 'single-image.html' );

		$this->enableCDN( $cdn );
		$smush->core()->mod->settings->set( 'auto_resize', true );

		// The new content should not match the old one, otherwise it will mean that nothing has changed.
		$this->assertNotEquals( $content, $cdn->process_img_tags( $content ) );
	}

	/**
	 * Test smush_cdn_skip_image filter.
	 *
	 * @depends testCdnGeneralFunctionality
	 * @throws ReflectionException
	 */
	public function testCdnSmush_cdn_skip_imageFilter() {
		$smush   = WP_Smush::get_instance();
		$cdn     = $smush->core()->mod->cdn;
		$content = $this->get_content( 'single-image.html' );

		$this->enableCDN( $cdn );
		$smush->core()->mod->settings->set( 'auto_resize', true );

		// Just skip all the images.
		add_filter( 'smush_skip_image_from_cdn', '__return_true', 10, 2 );

		$this->assertEquals( $content, $cdn->process_img_tags( $content ) );
	}

	/**
	 * Test valid URLs.
	 *
	 * TODO: see if we can test it via wp_calculate_image_srcset filter.
	 *
	 * @covers WP_Smush_CDN::is_valid_url
	 * @throws ReflectionException
	 */
	public function testCdnIs_valid_urlMethod() {
		$test_cases = [
			'http://localhost:8080/wp-content/plugins/wp-smushit/tests/_data/images/image1.jpeg' => true,
			'https://premium.wpmudev.org/wp-content/uploads/2014/11/smush-banner-1x.jpg' => true,
			'/wp-content/plugins/wp-smushit/tests/_data/images/image1.jpeg' => false,
			'http://localhost:8080/wp-content/plugins/wp-smushit/tests/_data/images/image1.doc' => false,
			'host:65536' => false,
		];

		$cdn = new WP_Smush_CDN();

		foreach ( $test_cases as $case => $value ) {
			$result = $this->tester->callPrivateMethod( $cdn, 'is_valid_url', array( $case ) );
			$this->assertEquals( $value, $result );
		}
	}

	/**
	 * Test adding additional srcset to image.
	 *
	 * @covers WP_Smush_CDN::update_image_srcset
	 */
	public function testCdnUpdate_image_srcsetMethod() {
		//$smush = WP_Smush::get_instance();
		//$cdn   = $smush->core()->mod->cdn;

		//$this->enableCDN( $cdn );
		//$smush->core()->mod->settings->set( 'auto_resize', true );

		$attachment_id = $this->tester->uploadImage();

		// This is similar to adding an image to the media library and then adding it to a page/post via editor.
		$image = wp_get_attachment_image( $attachment_id, 'full' );

		codecept_debug( $image );



		wp_delete_attachment( $attachment_id );
	}

	/**
	 * 1. Image from media library
	 * 2. Image from wp-contents folder (not in media library)
	 */

}
