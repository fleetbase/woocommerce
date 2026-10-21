<?php
/**
 * Plugin Name: Fleetbase for WooCommerce
 * Plugin URI: https://fleetbase.io/woocommerce
 * Description: Last-mile delivery for WooCommerce. Live delivery quotes at checkout, driver and vehicle management, and automatic order sync with Fleetbase.
 * Version: 1.0.0
 * Author: Fleetbase
 * Author URI: https://fleetbase.io
 * Text Domain: fleetbase-for-woocommerce
 * Domain Path: /languages
 * Requires at least: 6.8
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * WC requires at least: 9.5
 * WC tested up to: 10.5
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package Fleetbase\WooCommerce
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'FLEETBASE_WC_VERSION' ) ) {
	return;
}

define( 'FLEETBASE_WC_VERSION', '1.0.0' );
define( 'FLEETBASE_WC_FILE', __FILE__ );
define( 'FLEETBASE_WC_PATH', plugin_dir_path( __FILE__ ) );
define( 'FLEETBASE_WC_URL', plugin_dir_url( __FILE__ ) );
define( 'FLEETBASE_WC_BASENAME', plugin_basename( __FILE__ ) );
define( 'FLEETBASE_WC_MIN_WC_VERSION', '9.5' );

/**
 * Load the Composer autoloader when present, otherwise fall back to a minimal PSR-4 loader.
 */
if ( file_exists( FLEETBASE_WC_PATH . 'vendor/autoload.php' ) ) {
	require_once FLEETBASE_WC_PATH . 'vendor/autoload.php';
} else {
	spl_autoload_register(
		static function ( string $class_name ): void {
			$prefix = 'Fleetbase\\WooCommerce\\';
			if ( 0 !== strpos( $class_name, $prefix ) ) {
				return;
			}
			$relative = str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) );
			$file     = FLEETBASE_WC_PATH . 'src/' . $relative . '.php';
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
	);
}

/**
 * Declare compatibility with WooCommerce features before WooCommerce initialises.
 */
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

register_activation_hook( __FILE__, [ \Fleetbase\WooCommerce\Plugin::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \Fleetbase\WooCommerce\Plugin::class, 'deactivate' ] );

/**
 * Boot once every plugin is loaded so WooCommerce is available.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		\Fleetbase\WooCommerce\Plugin::instance()->boot();
	},
	20
);

/**
 * Convenience accessor for the plugin instance.
 */
function fleetbase_wc(): \Fleetbase\WooCommerce\Plugin {
	return \Fleetbase\WooCommerce\Plugin::instance();
}
