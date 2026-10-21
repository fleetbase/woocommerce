<?php

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Tests\Unit\Sync;

use Fleetbase\WooCommerce\Sync\OrderMapper;
use Fleetbase\WooCommerce\Sync\OrderSyncer;
use PHPUnit\Framework\TestCase;

final class OrderMapperTest extends TestCase {

	private function order( array $override = [] ): \WC_Order {
		$bread = new \WC_Product( [ 'id' => 15, 'sku' => 'WOO-BREAD', 'weight' => '0.9', 'length' => '30', 'width' => '15', 'height' => '10', 'short_description' => '<p>Fresh <b>daily</b></p>' ] );
		$gift  = new \WC_Product( [ 'id' => 99, 'virtual' => true ] );
		$data  = array_merge(
			[
				'id'                    => 21,
				'order_number'          => '21',
				'order_key'             => 'wc_order_abc',
				'status'                => 'processing',
				'currency'              => 'USD',
				'subtotal'              => '17.00',
				'shipping_total'        => '7.59',
				'total'                 => '24.59',
				'payment_method'        => 'bacs',
				'payment_method_title'  => 'Direct bank transfer',
				'customer_note'         => 'Ring the bell',
				'customer_id'           => 2,
				'billing_first_name'    => 'Test',
				'billing_last_name'     => 'Customer',
				'billing_email'         => 'Test@Example.test',
				'billing_phone'         => '+1 206 555 0100',
				'billing_address_1'     => '1 Billing Rd',
				'billing_city'          => 'Tacoma',
				'billing_state'         => 'WA',
				'billing_postcode'      => '98402',
				'billing_country'       => 'US',
				'shipping_first_name'   => 'Test',
				'shipping_last_name'    => 'Customer',
				'shipping_company'      => 'Acme',
				'shipping_address_1'    => '1124 Pike St',
				'shipping_address_2'    => 'Suite 4',
				'shipping_city'         => 'Seattle',
				'shipping_state'        => 'WA',
				'shipping_postcode'     => '98101',
				'shipping_country'      => 'us',
				'shipping_phone'        => '',
				'items'                 => [
					new \WC_Order_Item_Product( [ 'product' => $bread, 'quantity' => 2, 'name' => 'Sourdough Loaf', 'total' => '17.00', 'product_id' => 15 ] ),
					new \WC_Order_Item_Product( [ 'product' => $gift, 'quantity' => 1, 'name' => 'Gift card', 'total' => '10.00', 'product_id' => 99 ] ),
				],
				'shipping_methods'      => [
					new \WC_Order_Item_Shipping( [ 'method_id' => 'fleetbase', 'meta' => [ '_fleetbase_quote_id' => 'quote_abc', '_fleetbase_service_name' => 'Standard Delivery' ] ] ),
				],
				'meta'                  => [ '_fleetbase_scheduled_at' => '2026-10-21T11:00:00-07:00', '_fleetbase_delivery_label' => 'October 21, 11:00 am – 1:00 pm' ],
			],
			$override
		);
		return new \WC_Order( $data );
	}

	private function mapper( array $override = [] ): OrderMapper {
		return new OrderMapper(
			array_merge(
				[ 'pickup_place_id' => 'place_pickup', 'currency' => 'USD', 'weight_unit' => 'lbs', 'dimension_unit' => 'in', 'admin_url' => 'https://shop.test/wp-admin/', 'order_type' => 'transport', 'pod_required' => true, 'pod_method' => 'photo', 'auto_dispatch' => false ],
				$override
			)
		);
	}

	public function test_maps_a_full_order(): void {
		$request = $this->mapper()->map( $this->order() );

		$this->assertSame( '21', $request['internal_id'] );
		$this->assertSame( 'place_pickup', $request['payload']['pickup'] );
		$this->assertSame(
			[ 'street1' => '1124 Pike St', 'street2' => 'Suite 4', 'city' => 'Seattle', 'province' => 'WA', 'postal_code' => '98101', 'country' => 'US', 'name' => 'Test Customer (Acme)', 'phone' => '+1 206 555 0100' ],
			$request['payload']['dropoff']
		);
		$this->assertCount( 1, $request['payload']['entities'], 'virtual item dropped' );
		$entity = $request['payload']['entities'][0];
		$this->assertSame( 'Sourdough Loaf', $entity['name'] );
		$this->assertSame( 'WOO-BREAD', $entity['sku'] );
		$this->assertSame( 1700, $entity['price'] );
		$this->assertSame( 2, $entity['meta']['quantity'] );
		$this->assertSame( '8.50', $entity['meta']['unit_price'] );
		$this->assertSame( 0.9, $entity['weight'] );
		$this->assertSame( 'lb', $entity['weight_unit'] );
		$this->assertSame( 'in', $entity['dimensions_unit'] );
		$this->assertSame( 'Fresh daily', $entity['description'] );

		$this->assertSame( 'quote_abc', $request['service_quote'] );
		$this->assertSame( '2026-10-21T18:00:00Z', $request['scheduled_at'], 'sent as UTC because Fleetbase drops the offset' );
		$this->assertSame( 'Ring the bell', $request['notes'] );
		$this->assertTrue( $request['pod_required'] );
		$this->assertSame( 'photo', $request['pod_method'] );
		$this->assertFalse( $request['dispatch'] );
		$this->assertSame( 'transport', $request['type'] );

		$this->assertSame( 'Test Customer', $request['customer']['name'] );
		$this->assertSame( 'Test@Example.test', $request['customer']['email'] );
		$this->assertSame( 'wc_user_2', $request['customer']['internal_id'] );

		$this->assertSame( 'woocommerce', $request['meta']['source'] );
		$this->assertSame( 'https://shop.test/wp-admin/admin.php?page=wc-orders&action=edit&id=21', $request['meta']['admin_url'] );
		$this->assertSame( 'October 21, 11:00 am – 1:00 pm', $request['meta']['delivery_window'] );
		$this->assertSame( 'Standard Delivery', $request['meta']['service'] );
	}

