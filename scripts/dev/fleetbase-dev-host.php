<?php
/**
 * Plugin Name: Fleetbase for WooCommerce development host
 * Description: Local development only, never shipped. Lets the sandbox connect the plugin to a local Fleetbase API.
 *
 * scripts/seed-store.sh copies this into wp-content/mu-plugins.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

use Fleetbase\WooCommerce\Api\Connection;
use Fleetbase\WooCommerce\Connection\ConnectionProvider;

defined( 'ABSPATH' ) || exit;

add_filter(
	'fleetbase_wc_connection_providers',
	static function ( array $providers ): array {
		$providers['custom'] = new class() implements ConnectionProvider {

			public function id(): string {
				return 'custom';
			}

			public function label(): string {
				return 'Custom host (development)';
			}

			public function description(): string {
				return 'Connect to a local Fleetbase API. Provided by the fleetbase-dev-host mu-plugin.';
			}

			public function host_editable(): bool {
				return true;
			}

			public function default_host(): string {
				return 'http://host.docker.internal:8000';
			}

			public function validate_host( string $host ) {
				if ( ! Connection::is_valid_host( $host ) ) {
					return new \WP_Error( 'fleetbase_wc_invalid_host', 'Enter a valid API host, for example http://host.docker.internal:8000.' );
				}
				return Connection::normalize_host( $host );
			}

			public function authorize( string $host, array $organization ) {
				return true;
			}

			public function status(): array {
				return [ 'development' => true ];
			}

			public function supports_oauth(): bool {
				return true;
			}
		};
		return $providers;
	}
);
