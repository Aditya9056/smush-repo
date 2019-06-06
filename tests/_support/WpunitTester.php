<?php


/**
 * Inherited Methods
 *
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
	 * @param object $object    Object with the property
	 * @param string $property  Property name
	 *
	 * @return mixed
	 * @throws ReflectionException
	 */
	public function readPrivateProperty( &$object, $property ) {
		$reflection = new \ReflectionClass( get_class( $object ) );
		$property   = $reflection->getProperty( $property );

		$property->setAccessible( true );

		return $property->getValue( $object );
	}

	/**
	 * Allow to override private methods.
	 *
	 * @since 3.0
	 *
	 * @param object $object       Reference to the object.
	 * @param string $method_name  Method to call.
	 * @param array  $args         Array of objects to pass to the method.
	 *
	 * @return mixed  Method results.
	 * @throws ReflectionException
	 */
	public function callPrivateMethod( &$object, $method_name, array $args = array() ) {
		$reflection = new \ReflectionClass( get_class( $object ) );
		$method     = $reflection->getMethod( $method_name );
		$method->setAccessible( true );

		return $method->invokeArgs( $object, $args );
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

	/**
	 * Create blogs for multisite.
	 *
	 * @since 3.2.2
	 */
	public static function createBlogs() {
		global $wpdb;

		$wpdb->insert(
			$wpdb->site,
			[
				'id'     => 1,
				'domain' => 'localhost',
				'path'   => '/',
			],
		); // Db call ok.

		wpmu_create_blog( 'localhost', '/', 'Test Site 1', 1, [ 'public' => 1 ] );
		wpmu_create_blog( 'localhost', '/site1/', 'Test Site 2', 1, [ 'public' => 1 ] );
		switch_to_blog( 1 );
	}

}
