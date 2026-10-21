<?php
/**
 * Registers the shipping method and keeps quote caches in step with settings.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Shipping;

use Fleetbase\WooCommerce\Hookable;

final class ShippingMethods implements Hookable {

	public function register(): void {
		add_filter( 'woocommerce_shipping_methods', [ $this, 'add_method' ] );
		add_filter( 'woocommerce_cart_shipping_packages', [ $this, 'extend_packages' ] );

		// Anything that changes quotes invalidates both the plugin cache and WooCommerce's session rate cache.
		add_action( 'updated_option', [ $this, 'maybe_bump_on_option' ], 10, 1 );
		add_action( 'added_option', [ $this, 'maybe_bump_on_option' ], 10, 1 );
		$bump = static function (): void {
			QuoteCacheVersion::bump();
		};
		foreach ( [ 'fleetbase_wc_settings_saved', 'fleetbase_wc_connected', 'fleetbase_wc_disconnected', 'woocommerce_shipping_zone_method_added', 'woocommerce_shipping_zone_method_deleted', 'woocommerce_shipping_zone_method_status_toggled' ] as $hook ) {
			add_action( $hook, $bump );
		}
	}

	/**
	 * @param array<string, string|object> $methods
	 * @return array<string, string|object>
	 */
	public function add_method( array $methods ): array {
		$methods[ FleetbaseShippingMethod::ID ] = FleetbaseShippingMethod::class;
		return $methods;
	}

	/**
	 * Scheduling inputs become part of WooCommerce's rate cache key by living on the package.
	 *
	 * @param array<int, array<string, mixed>> $packages
	 * @return array<int, array<string, mixed>>
	 */
	public function extend_packages( array $packages ): array {
		$scheduled_at = '';
		if ( function_exists( 'WC' ) && WC()->session ) {
			$scheduled_at = (string) WC()->session->get( 'fleetbase_wc_scheduled_at', '' );
		}

		/**
		 * Filter the Fleetbase data attached to each shipping package.
		 *
		 * @param array<string, mixed> $data
		 */
		$data = (array) apply_filters( 'fleetbase_wc_package_data', [ 'scheduled_at' => $scheduled_at ] );

		foreach ( $packages as $index => $package ) {
			$packages[ $index ]['fleetbase'] = $data;
		}
		return $packages;
	}

	public function maybe_bump_on_option( string $option ): void {
		$is_method_settings = str_starts_with( $option, 'woocommerce_' . FleetbaseShippingMethod::ID . '_' ) && str_ends_with( $option, '_settings' );
		if ( $is_method_settings || 'fleetbase_wc_pickup' === $option || 'woocommerce_currency' === $option ) {
			QuoteCacheVersion::bump();
		}
	}
}
