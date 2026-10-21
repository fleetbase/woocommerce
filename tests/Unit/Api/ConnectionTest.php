<?php

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Tests\Unit\Api;

use Fleetbase\WooCommerce\Api\Connection;
use PHPUnit\Framework\TestCase;

final class ConnectionTest extends TestCase {

	public function test_cloud_detection_and_mode(): void {
		$c = Connection::cloud( 'flb_live_z2c4wXmikX0ykDUpzWfR' );
		$this->assertTrue( $c->is_cloud() );
		$this->assertSame( 'live', $c->mode() );
		$this->assertSame( 'https://api.fleetbase.io/v1', $c->base_url() );
		$this->assertSame( 'https://api.fleetbase.io/v1/orders', $c->url( '/orders' ) );
	}

	public function test_self_hosted_host_normalisation(): void {
		$c = new Connection( 'fleet.example.com/', 'flb_test_abcdefghijklmnopqrst' );
		$this->assertFalse( $c->is_cloud() );
		$this->assertTrue( $c->is_test_mode() );
		$this->assertSame( 'https://fleet.example.com', $c->host() );

		$local = new Connection( 'http://host.docker.internal:8000', 'flb_live_abcdefghijklmnopqrst' );
		$this->assertSame( 'http://host.docker.internal:8000/v1', $local->base_url() );

		$prefixed = new Connection( 'https://ops.example.com/fleetbase', 'flb_live_abcdefghijklmnopqrst' );
		$this->assertSame( 'https://ops.example.com/fleetbase/v1', $prefixed->base_url() );
	}

	public function test_key_validation(): void {
		$this->assertTrue( Connection::is_valid_key( 'flb_live_z2c4wXmikX0ykDUpzWfR' ) );
		$this->assertTrue( Connection::is_valid_key( ' flb_test_' . str_repeat( 'a', 32 ) . ' ' ) );
		$this->assertFalse( Connection::is_valid_key( 'sk_live_abc' ) );
		$this->assertFalse( Connection::is_valid_key( 'flb_live_' ) );
		$this->assertFalse( Connection::is_valid_key( 'flb_live_with-dash-chars-xx' ) );
	}

	public function test_masked_key_hides_middle(): void {
		$c = Connection::cloud( 'flb_live_z2c4wXmikX0ykDUpzWfR' );
		$this->assertSame( 'flb_live_****************zWfR', $c->masked_key() );
		$this->assertStringNotContainsString( 'wXmik', $c->masked_key() );
	}

	public function test_host_validation(): void {
		$this->assertTrue( Connection::is_valid_host( 'https://api.fleetbase.io' ) );
		$this->assertTrue( Connection::is_valid_host( 'localhost:8000' ) );
		$this->assertFalse( Connection::is_valid_host( 'ftp://x' ) );
		$this->assertFalse( Connection::is_valid_host( 'not a host' ) );
	}
}
