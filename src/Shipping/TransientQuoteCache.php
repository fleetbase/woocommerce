<?php
/**
 * Quote cache backed by WordPress transients.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Shipping;

final class TransientQuoteCache implements QuoteCache {

	private const PREFIX = 'fbwc_q_';

	public function get( string $key ): ?array {
		$value = get_transient( self::PREFIX . $key );
		return is_array( $value ) ? $value : null;
	}

	public function set( string $key, array $value, int $ttl ): void {
		if ( $ttl <= 0 ) {
			return;
		}
		set_transient( self::PREFIX . $key, $value, $ttl );
	}

	public function delete( string $key ): void {
		delete_transient( self::PREFIX . $key );
	}
}
