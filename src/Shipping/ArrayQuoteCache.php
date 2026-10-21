<?php
/**
 * In-memory quote cache for tests and single-request reuse.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Shipping;

final class ArrayQuoteCache implements QuoteCache {

	/** @var array<string, array{expires:int, value:array<string, mixed>}> */
	private array $items = [];

	public function get( string $key ): ?array {
		if ( ! isset( $this->items[ $key ] ) ) {
			return null;
		}
		if ( $this->items[ $key ]['expires'] < time() ) {
			unset( $this->items[ $key ] );
			return null;
		}
		return $this->items[ $key ]['value'];
	}

	public function set( string $key, array $value, int $ttl ): void {
		if ( $ttl <= 0 ) {
			return;
		}
		$this->items[ $key ] = [
			'expires' => time() + $ttl,
			'value'   => $value,
		];
	}

	public function delete( string $key ): void {
		unset( $this->items[ $key ] );
	}

	public function count(): int {
		return count( $this->items );
	}
}
