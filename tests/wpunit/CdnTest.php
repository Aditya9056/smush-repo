<?php
/**
 * Test the Smush CDN: CdnTest
 *
 * 1. Make sure you have a valid symlink from your test directory to your plugin folder:
 * # ln -s /srv/www/wordpress/public_html/wp-content/plugins/wp-smushit /tmp/wordpress/wp-content/plugins/wp-smushit
 *
 * 2. Make sure a you have a localhost accessible with WordPress and the linked plugin:
 * # php -S localhost:8080 -t /tmp/wordpress &>/dev/null&
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

		// Remove extra image sizes.
		$this->remove_image_sizes();
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
	 * Remove the image sizes that we do not want to test against. Keep only WordPress originals.
	 * This makes it more predictable and theme independent.
	 */
	private function remove_image_sizes() {
		// Default WordPress image sizes.
		$default_image_sizes  = [ 'thumbnail', 'medium', 'medium_large', 'large' ];
		$all_registered_sizes = get_intermediate_image_sizes();

		// Remove everything else.
		foreach ( $all_registered_sizes as $size ) {
			if ( in_array( $size, $default_image_sizes ) ) {
				continue;
			}

			remove_image_size( $size );
		}
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
	 * Test enable CDN.
	 *
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
		$cdn = new WP_Smush_CDN( new WP_Smush_Page_Parser() );

		$this->assertInstanceOf( 'WP_Smush_CDN', $cdn );
	}

	/**
	 * Test init method.
	 *
	 * @throws ReflectionException
	 */
	public function testCdnInitMethod() {
		$cdn = new WP_Smush_CDN( new WP_Smush_Page_Parser() );

		$cdn->init();

		$this->assertFalse( $cdn->get_status() );

		$this->tester->setPrivateProperty( $cdn, 'cdn_active', true );

		$this->assertTrue( $cdn->get_status() );
	}

	/**
	 * Verify that the proper settings are registered in the module.
	 *
	 * @covers WP_Smush_CDN::add_settings
	 */
	public function testCdnAddSettingsToGroup() {
		$this->assertEquals( [ 'auto_resize', 'webp' ], apply_filters( 'wp_smush_cdn_settings', [] ) );
	}

	/**
	 * Test if CDN settings descriptions are properly registered and match the settings fields.
	 *
	 * @depends testCdnAddSettingsToGroup
	 * @covers WP_Smush_CDN::register
	 */
	public function testCdnSettings() {
		$smush = WP_Smush::get_instance();

		// Init settings.
		$smush->core()->admin_init();

		$registered_settings = apply_filters( 'wp_smush_cdn_settings', [] );

		// Loop through all the settings and check for a description.
		foreach ( $registered_settings as $setting ) {
			$this->assertArrayHasKey( $setting, $smush->core()->settings );
		}
	}

	/**
	 * Test to see if init_flags() method can set the status property.
	 *
	 * @covers WP_Smush_CDN::init_flags
	 */
	public function testCdnInitFlagsMethod() {
		$cdn = new WP_Smush_CDN( new WP_Smush_Page_Parser() );

		$this->assertNull( $this->tester->readPrivateProperty( $cdn, 'status' ) );

		// Simulate the dash plugin.
		if ( ! file_exists( WP_PLUGIN_DIR . '/wpmudev-updates/update-notifications.php' ) ) {
			mkdir( WP_PLUGIN_DIR . '/wpmudev-updates' );
			touch( WP_PLUGIN_DIR . '/wpmudev-updates/update-notifications.php' );
		}

		$this->tester->setPro();
		$cdn->init_flags();

		// By this time the value should have been changed from null to false.
		$this->assertFalse( $this->tester->readPrivateProperty( $cdn, 'status' ) );

		// Add some bogus value to wp-smush-cdn_status setting.
		update_option( WP_SMUSH_PREFIX . 'cdn_status', 1 );
		$cdn->init_flags();

		// Check that the status is reflected in the private var.
		$this->assertEquals( 1, $this->tester->readPrivateProperty( $cdn, 'status' ) );

		// Remove the fake dash plugin.
		if ( file_exists( WP_PLUGIN_DIR . '/wpmudev-updates/update-notifications.php' ) ) {
			$this->unlink( WP_PLUGIN_DIR . '/wpmudev-updates/update-notifications.php' );
			rmdir( WP_PLUGIN_DIR . '/wpmudev-updates' );
		}
	}

	/**
	 * Check that CDN does not fail when process_buffer() sends empty content.
	 *
	 * @covers WP_Smush_CDN::process_img_tags
	 */
	public function testCdnParseImagesFromEmptyHTML() {
		$cdn = new WP_Smush_CDN( new WP_Smush_Page_Parser() );

		$this->assertEmpty( $cdn->process_img_tags( '' ) );
	}

	/**
	 * Verify external images are not parsed by the CDN.
	 *
	 * @covers WP_Smush_CDN::process_img_tags
	 */
	public function testCdnSkipImagesFromExternalSources() {
		$cdn = new WP_Smush_CDN( new WP_Smush_Page_Parser() );

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
		$cdn = new WP_Smush_CDN( new WP_Smush_Page_Parser() );

		// Test image without dimensions.
		$image = 'http://' . WP_TESTS_DOMAIN . '/wp-content/plugins/wp-smushit/tests/_data/images/image1.jpeg';
		$sizes = $this->tester->callPrivateMethod( $cdn, 'get_size_from_file_name', [ $image ] );

		$this->assertEquals( [ false, false ], $sizes );

		// Test image with dimensions.
		$image = 'http://' . WP_TESTS_DOMAIN . '/wp-content/plugins/wp-smushit/tests/_data/images/image-1920x1280.jpeg';
		$sizes = $this->tester->callPrivateMethod( $cdn, 'get_size_from_file_name', [ $image ] );

		$this->assertEquals( [ 1920, 1280 ], $sizes );
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

		$cdn = new WP_Smush_CDN( new WP_Smush_Page_Parser() );

		foreach ( $test_cases as $case => $value ) {
			$result = $this->tester->callPrivateMethod( $cdn, 'is_valid_url', [ $case ] );
			$this->assertEquals( $value, $result );
		}
	}

	/**
	 * Image from media library should be converted to CDN format.
	 * Also test adding additional srcset to image.
	 *
	 * This seems a bit too long for a single test.
	 *
	 * @covers WP_Smush_CDN::update_image_srcset
	 */
	public function testCdnUpdate_image_srcsetMethod() {

		$attachment_id = $this->tester->uploadImage();

		// This is similar to adding an image to the media library and then adding it to a page/post via editor.
		$image = wp_get_attachment_image( $attachment_id, 'full' );
		$this->assertEquals( 5, substr_count( $image, WP_TESTS_DOMAIN ) );

		$smush = WP_Smush::get_instance();
		$cdn   = $smush->core()->mod->cdn;

		$smush->core()->mod->settings->set( 'auto_resize', false );
		$smush->core()->mod->settings->set( 'cdn', true );
		$this->enableCDN( $cdn );
		$cdn->init();

		// This will convert all srcset links to CDN.
		$image = wp_get_attachment_image( $attachment_id, 'full' );

		// Convert image src to CDN.
		$cdn_image = $cdn->process_img_tags( $image );
		$this->assertEquals( 5, substr_count( $cdn_image, 'sid.smushcdn.com' ) );

		// Enable auto resize.
		$smush->core()->mod->settings->set( 'auto_resize', true );
		$image = wp_get_attachment_image( $attachment_id, 'full' );

		$cdn_image = $cdn->process_img_tags( $image );
		$this->assertEquals( 10, substr_count( $cdn_image, 'sid.smushcdn.com' ) );

		wp_delete_attachment( $attachment_id );
	}

	/**
	 * 1. Image from media library
	 * 2. Image from wp-contents folder (not in media library)
	 */

}
