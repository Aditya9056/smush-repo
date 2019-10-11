<?php
/**
 * Class StatsTest.
 *
 * @since 3.4.0
 * @package WP_Smush
 */

use Helpers\Helper;
use Smush\WP_Smush;

/**
 * Class StatsTest
 *
 * @covers \Smush\Core\Stats
 */
class StatsTest extends \WP_UnitTestCase {

	/**
	 * Smush instance.
	 *
	 * @since 3.4.0
	 * @var WP_Smush
	 */
	private $smush;

	/**
	 * WpunitTester tester.
	 *
	 * @since 3.4.0
	 * @var Helper $tester
	 */
	protected $tester;

	/**
	 * Run before actions.
	 *
	 * @since 3.4.0
	 */
	public function setUp() {

		require_once 'helpers/class-helper.php';
		$this->tester = new Helper();

		$this->smush = WP_Smush::get_instance();

	}

	/**
	 * Run after actions.
	 *
	 * @since 3.4.0
	 */
	public function tearDown() {
		delete_option( 'smush_global_stats' );
	}

	/**
	 * Test construct method
	 *
	 * @since 3.4.0
	 * @covers \Smush\Core\Stats::__construct
	 */
	public function testConstruct() {
		$this->assertInstanceOf( '\\Smush\\Core\\Stats', $this->smush->core() );
	}

	/**
	 * Test sending global stats to Hub.
	 *
	 * @since 3.4.0
	 * @covers \Smush\Core\Stats::send_smush_stats
	 */
	public function testSendSmushStats() {

		$stats = [
			'percent'      => 0,
			'human'        => '0.0 B',
			'bytes'        => 0,
			'total_images' => 0,
		];

		$this->assertEquals( $stats, $this->smush->core()->send_smush_stats() );

		// Make sure it is not auto smushed.
		Smush\Core\Settings::get_instance()->set( 'auto', true );
		// Upload.
		$id = $this->tester->upload_image();

		// Update stats.
		$this->smush->core()->setup_global_stats( true );

		$meta = get_post_meta( $id, 'wp-smpro-smush-data', true );

		$stats['percent']      = round( $meta['stats']['percent'], 1 );
		$stats['human']        = size_format( $meta['stats']['bytes'], 1 );
		$stats['bytes']        = $meta['stats']['bytes'];
		$stats['total_images'] = count( $meta['sizes'] );

		$this->assertEquals( $stats, $this->smush->core()->send_smush_stats() );

		// We don't need the attachment anymore. Delete.
		wp_delete_attachment( $id, true );

	}

	/**
	 * Test getting media attachment IDs.
	 *
	 * @since 3.4.0
	 * @covers \Smush\Core\Stats::get_media_attachments
	 */
	/*
	public function testSetupGlobalStats() {

		$posts = $this->smush->core()->get_media_attachments();
		var_dump( $posts );

	}
	*/

}
