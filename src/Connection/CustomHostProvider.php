<?php
/**
 * Development-only provider for connecting to any host. Enabled by defining
 * FLEETBASE_WC_ALLOW_CUSTOM_HOST as true in wp-config.php. The commercial
 * self-hosted connector add-on registers its own licensed provider instead.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Connection;

use Fleetbase\WooCommerce\Api\Connection;

final class CustomHostProvider implements ConnectionProvider {

	public const ID = 'custom';

	public static function enabled(): bool {
		return defined( 'FLEETBASE_WC_ALLOW_CUSTOM_HOST' ) && (bool) constant( 'FLEETBASE_WC_ALLOW_CUSTOM_HOST' );
	}

	public function id(): string {
		return self::ID;
	}

	public function label(): string {
		return __( 'Custom host (development)', 'fleetbase-for-woocommerce' );
	}

	public function description(): string {
		return __( 'Connect to any Fleetbase API host. Enabled because FLEETBASE_WC_ALLOW_CUSTOM_HOST is defined.', 'fleetbase-for-woocommerce' );
	}

	public function host_editable(): bool {
		return true;
	}

	public function default_host(): string {
		return defined( 'FLEETBASE_WC_DEFAULT_HOST' ) ? (string) constant( 'FLEETBASE_WC_DEFAULT_HOST' ) : 'http://localhost:8000';
	}

	public function validate_host( string $host ) {
		if ( ! Connection::is_valid_host( $host ) ) {
			return new \WP_Error( 'fleetbase_wc_invalid_host', __( 'Enter a valid API host, for example https://api.example.com.', 'fleetbase-for-woocommerce' ) );
		}
		return Connection::normalize_host( $host );
	}

	public function authorize( string $host, array $organization ) {
		return true;
	}

	public function status(): array {
		return [ 'development' => true ];
	}
}
