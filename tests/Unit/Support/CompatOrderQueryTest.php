<?php

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Tests\Unit\Support;

use Fleetbase\WooCommerce\Support\Compat;
use PHPUnit\Framework\TestCase;

final class CompatOrderQueryTest extends TestCase {

	private const META = [
		[
			'key'     => '_fleetbase_order_id',
			'compare' => 'EXISTS',
		],
	];

	public function test_legacy_storage_moves_meta_query_out_of_reach_of_woocommerce(): void {
		$args = Compat::order_query(
			[
				'limit'      => 5,
				'meta_query' => self::META,
			]
		);
		$this->assertArrayNotHasKey( 'meta_query', $args );
		$this->assertSame( 5, $args['limit'] );
		$this->assertContains( self::META, $args );
	}

	public function test_a_query_without_meta_is_unchanged(): void {
		$this->assertSame( [ 'limit' => 5 ], Compat::order_query( [ 'limit' => 5 ] ) );
	}

	public function test_the_filter_adds_the_clauses_to_what_woocommerce_built(): void {
		$vars    = Compat::order_query( [ 'meta_query' => self::META ] );
		$own     = [ 'key' => '_customer_user', 'value' => 3 ];
		$wp_args = array_merge( $vars, [ 'meta_query' => [ $own ] ] );

		$result = ( new Compat() )->apply_legacy_meta_query( $wp_args, $vars );

		$this->assertSame( [ 'meta_query' => [ $own, self::META ] ], $result );
	}

	public function test_the_filter_leaves_other_order_queries_alone(): void {
		$wp_args = [ 'meta_query' => [ [ 'key' => '_customer_user', 'value' => 3 ] ] ];
		$this->assertSame( $wp_args, ( new Compat() )->apply_legacy_meta_query( $wp_args, [ 'limit' => 5 ] ) );
	}
}
