<?php
/**
 * Fleetbase Order Sync
 *
 * Handles synchronization of WooCommerce orders to Fleetbase.
 *
 * @package FleetbaseWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fleetbase_Order_Sync
 */
class Fleetbase_Order_Sync extends Fleetbase_Singleton {
	/**
	 * Sync an order to Fleetbase.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function sync_order( $order_id ) {
		// Check if auto-sync is enabled
		$settings = Fleetbase_Settings::get_instance();
		if ( ! $settings->get( 'auto_sync_orders', true ) ) {
			return false;
		}

		// Check if already synced
		if ( get_post_meta( $order_id, '_fleetbase_order_id', true ) ) {
			return false;
		}

		// Get WooCommerce order
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'invalid_order', __( 'Invalid order ID.', 'fleetbase-woocommerce' ) );
		}

		// Map order data
		$order_data = $this->map_order_data( $order );

		// Send to Fleetbase API
		$api      = Fleetbase_API::get_instance();
		$response = $api->create_order( $order_data );

		if ( is_wp_error( $response ) ) {
			$order->add_order_note(
				sprintf(
					__( 'Failed to sync order to Fleetbase: %s', 'fleetbase-woocommerce' ),
					$response->get_error_message()
				)
			);
			return $response;
		}

		// Save Fleetbase order data
		update_post_meta( $order_id, '_fleetbase_order_id', $response['id'] );
		update_post_meta( $order_id, '_fleetbase_public_id', $response['public_id'] );
		update_post_meta( $order_id, '_fleetbase_tracking_number', $response['tracking_number']['number'] );

		// Add order note
		$order->add_order_note(
			sprintf(
				__( 'Order synced to Fleetbase. Tracking number: %s', 'fleetbase-woocommerce' ),
				$response['tracking_number']['number']
			)
		);

		// Track usage
		$tracker = Fleetbase_Usage_Tracker::get_instance();
		$tracker->increment( 'orders', 1 );

		return true;
	}

	/**
	 * Map WooCommerce order data to Fleetbase format.
	 *
	 * @param WC_Order $order WooCommerce order object.
	 * @return array Fleetbase order data.
	 */
	private function map_order_data( $order ) {
		$settings = Fleetbase_Settings::get_instance();

		// Get store address
		$store_address = $this->get_store_address();

		// Get customer shipping address
		$customer_address = array(
			'name'    => $order->get_formatted_shipping_full_name(),
			'street1' => $order->get_shipping_address_1(),
			'street2' => $order->get_shipping_address_2(),
			'city'    => $order->get_shipping_city(),
			'state'   => $order->get_shipping_state(),
			'postal_code' => $order->get_shipping_postcode(),
			'country' => $order->get_shipping_country(),
		);

		// Map line items to entities
		$entities = array();
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			
			$entities[] = array(
				'name'        => $item->get_name(),
				'description' => $product ? $product->get_short_description() : '',
				'sku'         => $product ? $product->get_sku() : '',
				'price'       => $item->get_total(),
				'quantity'    => $item->get_quantity(),
				'weight'      => $product ? $product->get_weight() : 0,
			);
		}

		// Build Fleetbase order payload
		return array(
			'customer' => array(
				'name'  => $order->get_formatted_billing_full_name(),
				'email' => $order->get_billing_email(),
				'phone' => $order->get_billing_phone(),
			),
			'payload' => array(
				'pickup'  => $store_address,
				'dropoff' => $customer_address,
				'entities' => $entities,
			),
			'meta' => array(
				'wc_order_id'     => $order->get_id(),
				'wc_order_number' => $order->get_order_number(),
				'order_total'     => $order->get_total(),
				'currency'        => $order->get_currency(),
			),
			'service_rate_id' => $settings->get( 'service_rate_id' ),
		);
	}

	/**
	 * Get store address from WooCommerce settings.
	 *
	 * @return array Store address.
	 */
	private function get_store_address() {
		return array(
			'name'    => get_bloginfo( 'name' ),
			'street1' => WC()->countries->get_base_address(),
			'street2' => WC()->countries->get_base_address_2(),
			'city'    => WC()->countries->get_base_city(),
			'state'   => WC()->countries->get_base_state(),
			'postal_code' => WC()->countries->get_base_postcode(),
			'country' => WC()->countries->get_base_country(),
		);
	}
}
