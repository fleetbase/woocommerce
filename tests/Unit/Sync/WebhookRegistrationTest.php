<?php

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Tests\Unit\Sync;

use Fleetbase\WooCommerce\Api\Client;
use Fleetbase\WooCommerce\Api\Connection;
use Fleetbase\WooCommerce\Api\Exception\ValidationException;
use Fleetbase\WooCommerce\Api\Fleetbase;
use Fleetbase\WooCommerce\Sync\WebhookRegistration;
use Fleetbase\WooCommerce\Tests\Support\FakeTransport;
use PHPUnit\Framework\TestCase;

final class WebhookRegistrationTest extends TestCase {

	private const URL = 'https://shop.example.com/wp-json/fleetbase/v1/webhook/token123token123';

	private FakeTransport $transport;

	protected function setUp(): void {
		$this->transport = new FakeTransport();
	}

	private function registration(): WebhookRegistration {
		$client = new Client(
			new Connection( 'https://api.fleetbase.io', 'flb_live_abcdefghijklmnopqrstuvwxyz' ),
			$this->transport,
			null,
			[
				'retries' => 0,
				'sleep'   => static function (): void {},
			]
		);
		return new WebhookRegistration( new Fleetbase( $client ) );
	}

	/**
	 * @return array<int, array{method:string, url:string, body:mixed}>
	 */
	private function calls(): array {
		return array_map(
			static fn( array $r ): array => [
				'method' => $r['method'],
				'url'    => $r['url'],
				'body'   => null === $r['body'] ? null : json_decode( (string) $r['body'], true ),
			],
			$this->transport->requests
		);
	}

	public function test_creates_an_endpoint_for_the_order_events(): void {
		$this->transport->queue( 200, [] );
		$this->transport->queue(
			201,
			[
				'id'     => 'ep-1',
				'url'    => self::URL,
				'secret' => 'whsec_1',
			]
		);

		$result = $this->registration()->sync( self::URL, 'Fleetbase for WooCommerce (shop.example.com)' );

		$this->assertSame(
			[
				'id'     => 'ep-1',
				'secret' => 'whsec_1',
				'url'    => self::URL,
			],
			$result
		);
		$calls = $this->calls();
		$this->assertSame( 'GET', $calls[0]['method'] );
		$this->assertSame( 'https://api.fleetbase.io/v1/webhook-endpoints?url=' . rawurlencode( self::URL ), $calls[0]['url'] );
		$this->assertSame( 'POST', $calls[1]['method'] );
		$this->assertSame( 'https://api.fleetbase.io/v1/webhook-endpoints', $calls[1]['url'] );
		$this->assertSame( self::URL, $calls[1]['body']['url'] );
		$this->assertSame( WebhookRegistration::EVENTS, $calls[1]['body']['events'] );
		$this->assertSame( 'enabled', $calls[1]['body']['status'] );
	}

	public function test_keeps_its_own_endpoint_without_touching_it(): void {
		$this->transport->queue(
			200,
			[
				[
					'id'     => 'ep-1',
					'url'    => self::URL,
					'status' => 'enabled',
				],
			]
		);

		$result = $this->registration()->sync(
			self::URL,
			'x',
			[
				'id'     => 'ep-1',
				'secret' => 'whsec_1',
				'url'    => self::URL,
			]
		);

		$this->assertSame( 'whsec_1', $result['secret'] );
		$this->assertCount( 1, $this->transport->requests );
	}

	public function test_adopts_an_existing_endpoint_by_rotating_its_secret_and_enabling_it(): void {
		$this->transport->queue(
			200,
			[
				[
					'id'     => 'ep-9',
					'url'    => self::URL,
					'status' => 'disabled',
				],
			]
		);
		$this->transport->queue(
			200,
			[
				'id'     => 'ep-9',
				'status' => 'enabled',
			]
		);
		$this->transport->queue(
			200,
			[
				'id'     => 'ep-9',
				'secret' => 'whsec_rotated',
			]
		);

		$result = $this->registration()->sync( self::URL, 'x' );

		$this->assertSame(
			[
				'id'     => 'ep-9',
				'secret' => 'whsec_rotated',
				'url'    => self::URL,
			],
			$result
		);
		$calls = $this->calls();
		$this->assertSame( [ 'PUT', 'https://api.fleetbase.io/v1/webhook-endpoints/ep-9' ], [ $calls[1]['method'], $calls[1]['url'] ] );
		$this->assertSame( [ 'status' => 'enabled' ], $calls[1]['body'] );
		$this->assertSame( [ 'POST', 'https://api.fleetbase.io/v1/webhook-endpoints/ep-9/rotate-secret' ], [ $calls[2]['method'], $calls[2]['url'] ] );
	}

	public function test_a_new_url_replaces_the_old_endpoint(): void {
		$this->transport->queue( 404, [ 'error' => 'Webhook endpoint not found.' ] );
		$this->transport->queue( 200, [] );
		$this->transport->queue(
			201,
			[
				'id'     => 'ep-2',
				'secret' => 'whsec_2',
			]
		);

		$result = $this->registration()->sync(
			self::URL,
			'x',
			[
				'id'     => 'ep-1',
				'secret' => 'whsec_1',
				'url'    => 'https://shop.example.com/wp-json/fleetbase/v1/webhook/oldtokenoldtoken',
			]
		);

		$this->assertSame( 'ep-2', $result['id'] );
		$this->assertSame( [ 'DELETE', 'https://api.fleetbase.io/v1/webhook-endpoints/ep-1' ], [ $this->calls()[0]['method'], $this->calls()[0]['url'] ] );
	}

	public function test_falls_back_to_all_events_when_the_server_names_them_differently(): void {
		$this->transport->queue( 200, [] );
		$this->transport->queue(
			422,
			[
				'message' => 'Unknown webhook event: waypoint.activity.',
				'errors'  => [ 'events.8' => [ 'Unknown webhook event: waypoint.activity.' ] ],
			]
		);
		$this->transport->queue(
			201,
			[
				'id'     => 'ep-3',
				'secret' => 'whsec_3',
			]
		);

		$result = $this->registration()->sync( self::URL, 'x' );

		$this->assertSame( 'ep-3', $result['id'] );
		$this->assertArrayNotHasKey( 'events', $this->calls()[2]['body'] );
	}

	public function test_a_url_fleetbase_cannot_reach_is_reported(): void {
		$this->transport->queue( 200, [] );
		$this->transport->queue(
			422,
			[
				'message' => 'The url must be a public HTTP or HTTPS URL.',
				'errors'  => [ 'url' => [ 'The url must be a public HTTP or HTTPS URL.' ] ],
			]
		);

		$this->expectException( ValidationException::class );
		$this->expectExceptionMessage( 'The url must be a public HTTP or HTTPS URL.' );
		$this->registration()->sync( 'http://localhost:8800/wp-json/fleetbase/v1/webhook/token123token123', 'x' );
	}

	public function test_remove_ignores_an_endpoint_that_is_already_gone(): void {
		$this->transport->queue( 404, [ 'error' => 'Webhook endpoint not found.' ] );

		$this->registration()->remove( 'ep-1' );

		$this->assertSame( 'DELETE', $this->transport->last()['method'] );
	}
}
