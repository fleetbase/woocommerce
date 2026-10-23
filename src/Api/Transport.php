<?php
/**
 * HTTP transport contract so the client can be unit tested without WordPress.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Api;

interface Transport {
	/**
	 * @param array<string, string> $headers
	 * @throws Exception\TransportException On network failure.
	 */
	public function send( string $method, string $url, array $headers, ?string $body, int $timeout ): TransportResponse;
}
