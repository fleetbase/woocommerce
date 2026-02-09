<?php
/**
 * Fleetbase API Wrapper
 *
 * Handles all communication with the Fleetbase API.
 *
 * @package FleetbaseWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fleetbase_API
 */
class Fleetbase_API extends Fleetbase_Singleton {
	/**
	 * API host URL.
	 *
	 * @var string
	 */
	private $api_host;

	/**
	 * API key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * API secret.
	 *
	 * @var string
	 */
	private $api_secret;

	/**
	 * Constructor.
	 */
	protected function __construct() {
		$settings         = get_option( 'fleetbase_settings', array() );
		$this->api_host   = isset( $settings['api_host'] ) ? $settings['api_host'] : 'https://api.fleetbase.io';
		$this->api_key    = isset( $settings['api_key'] ) ? $settings['api_key'] : '';
		$this->api_secret = isset( $settings['api_secret'] ) ? $this->decrypt_api_secret( $settings['api_secret'] ) : '';
	}

	/**
	 * Provision a new Fleetbase account for WooCommerce.
	 *
	 * @param array $data Account data (email, password, store_name, store_url).
	 * @return array|WP_Error Response data or error.
	 */
	public function provision_account( $data ) {
		$endpoint = $this->api_host . '/v1/woocommerce/provision-account';

		$response = wp_remote_post(
			$endpoint,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $data ),
				'timeout' => 30,
			)
		);

		return $this->handle_response( $response );
	}

	/**
	 * Create an order in Fleetbase.
	 *
	 * @param array $order_data Order data.
	 * @return array|WP_Error Response data or error.
	 */
	public function create_order( $order_data ) {
		$endpoint = $this->api_host . '/v1/orders';

		$response = wp_remote_post(
			$endpoint,
			array(
				'headers' => $this->get_auth_headers(),
				'body'    => wp_json_encode( $order_data ),
				'timeout' => 30,
			)
		);

		return $this->handle_response( $response );
	}

	/**
	 * Get service quotes for shipping rates.
	 *
	 * @param array $quote_data Quote request data.
	 * @return array|WP_Error Response data or error.
	 */
	public function get_service_quotes( $quote_data ) {
		$endpoint = $this->api_host . '/v1/service-quotes';

		$response = wp_remote_post(
			$endpoint,
			array(
				'headers' => $this->get_auth_headers(),
				'body'    => wp_json_encode( $quote_data ),
				'timeout' => 15,
			)
		);

		return $this->handle_response( $response );
	}

	/**
	 * Get order details from Fleetbase.
	 *
	 * @param string $order_id Fleetbase order ID.
	 * @return array|WP_Error Response data or error.
	 */
	public function get_order( $order_id ) {
		$endpoint = $this->api_host . '/v1/orders/' . $order_id;

		$response = wp_remote_get(
			$endpoint,
			array(
				'headers' => $this->get_auth_headers(),
				'timeout' => 15,
			)
		);

		return $this->handle_response( $response );
	}

	/**
	 * Get tracking status for public tracking page.
	 *
	 * @param string $public_id Fleetbase public tracking ID.
	 * @return array|WP_Error Response data or error.
	 */
	public function get_tracking_status( $public_id ) {
		$endpoint = $this->api_host . '/v1/tracking-statuses/' . $public_id;

		$response = wp_remote_get(
			$endpoint,
			array(
				'timeout' => 15,
			)
		);

		return $this->handle_response( $response );
	}

	/**
	 * Get current usage statistics.
	 *
	 * @return array|WP_Error Response data or error.
	 */
	public function get_current_usage() {
		$endpoint = $this->api_host . '/v1/usage/current';

		$response = wp_remote_get(
			$endpoint,
			array(
				'headers' => $this->get_auth_headers(),
				'timeout' => 15,
			)
		);

		return $this->handle_response( $response );
	}

	/**
	 * Get authentication headers.
	 *
	 * @return array Headers array.
	 */
	private function get_auth_headers() {
		return array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $this->api_secret,
		);
	}

	/**
	 * Handle API response.
	 *
	 * @param array|WP_Error $response The API response.
	 * @return array|WP_Error Parsed response or error.
	 */
	private function handle_response( $response ) {
		if ( is_wp_error( $response ) ) {
			$this->log_error( 'API request failed: ' . $response->get_error_message() );
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code >= 200 && $code < 300 ) {
			return $data;
		}

		$error_message = isset( $data['message'] ) ? $data['message'] : 'Unknown API error';
		$this->log_error( sprintf( 'API error (code %d): %s', $code, $error_message ) );

		return new WP_Error( 'fleetbase_api_error', $error_message, array( 'status' => $code ) );
	}

	/**
	 * Log error message.
	 *
	 * @param string $message Error message.
	 */
	private function log_error( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Fleetbase WooCommerce] ' . $message );
		}
	}

	/**
	 * Encrypt API secret for storage.
	 *
	 * @param string $secret The API secret.
	 * @return string Encrypted secret.
	 */
	public function encrypt_api_secret( $secret ) {
		if ( empty( $secret ) ) {
			return '';
		}

		$key    = wp_salt( 'auth' );
		$method = 'AES-256-CBC';
		$iv     = openssl_random_pseudo_bytes( openssl_cipher_iv_length( $method ) );
		
		$encrypted = openssl_encrypt( $secret, $method, $key, 0, $iv );
		
		return base64_encode( $encrypted . '::' . $iv );
	}

	/**
	 * Decrypt API secret from storage.
	 *
	 * @param string $encrypted_secret The encrypted API secret.
	 * @return string Decrypted secret.
	 */
	private function decrypt_api_secret( $encrypted_secret ) {
		if ( empty( $encrypted_secret ) ) {
			return '';
		}

		$key    = wp_salt( 'auth' );
		$method = 'AES-256-CBC';
		
		list( $encrypted_data, $iv ) = explode( '::', base64_decode( $encrypted_secret ), 2 );
		
		return openssl_decrypt( $encrypted_data, $method, $key, 0, $iv );
	}
}
