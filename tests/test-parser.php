<?php
/**
 * Test the Smush Parser: ParserTest
 *
 * @since 3.3.0
 * @package WP_Smush
 */

use Smush\Core\Modules\Helpers\Parser;

/**
 * Class ParserTest
 *
 * @covers Smush\Core\Modules\Helpers\Parser
 */
class ParserTest extends WP_UnitTestCase {

	/**
	 * WpunitTester tester.
	 *
	 * @var Helpers\Helper $tester
	 */
	protected $tester;

	/**
	 * Run before actions.
	 */
	public function setUp() {
		require_once 'helpers/class-helper.php';
		$this->tester = new Helpers\Helper();
	}

	/**
	 * Run after actions.
	 *
	 * @since 3.4.0
	 */
	public function tearDown() {
		delete_option( 'wp-smush-settings' );
	}

	/**
	 * Test background image support.
	 *
	 * @covers Smush\Core\Modules\Helpers\Parser::get_background_images
	 */
	public function test_parse_background_images() {
		$content = $this->tester->get_content( 'background-images.html' );
		$parser  = new Parser();

		$images = $this->tester->call_private_method( $parser, 'get_background_images', [ $content ] );

		// Check that result is an array, has the correct keys and all the images are there.
		$this->assertInternalType( 'array', $images );
		$this->assertArrayHasKey( '0', $images );
		$this->assertArrayHasKey( 'img_url', $images );
		$this->assertCount( 3, $images[0] );
		$this->assertCount( 3, $images['img_url'] );

		// Validate that parser is correct.
		$this->assertEquals( '<div class="div-image" style="background-image: url(https://example.com/image.jpg);">', $images[0][0] );
		$this->assertEquals( 'https://example.com/image.jpg', $images['img_url'][0] );

		$this->assertEquals( '<div style="background-image: url(\'https://example.com/image.png\');" class="div-image">', $images[0][1] );
		$this->assertEquals( 'https://example.com/image.png', $images['img_url'][1] );

		$this->assertEquals( '<span class="span-image" style=\'background-image: url("https://example.com/image.gif");\' id="imageID">', $images[0][2] );
		$this->assertEquals( 'https://example.com/image.gif', $images['img_url'][2] );
	}

}
