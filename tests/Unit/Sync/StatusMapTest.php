<?php

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Tests\Unit\Sync;

use Fleetbase\WooCommerce\Sync\StatusMap;
use Fleetbase\WooCommerce\Sync\WebhookController;
use PHPUnit\Framework\TestCase;

final class StatusMapTest extends TestCase {

	private array $defaults = [ 'use_out_for_delivery' => true, 'on_completed' => 'completed', 'on_canceled' => 'note' ];

	public function test_targets_with_default_settings(): void {
		$this->assertNull( StatusMap::target( 'created', $this->defaults ) );
		$this->assertSame( 'out-for-delivery', StatusMap::target( 'dispatched', $this->defaults ) );
		$this->assertSame( 'out-for-delivery', StatusMap::target( 'Started', $this->defaults ) );
		$this->assertSame( 'out-for-delivery', StatusMap::target( 'enroute', $this->defaults ) );
		$this->assertSame( 'completed', StatusMap::target( 'completed', $this->defaults ) );
		$this->assertNull( StatusMap::target( 'canceled', $this->defaults ), 'default cancel handling is a note' );
		$this->assertNull( StatusMap::target( 'something_new', $this->defaults ) );
	}

	public function test_settings_change_targets(): void {
		$s = [ 'use_out_for_delivery' => false, 'on_completed' => 'note', 'on_canceled' => 'cancelled' ];
		$this->assertNull( StatusMap::target( 'dispatched', $s ) );
		$this->assertNull( StatusMap::target( 'completed', $s ) );
		$this->assertSame( 'cancelled', StatusMap::target( 'canceled', $s ) );
		$this->assertSame( 'cancelled', StatusMap::target( 'cancelled', $s ) );
	}

	public function test_transitions_never_go_backwards_or_reopen(): void {
		$this->assertTrue( StatusMap::may_transition( 'processing', 'out-for-delivery' ) );
		$this->assertTrue( StatusMap::may_transition( 'wc-processing', 'completed' ) );
		$this->assertTrue( StatusMap::may_transition( 'out-for-delivery', 'completed' ) );
		$this->assertTrue( StatusMap::may_transition( 'out-for-delivery', 'cancelled' ) );
		$this->assertFalse( StatusMap::may_transition( 'out-for-delivery', 'out-for-delivery' ) );
		$this->assertFalse( StatusMap::may_transition( 'completed', 'out-for-delivery' ) );
		$this->assertFalse( StatusMap::may_transition( 'completed', 'cancelled' ) );
		$this->assertFalse( StatusMap::may_transition( 'refunded', 'completed' ) );
		$this->assertFalse( StatusMap::may_transition( 'cancelled', 'completed' ) );
	}

	public function test_predicates_and_labels(): void {
		$this->assertTrue( StatusMap::is_open( 'created' ) );
		$this->assertTrue( StatusMap::is_open( 'dispatched' ) );
		$this->assertFalse( StatusMap::is_open( 'completed' ) );
		$this->assertFalse( StatusMap::is_open( 'canceled' ) );
		$this->assertSame( 'Delivered', StatusMap::label( 'completed' ) );
		$this->assertSame( 'Driver on the way', StatusMap::label( 'started' ) );
		$this->assertSame( 'Dispatch failed', StatusMap::label( 'dispatch_failed' ) );
	}

	public function test_webhook_order_id_extraction(): void {
		$this->assertSame( 'order_abc', WebhookController::order_id_from( [ 'id' => 'order_abc', 'status' => 'dispatched' ] ) );
		$this->assertSame( 'order_abc', WebhookController::order_id_from( [ 'id' => 'entity_1', 'order' => 'order_abc' ] ) );
		$this->assertSame( 'order_abc', WebhookController::order_id_from( [ 'id' => 'track_1', 'order' => [ 'id' => 'order_abc' ] ] ) );
		$this->assertSame( '', WebhookController::order_id_from( [ 'id' => 'driver_1' ] ) );
		$this->assertSame( '', WebhookController::order_id_from( [] ) );
	}
}
