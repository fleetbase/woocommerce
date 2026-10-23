<?php
/**
 * Facts about the WooCommerce store the plugin needs.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Support;

final class StoreInfo {

	/**
	 * @return array<string, mixed>
	 */
	public static function describe(): array {
		$base = wc_get_base_location();
		return [
			'name'          => get_bloginfo( 'name' ),
			'currency'      => get_woocommerce_currency(),
			'country'       => (string) ( $base['country'] ?? '' ),
			'state'         => (string) ( $base['state'] ?? '' ),
			'address'       => self::address(),
			'timezone'      => wp_timezone_string(),
			'weightUnit'    => (string) get_option( 'woocommerce_weight_unit', 'kg' ),
			'dimensionUnit' => (string) get_option( 'woocommerce_dimension_unit', 'cm' ),
			'hposEnabled'   => Compat::hpos_enabled(),
			'wcVersion'     => defined( 'WC_VERSION' ) ? WC_VERSION : '',
		];
	}

	/**
	 * Store address from WooCommerce general settings.
	 *
	 * @return array<string, string>
	 */
	public static function address(): array {
		$base    = wc_get_base_location();
		$country = (string) ( $base['country'] ?? '' );
		$state   = (string) ( $base['state'] ?? '' );
		$states  = WC()->countries->get_states( $country );
		return [
			'street1'     => (string) get_option( 'woocommerce_store_address', '' ),
			'street2'     => (string) get_option( 'woocommerce_store_address_2', '' ),
			'city'        => (string) get_option( 'woocommerce_store_city', '' ),
			'province'    => is_array( $states ) && isset( $states[ $state ] ) ? (string) $states[ $state ] : $state,
			'state_code'  => $state,
			'postal_code' => (string) get_option( 'woocommerce_store_postcode', '' ),
			'country'     => $country,
			'formatted'   => self::formatted_address(),
		];
	}

	public static function formatted_address(): string {
		$a     = [
			get_option( 'woocommerce_store_address', '' ),
			get_option( 'woocommerce_store_address_2', '' ),
			get_option( 'woocommerce_store_city', '' ),
			get_option( 'woocommerce_store_postcode', '' ),
		];
		$base  = wc_get_base_location();
		$names = WC()->countries->get_countries();
		$a[]   = $names[ $base['country'] ?? '' ] ?? ( $base['country'] ?? '' );
		return implode( ', ', array_filter( array_map( 'strval', $a ) ) );
	}

	public static function fleetbase_shipping_method_configured(): bool {
		if ( ! class_exists( '\WC_Shipping_Zones' ) ) {
			return false;
		}
		$zones   = \WC_Shipping_Zones::get_zones();
		$zones[] = [ 'zone_id' => 0 ];
		foreach ( $zones as $zone_data ) {
			$zone = \WC_Shipping_Zones::get_zone( (int) ( $zone_data['zone_id'] ?? 0 ) );
			if ( ! $zone ) {
				continue;
			}
			foreach ( $zone->get_shipping_methods( true ) as $method ) {
				if ( 'fleetbase' === $method->id ) {
					return true;
				}
			}
		}
		return false;
	}
}