	public function test_cached_contact_replaces_customer_object(): void {
		$request = $this->mapper( [ 'contact_id' => 'contact_123' ] )->map( $this->order() );
		$this->assertSame( 'contact_123', $request['customer'] );
	}

	public function test_billing_address_is_used_when_shipping_is_empty(): void {
		$request = $this->mapper()->map( $this->order( [ 'shipping_address_1' => '', 'shipping_postcode' => '', 'shipping_company' => '' ] ) );
		$this->assertSame( '1 Billing Rd', $request['payload']['dropoff']['street1'] );
		$this->assertSame( 'Tacoma', $request['payload']['dropoff']['city'] );
	}

	public function test_guest_customer_and_optional_fields_are_omitted(): void {
		$order   = $this->order( [ 'customer_id' => 0, 'customer_note' => '', 'meta' => [], 'shipping_methods' => [] ] );
		$request = $this->mapper( [ 'pod_required' => false, 'auto_dispatch' => true ] )->map( $order );
		$this->assertStringStartsWith( 'wc_guest_', $request['customer']['internal_id'] );
		$this->assertArrayNotHasKey( 'service_quote', $request );
		$this->assertArrayNotHasKey( 'scheduled_at', $request );
		$this->assertArrayNotHasKey( 'notes', $request );
		$this->assertArrayNotHasKey( 'pod_required', $request );
		$this->assertTrue( $request['dispatch'] );
	}

	public function test_cod_details_and_eligibility(): void {
		$mapper = $this->mapper();
		$this->assertNull( $mapper->cod( $this->order() ) );
		$this->assertSame( [ 'cod_amount' => 2459, 'cod_currency' => 'USD', 'cod_payment_method' => 'cash' ], $mapper->cod( $this->order( [ 'payment_method' => 'cod' ] ) ) );

		$this->assertTrue( $mapper->is_deliverable( $this->order() ) );
		$virtual_only = $this->order( [ 'items' => [ new \WC_Order_Item_Product( [ 'product' => new \WC_Product( [ 'virtual' => true ] ), 'quantity' => 1, 'name' => 'Gift', 'total' => '10' ] ) ] ] );
		$this->assertFalse( $mapper->is_deliverable( $virtual_only ) );

		$this->assertTrue( OrderMapper::uses_fleetbase_shipping( $this->order() ) );
		$this->assertFalse( OrderMapper::uses_fleetbase_shipping( $this->order( [ 'shipping_methods' => [ new \WC_Order_Item_Shipping( [ 'method_id' => 'flat_rate' ] ) ] ] ) ) );
	}

	public function test_to_utc(): void {
		$this->assertSame( '2026-10-21T18:00:00Z', OrderMapper::to_utc( '2026-10-21T11:00:00-07:00' ) );
		$this->assertSame( '2026-10-21T18:00:00Z', OrderMapper::to_utc( '2026-10-21T18:00:00+00:00' ) );
		$this->assertSame( '', OrderMapper::to_utc( '' ) );
		$this->assertSame( '', OrderMapper::to_utc( 'not a date' ) );
	}

	public function test_coordinate_check(): void {
		$this->assertTrue( OrderSyncer::has_coordinates( [ 'payload' => [ 'dropoff' => [ 'location' => [ 'type' => 'Point', 'coordinates' => [ -122.33, 47.6 ] ] ] ] ] ) );
		$this->assertFalse( OrderSyncer::has_coordinates( [ 'payload' => [ 'dropoff' => [ 'location' => [ 'type' => 'Point', 'coordinates' => [ 0, 0 ] ] ] ] ] ) );
		$this->assertTrue( OrderSyncer::has_coordinates( [ 'payload' => [] ] ), 'no dropoff information means nothing to judge' );
	}
}
