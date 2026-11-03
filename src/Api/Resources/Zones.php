<?php
/**
 * Zones resource.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Api\Resources;

use Fleetbase\WooCommerce\Api\ApiResource;

defined( 'ABSPATH' ) || exit;

final class Zones extends ApiResource {

	protected string $path = 'zones';
}
