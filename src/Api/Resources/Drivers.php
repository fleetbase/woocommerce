<?php
/**
 * Drivers resource.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Api\Resources;

use Fleetbase\WooCommerce\Api\ApiResource;

final class Drivers extends ApiResource {

	protected string $path = 'drivers';

	/** @return array<string, mixed> */
	public function toggle_online( string $id, bool $online ): array {
		return (array) $this->client->patch( "drivers/{$id}/toggle-online", [ 'online' => $online ] );
	}
}
