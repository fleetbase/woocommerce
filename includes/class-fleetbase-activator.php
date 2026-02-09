<?php
/**
 * Plugin Activator
 *
 * Handles plugin activation and deactivation.
 *
 * @package FleetbaseWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fleetbase_Activator
 */
class Fleetbase_Activator {
	/**
	 * Activate the plugin.
	 */
	public static function activate() {
		// Check for WooCommerce
		if ( ! class_exists( 'WooCommerce' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die(
				esc_html__( 'Fleetbase for WooCommerce requires WooCommerce to be installed and active.', 'fleetbase-woocommerce' ),
				esc_html__( 'Plugin Activation Error', 'fleetbase-woocommerce' ),
				array( 'back_link' => true )
			);
		}

		// Set default options
		self::set_default_options();

		// Create custom order statuses
		self::register_custom_order_statuses();

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Deactivate the plugin.
	 */
	public static function deactivate() {
		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Set default plugin options.
	 */
	private static function set_default_options() {
		$defaults = array(
			'api_key'              => '',
			'api_secret'           => '',
			'api_host'             => 'https://api.fleetbase.io',
			'company_id'           => '',
			'service_rate_id'      => '',
			'auto_sync_orders'     => true,
			'enable_tracking_page' => true,
			'trial_units_used'     => 0,
			'trial_units_total'    => 200,
			'onboarding_completed' => false,
		);

		$existing_settings = get_option( 'fleetbase_settings', array() );
		$settings          = wp_parse_args( $existing_settings, $defaults );
		
		update_option( 'fleetbase_settings', $settings );
	}

	/**
	 * Register custom order statuses for Fleetbase.
	 */
	private static function register_custom_order_statuses() {
		// Register custom order statuses
		register_post_status(
			'wc-fleetbase-dispatched',
			array(
				'label'                     => _x( 'Out for Delivery', 'Order status', 'fleetbase-woocommerce' ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( 'Out for Delivery <span class="count">(%s)</span>', 'Out for Delivery <span class="count">(%s)</span>', 'fleetbase-woocommerce' ),
			)
		);

		register_post_status(
			'wc-fleetbase-completed',
			array(
				'label'                     => _x( 'Delivered', 'Order status', 'fleetbase-woocommerce' ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( 'Delivered <span class="count">(%s)</span>', 'Delivered <span class="count">(%s)</span>', 'fleetbase-woocommerce' ),
			)
		);
	}
}
