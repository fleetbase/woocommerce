<?php
/**
 * Organizations resource.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Api\Resources;

use Fleetbase\WooCommerce\Api\ApiResource;

final class Organizations extends ApiResource {

	protected string $path = 'organizations';

	/**
	 * The organization that owns the API key.
	 *
	 * @return array<string, mixed>
	 */
	public function current(): array {
		return (array) $this->client->get( 'organizations/current' );
	}
}
