<?php
/**
 * Contract for services that attach WordPress hooks.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce;

interface Hookable {
	/**
	 * Attach hooks. Called once during boot.
	 */
	public function register(): void;
}
