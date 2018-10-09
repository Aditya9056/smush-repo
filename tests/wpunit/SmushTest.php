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
	 * WpunitTester tester.
	 *
	 * @var \WpunitTester $tester
	 */
	protected $tester;

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
	 * Set setting value temporarily.
	 *
	 * @param string $key Setting name.
	 * @param bool   $value True or false.
	 */
	private function setSetting( $key, $value = true ) {
		$settings = WP_Smush_Settings::get_instance();
		$settings->set( $key, $value );
	}

	/**
	 * Test Smush single image.
	 */
	public function testSmushSingle() {
		$smush = WP_Smush::get_instance();
		// Make sure it is not auto smushed.
		$smush->core()->mod->settings->set( 'auto', false );

		// Upload image.
		$id = $this->tester->uploadImage();

		// Smush the image.
		// TODO: we need to actually Smush, not pass throught the smush_single.
		$a = WP_Smush::get_instance()->core()->mod->smush->smush_single( $id, true );
		codecept_debug( $a );

		// Try to get the smushed meta.
		$smush_meta = get_post_meta( $id, WP_Smushit::$smushed_meta_key, true );

		codecept_debug( $smush_meta );

		// We don't need the attachment anymore. Delete.
		wp_delete_attachment( $id, true );

		// Make sure meta is set.
		$this->assertTrue( ! empty( $smush_meta ) );
	}

	/**
	 * Test restore image after smushing.
	 */
	public function testRestoreSingle() {
		// Set smush pro.
		$this->tester->setPro();

		// Make sure it is auto smushed.
		$this->setSetting( 'auto', true );
		// Make sure smush original enabled.
		$this->setSetting( 'original', true );
		// Make sure backup original enabled.
		$this->setSetting( 'backup', true );

		// Enable backup image.
		$backup = WP_Smush::get_instance()->core()->mod->backup;
		$this->tester->setPrivateProperty( $backup, 'backup_enabled', true );

		// Upload an image.
		$id = $this->tester->uploadImage();

		// Restore image.
		$backup->restore_image( $id, false );
		// Try to get smushed meta.
		$restored_meta = get_post_meta( $id, WP_Smushit::$smushed_meta_key, true );

		// We don't need the attachment anymore. Delete.
		wp_delete_attachment( $id, true );

		// Make sure meta is empty.
		$this->assertEmpty( $restored_meta );
	}

}
