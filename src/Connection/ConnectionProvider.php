<?php
/**
 * A way of reaching a Fleetbase API. The plugin ships Fleetbase Cloud; others can be registered
 * with the fleetbase_wc_connection_providers filter.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Connection;

defined( 'ABSPATH' ) || exit;

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
	 * Additional checks the provider needs after the API key has been verified.
	 *
	 * @param array<string, mixed> $organization The organisation returned by the API.
	 * @return true|\WP_Error
	 */
	public function authorize( string $host, array $organization );

	/**
	 * Extra, non-secret data about the connection, shown in the admin.
	 *
	 * @return array<string, mixed>
	 */
	public function status(): array;

	/**
	 * Whether "Connect with Fleetbase" (OAuth) can be offered for this connection type.
	 */
	public function supports_oauth(): bool;
}
