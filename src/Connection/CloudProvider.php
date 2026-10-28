<?php
/**
 * Fleetbase Cloud: fixed host, no licence.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Connection;

use Fleetbase\WooCommerce\Api\Connection;

final class CloudProvider implements ConnectionProvider {

	public const ID = 'cloud';

	public function id(): string {
		return self::ID;
	}

	public function label(): string {
		return __( 'Fleetbase Cloud', 'fleetbase-for-woocommerce' );
	}

	public function description(): string {
		return __( 'Hosted by Fleetbase. Nothing to install, free to connect. You pay only for your Fleetbase Cloud subscription.', 'fleetbase-for-woocommerce' );
	}

	public function host_editable(): bool {
		return false;
	}

	public function default_host(): string {
		return Connection::CLOUD_HOST;
	}

	public function validate_host( string $host ): string {
		return Connection::CLOUD_HOST;
	}

	public function authorize( string $host, array $organization ) {
		return true;
	}

	public function status(): array {
		return [];
	}

	public function supports_oauth(): bool {
		return true;
	}
}
