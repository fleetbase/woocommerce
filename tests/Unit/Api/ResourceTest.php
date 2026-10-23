<?php

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Tests\Unit\Api;

use Fleetbase\WooCommerce\Api\Client;
use Fleetbase\WooCommerce\Api\Connection;
use Fleetbase\WooCommerce\Api\Fleetbase;
use Fleetbase\WooCommerce\Tests\Support\FakeTransport;
use PHPUnit\Framework\TestCase;

final class ResourceTest extends TestCase {

	private FakeTransport $transport;
	private Fleetbase $api;

	protected function setUp(): void {
		$this->transport = new FakeTransport();
		$client          = new Client( Connection::cloud( 'flb_live_abcdefghijklmnopqrst' ), $this->transport, null, [ 'sleep' => static fn() => null ] );
		$this->api       = new Fleetbase( $client );
	}

	public function test_order_create_uses_fixture_shape(): void {
		$fixture = json_decode( (string) file_get_contents( __DIR__ . '/../../fixtures/order.create.response.json' ), true );
		$this->transport->queue( 201, $fixture );

		$order = $this->api->orders()->create( [ 'pickup' => 'place_1', 'dropoff' => 'place_2' ] );
		$this->assertSame( 'order_7YqM3KpL2n', $order['id'] );
		$this->assertSame( 'FB-1001', $order['tracking_number']['tracking_number'] );
		$this->assertSame( 'https://api.fleetbase.io/v1/orders', $this->transport->last()['url'] );
	}

	public function test_order_actions_hit_expected_paths(): void {
		$this->transport->queue( 200, [] )->queue( 200, [] )->queue( 200, [] )->queue( 200, [] );
		$this->api->orders()->dispatch( 'order_1' );
		$this->assertSame( [ 'PATCH', 'https://api.fleetbase.io/v1/orders/order_1/dispatch' ], [ $this->transport->last()['method'], $this->transport->last()['url'] ] );

		$this->api->orders()->cancel( 'order_1', 'Customer refunded' );
		$this->assertSame( 'https://api.fleetbase.io/v1/orders/order_1/cancel?reason=Customer%20refunded', $this->transport->last()['url'] );
		$this->assertSame( 'DELETE', $this->transport->last()['method'] );

		$this->api->orders()->schedule( 'order_1', '2026-11-01', '14:00', 'America/Los_Angeles' );
		$this->assertSame( '{"date":"2026-11-01","time":"14:00","timezone":"America\/Los_Angeles"}', $this->transport->last()['body'] );

		$this->api->orders()->proofs( 'order_1' );
		$this->assertSame( 'https://api.fleetbase.io/v1/orders/order_1/proofs', $this->transport->last()['url'] );
	}

	public function test_service_quotes_never_send_currency(): void {
		$this->transport->queue( 200, [] );
		$this->api->service_quotes()->query( [ 'pickup' => 'place_1', 'dropoff' => 'place_2', 'currency' => 'USD', 'single' => true ] );
		$this->assertStringNotContainsString( 'currency', $this->transport->last()['url'] );
		$this->assertStringContainsString( 'single=1', $this->transport->last()['url'] );
	}

	public function test_places_search_builds_query(): void {
		$this->transport->queue( 200, [ [ 'id' => 'place_x' ] ] );
		$results = $this->api->places()->search( '1 Main St', true, 47.6, -122.3 );
		$this->assertSame( [ [ 'id' => 'place_x' ] ], $results );
		$this->assertSame( 'https://api.fleetbase.io/v1/places/search?query=1%20Main%20St&geo=1&latitude=47.6&longitude=-122.3&limit=10', $this->transport->last()['url'] );
	}

	public function test_each_pages_until_short_page(): void {
		$this->transport->queue( 200, [ [ 'id' => 'a' ], [ 'id' => 'b' ] ] )->queue( 200, [ [ 'id' => 'c' ] ] );
		$ids = [];
		foreach ( $this->api->drivers()->each( [], 2 ) as $driver ) {
			$ids[] = $driver['id'];
		}
		$this->assertSame( [ 'a', 'b', 'c' ], $ids );
		$this->assertCount( 2, $this->transport->requests );
		$this->assertStringContainsString( 'page=2', $this->transport->last()['url'] );
	}

	public function test_organizations_current(): void {
		$this->transport->queue( 200, [ 'id' => 'company_1', 'name' => 'Woo Test Store' ] );
		$this->assertSame( 'Woo Test Store', $this->api->organizations()->current()['name'] );
		$this->assertSame( 'https://api.fleetbase.io/v1/organizations/current', $this->transport->last()['url'] );
	}
}
