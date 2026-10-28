<?php
/**
 * Persists the active connection (provider, host, encrypted key, organisation).
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Support;

use Fleetbase\WooCommerce\Api\Connection;
use Fleetbase\WooCommerce\Connection\CloudProvider;

final class ConnectionStore {

	public const OPTION = 'fleetbase_wc_connection';

	/** @var array<string, mixed>|null */
	private ?array $cache = null;

	/**
	 * @return array<string, mixed>
	 */
	private function raw(): array {
		if ( null === $this->cache ) {
			$stored      = get_option( self::OPTION, [] );
			$this->cache = is_array( $stored ) ? $stored : [];
		}
		return $this->cache;
	}

	public function is_connected(): bool {
		$raw = $this->raw();
		return ! empty( $raw['key'] ) && ! empty( $raw['host'] );
	}

	public function connection(): ?Connection {
		if ( ! $this->is_connected() ) {
			return null;
		}
		$raw = $this->raw();
		$key = Crypto::decrypt( (string) $raw['key'] );
		if ( '' === $key ) {
			return null;
		}
		return new Connection( (string) $raw['host'], $key );
	}

	public function provider_id(): string {
		return (string) ( $this->raw()['provider'] ?? CloudProvider::ID );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function organization(): array {
		$org = $this->raw()['organization'] ?? [];
		return is_array( $org ) ? $org : [];
	}

	/**
	 * @param array<string, mixed> $organization
	 */
	public function save( string $provider, string $host, string $key, array $organization ): void {
		$previous = $this->connection();
		if ( null !== $previous && ( $previous->host() !== Connection::normalize_host( $host ) || $previous->key() !== trim( $key ) ) ) {
			/** Fires while the outgoing connection still works, so it can be cleaned up. */
			do_action( 'fleetbase_wc_before_disconnect' );
		}

		$this->cache = [
			'provider'         => $provider,
			'host'             => Connection::normalize_host( $host ),
			'key'              => Crypto::encrypt( trim( $key ) ),
			'organization'     => self::pick_organization( $organization ),
			'connected_at'     => time(),
			'last_verified_at' => time(),
			'last_error'       => '',
		];
		update_option( self::OPTION, $this->cache, false );

		/**
		 * Fires when a Fleetbase connection has been saved.
		 *
		 * @param string $provider
		 * @param string $host
		 */
		do_action( 'fleetbase_wc_connected', $provider, $this->cache['host'] );
	}

	public function disconnect(): void {
		if ( $this->is_connected() ) {
			/** Fires while the connection still works, so it can be cleaned up. */
			do_action( 'fleetbase_wc_before_disconnect' );
		}
		delete_option( self::OPTION );
		$this->cache = null;

		/** Fires when the Fleetbase connection is removed. */
		do_action( 'fleetbase_wc_disconnected' );
	}

	public function touch_verified( string $error = '' ): void {
		if ( ! $this->is_connected() ) {
			return;
		}
		$raw               = $this->raw();
		$raw['last_error'] = $error;
		if ( '' === $error ) {
			$raw['last_verified_at'] = time();
		}
		$this->cache = $raw;
		update_option( self::OPTION, $raw, false );
	}

	/**
	 * Safe-to-expose status (no secrets).
	 *
	 * @return array<string, mixed>
	 */
	public function status(): array {
		$raw        = $this->raw();
		$connection = $this->connection();
		return [
			'connected'        => null !== $connection,
			'provider'         => $this->provider_id(),
			'host'             => (string) ( $raw['host'] ?? '' ),
			'is_cloud'         => null !== $connection && $connection->is_cloud(),
			'mode'             => null !== $connection ? $connection->mode() : null,
			'masked_key'       => null !== $connection ? $connection->masked_key() : '',
			'organization'     => $this->organization(),
			'connected_at'     => isset( $raw['connected_at'] ) ? (int) $raw['connected_at'] : null,
			'last_verified_at' => isset( $raw['last_verified_at'] ) ? (int) $raw['last_verified_at'] : null,
			'last_error'       => (string) ( $raw['last_error'] ?? '' ),
		];
	}

	/**
	 * @param array<string, mixed> $organization
	 * @return array<string, mixed>
	 */
	private static function pick_organization( array $organization ): array {
		$keys = [ 'id', 'name', 'currency', 'timezone', 'country', 'phone', 'status', 'slug' ];
		$out  = [];
		foreach ( $keys as $key ) {
			if ( isset( $organization[ $key ] ) && is_scalar( $organization[ $key ] ) ) {
				$out[ $key ] = $organization[ $key ];
			}
		}
		return $out;
	}
}
