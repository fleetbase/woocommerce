<?php
/**
 * Minor-unit conversions. Fleetbase stores money as integers in the currency's smallest unit.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Api;

final class Money {

	private const ZERO_DECIMAL  = [ 'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' ];
	private const THREE_DECIMAL = [ 'BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND' ];

	public static function decimals( string $currency ): int {
		$currency = strtoupper( $currency );
		if ( in_array( $currency, self::ZERO_DECIMAL, true ) ) {
			return 0;
		}
		if ( in_array( $currency, self::THREE_DECIMAL, true ) ) {
			return 3;
		}
		return 2;
	}

	/**
	 * @param int|float|string $amount Major units, e.g. "12.50".
	 */
	public static function to_minor( $amount, string $currency ): int {
		$decimals = self::decimals( $currency );
		$value    = is_string( $amount ) ? (float) str_replace( [ ',', ' ' ], [ '', '' ], $amount ) : (float) $amount;
		return (int) round( $value * ( 10 ** $decimals ) );
	}

	public static function from_minor( int $minor, string $currency ): float {
		$decimals = self::decimals( $currency );
		if ( 0 === $decimals ) {
			return (float) $minor;
		}
		return round( $minor / ( 10 ** $decimals ), $decimals );
	}

	public static function format_minor( int $minor, string $currency ): string {
		return number_format( self::from_minor( $minor, $currency ), self::decimals( $currency ), '.', '' );
	}
}
