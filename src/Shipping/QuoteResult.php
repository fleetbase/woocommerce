<?php
/**
 * Outcome of a quote request.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Shipping;

final class QuoteResult {

	public const OK          = 'ok';
	public const EMPTY       = 'empty';
	public const UNAVAILABLE = 'unavailable';

	/**
	 * @param array<int, array<string, mixed>> $quotes Normalised quotes.
	 */
	public function __construct(
		public readonly string $status,
		public readonly array $quotes = [],
		public readonly string $error = '',
		public readonly bool $from_cache = false
	) {}

	/**
	 * @param array<int, array<string, mixed>> $quotes
	 */
	public static function ok( array $quotes, bool $from_cache = false ): self {
		return new self( [] === $quotes ? self::EMPTY : self::OK, $quotes, '', $from_cache );
	}

	public static function unavailable( string $error ): self {
		return new self( self::UNAVAILABLE, [], $error );
	}

	public function is_ok(): bool {
		return self::OK === $this->status;
	}

	public function is_empty(): bool {
		return self::EMPTY === $this->status;
	}

	public function is_unavailable(): bool {
		return self::UNAVAILABLE === $this->status;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'status' => $this->status,
			'quotes' => $this->quotes,
			'error'  => $this->error,
		];
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function from_array( array $data, bool $from_cache ): self {
		return new self( (string) ( $data['status'] ?? self::EMPTY ), (array) ( $data['quotes'] ?? [] ), (string) ( $data['error'] ?? '' ), $from_cache );
	}
}
