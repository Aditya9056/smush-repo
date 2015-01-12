<?php

/**
 * Error registry class for exception transport.
 */
class WpSmushitErrorRegistry {
	private static $_errors = array();

	private function __construct() {
	}

	public static function store( $exception ) {
		self::$_errors[] = $exception;
	}

	public static function clear() {
		self::$_errors = array();
	}

	public static function get_errors() {
		return self::$_errors;
	}

	public static function get_last_error() {
		return end( self::$_errors );
	}

	public static function get_last_error_message() {
		$e = self::get_last_error();

		return ( $e && is_object( $e ) && $e instanceof Exception )
			? $e->getMessage()
			: false;
	}
}

?>