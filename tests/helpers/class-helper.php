<?php
/**
 * General helper class.
 *
 * @package WP_Smush
 */

namespace Helpers;

use ReflectionClass;
use ReflectionException;
use WP_Smush;
use WP_UnitTestCase;

/**
 * Class Helper
 *
 * @package Helpers
 */
class Helper extends WP_UnitTestCase {

	/**
	 * Upload single image to media library.
	 *
	 * @return mixed  Image ID on success.
	 */
	public function upload_image() {
		$file = dirname( dirname( __FILE__ ) ) . '/_data/images/image1.jpeg';

		return $this->factory->attachment->create_upload_object( $file );
	}

	/**
	 * Add an image to the wp_posts table.
	 *
	 * @return mixed  Image ID on success.
	 */
	public function create_img_post() {
		$file = dirname( dirname( __FILE__ ) ) . '/_data/images/image1.jpeg';

		$args = [
			'post_title'   => basename( $file ),
			'post_content' => $file,
		];

		return $this->factory->attachment->create( $args );
	}

	/**
	 * Allow to override private properties.
	 *
	 * @param object $object    Object with the property.
	 * @param string $property  Property name.
	 * @param mixed  $value     Value to set.
	 *
	 * @throws ReflectionException  Exception.
	 */
	public function set_private_property( &$object, $property, $value ) {
		$reflection = new ReflectionClass( get_class( $object ) );
		$property   = $reflection->getProperty( $property );

		$property->setAccessible( true );
		$property->setValue( $object, $value );
	}

	/**
	 * Read a private property of an object.
	 *
	 * @param object $object    Object with the property.
	 * @param string $property  Property name.
	 *
	 * @return mixed
	 * @throws ReflectionException  Exception.
	 */
	public function read_private_property( &$object, $property ) {
		$reflection = new ReflectionClass( get_class( $object ) );
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
	 * @throws ReflectionException  Exception.
	 */
	public function call_private_method( &$object, $method_name, array $args = array() ) {
		$reflection = new ReflectionClass( get_class( $object ) );
		$method     = $reflection->getMethod( $method_name );
		$method->setAccessible( true );

		return $method->invokeArgs( $object, $args );
	}

	/**
	 * Set Smush to Smush Pro.
	 *
	 * @throws ReflectionException  Exception.
	 */
	public function set_pro() {
		$smush = WP_Smush::get_instance();

		$this->set_private_property( $smush, 'is_pro', true );

		$smush->validate_install();
	}

	/**
	 * Set Smush to free version.
	 *
	 * @throws ReflectionException  Exception.
	 */
	public function set_free() {
		$smush = WP_Smush::get_instance();

		$this->set_private_property( $smush, 'is_pro', false );

		$smush->validate_install();
	}

}
