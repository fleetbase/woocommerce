<?php
/**
 * Address helpers shared by quotes and order sync.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Support;

final class Address {

	/**
	 * Turn a WooCommerce destination/address array into the structured place Fleetbase accepts.
	 *
	 * @param array<string, mixed> $destination Keys: address_1|address, address_2, city, state, postcode, country.
	 * @return array<string, string>
	 */
	public static function to_place( array $destination ): array {
		$street1 = trim( (string) ( $destination['address_1'] ?? $destination['address'] ?? '' ) );
		$street2 = trim( (string) ( $destination['address_2'] ?? '' ) );
		$country = strtoupper( trim( (string) ( $destination['country'] ?? '' ) ) );
		$state   = trim( (string) ( $destination['state'] ?? '' ) );

		$place = [
			'street1'     => $street1,
			'street2'     => $street2,
			'city'        => trim( (string) ( $destination['city'] ?? '' ) ),
			'province'    => self::state_name( $country, $state ),
			'postal_code' => trim( (string) ( $destination['postcode'] ?? '' ) ),
			'country'     => $country,
		];

		return array_filter(
			$place,
			static function ( string $value ): bool {
				return '' !== $value;
			}
		);
	}

	/**
	 * Whether there is enough of an address to ask for a quote.
	 *
	 * @param array<string, mixed> $destination
	 */
	public static function is_quotable( array $destination ): bool {
		$country = trim( (string) ( $destination['country'] ?? '' ) );
		if ( '' === $country ) {
			return false;
		}
		$street   = trim( (string) ( $destination['address_1'] ?? $destination['address'] ?? '' ) );
		$postcode = trim( (string) ( $destination['postcode'] ?? '' ) );
		$city     = trim( (string) ( $destination['city'] ?? '' ) );
		return '' !== $postcode || ( '' !== $city && '' !== $street );
	}

	/**
	 * Stable string for cache keys: lower-cased, single-spaced, ordered fields.
	 *
	 * @param array<string, mixed> $destination
	 */
	public static function fingerprint( array $destination ): string {
		$parts = [];
		foreach ( [ 'address_1', 'address', 'address_2', 'city', 'state', 'postcode', 'country' ] as $key ) {
			$value = strtolower( trim( (string) ( $destination[ $key ] ?? '' ) ) );
			$value = preg_replace( '/\s+/', ' ', $value ) ?? $value;
			if ( '' !== $value ) {
				$parts[] = $key . '=' . $value;
			}
		}
		return implode( '|', $parts );
	}

	/**
	 * WooCommerce stores state codes; geocoders do better with names.
	 */
	public static function state_name( string $country, string $state ): string {
		if ( '' === $state || ! function_exists( 'WC' ) || ! WC()->countries ) {
			return $state;
		}
		$states = WC()->countries->get_states( $country );
		if ( is_array( $states ) && isset( $states[ $state ] ) ) {
			return html_entity_decode( (string) $states[ $state ], ENT_QUOTES, 'UTF-8' );
		}
		return $state;
	}
}
