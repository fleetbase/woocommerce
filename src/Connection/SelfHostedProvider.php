<?php
/**
 * A Fleetbase server the merchant runs themselves.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Connection;

use Fleetbase\WooCommerce\Api\Connection;

defined( 'ABSPATH' ) || exit;

final class SelfHostedProvider implements ConnectionProvider {

	public const ID = 'self-hosted';

	public function id(): string {
		return self::ID;
	}

	public function label(): string {
		return __( 'Self-hosted Fleetbase', 'fleetbase-for-woocommerce' );
	}

	public function description(): string {
		return __( 'Connect to a Fleetbase server you run yourself, at no cost. You will enter its API host in the next step.', 'fleetbase-for-woocommerce' );
	}

	public function host_editable(): bool {
		return true;
	}

	public function default_host(): string {
		return '';
	}

	/**
	 * Every step that chooses a host passes through here. Order data and the API key go to this
	 * host, and WordPress makes requests to it from the server, so choosing one is for site
	 * administrators rather than everyone who can manage the shop.
	 */
	public function validate_host( string $host ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'fleetbase_wc_host_permission', __( 'Only a site administrator can connect to a self-hosted Fleetbase server.', 'fleetbase-for-woocommerce' ) );
		}
		if ( '' === trim( $host ) ) {
			return new \WP_Error( 'fleetbase_wc_invalid_host', __( 'Enter the API host of your Fleetbase server, for example https://api.example.com.', 'fleetbase-for-woocommerce' ) );
		}
		if ( ! Connection::is_valid_host( $host ) ) {
			return new \WP_Error( 'fleetbase_wc_invalid_host', __( 'Enter a valid API host, for example https://api.example.com.', 'fleetbase-for-woocommerce' ) );
		}
		$host = Connection::normalize_host( $host );
		if ( ! Connection::is_transport_secure( $host ) ) {
			return new \WP_Error( 'fleetbase_wc_insecure_host', __( 'Use https for a server on the internet, so the API key and customer details are encrypted. Plain http only works for a server on a private network.', 'fleetbase-for-woocommerce' ) );
		}
		return $host;
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
