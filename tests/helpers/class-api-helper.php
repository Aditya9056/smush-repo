<?php
/**
 * API helpers class.
 *
 * @package WP_Smush
 */

namespace Helpers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Class API
 *
 * @package Helpers
 */
class API {

	/**
	 * Define custom actions here.
	 *
	 * @param Client $client  GuzzleHttp client.
	 * @param array  $data    Data object.
	 *
	 * @return Object
	 * @throws GuzzleException  Exception.
	 */
	public function post( $client, $data = [] ) {
		$response = $client->request( 'POST', '/', $data );
		return $response;
	}

	/**
	 * Get success status of a response.
	 *
	 * @param object $response  Response object.
	 *
	 * @return mixed
	 */
	public function get_status( $response ) {
		return json_decode( $response->getBody() )->{'success'};
	}

	/**
	 * Get data status of a response.
	 *
	 * @param object $response  Response object.
	 *
	 * @return mixed
	 */
	public function get_data( $response ) {
		return json_decode( $response->getBody() )->{'data'};
	}

}
