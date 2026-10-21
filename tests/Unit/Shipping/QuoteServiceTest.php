<?php

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Tests\Unit\Shipping;

use Fleetbase\WooCommerce\Api\Client;
use Fleetbase\WooCommerce\Api\Connection;
use Fleetbase\WooCommerce\Api\Fleetbase;
use Fleetbase\WooCommerce\Shipping\ArrayQuoteCache;
use Fleetbase\WooCommerce\Shipping\QuoteResult;
use Fleetbase\WooCommerce\Shipping\QuoteService;
use Fleetbase\WooCommerce\Tests\Support\ArrayLogger;
use Fleetbase\WooCommerce\Tests\Support\FakeTransport;
use PHPUnit\Framework\TestCase;

final class QuoteServiceTest extends TestCase {

	private FakeTransport $transport;
	private ArrayQuoteCache $cache;
	private ArrayLogger $logger;

	/** @var array<string, mixed> */
	private array $seattle = [
		'address_1' => '1124 Pike St',
		'address_2' => '',
		'city'      => 'Seattle',
		'state'     => 'WA',
		'postcode'  => '98101',
		'country'   => 'US',
	];

	protected function setUp(): void {
		$this->transport = new FakeTransport();
		$this->cache     = new ArrayQuoteCache();
		$this->logger    = new ArrayLogger();
	}

	private function service( array $options = [] ): QuoteService {
		$client = new Client( new Connection( 'http://localhost:8000', 'flb_test_abcdefghijklmnopqrstuvwxyz' ), $this->transport, $this->logger, [ 'sleep' => static fn() => null ] );
		return new QuoteService(
			new Fleetbase( $client ),
			$this->cache,
			array_merge( [ 'pickup_place_id' => 'place_pickup', 'store_currency' => 'USD', 'ttl' => 600, 'cache_version' => '7' ], $options ),
			$this->logger
		);
	}

	private function fixture( string $name ): array {
		return json_decode( (string) file_get_contents( __DIR__ . '/../../fixtures/' . $name ), true );
	}

	private function queue_quotes_and_rates(): void {
		$this->transport->queue( 200, $this->fixture( 'service-quotes.response.json' ) )->queue( 200, $this->fixture( 'service-rates.response.json' ) );
	}

	public function test_quotes_are_normalised_sorted_and_filtered_to_store_currency(): void {
		$this->queue_quotes_and_rates();
		$result = $this->service()->quotes( $this->seattle );

		$this->assertSame( QuoteResult::OK, $result->status );
		$this->assertCount( 2, $result->quotes, 'SGD quote dropped' );
		$this->assertSame( [ 'quote_73s6jrcv9q', 'quote_meqmgrsrlw' ], array_column( $result->quotes, 'id' ) );
		$this->assertSame( 7.58, $result->quotes[0]['cost'] );
		$this->assertSame( 'Same day, 2-4 hours', $result->quotes[0]['description'] );
		$this->assertSame( 'standard', $result->quotes[0]['service_type'] );
		$this->assertSame( 0, $result->quotes[0]['estimated_days'] );
		$this->assertSame( 'BASE_FEE', $result->quotes[0]['items'][0]['code'] );

		$quote_request = $this->transport->requests[0];
		$this->assertStringContainsString( 'pickup=place_pickup', $quote_request['url'] );
		$this->assertStringContainsString( 'dropoff%5Bstreet1%5D=1124%20Pike%20St', $quote_request['url'] );
		$this->assertStringContainsString( 'dropoff%5Bcountry%5D=US', $quote_request['url'] );
		$this->assertStringNotContainsString( 'currency', $quote_request['url'] );
		$this->assertStringNotContainsString( 'street2', $quote_request['url'], 'empty fields are omitted' );
	}

	public function test_second_call_is_served_from_cache_without_hitting_the_api(): void {
		$this->queue_quotes_and_rates();
		$service = $this->service();
		$first   = $service->quotes( $this->seattle );
		$second  = $service->quotes( array_merge( $this->seattle, [ 'address_1' => ' 1124  pike st ' ] ) );

		$this->assertFalse( $first->from_cache );
		$this->assertTrue( $second->from_cache, 'whitespace and case differences hit the same key' );
		$this->assertSame( $first->quotes, $second->quotes );
		$this->assertCount( 2, $this->transport->requests, 'one quote call and one rates call' );
	}

