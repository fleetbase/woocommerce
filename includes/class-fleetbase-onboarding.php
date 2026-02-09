<?php
/**
 * Fleetbase Onboarding
 *
 * Handles the frictionless onboarding wizard.
 *
 * @package FleetbaseWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fleetbase_Onboarding
 */
class Fleetbase_Onboarding extends Fleetbase_Singleton {
	/**
	 * Constructor.
	 */
	protected function __construct() {
		add_action( 'wp_ajax_fleetbase_provision_account', array( $this, 'ajax_provision_account' ) );
	}

	/**
	 * Display onboarding notice if not completed.
	 */
	public function display_onboarding_notice() {
		$settings = Fleetbase_Settings::get_instance();

		if ( $settings->is_onboarding_completed() ) {
			return;
		}

		// Only show on relevant admin pages
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'fleetbase' ) === false ) {
			return;
		}

		?>
		<div class="notice notice-info fleetbase-onboarding-notice">
			<p>
				<strong><?php esc_html_e( 'Welcome to Fleetbase for WooCommerce!', 'fleetbase-woocommerce' ); ?></strong>
			</p>
			<p>
				<?php esc_html_e( 'Get started in 60 seconds with your free 200-unit trial (100 orders). No credit card required.', 'fleetbase-woocommerce' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=fleetbase' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Start Setup', 'fleetbase-woocommerce' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * AJAX handler for account provisioning.
	 */
	public function ajax_provision_account() {
		check_ajax_referer( 'fleetbase_onboarding_nonce', 'nonce' );

		// Get form data
		$email      = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
		$password   = isset( $_POST['password'] ) ? $_POST['password'] : '';
		$store_name = isset( $_POST['store_name'] ) ? sanitize_text_field( $_POST['store_name'] ) : '';
		$store_url  = get_site_url();

		// Validate
		if ( empty( $email ) || empty( $password ) || empty( $store_name ) ) {
			wp_send_json_error( array( 'message' => __( 'Please fill in all required fields.', 'fleetbase-woocommerce' ) ) );
		}

		// Call Fleetbase API to provision account
		$api      = Fleetbase_API::get_instance();
		$response = $api->provision_account(
			array(
				'email'      => $email,
				'password'   => $password,
				'store_name' => $store_name,
				'store_url'  => $store_url,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		// Save credentials
		$settings = Fleetbase_Settings::get_instance();
		$settings->update( 'api_key', $response['api_key'] );
		$settings->update( 'api_secret', $api->encrypt_api_secret( $response['api_secret'] ) );
		$settings->update( 'company_id', $response['company_id'] );
		$settings->update( 'service_rate_id', $response['service_rate_id'] );
		$settings->update( 'onboarding_completed', true );

		wp_send_json_success(
			array(
				'message'      => __( 'Your Fleetbase account has been created successfully!', 'fleetbase-woocommerce' ),
				'redirect_url' => admin_url( 'admin.php?page=fleetbase' ),
			)
		);
	}
}
