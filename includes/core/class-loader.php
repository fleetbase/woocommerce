<?php
/**
 * Plugin Loader
 *
 * Registers all hooks and filters for the plugin.
 *
 * @package FleetbaseWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fleetbase_Loader
 */
class Fleetbase_Loader extends Fleetbase_Singleton {
	/**
	 * Initialize the plugin.
	 */
	protected function __construct() {
		$this->register_hooks();
	}

	/**
	 * Register all hooks and filters.
	 */
	private function register_hooks() {
		// Admin hooks
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );

		// WooCommerce hooks
		add_filter( 'woocommerce_shipping_methods', array( $this, 'register_shipping_method' ) );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'sync_order_to_fleetbase' ), 10, 1 );

		// REST API hooks for webhooks
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Shortcodes
		add_shortcode( 'fleetbase_tracking_page', array( $this, 'render_tracking_page' ) );
	}

	/**
	 * Register admin menu.
	 */
	public function register_admin_menu() {
		Fleetbase_Settings::get_instance()->register_menu();
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on our plugin pages
		if ( strpos( $hook, 'fleetbase' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'fleetbase-admin',
			FLEETBASE_WOOCOMMERCE_PLUGIN_URL . 'admin/css/fleetbase-admin.css',
			array(),
			FLEETBASE_WOOCOMMERCE_VERSION
		);

		wp_enqueue_script(
			'fleetbase-admin',
			FLEETBASE_WOOCOMMERCE_PLUGIN_URL . 'admin/js/fleetbase-admin.js',
			array( 'jquery' ),
			FLEETBASE_WOOCOMMERCE_VERSION,
			true
		);

		// Pass data to JavaScript
		wp_localize_script(
			'fleetbase-admin',
			'fleetbaseAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'fleetbase_admin_nonce' ),
			)
		);
	}

	/**
	 * Display admin notices.
	 */
	public function display_admin_notices() {
		Fleetbase_Onboarding::get_instance()->display_onboarding_notice();
	}

	/**
	 * Register shipping method with WooCommerce.
	 *
	 * @param array $methods Existing shipping methods.
	 * @return array Modified shipping methods.
	 */
	public function register_shipping_method( $methods ) {
		$methods['fleetbase'] = 'Fleetbase_Shipping_Method';
		return $methods;
	}

	/**
	 * Sync order to Fleetbase after checkout.
	 *
	 * @param int $order_id The WooCommerce order ID.
	 */
	public function sync_order_to_fleetbase( $order_id ) {
		Fleetbase_Order_Sync::get_instance()->sync_order( $order_id );
	}

	/**
	 * Register REST API routes for webhooks.
	 */
	public function register_rest_routes() {
		Fleetbase_Webhook_Handler::get_instance()->register_routes();
	}

	/**
	 * Render the tracking page shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string The rendered tracking page HTML.
	 */
	public function render_tracking_page( $atts ) {
		ob_start();
		include FLEETBASE_WOOCOMMERCE_PLUGIN_DIR . 'templates/shortcode-tracking-page.php';
		return ob_get_clean();
	}

	/**
	 * Run the plugin.
	 */
	public function run() {
		// Plugin is now running
		do_action( 'fleetbase_woocommerce_loaded' );
	}
}
