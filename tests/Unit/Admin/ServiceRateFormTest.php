<?php

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Tests\Unit\Admin;

use Fleetbase\WooCommerce\Admin\DeliveriesQuery;
use Fleetbase\WooCommerce\Admin\ServiceRateForm;
use PHPUnit\Framework\TestCase;

final class ServiceRateFormTest extends TestCase {

	public function test_per_meter_rate_maps_to_minor_units_and_always_carries_currency(): void {
		$mapped = ServiceRateForm::to_request(
			[ 'service_name' => 'Standard Delivery', 'service_type' => 'Standard', 'rate_calculation_method' => 'per_meter', 'base_fee' => '5.00', 'per_meter_unit' => 'km', 'per_meter_flat_rate_fee' => '1.50', 'duration_terms' => 'Same day', 'estimated_days' => '0', 'service_area' => 'sa_1' ],
			'usd'
		);
		$this->assertSame( [], $mapped['errors'] );
		$r = $mapped['request'];
		$this->assertSame( 'USD', $r['currency'] );
		$this->assertSame( 500, $r['base_fee'] );
		$this->assertSame( 150, $r['per_meter_flat_rate_fee'] );
		$this->assertSame( 'standard', $r['service_type'] );
		$this->assertSame( 'sa_1', $r['service_area'] );
		$this->assertArrayNotHasKey( 'zone', $r );
		$this->assertArrayNotHasKey( 'meter_fees', $r );
		$this->assertFalse( $r['has_cod_fee'] );
	}

	public function test_tiered_rate_sorts_tiers_and_drops_blank_rows(): void {
		$mapped = ServiceRateForm::to_request(
			[ 'service_name' => 'Express', 'service_type' => 'express', 'rate_calculation_method' => 'fixed_rate', 'meter_fees' => [ [ 'distance' => '15', 'fee' => '18' ], [ 'distance' => '', 'fee' => '' ], [ 'distance' => '5', 'fee' => '12.00' ] ] ],
			'USD'
		);
		$this->assertSame( [], $mapped['errors'] );
		$this->assertSame( [ [ 'distance' => 5.0, 'fee' => 1200 ], [ 'distance' => 15.0, 'fee' => 1800 ] ], $mapped['request']['meter_fees'] );
	}

	public function test_validation_errors(): void {
		$mapped = ServiceRateForm::to_request( [ 'service_name' => '', 'service_type' => '', 'rate_calculation_method' => 'magic', 'per_meter_flat_rate_fee' => '' ], 'USD' );
		$this->assertArrayHasKey( 'service_name', $mapped['errors'] );
		$this->assertArrayHasKey( 'service_type', $mapped['errors'] );
		$this->assertArrayHasKey( 'rate_calculation_method', $mapped['errors'] );

		$tiered = ServiceRateForm::to_request( [ 'service_name' => 'X', 'service_type' => 'x', 'rate_calculation_method' => 'fixed_rate', 'meter_fees' => [] ], 'USD' );
		$this->assertArrayHasKey( 'meter_fees', $tiered['errors'] );

		$per_meter = ServiceRateForm::to_request( [ 'service_name' => 'X', 'service_type' => 'x', 'rate_calculation_method' => 'per_meter' ], 'USD' );
		$this->assertArrayHasKey( 'per_meter_flat_rate_fee', $per_meter['errors'] );
	}

	public function test_cod_and_peak_fees(): void {
		$mapped = ServiceRateForm::to_request(
			[ 'service_name' => 'X', 'service_type' => 'x', 'rate_calculation_method' => 'per_meter', 'per_meter_flat_rate_fee' => '1', 'has_cod_fee' => true, 'cod_calculation_method' => 'flat', 'cod_flat_fee' => '2.50', 'has_peak_hours_fee' => true, 'peak_hours_calculation_method' => 'percentage', 'peak_hours_percent' => '15', 'peak_hours_start' => '17:00', 'peak_hours_end' => '19:00' ],
			'USD'
		);
		$r = $mapped['request'];
		$this->assertSame( [], $mapped['errors'] );
		$this->assertTrue( $r['has_cod_fee'] );
		$this->assertSame( 250, $r['cod_flat_fee'] );
		$this->assertTrue( $r['has_peak_hours_fee'] );
		$this->assertTrue( $r['has_peak_hours'] );
		$this->assertSame( 15.0, $r['peak_hours_percent'] );

		$bad = ServiceRateForm::to_request( [ 'service_name' => 'X', 'service_type' => 'x', 'rate_calculation_method' => 'per_meter', 'per_meter_flat_rate_fee' => '1', 'has_peak_hours_fee' => true, 'peak_hours_start' => '' ], 'USD' );
		$this->assertArrayHasKey( 'peak_hours', $bad['errors'] );
	}

	public function test_from_api_round_trip(): void {
		$form = ServiceRateForm::from_api( [ 'id' => 'service_1', 'service_name' => 'Express', 'service_type' => 'express', 'rate_calculation_method' => 'fixed_rate', 'currency' => 'USD', 'base_fee' => 0, 'per_meter_unit' => 'km', 'rate_fees' => [ [ 'distance' => 5, 'fee' => 1200 ], [ 'distance' => 15, 'fee' => 1800 ] ], 'service_area' => [ 'id' => 'sa_1' ], 'has_cod_fee' => false ] );
		$this->assertSame( '12.00', $form['meter_fees'][0]['fee'] );
		$this->assertSame( 'sa_1', $form['service_area'] );
		$this->assertSame( '0.00', $form['base_fee'] );

		$back = ServiceRateForm::to_request( $form, 'USD' );
		$this->assertSame( [], $back['errors'] );
		$this->assertSame( [ [ 'distance' => 5.0, 'fee' => 1200 ], [ 'distance' => 15.0, 'fee' => 1800 ] ], $back['request']['meter_fees'] );
	}

	public function test_deliveries_query_args(): void {
		$open = DeliveriesQuery::args( [] );
		$this->assertSame( 25, $open['limit'] );
		$this->assertTrue( $open['paginate'] );
		$this->assertSame( 'EXISTS', $open['meta_query'][0]['compare'] );
		$this->assertSame( 'NOT IN', $open['meta_query'][1]['compare'] );
		$this->assertSame( 'AND', $open['meta_query']['relation'] );

		$transit = DeliveriesQuery::args( [ 'status' => 'transit', 'driver' => 'Dana Driver', 'per_page' => 500, 'page' => 2, 'search' => 'pike' ] );
		$this->assertSame( 100, $transit['limit'] );
		$this->assertSame( 2, $transit['page'] );
		$this->assertSame( 'IN', $transit['meta_query'][1]['compare'] );
		$this->assertSame( 'Dana Driver', $transit['meta_query'][2]['value'] );
		$this->assertSame( 'pike', $transit['s'] );

		$all = DeliveriesQuery::args( [ 'status' => 'all' ] );
		$this->assertCount( 1, $all['meta_query'] );
		$this->assertArrayNotHasKey( 'relation', $all['meta_query'] );
	}
}
