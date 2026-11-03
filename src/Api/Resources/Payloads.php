<?php
/**
 * Payloads resource.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Api\Resources;

use Fleetbase\WooCommerce\Api\ApiResource;

defined( 'ABSPATH' ) || exit;

final class Payloads extends ApiResource {

	protected string $path = 'payloads';
}
