<?php

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Tests\Unit\Api;

use Fleetbase\WooCommerce\Api\Client;
use Fleetbase\WooCommerce\Api\Connection;
use Fleetbase\WooCommerce\Api\Exception\AuthenticationException;
use Fleetbase\WooCommerce\Api\Exception\DecodingException;
use Fleetbase\WooCommerce\Api\Exception\RateLimitException;
use Fleetbase\WooCommerce\Api\Exception\ServerException;
use Fleetbase\WooCommerce\Api\Exception\TransportException;
use Fleetbase\WooCommerce\Api\Exception\ValidationException;
use Fleetbase\WooCommerce\Tests\Support\ArrayLogger;
use Fleetbase\WooCommerce\Tests\Support\FakeTransport;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase {

	private FakeTransport $transport;
	private ArrayLogger $logger;
	/** @var array<int, float> */
	private array $sleeps = [];

	protected function setUp(): void {
		$this->transport = new FakeTransport();
		$this->logger    = new ArrayLogger();
		$this->sleeps    = [];
	}

	private function client( array $options = [] ): Client {
		$options['sleep'] = function ( float $seconds ): void {
			$this->sleeps[] = $seconds;
		};
		return new Client( new Connection( 'http://localhost:8000', 'flb_test_abcdefghijklmnopqrstuvwxyz' ), $this->transport, $this->logger, $options );
	}

	public function test_get_sends_bearer_and_query(): void {
		$this->transport->queue( 200, [ [ 'id' => 'order_1' ] ] );
		$result = $this->client()->get( 'orders', [ 'limit' => 5, 'single' => true, 'empty' => '', 'skip' => null ] );

		$req = $this->transport->last();
		$this->assertSame( 'GET', $req['method'] );
		$this->assertSame( 'http://localhost:8000/v1/orders?limit=5&single=1', $req['url'] );
		$this->assertSame( 'Bearer flb_test_abcdefghijklmnopqrstuvwxyz', $req['headers']['Authorization'] );
		$this->assertSame( 'application/json', $req['headers']['Accept'] );
		$this->assertNull( $req['body'] );
		$this->assertSame( [ [ 'id' => 'order_1' ] ], $result );
	}

	public function test_nested_query_is_encoded_for_laravel(): void {
		$this->transport->queue( 200, [] );
		$this->client()->get( 'service-quotes', [ 'dropoff' => [ 'street1' => '1 Main St', 'city' => 'Seattle' ] ] );
		$this->assertSame(
			'http://localhost:8000/v1/service-quotes?dropoff%5Bstreet1%5D=1%20Main%20St&dropoff%5Bcity%5D=Seattle',
			$this->transport->last()['url']
		);
	}

	public function test_post_encodes_json_body(): void {
		$this->transport->queue( 201, [ 'id' => 'order_2' ] );
		$result = $this->client()->post( 'orders', [ 'pickup' => 'place_1', 'dispatch' => false ] );

		$req = $this->transport->last();
		$this->assertSame( 'POST', $req['method'] );
		$this->assertSame( 'application/json', $req['headers']['Content-Type'] );
		$this->assertSame( '{"pickup":"place_1","dispatch":false}', $req['body'] );
		$this->assertSame( [ 'id' => 'order_2' ], $result );
	}

	public function test_422_becomes_validation_exception_with_errors(): void {
		$this->transport->queue( 422, [ 'errors' => [ 'The pickup field is required.', 'The dropoff field is required.' ] ] );
		try {
			$this->client()->post( 'orders', [] );
			$this->fail( 'Expected ValidationException' );
		} catch ( ValidationException $e ) {
			$this->assertSame( 422, $e->status() );
			$this->assertSame( 'The pickup field is required.', $e->getMessage() );
			$this->assertCount( 2, $e->errors() );
		}
	}

	public function test_error_key_shape_is_supported(): void {
		$this->transport->queue( 401, [ 'error' => 'Invalid API key.' ] );
		$this->expectException( AuthenticationException::class );
		$this->expectExceptionMessage( 'Invalid API key.' );
		$this->client()->get( 'organizations/current' );
	}

	public function test_429_is_retried_after_retry_after_header(): void {
		$this->transport->queue( 429, [ 'error' => 'Too many' ], [ 'Retry-After' => '2' ] )->queue( 200, [ 'ok' => true ] );
		$result = $this->client()->get( 'drivers' );
		$this->assertSame( [ 'ok' => true ], $result );
		$this->assertSame( [ 2.0 ], $this->sleeps );
		$this->assertCount( 2, $this->transport->requests );
	}

	public function test_429_gives_up_after_retries(): void {
		$this->transport->queue( 429, '', [ 'Retry-After' => '1' ] )->queue( 429, '', [ 'Retry-After' => '7' ] );
		try {
			$this->client( [ 'retries' => 1 ] )->get( 'drivers' );
			$this->fail( 'Expected RateLimitException' );
		} catch ( RateLimitException $e ) {
			$this->assertSame( 7, $e->retry_after() );
		}
	}

	public function test_server_error_retried_for_get_but_not_post(): void {
		$this->transport->queue( 503, '' )->queue( 200, [ 'ok' => 1 ] );
		$this->assertSame( [ 'ok' => 1 ], $this->client()->get( 'vehicles' ) );
		$this->assertSame( [ 0.5 ], $this->sleeps );

		$this->transport->queue( 500, [ 'error' => 'boom' ] );
		$this->expectException( ServerException::class );
		$this->client()->post( 'orders', [ 'x' => 1 ] );
	}

	public function test_post_can_opt_in_to_retries(): void {
		$this->transport->queue_failure()->queue( 200, [ 'ok' => 1 ] );
		$this->assertSame( [ 'ok' => 1 ], $this->client()->post( 'payloads', [], [ 'idempotent' => true ] ) );
	}

	public function test_transport_failure_surfaces_for_post(): void {
		$this->transport->queue_failure( 'cURL error 28' );
		$this->expectException( TransportException::class );
		$this->client()->post( 'orders', [] );
	}

	public function test_invalid_json_raises_decoding_exception(): void {
		$this->transport->queue( 200, '<html>oops</html>' );
		$this->expectException( DecodingException::class );
		$this->client()->get( 'orders' );
	}

	public function test_empty_body_returns_null(): void {
		$this->transport->queue( 204, '' );
		$this->assertNull( $this->client()->delete( 'orders/order_1' ) );
	}

	public function test_debug_log_never_contains_key(): void {
		$this->transport->queue( 200, [] );
		$this->client()->get( 'orders' );
		$this->assertNotEmpty( $this->logger->entries );
		foreach ( $this->logger->entries as $entry ) {
			$this->assertStringNotContainsString( 'abcdefghijklmnopqrstuvwxyz', json_encode( $entry ) );
		}
	}
}
