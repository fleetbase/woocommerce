<?php
/**
 * A version string folded into every quote cache key, bumped whenever something
 * that changes quotes is saved (rates settings, pickup location, connection).
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Shipping;

final class QuoteCacheVersion {

	public const OPTION = 'fleetbase_wc_quote_cache_version';

	public static function current(): string {
		$version = (string) get_option( self::OPTION, '' );
		if ( '' === $version ) {
			$version = self::bump();
		}
		return $version;
	}

	public static function bump(): string {
		$version = (string) time() . '.' . wp_rand( 100, 999 );
		update_option( self::OPTION, $version, true );
		if ( class_exists( '\WC_Cache_Helper' ) ) {
			\WC_Cache_Helper::get_transient_version( 'shipping', true );
		}
		return $version;
	}
}
