<?php
/**
 * Smush tests
 *
 * @package WP_Smush
 */

use Helpers\Helper;

/**
 * Class SmushTest
 */
class SmushTest extends WP_UnitTestCase {

	/**
	 * WpunitTester tester.
	 *
	 * @var Helper $tester
	 */
	protected $tester;

	/**
	 * Setup method.
	 */
	public function setUp() {
		require_once 'helpers/class-helper.php';
		$this->tester = new Helper();

		WP_Smush_Installer::smush_activated();
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
		$id = $this->tester->upload_image();

		// Smush the image.
		WP_Smush::get_instance()->core()->mod->smush->smush_single( $id, true );

		// Try to get the smushed meta.
		$smush_meta = get_post_meta( $id, WP_Smushit::$smushed_meta_key, true );

		// We don't need the attachment anymore. Delete.
		wp_delete_attachment( $id, true );

		// Make sure meta is set.
		$this->assertTrue( ! empty( $smush_meta ) );
	}

	/**
	 * Test restore image after smushing.
	 */
	/*
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
		$backup = new WP_Smush_Backup();
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
	*/

}
