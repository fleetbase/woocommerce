<?php
/**
 * Builds the wc_get_orders arguments for the Deliveries page. Pure, for unit tests.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Admin;

use Fleetbase\WooCommerce\Sync\StatusMap;
use Fleetbase\WooCommerce\Sync\SyncState;

final class DeliveriesQuery {

	public const PER_PAGE_MAX = 100;

	/**
	 * @param array<string, mixed> $params status (open|transit|done|canceled|all), driver, search, page, per_page
	 * @return array<string, mixed>
	 */
	public static function args( array $params ): array {
		$per_page = max( 1, min( self::PER_PAGE_MAX, (int) ( $params['per_page'] ?? 25 ) ) );
		$page     = max( 1, (int) ( $params['page'] ?? 1 ) );
		$status   = (string) ( $params['status'] ?? 'open' );
		$driver   = trim( (string) ( $params['driver'] ?? '' ) );
		$search   = trim( (string) ( $params['search'] ?? '' ) );

		$meta = [
			[
				'key'     => SyncState::META_ORDER_ID,
				'compare' => 'EXISTS',
			],
		];
		switch ( $status ) {
			case 'transit':
				$meta[] = [
					'key'     => SyncState::META_STATUS,
					'value'   => StatusMap::IN_TRANSIT,
					'compare' => 'IN',
				];
				break;
			case 'done':
				$meta[] = [
					'key'     => SyncState::META_STATUS,
					'value'   => StatusMap::DONE,
					'compare' => 'IN',
				];
				break;
			case 'canceled':
				$meta[] = [
					'key'     => SyncState::META_STATUS,
					'value'   => StatusMap::CANCELED,
					'compare' => 'IN',
				];
				break;
			case 'all':
				break;
			default:
				$meta[] = [
					'key'     => SyncState::META_STATUS,
					'value'   => array_merge( StatusMap::DONE, StatusMap::CANCELED ),
					'compare' => 'NOT IN',
				];
		}
		if ( '' !== $driver ) {
			$meta[] = [
				'key'     => SyncState::META_DRIVER,
				'value'   => $driver,
				'compare' => '=',
			];
		}
		if ( count( $meta ) > 1 ) {
			$meta['relation'] = 'AND';
		}

		$args = [
			'type'       => 'shop_order',
			'limit'      => $per_page,
			'page'       => $page,
			'paginate'   => true,
			'orderby'    => 'date',
			'order'      => 'DESC',
			'meta_query' => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		];
		if ( '' !== $search ) {
			$args['s'] = $search;
		}
		return $args;
	}
}
