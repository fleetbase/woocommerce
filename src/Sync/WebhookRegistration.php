<?php
/**
 * Registers this store's webhook URL with Fleetbase through the API key, so the
 * merchant never copies it into the console. Pure logic over the API; storage and
 * hooks live in WebhookRegistrar.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Sync;

use Fleetbase\WooCommerce\Api\Exception\ApiException;
use Fleetbase\WooCommerce\Api\Exception\NotFoundException;
use Fleetbase\WooCommerce\Api\Exception\ValidationException;
use Fleetbase\WooCommerce\Api\Fleetbase;

final class WebhookRegistration {

	/**
	 * The events the receiver acts on: anything that moves an order or its proof of delivery.
	 */
	public const EVENTS = [
		'order.updated',
		'order.dispatched',
		'order.dispatch_failed',
		'order.driver_assigned',
		'order.completed',
		'order.canceled',
		'order.failed',
		'entity.activity',
		'entity.completed',
		'waypoint.activity',
		'waypoint.completed',
		'tracking_status.created',
	];

	public function __construct( private Fleetbase $api ) {}

	/**
	 * Make sure an enabled endpoint for $url exists and that we hold its signing secret.
	 *
	 * @param array{id?:string, secret?:string, url?:string} $current What is stored now (secret in plain text).
	 * @return array{id:string, secret:string, url:string}
	 * @throws ApiException When Fleetbase refuses (for example a URL it does not consider public).
	 */
	public function sync( string $url, string $description, array $current = [] ): array {
		$endpoints = $this->api->webhook_endpoints();

		// The URL changed (the merchant generated a new one): drop the old endpoint.
		if ( ! empty( $current['id'] ) && ( $current['url'] ?? '' ) !== $url ) {
			$this->remove( (string) $current['id'] );
			$current = [];
		}

		$existing = $endpoints->find_by_url( $url );
		if ( null !== $existing && ! empty( $existing['id'] ) ) {
			$id = (string) $existing['id'];
			if ( 'enabled' !== ( $existing['status'] ?? 'enabled' ) ) {
				$endpoints->update( $id, [ 'status' => 'enabled' ] );
			}
			if ( ( $current['id'] ?? '' ) === $id && ! empty( $current['secret'] ) ) {
				return [
					'id'     => $id,
					'secret' => (string) $current['secret'],
					'url'    => $url,
				];
			}
			// Someone else made it, or we lost the secret: a new one is the only way to read it.
			$rotated = $endpoints->rotate_secret( $id );
			return $this->result( $rotated, $url, $id );
		}

		$body = [
			'url'         => $url,
			'events'      => self::EVENTS,
			'description' => $description,
			'status'      => 'enabled',
		];
		try {
			$created = $endpoints->create( $body );
		} catch ( ValidationException $e ) {
			if ( ! self::is_about_events( $e ) ) {
				throw $e;
			}
			// A Fleetbase that names its events differently: subscribe to everything instead.
			unset( $body['events'] );
			$created = $endpoints->create( $body );
		}

		return $this->result( $created, $url );
	}

	/**
	 * Delete an endpoint, ignoring one that is already gone.
	 */
	public function remove( string $id ): void {
		try {
			$this->api->webhook_endpoints()->delete( $id );
		} catch ( NotFoundException $e ) {
			unset( $e );
		}
	}

	/**
	 * @param array<string, mixed> $response
	 * @return array{id:string, secret:string, url:string}
	 * @throws ApiException When the response has no id or secret.
	 */
	private function result( array $response, string $url, string $fallback_id = '' ): array {
		$id     = (string) ( $response['id'] ?? $fallback_id );
		$secret = (string) ( $response['secret'] ?? '' );
		if ( '' === $id || '' === $secret ) {
			throw new ApiException( 'Fleetbase did not return the webhook signing secret.' );
		}
		return [
			'id'     => $id,
			'secret' => $secret,
			'url'    => $url,
		];
	}

	private static function is_about_events( ValidationException $e ): bool {
		foreach ( array_keys( $e->errors() ) as $field ) {
			if ( is_string( $field ) && str_starts_with( $field, 'events' ) ) {
				return true;
			}
		}
		return false !== stripos( $e->getMessage(), 'event' );
	}
}
