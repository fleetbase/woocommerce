<?php
/**
 * Maps the service-rate form to a Fleetbase request and back. Pure, for unit tests.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Admin;

use Fleetbase\WooCommerce\Api\Money;

final class ServiceRateForm {

	public const METHODS = [ 'per_meter', 'fixed_rate', 'fixed_meter' ];
	public const UNITS   = [ 'km', 'mi', 'm', 'ft', 'yd' ];

	/**
	 * @param array<string, mixed> $form
	 * @return array{request: array<string, mixed>, errors: array<string, string>}
	 */
	public static function to_request( array $form, string $currency ): array {
		$errors   = [];
		$currency = strtoupper( $currency );
		$name     = trim( (string) ( $form['service_name'] ?? '' ) );
		$type     = strtolower( trim( (string) ( $form['service_type'] ?? '' ) ) );
		$type     = preg_replace( '/[^a-z0-9_-]+/', '_', $type ) ?? $type;
		$method   = (string) ( $form['rate_calculation_method'] ?? 'per_meter' );
		$unit     = (string) ( $form['per_meter_unit'] ?? 'km' );

		if ( '' === $name ) {
			$errors['service_name'] = 'A service name is required.';
		}
		if ( '' === trim( $type, '_' ) ) {
			$errors['service_type'] = 'A service type is required.';
		}
		if ( ! in_array( $method, self::METHODS, true ) ) {
			$errors['rate_calculation_method'] = 'Unknown rate calculation method.';
		}
		if ( ! in_array( $unit, self::UNITS, true ) ) {
			$unit = 'km';
		}

		$request = [
			'service_name'            => $name,
			'service_type'            => $type,
			'rate_calculation_method' => $method,
			'currency'                => $currency,
			'per_meter_unit'          => $unit,
			'base_fee'                => Money::to_minor( (string) ( $form['base_fee'] ?? '0' ), $currency ),
			'duration_terms'          => trim( (string) ( $form['duration_terms'] ?? '' ) ),
			'estimated_days'          => max( 0, (int) ( $form['estimated_days'] ?? 0 ) ),
		];

		if ( 'per_meter' === $method ) {
			$fee = trim( (string) ( $form['per_meter_flat_rate_fee'] ?? '' ) );
			if ( '' === $fee ) {
				$errors['per_meter_flat_rate_fee'] = 'A fee per distance unit is required.';
			}
			$request['per_meter_flat_rate_fee'] = Money::to_minor( $fee, $currency );
		} else {
			$tiers = [];
			foreach ( (array) ( $form['meter_fees'] ?? [] ) as $tier ) {
				if ( ! is_array( $tier ) ) {
					continue;
				}
				$distance = (float) ( $tier['distance'] ?? 0 );
				$fee      = trim( (string) ( $tier['fee'] ?? '' ) );
				if ( $distance <= 0 || '' === $fee ) {
					continue;
				}
				$tiers[] = [
					'distance' => $distance,
					'fee'      => Money::to_minor( $fee, $currency ),
				];
			}
			usort(
				$tiers,
				static function ( array $a, array $b ): int {
					return $a['distance'] <=> $b['distance'];
				}
			);
			if ( [] === $tiers ) {
				$errors['meter_fees'] = 'Add at least one distance tier.';
			}
			$request['meter_fees'] = $tiers;
		}

		foreach ( [ 'service_area', 'zone' ] as $key ) {
			$value = trim( (string) ( $form[ $key ] ?? '' ) );
			if ( '' !== $value ) {
				$request[ $key ] = $value;
			}
		}

		$has_cod                = ! empty( $form['has_cod_fee'] );
		$request['has_cod_fee'] = $has_cod;
		if ( $has_cod ) {
			$cod_method                        = 'percentage' === ( $form['cod_calculation_method'] ?? 'flat' ) ? 'percentage' : 'flat';
			$request['cod_calculation_method'] = $cod_method;
			$request['cod_flat_fee']           = Money::to_minor( (string) ( $form['cod_flat_fee'] ?? '0' ), $currency );
			$request['cod_percent']            = (float) ( $form['cod_percent'] ?? 0 );
		}

		$has_peak                      = ! empty( $form['has_peak_hours_fee'] );
		$request['has_peak_hours_fee'] = $has_peak;
		$request['has_peak_hours']     = $has_peak;
		if ( $has_peak ) {
			$peak_method                              = 'percentage' === ( $form['peak_hours_calculation_method'] ?? 'flat' ) ? 'percentage' : 'flat';
			$request['peak_hours_calculation_method'] = $peak_method;
			$request['peak_hours_flat_fee']           = Money::to_minor( (string) ( $form['peak_hours_flat_fee'] ?? '0' ), $currency );
			$request['peak_hours_percent']            = (float) ( $form['peak_hours_percent'] ?? 0 );
			$start                                    = (string) ( $form['peak_hours_start'] ?? '' );
			$end                                      = (string) ( $form['peak_hours_end'] ?? '' );
			if ( ! preg_match( '/^\d{2}:\d{2}$/', $start ) || ! preg_match( '/^\d{2}:\d{2}$/', $end ) ) {
				$errors['peak_hours'] = 'Peak hours need a start and end time.';
			}
			$request['peak_hours_start'] = $start;
			$request['peak_hours_end']   = $end;
		}

		return [
			'request' => $request,
			'errors'  => $errors,
		];
	}

	/**
	 * Fleetbase rate to form values with decimal amounts.
	 *
	 * @param array<string, mixed> $rate
	 * @return array<string, mixed>
	 */
	public static function from_api( array $rate ): array {
		$currency = strtoupper( (string) ( $rate['currency'] ?? 'USD' ) );
		$tiers    = [];
		foreach ( (array) ( $rate['rate_fees'] ?? [] ) as $fee ) {
			if ( is_array( $fee ) ) {
				$tiers[] = [
					'distance' => (float) ( $fee['distance'] ?? 0 ),
					'fee'      => Money::format_minor( (int) ( $fee['fee'] ?? 0 ), $currency ),
				];
			}
		}
		$area = $rate['service_area'] ?? '';
		$zone = $rate['zone'] ?? '';
		return [
			'id'                            => (string) ( $rate['id'] ?? '' ),
			'service_name'                  => (string) ( $rate['service_name'] ?? '' ),
			'service_type'                  => (string) ( $rate['service_type'] ?? '' ),
			'rate_calculation_method'       => (string) ( $rate['rate_calculation_method'] ?? 'per_meter' ),
			'currency'                      => $currency,
			'base_fee'                      => Money::format_minor( (int) ( $rate['base_fee'] ?? 0 ), $currency ),
			'per_meter_unit'                => (string) ( $rate['per_meter_unit'] ?? 'km' ),
			'per_meter_flat_rate_fee'       => Money::format_minor( (int) ( $rate['per_meter_flat_rate_fee'] ?? 0 ), $currency ),
			'meter_fees'                    => $tiers,
			'service_area'                  => is_array( $area ) ? (string) ( $area['id'] ?? '' ) : (string) $area,
			'zone'                          => is_array( $zone ) ? (string) ( $zone['id'] ?? '' ) : (string) $zone,
			'has_cod_fee'                   => (bool) ( $rate['has_cod_fee'] ?? false ),
			'cod_calculation_method'        => (string) ( $rate['cod_calculation_method'] ?? 'flat' ),
			'cod_flat_fee'                  => Money::format_minor( (int) ( $rate['cod_flat_fee'] ?? 0 ), $currency ),
			'cod_percent'                   => (float) ( $rate['cod_percent'] ?? 0 ),
			'has_peak_hours_fee'            => (bool) ( $rate['has_peak_hours_fee'] ?? false ),
			'peak_hours_calculation_method' => (string) ( $rate['peak_hours_calculation_method'] ?? 'flat' ),
			'peak_hours_flat_fee'           => Money::format_minor( (int) ( $rate['peak_hours_flat_fee'] ?? 0 ), $currency ),
			'peak_hours_percent'            => (float) ( $rate['peak_hours_percent'] ?? 0 ),
			'peak_hours_start'              => (string) ( $rate['peak_hours_start'] ?? '' ),
			'peak_hours_end'                => (string) ( $rate['peak_hours_end'] ?? '' ),
			'duration_terms'                => (string) ( $rate['duration_terms'] ?? '' ),
			'estimated_days'                => (int) ( $rate['estimated_days'] ?? 0 ),
			'updated_at'                    => (string) ( $rate['updated_at'] ?? '' ),
		];
	}
}
