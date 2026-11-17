<?php
/**
 * Vehicles resource.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Api\Resources;

use Fleetbase\WooCommerce\Api\ApiResource;

defined( 'ABSPATH' ) || exit;

final class Vehicles extends ApiResource {

	protected string $path = 'vehicles';
}
