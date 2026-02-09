<?php
/**
 * Fleetbase Webhook Handler
 *
 * Handles incoming webhooks from Fleetbase.
 *
 * @package FleetbaseWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fleetbase_Webhook_Handler
 */
class Fleetbase_Webhook_Handler extends Fleetbase_Singleton {
	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			'fleetbase/v1',
			'/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => array( $this, 'verify_webhook_signature' ),
			)
		);
	}

	/**
	 * Verify webhook signature.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool True if valid.
	 */
	public function verify_webhook_signature( $request ) {
		$signature = $request->get_header( 'X-Fleetbase-Signature' );
		
		if ( empty( $signature ) ) {
			return false;
		}

		// Get webhook secret
		$settings = Fleetbase_Settings::get_instance();
		$secret   = $settings->get( 'webhook_secret' );

		if ( empty( $secret ) ) {
			// If no secret is set, allow for now (should be configured during setup)
			return true;
		}

		// Verify HMAC signature
		$body     = $request->get_body();
		$expected = hash_hmac( 'sha256', $body, $secret );

		return hash_equals( $expected, $signature );
	}

	/**
	 * Handle incoming webhook.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_webhook( $request ) {
		$payload = $request->get_json_params();

		if ( empty( $payload['event'] ) || empty( $payload['data'] ) ) {
			return new WP_REST_Response(
				array( 'error' => 'Invalid payload' ),
				400
			);
		}

		$event = $payload['event'];
		$data  = $payload['data'];

		// Handle different event types
		switch ( $event ) {
			case 'order.dispatched':
				$this->handle_order_dispatched( $data );
				break;

			case 'order.completed':
				$this->handle_order_completed( $data );
				break;

			case 'order.failed':
				$this->handle_order_failed( $data );
				break;

			default:
				// Unknown event type
				break;
		}

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Handle order dispatched event.
	 *
	 * @param array $data Event data.
	 */
	private function handle_order_dispatched( $data ) {
		$order = $this->get_wc_order_from_fleetbase_data( $data );
		
		if ( ! $order ) {
			return;
		}

		// Update order status
		$order->update_status( 'fleetbase-dispatched', __( 'Order dispatched for delivery.', 'fleetbase-woocommerce' ) );

		// Add order note with driver info
		if ( isset( $data['driver']['name'] ) ) {
			$order->add_order_note(
				sprintf(
					__( 'Order dispatched to driver: %s', 'fleetbase-woocommerce' ),
					$data['driver']['name']
				)
			);
		}
	}

	/**
	 * Handle order completed event.
	 *
	 * @param array $data Event data.
	 */
	private function handle_order_completed( $data ) {
		$order = $this->get_wc_order_from_fleetbase_data( $data );
		
		if ( ! $order ) {
			return;
		}

		// Update order status
		$order->update_status( 'fleetbase-completed', __( 'Order delivered successfully.', 'fleetbase-woocommerce' ) );

		// Add proof of delivery note if available
		if ( isset( $data['proof_of_delivery'] ) ) {
			$pod = $data['proof_of_delivery'];
			$note = __( 'Delivery confirmed.', 'fleetbase-woocommerce' );
			
			if ( isset( $pod['signature_url'] ) ) {
				$note .= ' ' . sprintf( __( 'Signature: %s', 'fleetbase-woocommerce' ), $pod['signature_url'] );
			}
			
			$order->add_order_note( $note );
		}
	}

	/**
	 * Handle order failed event.
	 *
	 * @param array $data Event data.
	 */
	private function handle_order_failed( $data ) {
		$order = $this->get_wc_order_from_fleetbase_data( $data );
		
		if ( ! $order ) {
			return;
		}

		// Add order note
		$reason = isset( $data['failure_reason'] ) ? $data['failure_reason'] : __( 'Unknown reason', 'fleetbase-woocommerce' );
		$order->add_order_note(
			sprintf(
				__( 'Delivery failed: %s', 'fleetbase-woocommerce' ),
				$reason
			)
		);
	}

	/**
	 * Get WooCommerce order from Fleetbase webhook data.
	 *
	 * @param array $data Webhook data.
	 * @return WC_Order|null Order object or null.
	 */
	private function get_wc_order_from_fleetbase_data( $data ) {
		if ( empty( $data['id'] ) ) {
			return null;
		}

		$fleetbase_order_id = $data['id'];

		// Find WooCommerce order by Fleetbase order ID
		$args = array(
			'meta_key'   => '_fleetbase_order_id',
			'meta_value' => $fleetbase_order_id,
			'limit'      => 1,
			'return'     => 'ids',
		);

		$orders = wc_get_orders( $args );

		if ( empty( $orders ) ) {
			return null;
		}

		return wc_get_order( $orders[0] );
	}
}
