<?php
/**
 * Fleetbase Settings
 *
 * Manages plugin settings and admin pages.
 *
 * @package FleetbaseWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fleetbase_Settings
 */
class Fleetbase_Settings extends Fleetbase_Singleton {
	/**
	 * Settings option name.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'fleetbase_settings';

	/**
	 * Register admin menu.
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Fleetbase', 'fleetbase-woocommerce' ),
			__( 'Fleetbase', 'fleetbase-woocommerce' ),
			'manage_woocommerce',
			'fleetbase',
			array( $this, 'render_dashboard_page' ),
			'dashicons-location-alt',
			56
		);

		add_submenu_page(
			'fleetbase',
			__( 'Dashboard', 'fleetbase-woocommerce' ),
			__( 'Dashboard', 'fleetbase-woocommerce' ),
			'manage_woocommerce',
			'fleetbase',
			array( $this, 'render_dashboard_page' )
		);

		add_submenu_page(
			'fleetbase',
			__( 'Settings', 'fleetbase-woocommerce' ),
			__( 'Settings', 'fleetbase-woocommerce' ),
			'manage_woocommerce',
			'fleetbase-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Render the dashboard page.
	 */
	public function render_dashboard_page() {
		$settings = $this->get_settings();
		
		// Check if onboarding is completed
		if ( ! $settings['onboarding_completed'] ) {
			include FLEETBASE_WOOCOMMERCE_PLUGIN_DIR . 'admin/views/html-onboarding-wizard.php';
			return;
		}

		include FLEETBASE_WOOCOMMERCE_PLUGIN_DIR . 'admin/views/html-usage-dashboard.php';
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		// Handle form submission
		if ( isset( $_POST['fleetbase_settings_nonce'] ) && wp_verify_nonce( $_POST['fleetbase_settings_nonce'], 'fleetbase_settings' ) ) {
			$this->save_settings( $_POST );
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved successfully.', 'fleetbase-woocommerce' ) . '</p></div>';
		}

		$settings = $this->get_settings();
		include FLEETBASE_WOOCOMMERCE_PLUGIN_DIR . 'admin/views/html-settings-page.php';
	}

	/**
	 * Get all plugin settings.
	 *
	 * @return array Settings array.
	 */
	public function get_settings() {
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

		$settings = get_option( self::OPTION_NAME, array() );
		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Get a specific setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value if not found.
	 * @return mixed Setting value.
	 */
	public function get( $key, $default = null ) {
		$settings = $this->get_settings();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	/**
	 * Update a specific setting value.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Setting value.
	 * @return bool True on success.
	 */
	public function update( $key, $value ) {
		$settings         = $this->get_settings();
		$settings[ $key ] = $value;
		return update_option( self::OPTION_NAME, $settings );
	}

	/**
	 * Save settings from POST data.
	 *
	 * @param array $post_data POST data.
	 */
	private function save_settings( $post_data ) {
		$settings = $this->get_settings();

		// Update settings from POST
		if ( isset( $post_data['api_host'] ) ) {
			$settings['api_host'] = sanitize_text_field( $post_data['api_host'] );
		}

		if ( isset( $post_data['auto_sync_orders'] ) ) {
			$settings['auto_sync_orders'] = (bool) $post_data['auto_sync_orders'];
		}

		if ( isset( $post_data['enable_tracking_page'] ) ) {
			$settings['enable_tracking_page'] = (bool) $post_data['enable_tracking_page'];
		}

		update_option( self::OPTION_NAME, $settings );
	}

	/**
	 * Check if the plugin is configured.
	 *
	 * @return bool True if configured.
	 */
	public function is_configured() {
		$settings = $this->get_settings();
		return ! empty( $settings['api_key'] ) && ! empty( $settings['api_secret'] );
	}

	/**
	 * Check if onboarding is completed.
	 *
	 * @return bool True if completed.
	 */
	public function is_onboarding_completed() {
		return (bool) $this->get( 'onboarding_completed', false );
	}
}
