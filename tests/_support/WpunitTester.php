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
 * @method \Codeception\Lib\Friend haveFriend($name, $actorClass = NULL)
 *
 * @SuppressWarnings(PHPMD)
*/
class WpunitTester extends \Codeception\Actor
{
    use _generated\WpunitTesterActions;

	/**
	 * Upload single image to media library.
	 *
	 * @return mixed  Image ID on success.
	 */
	public function uploadImage() {
		$file = dirname( dirname( __FILE__ ) ) . '/_data/images/image1.jpeg';

		$factory = new WP_UnitTest_Factory();

		return $factory->attachment->create_upload_object( $file );
	}

	/**
	 * Add an image to the wp_posts table.
	 *
	 * @return mixed  Image ID on success.
	 */
	public function createImgPost() {
		$file = dirname( dirname( __FILE__ ) ) . '/_data/images/image1.jpeg';

		$args = [
			'post_title'   => basename( $file ),
			'post_content' => $file,
		];

		$factory = new WP_UnitTest_Factory();

		return $factory->attachment->create( $args );
	}

	/**
	 * Allow to override private properties.
	 *
	 * @param object $object    Object with the property.
	 * @param string $property  Property name.
	 * @param mixed  $value     Value to set.
	 *
	 * @throws ReflectionException
	 */
	public function setPrivateProperty( &$object, $property, $value ) {
		$reflection = new \ReflectionClass( get_class( $object ) );
		$property   = $reflection->getProperty( $property );

		$property->setAccessible( true );
		$property->setValue( $object, $value );
	}

	/**
	 * Set Smush to Smush Pro.
	 */
	public function setPro() {
		$smush = WP_Smush::get_instance();

		$this->setPrivateProperty( $smush, 'is_pro', true );

		$smush->validate_install();
	}

	/**
	 * Set Smush to free version.
	 */
	public function setFree() {
		$smush = WP_Smush::get_instance();

		$this->setPrivateProperty( $smush, 'is_pro', false );

		$smush->validate_install();
	}
}
