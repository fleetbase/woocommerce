<?php
/**
 * Orders resource.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Api\Resources;

use Fleetbase\WooCommerce\Api\ApiResource;

final class Orders extends ApiResource {

	protected string $path = 'orders';

	/** @return array<string, mixed> */
	public function dispatch( string $id ): array {
		return (array) $this->client->patch( "orders/{$id}/dispatch" );
	}

	/** @return array<string, mixed> */
	public function cancel( string $id, string $reason = '' ): array {
		return (array) $this->client->delete( "orders/{$id}/cancel", '' !== $reason ? [ 'reason' => $reason ] : [] );
	}

	/** @return array<string, mixed> */
	public function schedule( string $id, string $date, ?string $time = null, ?string $timezone = null ): array {
		return (array) $this->client->patch(
			"orders/{$id}/schedule",
			array_filter(
				[
					'date'     => $date,
					'time'     => $time,
					'timezone' => $timezone,
				]
			)
		);
	}

	/** @return array<string, mixed> */
	public function start( string $id, bool $skip_dispatch = false ): array {
		return (array) $this->client->post( "orders/{$id}/start", [ 'skip_dispatch' => $skip_dispatch ] );
	}

	/** @return array<string, mixed> */
	public function complete( string $id ): array {
		return (array) $this->client->post( "orders/{$id}/complete" );
	}

	/** @return array<string, mixed> */
	public function tracker( string $id ): array {
		return (array) $this->client->get( "orders/{$id}/tracker" );
	}

	/** @return array<string, mixed> */
	public function eta( string $id ): array {
		return (array) $this->client->get( "orders/{$id}/eta" );
	}

	/** @return array<int, array<string, mixed>> */
	public function proofs( string $id ): array {
		$result = $this->client->get( "orders/{$id}/proofs" );
		return is_array( $result ) ? array_values( array_filter( $result, 'is_array' ) ) : [];
	}

	/** @return array<string, mixed> */
	public function next_activity( string $id ): array {
		return (array) $this->client->get( "orders/{$id}/next-activity" );
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	public function update_activity( string $id, array $data ): array {
		return (array) $this->client->patch( "orders/{$id}/update-activity", $data );
	}

	/** @return array<string, mixed> */
	public function distance_and_time( string $id ): array {
		return (array) $this->client->get( "orders/{$id}/distance-and-time" );
	}
}
