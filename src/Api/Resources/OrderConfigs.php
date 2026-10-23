<?php
/**
 * Order configs resource (read only).
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Api\Resources;

use Fleetbase\WooCommerce\Api\ApiResource;

final class OrderConfigs extends ApiResource {

	protected string $path = 'order-configs';
}
