<?php
/**
 * A way of reaching a Fleetbase API: Cloud, or a self-hosted instance (via the companion add-on).
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Connection;

interface ConnectionProvider {

	public function id(): string;

	public function label(): string;

	public function description(): string;

	/**
	 * Whether the merchant can type a host. Cloud is fixed.
	 */
	public function host_editable(): bool;

	public function default_host(): string;

	/**
	 * Validate and normalise a host supplied by the merchant.
	 *
	 * @return string|\WP_Error Normalised host, or an error.
	 */
	public function validate_host( string $host );

	/**
	 * Additional checks after the API key has been verified (for example a license).
	 *
	 * @param array<string, mixed> $organization The organisation returned by the API.
	 * @return true|\WP_Error
	 */
	public function authorize( string $host, array $organization );

	/**
	 * Extra, non-secret data shown in the admin (for example a license status).
	 *
	 * @return array<string, mixed>
	 */
	public function status(): array;

	/**
	 * Whether "Connect with Fleetbase" (OAuth) can be offered for this connection type.
	 */
	public function supports_oauth(): bool;
}
