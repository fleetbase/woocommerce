<?php
/**
 * Minimal logger contract so the API client does not depend on WordPress.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Support;

interface LoggerInterface {
	/**
	 * @param array<string, mixed> $context
	 */
	public function log( string $level, string $message, array $context = [] ): void;
}
