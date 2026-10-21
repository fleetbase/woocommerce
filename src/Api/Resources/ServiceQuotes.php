<?php
/**
 * Service quotes resource.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Api\Resources;

use Fleetbase\WooCommerce\Api\ApiResource;

final class ServiceQuotes extends ApiResource {

	protected string $path = 'service-quotes';

	/**
	 * Query quotes for a pickup/dropoff pair. Never pass "currency": the API filters
	 * on a boolean when no payload id is given and returns nothing.
	 *
	 * @param array<string, mixed> $params pickup, dropoff, service_type, scheduled_at, single, service, cod, distance, time
	 * @return array<int, array<string, mixed>>
	 */
	public function query( array $params ): array {
		unset( $params['currency'] );
		return $this->all( $params );
	}
}