	public function test_cache_key_changes_with_inputs_that_change_the_price(): void {
		$service = $this->service();
		$base    = $service->cache_key( $this->seattle );
		$this->assertNotSame( $base, $service->cache_key( array_merge( $this->seattle, [ 'postcode' => '98109' ] ) ) );
		$this->assertNotSame( $base, $service->cache_key( $this->seattle, [ 'service_type' => 'express' ] ) );
		$this->assertNotSame( $base, $service->cache_key( $this->seattle, [ 'scheduled_at' => '2026-11-01 14:00' ] ) );
		$this->assertNotSame( $base, $service->cache_key( $this->seattle, [ 'single' => true ] ) );
		$this->assertNotSame( $base, $service->cache_key( $this->seattle, [ 'weight_kg' => 12.4 ] ) );
		$this->assertSame( $service->cache_key( $this->seattle, [ 'weight_kg' => 12.4 ] ), $service->cache_key( $this->seattle, [ 'weight_kg' => 12.9 ] ), 'weights are banded to whole kilograms' );
		$this->assertNotSame( $base, $this->service( [ 'cache_version' => '8' ] )->cache_key( $this->seattle ) );
		$this->assertNotSame( $base, $this->service( [ 'pickup_place_id' => 'place_other' ] )->cache_key( $this->seattle ) );
	}

	public function test_empty_result_is_cached_too(): void {
		$this->transport->queue( 200, [] );
		$service = $this->service();
		$this->assertTrue( $service->quotes( $this->seattle )->is_empty() );
		$this->assertTrue( $service->quotes( $this->seattle )->is_empty() );
		$this->assertCount( 1, $this->transport->requests );
	}

	public function test_api_failure_is_unavailable_and_not_cached(): void {
		$this->transport->queue( 503, '' )->queue( 200, [] );
		$service = $this->service();
		$first   = $service->quotes( $this->seattle );
		$this->assertTrue( $first->is_unavailable() );
		$this->assertSame( 0, $this->cache->count() );
		$this->assertTrue( $service->quotes( $this->seattle )->is_empty(), 'next call retries the API' );
		$this->assertContains( 'warning', array_column( $this->logger->entries, 'level' ) );
	}

	public function test_transport_failure_is_unavailable(): void {
		$this->transport->queue_failure( 'cURL error 28: timeout' );
		$result = $this->service()->quotes( $this->seattle );
		$this->assertTrue( $result->is_unavailable() );
		$this->assertStringContainsString( 'timeout', $result->error );
	}

	public function test_unquotable_address_short_circuits(): void {
		$result = $this->service()->quotes( [ 'country' => 'US' ] );
		$this->assertTrue( $result->is_empty() );
		$this->assertCount( 0, $this->transport->requests );
	}

	public function test_missing_pickup_is_unavailable(): void {
		$result = $this->service( [ 'pickup_place_id' => '' ] )->quotes( $this->seattle );
		$this->assertTrue( $result->is_unavailable() );
		$this->assertCount( 0, $this->transport->requests );
	}

	public function test_filters_are_passed_through(): void {
		$this->queue_quotes_and_rates();
		$this->service()->quotes( $this->seattle, [ 'service_type' => 'express', 'single' => true, 'scheduled_at' => '2026-11-01 14:00:00' ] );
		$url = $this->transport->requests[0]['url'];
		$this->assertStringContainsString( 'service_type=express', $url );
		$this->assertStringContainsString( 'single=1', $url );
		$this->assertStringContainsString( 'scheduled_at=2026-11-01%2014%3A00%3A00', $url );
	}

	public function test_rates_index_is_reused_across_quotes(): void {
		$this->transport->queue( 200, $this->fixture( 'service-quotes.response.json' ) )->queue( 200, $this->fixture( 'service-rates.response.json' ) )->queue( 200, $this->fixture( 'service-quotes.response.json' ) );
		$service = $this->service();
		$service->quotes( $this->seattle );
		$service->quotes( array_merge( $this->seattle, [ 'postcode' => '98109' ] ) );
		$this->assertCount( 3, $this->transport->requests, 'rates fetched once' );
	}
}
