<?php
/**
 * Remembers, per request and session, that the customer's address got no quotes,
 * so checkout can say "we don't deliver there yet" instead of a generic message.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Shipping;

use Fleetbase\WooCommerce\Hookable;
use Fleetbase\WooCommerce\Plugin;
use Fleetbase\WooCommerce\Support\Address;

final class NoService implements Hookable {

	private const SESSION_KEY = 'fleetbase_wc_no_service';

	public function register(): void {
		add_filter( 'woocommerce_no_shipping_available_html', [ $this, 'no_shipping_html' ] );
		add_filter( 'woocommerce_cart_no_shipping_available_html', [ $this, 'no_shipping_html' ] );
		add_action( 'woocommerce_after_shipping_rate', [ $this, 'classic_rate_description' ], 10, 2 );
		if ( did_action( 'woocommerce_blocks_loaded' ) ) {
			$this->extend_store_api();
		} else {
			add_action( 'woocommerce_blocks_loaded', [ $this, 'extend_store_api' ] );
		}
	}

	/**
	 * @param array<string, mixed> $destination
	 */
	public static function flag( array $destination ): void {
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( self::SESSION_KEY, Address::fingerprint( $destination ) );
		}
	}

	public static function clear(): void {
		if ( function_exists( 'WC' ) && WC()->session && WC()->session->get( self::SESSION_KEY ) ) {
			WC()->session->set( self::SESSION_KEY, '' );
		}
	}

	/**
	 * True when the current customer address was quoted and got nothing.
	 */
	public static function applies(): bool {
		if ( ! function_exists( 'WC' ) || ! WC()->session || ! WC()->customer ) {
			return false;
		}
		$flag = (string) WC()->session->get( self::SESSION_KEY, '' );
		if ( '' === $flag ) {
			return false;
		}
		$destination = [
			'address_1' => WC()->customer->get_shipping_address_1(),
			'address_2' => WC()->customer->get_shipping_address_2(),
			'city'      => WC()->customer->get_shipping_city(),
			'state'     => WC()->customer->get_shipping_state(),
			'postcode'  => WC()->customer->get_shipping_postcode(),
			'country'   => WC()->customer->get_shipping_country(),
		];
		return hash_equals( $flag, Address::fingerprint( $destination ) );
	}

	public static function message(): string {
		$message = (string) Plugin::instance()->settings()->get( 'checkout.no_service_message', '' );
		if ( '' === $message ) {
			$message = __( "Sorry, we don't deliver to this address yet.", 'fleetbase-for-woocommerce' );
		}

		/**
		 * Filter the message shown when no delivery quote covers the customer's address.
		 *
		 * @param string $message
		 */
		return (string) apply_filters( 'fleetbase_wc_no_service_message', $message );
	}

	public function no_shipping_html( string $html ): string {
		if ( ! self::applies() ) {
			return $html;
		}
		return '<p class="fleetbase-wc-no-service">' . esc_html( self::message() ) . '</p>';
	}

	/**
	 * The classic checkout does not render rate descriptions; print them here.
	 *
	 * @param \WC_Shipping_Rate $method
	 * @param int               $index
	 */
	public function classic_rate_description( $method, $index ): void {
		if ( ! $method instanceof \WC_Shipping_Rate || FleetbaseShippingMethod::ID !== $method->get_method_id() ) {
			return;
		}
		$meta        = $method->get_meta_data();
		$description = (string) ( $meta['_fleetbase_description'] ?? '' );
		if ( '' === $description ) {
			return;
		}
		printf( '<p class="fleetbase-wc-rate-description" style="margin:2px 0 6px 24px;font-size:.85em;opacity:.8">%s</p>', esc_html( $description ) );
	}

	/**
	 * Expose the no-service state to the Cart and Checkout blocks under extensions.fleetbase.
	 */
	public function extend_store_api(): void {
		if ( ! class_exists( \Automattic\WooCommerce\StoreApi\StoreApi::class ) || ! class_exists( \Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema::class ) ) {
			return;
		}
		$extend = \Automattic\WooCommerce\StoreApi\StoreApi::container()->get( \Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema::class );
		$extend->register_endpoint_data(
			[
				'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema::IDENTIFIER,
				'namespace'       => 'fleetbase',
				'data_callback'   => static function (): array {
					return [
						'no_service'         => self::applies(),
						'no_service_message' => self::message(),
					];
				},
				'schema_callback' => static function (): array {
					return [
						'no_service'         => [
							'type'     => 'boolean',
							'readonly' => true,
						],
						'no_service_message' => [
							'type'     => 'string',
							'readonly' => true,
						],
					];
				},
				'schema_type'     => ARRAY_A,
			]
		);
	}
}
