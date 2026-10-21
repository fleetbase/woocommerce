<?php
/**
 * Connection status, test, save and disconnect.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Admin\Rest;

use Fleetbase\WooCommerce\Admin\Assets;
use Fleetbase\WooCommerce\Api\Connection;
use Fleetbase\WooCommerce\Connection\ConnectionProvider;
use Fleetbase\WooCommerce\Connection\ProviderRegistry;
use Fleetbase\WooCommerce\Plugin;

final class ConnectionController extends RestController {

	public function register_routes(): void {
		$this->route( '/status', 'GET', [ $this, 'status' ] );
		$this->route( '/connection/test', 'POST', [ $this, 'test' ], $this->connection_args() );
		$this->route( '/connection', 'POST', [ $this, 'save' ], $this->connection_args() );
		$this->route( '/connection', 'DELETE', [ $this, 'disconnect' ] );
		$this->route( '/connection/verify', 'POST', [ $this, 'verify' ] );
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function connection_args(): array {
		return [
			'provider' => [
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_key',
			],
			'host'     => [
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'key'      => [
				'type'     => 'string',
				'required' => true,
			],
		];
	}

	public function status(): \WP_REST_Response {
		return $this->respond( Plugin::instance()->get( Assets::class )->runtime_data() );
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function test( \WP_REST_Request $request ) {
		$resolved = $this->resolve( $request );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}
		[ $provider, $connection, $organization ] = $resolved;
		return $this->respond(
			[
				'organization' => $organization,
				'mode'         => $connection->mode(),
				'host'         => $connection->host(),
				'is_cloud'     => $connection->is_cloud(),
				'provider'     => $provider->id(),
			]
		);
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save( \WP_REST_Request $request ) {
		$resolved = $this->resolve( $request );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}
		[ $provider, $connection, $organization ] = $resolved;

		$authorized = $provider->authorize( $connection->host(), $organization );
		if ( is_wp_error( $authorized ) ) {
			return $authorized;
		}

		Plugin::instance()->connections()->save( $provider->id(), $connection->host(), $connection->key(), $organization );
		Plugin::instance()->logger()->info(
			'Connected to Fleetbase',
			[
				'provider' => $provider->id(),
				'host'     => $connection->host(),
				'mode'     => $connection->mode(),
			]
		);

		return $this->status();
	}

	public function disconnect(): \WP_REST_Response {
		Plugin::instance()->connections()->disconnect();
		return $this->status();
	}

	/**
	 * Re-check the stored connection.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function verify() {
		$plugin = Plugin::instance();
		if ( ! $plugin->connections()->is_connected() ) {
			return new \WP_Error( 'fleetbase_wc_not_connected', __( 'Fleetbase is not connected.', 'fleetbase-for-woocommerce' ), [ 'status' => 400 ] );
		}
		try {
			$organization = $plugin->api()->organizations()->current();
			$plugin->connections()->touch_verified();
		} catch ( \Throwable $e ) {
			$plugin->connections()->touch_verified( $e->getMessage() );
			return $this->error_from_exception( $e );
		}
		return $this->respond(
			[
				'organization' => $organization,
				'connection'   => $plugin->connections()->status(),
			]
		);
	}

	/**
	 * Validate provider, host and key, then prove the key by fetching the organisation.
	 *
	 * @return array{0:ConnectionProvider,1:Connection,2:array<string, mixed>}|\WP_Error
	 */
	private function resolve( \WP_REST_Request $request ) {
		$registry = Plugin::instance()->get( ProviderRegistry::class );
		$provider = $registry->get( (string) $request->get_param( 'provider' ) );
		if ( null === $provider ) {
			return new \WP_Error( 'fleetbase_wc_provider', __( 'Unknown connection type.', 'fleetbase-for-woocommerce' ), [ 'status' => 400 ] );
		}

		$key = trim( (string) $request->get_param( 'key' ) );
		if ( ! Connection::is_valid_key( $key ) ) {
			return new \WP_Error( 'fleetbase_wc_key', __( 'That does not look like a Fleetbase API key. Keys start with flb_live_ or flb_test_.', 'fleetbase-for-woocommerce' ), [ 'status' => 400 ] );
		}

		$host = $provider->host_editable() ? (string) $request->get_param( 'host' ) : $provider->default_host();
		$host = $provider->validate_host( $host );
		if ( is_wp_error( $host ) ) {
			return $host;
		}

		$connection = new Connection( $host, $key );
		try {
			$organization = Plugin::instance()->api( $connection )->organizations()->current();
		} catch ( \Throwable $e ) {
			return $this->error_from_exception( $e );
		}
		if ( empty( $organization['id'] ) && empty( $organization['name'] ) ) {
			return new \WP_Error( 'fleetbase_wc_org', __( 'Fleetbase did not return an organisation for this key.', 'fleetbase-for-woocommerce' ), [ 'status' => 400 ] );
		}

		return [ $provider, $connection, $organization ];
	}
}
