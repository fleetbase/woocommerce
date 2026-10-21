<?php
/**
 * Base exception for Fleetbase API failures.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Api\Exception;

class ApiException extends \RuntimeException {

	/** @var array<int|string, mixed> */
	protected array $errors = [];

	protected ?string $body = null;

	/**
	 * @param array<int|string, mixed> $errors
	 */
	public function __construct( string $message, int $status = 0, array $errors = [], ?string $body = null, ?\Throwable $previous = null ) {
		parent::__construct( $message, $status, $previous );
		$this->errors = $errors;
		$this->body   = $body;
	}

	public function status(): int {
		return $this->getCode();
	}

	/**
	 * @return array<int|string, mixed>
	 */
	public function errors(): array {
		return $this->errors;
	}

	public function body(): ?string {
		return $this->body;
	}

	/**
	 * A short, user-safe message without internal details.
	 */
	public function user_message(): string {
		return $this->getMessage();
	}
}
