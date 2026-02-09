<?php
/**
 * Fleetbase Shipping Method
 *
 * Provides real-time shipping rates from Fleetbase.
 *
 * @package FleetbaseWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fleetbase_Shipping_Method
 */
class Fleetbase_Shipping_Method extends WC_Shipping_Method {
	/**
	 * Constructor.
	 *
	 * @param int $instance_id Shipping instance ID.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id                 = 'fleetbase';
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __( 'Fleetbase', 'fleetbase-woocommerce' );
		$this->method_description = __( 'Real-time delivery rates from Fleetbase.', 'fleetbase-woocommerce' );
		$this->supports           = array(
			'shipping-zones',
			'instance-settings',
		);

		$this->init();
	}

	/**
	 * Initialize shipping method.
	 */
	public function init() {
		$this->init_form_fields();
		$this->init_settings();

		$this->title   = $this->get_option( 'title', __( 'Delivery', 'fleetbase-woocommerce' ) );
		$this->enabled = $this->get_option( 'enabled', 'yes' );

		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Initialize form fields.
	 */
	public function init_form_fields() {
		$this->instance_form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'fleetbase-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Fleetbase shipping', 'fleetbase-woocommerce' ),
				'default' => 'yes',
			),
			'title'   => array(
				'title'       => __( 'Method Title', 'fleetbase-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'fleetbase-woocommerce' ),
				'default'     => __( 'Delivery', 'fleetbase-woocommerce' ),
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * Calculate shipping rates.
	 *
	 * @param array $package Shipping package.
	 */
	public function calculate_shipping( $package = array() ) {
		// Check if Fleetbase is configured
		$settings = Fleetbase_Settings::get_instance();
		if ( ! $settings->is_configured() ) {
			return;
		}

		// Build quote request
		$quote_data = $this->build_quote_request( $package );

		// Try to get cached rates
		$cache_key = 'fleetbase_rates_' . md5( wp_json_encode( $quote_data ) );
		$rates     = get_transient( $cache_key );

		if ( false === $rates ) {
			// Fetch rates from API
			$api   = Fleetbase_API::get_instance();
			$response = $api->get_service_quotes( $quote_data );

			if ( is_wp_error( $response ) ) {
				return;
			}

			$rates = isset( $response['quotes'] ) ? $response['quotes'] : array();
			
			// Cache for 5 minutes
			set_transient( $cache_key, $rates, 5 * MINUTE_IN_SECONDS );
		}

		// Add rates to WooCommerce
		foreach ( $rates as $rate_data ) {
			$this->add_rate(
				array(
					'id'    => $this->id . '_' . sanitize_title( $rate_data['service_name'] ),
					'label' => $rate_data['service_name'],
					'cost'  => $rate_data['amount'],
					'meta_data' => array(
						'fleetbase_service_rate_id' => $rate_data['id'],
					),
				)
			);
		}
	}

	/**
	 * Build quote request data.
	 *
	 * @param array $package Shipping package.
	 * @return array Quote request data.
	 */
	private function build_quote_request( $package ) {
		$settings = Fleetbase_Settings::get_instance();

		// Get store address
		$pickup = array(
			'street1' => WC()->countries->get_base_address(),
			'street2' => WC()->countries->get_base_address_2(),
			'city'    => WC()->countries->get_base_city(),
			'state'   => WC()->countries->get_base_state(),
			'postal_code' => WC()->countries->get_base_postcode(),
			'country' => WC()->countries->get_base_country(),
		);

		// Get customer address
		$destination = $package['destination'];
		$dropoff     = array(
			'street1' => $destination['address'],
			'street2' => $destination['address_2'],
			'city'    => $destination['city'],
			'state'   => $destination['state'],
			'postal_code' => $destination['postcode'],
			'country' => $destination['country'],
		);

		// Calculate total weight
		$total_weight = 0;
		foreach ( $package['contents'] as $item ) {
			$product = $item['data'];
			$weight  = $product->get_weight();
			if ( $weight ) {
				$total_weight += floatval( $weight ) * $item['quantity'];
			}
		}

		return array(
			'pickup'  => $pickup,
			'dropoff' => $dropoff,
			'entities' => array(
				array(
					'weight' => $total_weight,
				),
			),
		);
	}
}
