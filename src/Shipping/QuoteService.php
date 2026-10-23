<?php
/**
 * Asks Fleetbase for delivery quotes and caches them. Every uncached call persists
 * quote rows on the merchant's Fleetbase account, so the cache is not optional.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Shipping;

use Fleetbase\WooCommerce\Api\Exception\ApiException;
use Fleetbase\WooCommerce\Api\Fleetbase;
use Fleetbase\WooCommerce\Api\Money;
use Fleetbase\WooCommerce\Support\Address;
use Fleetbase\WooCommerce\Support\LoggerInterface;

final class QuoteService {

	public const RATES_TTL = 3600;

	/** @var array{pickup_place_id:string, store_currency:string, ttl:int, cache_version:string, timeout:int} */
	private array $options;

	/**
	 * @param array<string, mixed> $options pickup_place_id, store_currency, ttl (s), cache_version, timeout (s)
	 */
	public function __construct(
		private Fleetbase $api,
		private QuoteCache $cache,
		array $options,
		private ?LoggerInterface $logger = null
	) {
		$this->options = [
			'pickup_place_id' => (string) ( $options['pickup_place_id'] ?? '' ),
			'store_currency'  => strtoupper( (string) ( $options['store_currency'] ?? '' ) ),
			'ttl'             => (int) ( $options['ttl'] ?? 600 ),
			'cache_version'   => (string) ( $options['cache_version'] ?? '1' ),
			'timeout'         => (int) ( $options['timeout'] ?? 15 ),
		];
	}

	/**
	 * @param array<string, mixed> $destination WooCommerce destination array.
	 * @param array<string, mixed> $filter      service_type, single, scheduled_at, weight_kg
	 */
	public function quotes( array $destination, array $filter = [] ): QuoteResult {
		if ( '' === $this->options['pickup_place_id'] ) {
			return QuoteResult::unavailable( 'No pickup location is configured.' );
		}
		if ( ! Address::is_quotable( $destination ) ) {
			return QuoteResult::ok( [] );
		}

		$key    = $this->cache_key( $destination, $filter );
		$cached = $this->cache->get( $key );
		if ( null !== $cached ) {
			return QuoteResult::from_array( $cached, true );
		}

		$params = [
			'pickup'  => $this->options['pickup_place_id'],
			'dropoff' => Address::to_place( $destination ),
		];
		if ( ! empty( $filter['service_type'] ) ) {
			$params['service_type'] = (string) $filter['service_type'];
		}
		if ( ! empty( $filter['single'] ) ) {
			$params['single'] = true;
		}
		if ( ! empty( $filter['scheduled_at'] ) ) {
			$params['scheduled_at'] = (string) $filter['scheduled_at'];
		}

		try {
			$raw = $this->api->client()->get(
				'service-quotes',
				$params,
				[
					'timeout' => $this->options['timeout'],
					'retries' => 0,
				]
			);
		} catch ( ApiException $e ) {
			$this->log(
				'warning',
				'Quote request failed',
				[
					'error'  => $e->getMessage(),
					'status' => $e->status(),
				]
			);
			return QuoteResult::unavailable( $e->getMessage() );
		}

		$quotes = $this->normalize( is_array( $raw ) ? $raw : [] );
		$result = QuoteResult::ok( $quotes );
		$this->cache->set( $key, $result->to_array(), $this->options['ttl'] );
		$this->log(
			'debug',
			'Quotes fetched',
			[
				'count'     => count( $quotes ),
				'cache_key' => $key,
			]
		);

		return $result;
	}

	/**
	 * @param array<string, mixed> $destination
	 * @param array<string, mixed> $filter
	 */
	public function cache_key( array $destination, array $filter = [] ): string {
		$weight = isset( $filter['weight_kg'] ) ? (int) ceil( (float) $filter['weight_kg'] ) : 0;
		$parts  = [
			'v' . $this->options['cache_version'],
			$this->options['pickup_place_id'],
			$this->options['store_currency'],
			Address::fingerprint( $destination ),
			'st=' . strtolower( (string) ( $filter['service_type'] ?? '' ) ),
			'single=' . ( empty( $filter['single'] ) ? '0' : '1' ),
			'at=' . (string) ( $filter['scheduled_at'] ?? '' ),
			'w=' . $weight,
		];
		return md5( implode( '|', $parts ) );
	}

	/**
	 * Keep quotes in the store currency and attach description and delivery time from the rate.
	 *
	 * @param array<int, mixed> $raw
	 * @return array<int, array<string, mixed>>
	 */
	private function normalize( array $raw ): array {
		$raw    = array_values( array_filter( $raw, 'is_array' ) );
		$rates  = [] === $raw ? [] : $this->rates_index();
		$quotes = [];
		foreach ( $raw as $quote ) {
			if ( ! is_array( $quote ) || empty( $quote['id'] ) ) {
				continue;
			}
			$currency = strtoupper( (string) ( $quote['currency'] ?? '' ) );
			if ( '' !== $this->options['store_currency'] && $currency !== $this->options['store_currency'] ) {
				$this->log(
					'warning',
					'Quote skipped: currency does not match the store',
					[
						'quote'    => $quote['id'],
						'currency' => $currency,
					]
				);
				continue;
			}
			$rate_id = (string) ( $quote['service_rate'] ?? '' );
			$rate    = $rates[ $rate_id ] ?? [];
			$amount  = (int) ( $quote['amount'] ?? 0 );

			$quotes[] = [
				'id'              => (string) $quote['id'],
				'request_id'      => (string) ( $quote['request_id'] ?? '' ),
				'service_rate_id' => $rate_id,
				'service_name'    => (string) ( $quote['service_name'] ?? $quote['service_rate_name'] ?? $rate['service_name'] ?? 'Delivery' ),
				'service_type'    => (string) ( $rate['service_type'] ?? '' ),
				'amount'          => $amount,
				'currency'        => $currency,
				'cost'            => Money::from_minor( $amount, $currency ),
				'description'     => (string) ( $rate['duration_terms'] ?? '' ),
				'estimated_days'  => isset( $rate['estimated_days'] ) ? (int) $rate['estimated_days'] : null,
				'items'           => array_values(
					array_map(
						static function ( $item ): array {
							return [
								'code'    => (string) ( $item['code'] ?? '' ),
								'details' => (string) ( $item['details'] ?? '' ),
								'amount'  => (int) ( $item['amount'] ?? 0 ),
							];
						},
						array_filter( (array) ( $quote['items'] ?? [] ), 'is_array' )
					)
				),
			];
		}

		usort(
			$quotes,
			static function ( array $a, array $b ): int {
				return $a['amount'] <=> $b['amount'];
			}
		);

		return $quotes;
	}

	/**
	 * Service rates keyed by id, cached for an hour. Quotes only carry the rate id.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function rates_index(): array {
		$key    = 'rates_' . md5( $this->options['pickup_place_id'] . '|' . $this->options['cache_version'] );
		$cached = $this->cache->get( $key );
		if ( null !== $cached ) {
			return $cached;
		}
		$index = [];
		try {
			foreach ( $this->api->service_rates()->all( [ 'limit' => 100 ] ) as $rate ) {
				if ( ! empty( $rate['id'] ) ) {
					$index[ (string) $rate['id'] ] = [
						'service_name'   => (string) ( $rate['service_name'] ?? '' ),
						'service_type'   => (string) ( $rate['service_type'] ?? '' ),
						'currency'       => (string) ( $rate['currency'] ?? '' ),
						'duration_terms' => (string) ( $rate['duration_terms'] ?? '' ),
						'estimated_days' => isset( $rate['estimated_days'] ) ? (int) $rate['estimated_days'] : null,
					];
				}
			}
		} catch ( ApiException $e ) {
			$this->log( 'warning', 'Could not load service rates for quote descriptions', [ 'error' => $e->getMessage() ] );
			return [];
		}
		$this->cache->set( $key, $index, self::RATES_TTL );
		return $index;
	}

	/**
	 * @param array<string, mixed> $context
	 */
	private function log( string $level, string $message, array $context ): void {
		if ( null !== $this->logger ) {
			$this->logger->log( $level, $message, $context );
		}
	}
}
