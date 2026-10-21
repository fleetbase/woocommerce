<?php
/**
 * 429 Too Many Requests.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Api\Exception;

class RateLimitException extends ApiException {

	private int $retry_after = 0;

	public function with_retry_after( int $seconds ): self {
		$this->retry_after = max( 0, $seconds );
		return $this;
	}

	public function retry_after(): int {
		return $this->retry_after;
	}
}
