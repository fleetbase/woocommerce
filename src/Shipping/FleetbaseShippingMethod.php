<?php
/**
 * "Fleetbase Delivery" shipping method: live quotes from the merchant's service rates.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Shipping;

use Fleetbase\WooCommerce\Plugin;
use Fleetbase\WooCommerce\Support\Address;
use Fleetbase\WooCommerce\Support\Pickup;

final class FleetbaseShippingMethod extends \WC_Shipping_Method {

	public const ID = 'fleetbase';

	public const MODE_ALL      = 'all';
	public const MODE_CHEAPEST = 'cheapest';

	public const FALLBACK_HIDE = 'hide';
	public const FALLBACK_FLAT = 'flat';

	/**
	 * @param int $instance_id
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id                 = self::ID;
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __( 'Fleetbase Delivery', 'fleetbase-for-woocommerce' );
		$this->method_description = __( 'Live delivery prices from your Fleetbase service rates, based on the customer address. Customers only see this method when their address is inside a service area with at least one rate.', 'fleetbase-for-woocommerce' );
		$this->supports           = [ 'shipping-zones', 'instance-settings', 'instance-settings-modal' ];

		$this->init();
	}

	public function init(): void {
		$this->init_form_fields();
		$this->init_settings();

		$this->title      = (string) $this->get_option( 'title', __( 'Local delivery', 'fleetbase-for-woocommerce' ) );
		$this->tax_status = (string) $this->get_option( 'tax_status', 'taxable' );

		add_action(
			'woocommerce_update_options_shipping_' . $this->id,
			function (): void {
				$this->process_admin_options();
			}
		);
	}

	public function init_form_fields(): void {
		$this->instance_form_fields = [
			'title'          => [
				'title'       => __( 'Method title', 'fleetbase-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Shown at checkout when only the cheapest quote is offered. When all quotes are offered, each uses its Fleetbase service name.', 'fleetbase-for-woocommerce' ),
				'default'     => __( 'Local delivery', 'fleetbase-for-woocommerce' ),
				'desc_tip'    => true,
			],
			'mode'           => [
				'title'   => __( 'Quotes to offer', 'fleetbase-for-woocommerce' ),
				'type'    => 'select',
				'default' => self::MODE_ALL,
				'options' => [
					self::MODE_ALL      => __( 'All matching service rates', 'fleetbase-for-woocommerce' ),
					self::MODE_CHEAPEST => __( 'Only the cheapest', 'fleetbase-for-woocommerce' ),
				],
			],
			'service_type'   => [
				'title'       => __( 'Service type filter', 'fleetbase-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Optional. Only quote service rates with this service type, for example "standard" or "express". Leave empty for all.', 'fleetbase-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			],
			'tax_status'     => [
				'title'   => __( 'Tax status', 'fleetbase-for-woocommerce' ),
				'type'    => 'select',
				'default' => 'taxable',
				'options' => [
					'taxable' => __( 'Taxable', 'fleetbase-for-woocommerce' ),
					'none'    => _x( 'None', 'Tax status', 'fleetbase-for-woocommerce' ),
				],
			],
			'fallback_mode'  => [
				'title'       => __( 'If Fleetbase cannot be reached', 'fleetbase-for-woocommerce' ),
				'type'        => 'select',
				'default'     => self::FALLBACK_HIDE,
				'description' => __( 'A flat fallback keeps checkout working during an outage. The order still syncs once Fleetbase is back.', 'fleetbase-for-woocommerce' ),
				'desc_tip'    => true,
				'options'     => [
					self::FALLBACK_HIDE => __( 'Hide this method', 'fleetbase-for-woocommerce' ),
					self::FALLBACK_FLAT => __( 'Offer a flat fallback rate', 'fleetbase-for-woocommerce' ),
				],
			],
			'fallback_fee'   => [
				'title'       => __( 'Fallback rate', 'fleetbase-for-woocommerce' ),
				'type'        => 'price',
				'default'     => '',
				'description' => __( 'Charged only when Fleetbase is unreachable and the fallback is enabled.', 'fleetbase-for-woocommerce' ),
				'desc_tip'    => true,
			],
			'fallback_label' => [
				'title'   => __( 'Fallback label', 'fleetbase-for-woocommerce' ),
				'type'    => 'text',
				'default' => __( 'Local delivery (estimate)', 'fleetbase-for-woocommerce' ),
			],
		];
	}

	/**
	 * @param array<string, mixed> $package
	 */
	public function is_available( $package ): bool {
		$plugin    = Plugin::instance();
		$available = $plugin->connections()->is_connected()
			&& '' !== Pickup::place_id()
			&& isset( $package['destination'] )
			&& is_array( $package['destination'] )
			&& Address::is_quotable( $package['destination'] );

		/** This filter is documented in WooCommerce's WC_Shipping_Method::is_available(). */
		return (bool) apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', $available, $package, $this ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core hook.
	}

	/**
	 * @param array<string, mixed> $package
	 */
	public function calculate_shipping( $package = [] ): void {
		$package     = is_array( $package ) ? $package : [];
		$destination = (array) ( $package['destination'] ?? [] );

		$filter = [
			'service_type' => trim( (string) $this->get_option( 'service_type', '' ) ),
			'single'       => self::MODE_CHEAPEST === $this->get_option( 'mode', self::MODE_ALL ),
			'scheduled_at' => (string) ( $package['fleetbase']['scheduled_at'] ?? '' ),
			'weight_kg'    => self::package_weight_kg( $package ),
		];

		$result = self::quote_service()->quotes( $destination, $filter );

		if ( $result->is_unavailable() ) {
			NoService::clear();
			$this->add_fallback_rate( $package, $result->error );
			return;
		}

		if ( $result->is_empty() ) {
			NoService::flag( $destination );
			return;
		}

		NoService::clear();
		$quotes = $result->quotes;
		if ( $filter['single'] ) {
			$quotes = array_slice( $quotes, 0, 1 );
		}

		foreach ( $quotes as $quote ) {
			$this->add_quote_rate( $quote, $package, $filter['single'] );
		}
	}

	/**
	 * @param array<string, mixed> $quote
	 * @param array<string, mixed> $package
	 */
	private function add_quote_rate( array $quote, array $package, bool $single ): void {
		$rate_id = $this->get_rate_id( (string) $quote['id'] );
		$label   = $single || '' === (string) $quote['service_name'] ? $this->title : (string) $quote['service_name'];

		$this->add_rate(
			[
				'id'        => $rate_id,
				'label'     => $label,
				'cost'      => (float) $quote['cost'],
				'taxes'     => 'taxable' === $this->tax_status ? '' : false,
				'calc_tax'  => 'per_order',
				'package'   => $package,
				'meta_data' => [
					'_fleetbase_quote_id'        => (string) $quote['id'],
					'_fleetbase_request_id'      => (string) $quote['request_id'],
					'_fleetbase_service_rate_id' => (string) $quote['service_rate_id'],
					'_fleetbase_service_name'    => (string) $quote['service_name'],
					'_fleetbase_amount'          => (int) $quote['amount'],
					'_fleetbase_currency'        => (string) $quote['currency'],
					'_fleetbase_description'     => (string) $quote['description'],
				],
			]
		);

		$this->decorate_rate( $rate_id, (string) $quote['description'], $quote['estimated_days'] ?? null );
	}

	/**
	 * @param array<string, mixed> $package
	 */
	private function add_fallback_rate( array $package, string $error ): void {
		Plugin::instance()->logger()->warning(
			'Fleetbase quotes unavailable at checkout',
			[
				'error'    => $error,
				'fallback' => (string) $this->get_option( 'fallback_mode', self::FALLBACK_HIDE ),
			]
		);

		if ( self::FALLBACK_FLAT !== $this->get_option( 'fallback_mode', self::FALLBACK_HIDE ) ) {
			return;
		}
		$fee = (string) $this->get_option( 'fallback_fee', '' );
		if ( '' === trim( $fee ) ) {
			return;
		}

		$rate_id = $this->get_rate_id( 'fallback' );
		$this->add_rate(
			[
				'id'        => $rate_id,
				'label'     => (string) $this->get_option( 'fallback_label', __( 'Local delivery (estimate)', 'fleetbase-for-woocommerce' ) ),
				'cost'      => (float) wc_format_decimal( $fee ),
				'taxes'     => 'taxable' === $this->tax_status ? '' : false,
				'calc_tax'  => 'per_order',
				'package'   => $package,
				'meta_data' => [
					'_fleetbase_fallback' => '1',
				],
			]
		);
	}

	/**
	 * Description and delivery time render under the option in the Checkout block (WooCommerce 9.7+).
	 */
	private function decorate_rate( string $rate_id, string $description, ?int $estimated_days ): void {
		if ( ! isset( $this->rates[ $rate_id ] ) ) {
			return;
		}
		$rate = $this->rates[ $rate_id ];
		if ( '' !== $description && method_exists( $rate, 'set_description' ) ) {
			$rate->set_description( $description );
		}
		if ( null !== $estimated_days && method_exists( $rate, 'set_delivery_time' ) ) {
			$rate->set_delivery_time( self::delivery_time_label( $estimated_days ) );
		}
	}

	public static function delivery_time_label( int $days ): string {
		if ( $days <= 0 ) {
			return __( 'Today', 'fleetbase-for-woocommerce' );
		}
		if ( 1 === $days ) {
			return __( 'Next day', 'fleetbase-for-woocommerce' );
		}
		/* translators: %d: number of days */
		return sprintf( __( '%d days', 'fleetbase-for-woocommerce' ), $days );
	}

	/**
	 * @param array<string, mixed> $package
	 */
	public static function package_weight_kg( array $package ): float {
		$weight = 0.0;
		foreach ( (array) ( $package['contents'] ?? [] ) as $item ) {
			$product = is_array( $item ) ? ( $item['data'] ?? null ) : null;
			if ( $product instanceof \WC_Product ) {
				$weight += (float) $product->get_weight() * (int) ( $item['quantity'] ?? 1 );
			}
		}
		return (float) wc_get_weight( $weight, 'kg' );
	}

	public static function quote_service(): QuoteService {
		$plugin = Plugin::instance();
		return new QuoteService(
			$plugin->api(),
			new TransientQuoteCache(),
			[
				'pickup_place_id' => Pickup::place_id(),
				'store_currency'  => get_woocommerce_currency(),
				'ttl'             => (int) $plugin->settings()->get( 'checkout.quote_cache_ttl', 600 ),
				'cache_version'   => QuoteCacheVersion::current(),
				'timeout'         => (int) $plugin->settings()->get( 'checkout.quote_timeout', 10 ),
			],
			$plugin->logger()
		);
	}
}
