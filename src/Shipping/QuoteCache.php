<?php
/**
 * Cache contract for quotes so the service can be tested without WordPress.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Shipping;

interface QuoteCache {
	/**
	 * @return array<string, mixed>|null
	 */
	public function get( string $key ): ?array;

	/**
	 * @param array<string, mixed> $value
	 */
	public function set( string $key, array $value, int $ttl ): void;

	public function delete( string $key ): void;
}
