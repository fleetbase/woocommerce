<?php
/**
 * The stored pickup location (a Fleetbase Place).
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Support;

final class Pickup {

	public const OPTION = 'fleetbase_wc_pickup';

	/**
	 * @return array<string, mixed>|null
	 */
	public static function get(): ?array {
		$stored = get_option( self::OPTION, null );
		return is_array( $stored ) && ! empty( $stored['place_id'] ) ? $stored : null;
	}

	public static function place_id(): string {
		$pickup = self::get();
		return null === $pickup ? '' : (string) $pickup['place_id'];
	}

	/**
	 * Normalise a Fleetbase place and store it.
	 *
	 * @param array<string, mixed> $place
	 * @return array<string, mixed>
	 */
	public static function save_from_place( array $place ): array {
		$pickup               = self::normalize_place( $place );
		$pickup['updated_at'] = time();
		update_option( self::OPTION, $pickup, false );
		return $pickup;
	}

	public static function clear(): void {
		delete_option( self::OPTION );
	}

	/**
	 * Fleetbase places carry a GeoJSON point; older shapes used latitude/longitude.
	 *
	 * @param array<string, mixed> $place
	 * @return array<string, mixed>
	 */
	public static function normalize_place( array $place ): array {
		$lat = null;
		$lng = null;
		if ( isset( $place['location'] ) && is_array( $place['location'] ) ) {
			$loc = $place['location'];
			if ( isset( $loc['coordinates'] ) && is_array( $loc['coordinates'] ) && count( $loc['coordinates'] ) >= 2 ) {
				$lng = (float) $loc['coordinates'][0];
				$lat = (float) $loc['coordinates'][1];
			} elseif ( isset( $loc['latitude'], $loc['longitude'] ) ) {
				$lat = (float) $loc['latitude'];
				$lng = (float) $loc['longitude'];
			}
		}
		if ( null === $lat && isset( $place['latitude'], $place['longitude'] ) ) {
			$lat = (float) $place['latitude'];
			$lng = (float) $place['longitude'];
		}

		$address = (string) ( $place['address'] ?? '' );
		if ( '' === $address ) {
			$address = implode( ', ', array_filter( [ $place['street1'] ?? '', $place['city'] ?? '', $place['province'] ?? '', $place['postal_code'] ?? '', $place['country'] ?? '' ] ) );
		}

		return [
			'place_id'     => (string) ( $place['id'] ?? '' ),
			'name'         => (string) ( $place['name'] ?? '' ),
			'address'      => $address,
			'street1'      => (string) ( $place['street1'] ?? '' ),
			'street2'      => (string) ( $place['street2'] ?? '' ),
			'city'         => (string) ( $place['city'] ?? '' ),
			'province'     => (string) ( $place['province'] ?? '' ),
			'postal_code'  => (string) ( $place['postal_code'] ?? '' ),
			'country'      => (string) ( $place['country'] ?? '' ),
			'latitude'     => $lat,
			'longitude'    => $lng,
			'has_location' => null !== $lat && ( 0.0 !== $lat || 0.0 !== $lng ),
		];
	}
}
