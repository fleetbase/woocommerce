<?php
/**
 * In-memory logger for assertions.
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Tests\Support;

use Fleetbase\WooCommerce\Support\LoggerInterface;

final class ArrayLogger implements LoggerInterface {

	/** @var array<int, array{level:string,message:string,context:array<string,mixed>}> */
	public array $entries = [];

	public function log( string $level, string $message, array $context = [] ): void {
		$this->entries[] = compact( 'level', 'message', 'context' );
	}
}
