<?php
/**
 * Plugin Name: Fleetbase for WooCommerce
 * Plugin URI: https://github.com/fleetbase/fleetbase-woocommerce
 * Description: Official Fleetbase integration for WooCommerce - Open source logistics management for your online store. Automate deliveries, track orders in real-time, and scale your logistics operations.
 * Version: 1.0.0
 * Author: Fleetbase
 * Author URI: https://fleetbase.io
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fleetbase-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.5
 *
 * @package FleetbaseWooCommerce
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 */
define( 'FLEETBASE_WOOCOMMERCE_VERSION', '1.0.0' );
define( 'FLEETBASE_WOOCOMMERCE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLEETBASE_WOOCOMMERCE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FLEETBASE_WOOCOMMERCE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 */
function activate_fleetbase_woocommerce() {
	require_once FLEETBASE_WOOCOMMERCE_PLUGIN_DIR . 'includes/class-fleetbase-activator.php';
	Fleetbase_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_fleetbase_woocommerce() {
	require_once FLEETBASE_WOOCOMMERCE_PLUGIN_DIR . 'includes/class-fleetbase-activator.php';
	Fleetbase_Activator::deactivate();
}

register_activation_hook( __FILE__, 'activate_fleetbase_woocommerce' );
register_deactivation_hook( __FILE__, 'deactivate_fleetbase_woocommerce' );

/**
 * Check if WooCommerce is active
 */
function fleetbase_woocommerce_check_dependencies() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'fleetbase_woocommerce_missing_wc_notice' );
		return false;
	}
	return true;
}

/**
 * Display admin notice if WooCommerce is not active
 */
function fleetbase_woocommerce_missing_wc_notice() {
	?>
	<div class="error">
		<p><?php esc_html_e( 'Fleetbase for WooCommerce requires WooCommerce to be installed and active.', 'fleetbase-woocommerce' ); ?></p>
	</div>
	<?php
}

/**
 * Begin execution of the plugin.
 */
function run_fleetbase_woocommerce() {
	// Check dependencies first
	if ( ! fleetbase_woocommerce_check_dependencies() ) {
		return;
	}

	// Load core classes
	require_once FLEETBASE_WOOCOMMERCE_PLUGIN_DIR . 'includes/core/class-singleton.php';
	require_once FLEETBASE_WOOCOMMERCE_PLUGIN_DIR . 'includes/core/class-loader.php';
	require_once FLEETBASE_WOOCOMMERCE_PLUGIN_DIR . 'includes/class-fleetbase-api.php';
	require_once FLEETBASE_WOOCOMMERCE_PLUGIN_DIR . 'includes/class-fleetbase-settings.php';
	require_once FLEETBASE_WOOCOMMERCE_PLUGIN_DIR . 'includes/class-fleetbase-onboarding.php';
	require_once FLEETBASE_WOOCOMMERCE_PLUGIN_DIR . 'includes/class-fleetbase-usage-tracker.php';
	require_once FLEETBASE_WOOCOMMERCE_PLUGIN_DIR . 'includes/class-fleetbase-webhook-handler.php';
	require_once FLEETBASE_WOOCOMMERCE_PLUGIN_DIR . 'includes/sync/class-order-sync.php';
	require_once FLEETBASE_WOOCOMMERCE_PLUGIN_DIR . 'includes/shipping/class-fleetbase-shipping-method.php';

	// Initialize the plugin
	$loader = Fleetbase_Loader::get_instance();
	$loader->run();
}

add_action( 'plugins_loaded', 'run_fleetbase_woocommerce' );
