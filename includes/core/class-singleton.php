<?php
/**
 * Singleton Base Class
 *
 * @package FleetbaseWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base singleton class for ensuring only one instance of a class exists.
 */
abstract class Fleetbase_Singleton {
	/**
	 * The single instance of the class.
	 *
	 * @var object
	 */
	protected static $instances = array();

	/**
	 * Protected constructor to prevent creating a new instance via the `new` operator.
	 */
	protected function __construct() {}

	/**
	 * Private clone method to prevent cloning of the instance.
	 */
	private function __clone() {}

	/**
	 * Private unserialize method to prevent unserializing.
	 */
	public function __wakeup() {
		throw new Exception( 'Cannot unserialize singleton' );
	}

	/**
	 * Get the singleton instance.
	 *
	 * @return object The singleton instance.
	 */
	public static function get_instance() {
		$class = get_called_class();
		
		if ( ! isset( self::$instances[ $class ] ) ) {
			self::$instances[ $class ] = new $class();
		}
		
		return self::$instances[ $class ];
	}
}
