<?php
/**
 * Contacts resource (customers are contacts with type "customer").
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Api\Resources;

use Fleetbase\WooCommerce\Api\ApiResource;

final class Contacts extends ApiResource {

	protected string $path = 'contacts';
}
