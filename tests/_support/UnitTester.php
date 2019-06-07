<?php


/**
 * Inherited Methods
 * @method void wantToTest($text)
 * @method void wantTo($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method void pause()
 *
 * @SuppressWarnings(PHPMD)
*/
class UnitTester extends \Codeception\Actor
{
    use _generated\UnitTesterActions;

	/**
	 * Define custom actions here.
	 *
	 * @param GuzzleHttp\Client $client
	 * @param array $data
	 *
	 * @return Object
	 */
	public function post( $client, $data = [] ) {
		$response = $client->request( 'POST', '/', $data );

		return $response;
	}

	/**
	 * Get success status of a response.
	 *
	 * @param object $response
	 *
	 * @return mixed
	 */
	public function getStatus( $response ) {
		return json_decode( $response->getBody() )->{'success'};
	}

	/**
	 * Get data status of a response.
	 *
	 * @param object $response
	 *
	 * @return mixed
	 */
	public function getData( $response ) {
		return json_decode( $response->getBody() )->{'data'};
	}
}
