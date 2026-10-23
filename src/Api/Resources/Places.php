<?php
/**
 * Places resource.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Api\Resources;

use Fleetbase\WooCommerce\Api\ApiResource;

final class Places extends ApiResource {

	protected string $path = 'places';

	/**
	 * Geocoder-backed search merged with saved places.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function search( string $query, bool $geo = true, ?float $latitude = null, ?float $longitude = null, int $limit = 10 ): array {
		$result = $this->client->get(
			'places/search',
			[
				'query'     => $query,
				'geo'       => $geo,
				'latitude'  => $latitude,
				'longitude' => $longitude,
				'limit'     => $limit,
			]
		);
		return is_array( $result ) ? array_values( array_filter( $result, 'is_array' ) ) : [];
	}
}
