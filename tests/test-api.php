<?php
/**
 * Class ApiTest
 *
 * @package WP_Smush
 */

use Helpers\API;

/**
 * Class ApiTest
 *
 * Test the Smush API. Requires that WP_SMUSH_API and WPMUDEV_APIKEY are defined in the wp-config.php file.
 */
class ApiTest extends WP_UnitTestCase {

	/**
	 * Unit tester.
	 *
	 * @var API $tester
	 */
	protected $tester;

	/**
	 * Default Smush API server.
	 *
	 * Can be overwritten with a WP_SMUSH_API define in wp-config.php.
	 *
	 * @var string $api
	 */
	private $api = 'https://smushpro.wpmudev.org/1.0/';

	/**
	 * Smush API key.
	 *
	 * Can be overwritten with a WPMUDEV_APIKEY define in wp-config.php
	 *
	 * @var string $api_key
	 */
	private $api_key = '';

	/**
	 * Guzzle client.
	 *
	 * @var null|GuzzleHttp\Client $client
	 */
	private $client = null;

	/**
	 * Run before actions.
	 */
	public function setUp() {
		require_once 'helpers/class-api-helper.php';
		$this->tester = new API();
		$this->client = new GuzzleHttp\Client( [ 'base_uri' => $this->api ] );
	}

	/**
	 * Run after actions.
	 */
	public function tearDown() {
		$this->client = null;
		delete_option( 'wp-smush-settings' );
	}

	/**
	 * Init API server keys for Pro features.
	 */
	private function initApiKeys() {
		$api = WP_SMUSH_API;

		if ( defined( 'WPMUDEV_APIKEY' ) && WPMUDEV_APIKEY ) {
			$key = WPMUDEV_APIKEY;
		} elseif ( class_exists( 'WPMUDEV_Dashboard' ) ) {
			// If dashboard plugin is active, get API key from db.
			$key = get_site_option( 'wpmudev_apikey' );
		}

		// Get Smush API from wp-config.php file.
		if ( is_array( $api ) && ! empty( $api ) ) {
			$this->api = $api[0];
		}

		// Get WPMUDEV API key from wp-config.php file.
		if ( is_array( $key ) && ! empty( $key ) ) {
			$this->api_key = $key[0];
		}
	}

	/**
	 * Test API default response.
	 */
	public function testApiDefaultResponse() {
		$response = $this->tester->post( $this->client );

		$this->assertEquals( 200, $response->getStatusCode() );
		$this->assertEquals( false, $this->tester->get_status( $response ) );
		$this->assertEquals( 'No file content sent.', $this->tester->get_data( $response ) );
	}

	/**
	 * Test that images over 5 Mb fail for free members.
	 */
	public function testImageOverSizeLimit() {
		$jpeg  = dirname( __FILE__ ) . '/_data/images/image-large.jpg';
		$image = fopen( $jpeg, 'r' );

		$response = $this->tester->post( $this->client, [ 'body' => $image ] );

		$this->assertEquals( 200, $response->getStatusCode() );
		$this->assertEquals( false, $this->tester->get_status( $response ) );
		$this->assertEquals( 'File too big for premium level.', $this->tester->get_data( $response ) );
	}

	/**
	 * Test invalid format.
	 */
	public function testInvalidFormat() {
		$jpeg  = dirname( __FILE__ ) . '/_data/images/invalid-format.css';
		$image = fopen( $jpeg, 'r' );

		$response = $this->tester->post( $this->client, [ 'body' => $image ] );

		$this->assertEquals( 200, $response->getStatusCode() );
		$this->assertEquals( false, $this->tester->get_status( $response ) );
		$this->assertEquals( 'Invalid file format. Only PNG, JPEG, and GIF files are supported.', $this->tester->get_data( $response ) );
	}

	/**
	 * Test JpegTran library.
	 *
	 * Prerequisites:
	 * - lossy = 'false'
	 */
	public function testCheckJpegtranLibrary() {
		$jpeg  = dirname( __FILE__ ) . '/_data/images/image1.jpeg';
		$image = fopen( $jpeg, 'r' );

		$response = $this->tester->post( $this->client, [ 'body' => $image ] );

		$this->assertEquals( 200, $response->getStatusCode() );
		$this->assertEquals( true, $this->tester->get_status( $response ) );

		$data = $this->tester->get_data( $response );
		$this->assertEquals( false, $data->lossy );
		$this->assertEquals( true, $data->before_size > $data->after_size );
	}

