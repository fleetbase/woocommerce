<?php
/**
 * Raw HTTP response.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Api;

final class TransportResponse {

	/**
	 * @param array<string, string> $headers Lower-cased header names.
	 */
	public function __construct(
		public readonly int $status,
		public readonly array $headers,
		public readonly string $body
	) {}

	public function header( string $name ): ?string {
		$name = strtolower( $name );
		return $this->headers[ $name ] ?? null;
	}

	public function ok(): bool {
		return $this->status >= 200 && $this->status < 300;
	}
}