	/**
	 * Test JpegOptim library.
	 *
	 * Prerequisites:
	 * - premium member
	 * - lossy = 'true'
	 */
	public function testCheckJpegoptimLibrary() {
		$this->initApiKeys();

		if ( empty( $this->api_key ) ) {
			$this->markTestSkipped(
				'WPMUDEV API key is required to run this test.'
			);
		}

		$jpeg  = dirname( __FILE__ ) . '/_data/images/image1.jpeg';
		$image = fopen( $jpeg, 'r' );

		$response = $this->tester->post(
			$this->client,
			[
				'body'    => $image,
				'headers' => [
					'apikey' => $this->api_key,
					'lossy'  => 'true',
				],
			]
		);

		$this->assertEquals( 200, $response->getStatusCode() );
		$this->assertEquals( true, $this->tester->get_status( $response ) );

		$data = $this->tester->get_data( $response );

		$this->assertEquals( true, $data->is_premium );
		$this->assertEquals( true, $data->lossy );
		$this->assertEquals( true, $data->before_size > $data->after_size );
	}

	/**
	 * Test Gifsicle library.
	 */
	public function testCheckGifsicleLibrary() {
		$jpeg  = dirname( __FILE__ ) . '/_data/images/image4.gif';
		$image = fopen( $jpeg, 'r' );

		$response = $this->tester->post( $this->client, [ 'body' => $image ] );

		$this->assertEquals( 200, $response->getStatusCode() );
		$this->assertEquals( true, $this->tester->get_status( $response ) );

		$data = $this->tester->get_data( $response );
		$this->assertEquals( true, $data->before_size > $data->after_size );
	}

	/**
	 * Test AdvPng library.
	 *
	 * Prerequisites:
	 * - free member
	 * - lossy = 'false'
	 */
	public function testCheckAdvpngLibrary() {
		$jpeg  = dirname( __FILE__ ) . '/_data/images/image5.png';
		$image = fopen( $jpeg, 'r' );

		$response = $this->tester->post( $this->client, [ 'body' => $image ] );

		$this->assertEquals( 200, $response->getStatusCode() );
		$this->assertEquals( true, $this->tester->get_status( $response ) );

		$data = $this->tester->get_data( $response );
		$this->assertEquals( false, $data->lossy );

		// We are not running this assertion, because advpng often does not compress the image.
		//$this->assertEquals( true, $data->before_size > $data->after_size );
	}

	/**
	 * Test OptiPng library.
	 *
	 * Prerequisites:
	 * - premium member
	 * - lossy = 'false'
	 */
	public function testCheckOptipngLibrary() {
		$this->initApiKeys();

		if ( empty( $this->api_key ) ) {
			$this->markTestSkipped(
				'WPMUDEV API key is required to run this test.'
			);
		}

		$jpeg  = dirname( __FILE__ ) . '/_data/images/image5.png';
		$image = fopen( $jpeg, 'r' );

		$response = $this->tester->post(
			$this->client,
			[
				'body'    => $image,
				'headers' => [
					'apikey' => $this->api_key,
				],
			]
		);

		$this->assertEquals( 200, $response->getStatusCode() );
		$this->assertEquals( true, $this->tester->get_status( $response ) );

		$data = $this->tester->get_data( $response );
		$this->assertEquals( false, $data->lossy );
		$this->assertEquals( true, $data->before_size > $data->after_size );
	}

	/**
	 * Test PngQuant library.
	 *
	 * Prerequisites:
	 * - premium member
	 * - lossy = 'true'
	 */
	public function testCheckPngquantLibrary() {
		$this->initApiKeys();

		if ( empty( $this->api_key ) ) {
			$this->markTestSkipped(
				'WPMUDEV API key is required to run this test.'
			);
		}

		$jpeg  = dirname( __FILE__ ) . '/_data/images/image5.png';
		$image = fopen( $jpeg, 'r' );

		$response = $this->tester->post(
			$this->client,
			[
				'body'    => $image,
				'headers' => [
					'apikey' => $this->api_key,
					'lossy'  => 'true',
				],
			]
		);

		$this->assertEquals( 200, $response->getStatusCode() );
		$this->assertEquals( true, $this->tester->get_status( $response ) );

		$data = $this->tester->get_data( $response );
		$this->assertEquals( true, $data->lossy );
		$this->assertEquals( true, $data->before_size > $data->after_size );
	}

	/**
	 * Stress test the server with multiple requests.
	 */
	public function testStressTest() {
		$jpeg = dirname( __FILE__ ) . '/_data/images/image1.jpeg';
		$data = [
			'body'        => fopen( $jpeg, 'r' ),
			'http_errors' => false,
		];

		// Initiate each request but do not block.
		$promises = [];
		for ( $i = 1; $i <= 20; $i++ ) {
			// Setting 'http_errors' to false will not error out on non 200 requests. We will compare the response code
			// later on in the assertion.
			$promises[ $i ] = $this->client->postAsync( '/', $data );
		}

		// Wait on all of the requests to complete. Throws a ConnectException if any of the requests fail.
		$results = \GuzzleHttp\Promise\unwrap( $promises );
		foreach ( $results as $response ) {
			$this->assertEquals( 200, $response->getStatusCode() );
			$this->assertEquals( true, $this->tester->get_status( $response ) );
		}
	}

}
