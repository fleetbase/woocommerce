<?php
/**
 * Periodic reconciliation of open deliveries, for stores without a webhook and as a
 * safety net for missed ones.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Sync;

use Fleetbase\WooCommerce\Hookable;
use Fleetbase\WooCommerce\Orders\Statuses;
use Fleetbase\WooCommerce\Plugin;

final class Poller implements Hookable {

	public const HOOK  = 'fleetbase_wc_poll_orders';
	public const GROUP = 'fleetbase-wc';

	public const OPTION_LAST = 'fleetbase_wc_poll_last';

	public function register(): void {
		add_action(
			self::HOOK,
			function (): void {
				$this->run();
			}
		);
		add_action( 'init', [ $this, 'ensure_schedule' ], 20 );
		add_action( 'fleetbase_wc_settings_saved', [ $this, 'ensure_schedule' ] );
		add_action( 'fleetbase_wc_disconnected', [ $this, 'unschedule' ] );
	}

	public function ensure_schedule(): void {
		if ( ! function_exists( 'as_schedule_recurring_action' ) || ! function_exists( 'as_has_scheduled_action' ) ) {
			return;
		}
		$plugin   = Plugin::instance();
		$enabled  = (bool) $plugin->settings()->get( 'polling.enabled', true ) && $plugin->connections()->is_connected();
		$interval = max( 1, (int) $plugin->settings()->get( 'polling.interval_minutes', 5 ) ) * MINUTE_IN_SECONDS;

		if ( ! $enabled ) {
			$this->unschedule();
			return;
		}

		$next = function_exists( 'as_next_scheduled_action' ) ? as_next_scheduled_action( self::HOOK, [], self::GROUP ) : false;
		if ( false === $next || null === $next ) {
			as_schedule_recurring_action( time() + $interval, $interval, self::HOOK, [], self::GROUP, true );
			return;
		}
		$stored = (int) get_option( 'fleetbase_wc_poll_interval', 0 );
		if ( $stored !== $interval ) {
			$this->unschedule();
			as_schedule_recurring_action( time() + $interval, $interval, self::HOOK, [], self::GROUP, true );
		}
		update_option( 'fleetbase_wc_poll_interval', $interval, false );
	}

	public function unschedule(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK, [], self::GROUP );
		}
	}

	/**
	 * Reconcile every open delivery. Returns the number of orders checked.
	 */
	public function run(): int {
		$plugin = Plugin::instance();
		if ( ! $plugin->connections()->is_connected() ) {
			return 0;
		}
		$orders = wc_get_orders(
			[
				'limit'        => 50,
				'type'         => 'shop_order',
				'status'       => [ 'processing', 'on-hold', Statuses::OUT_FOR_DELIVERY ],
				'date_created' => '>' . ( time() - 14 * DAY_IN_SECONDS ),
				'meta_query'   => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					[
						'key'     => SyncState::META_ORDER_ID,
						'compare' => 'EXISTS',
					],
				],
			]
		);

		$reconciler = $plugin->get( Reconciler::class );
		$checked    = 0;
		foreach ( (array) $orders as $order ) {
			if ( ! $order instanceof \WC_Order ) {
				continue;
			}
			if ( ! StatusMap::is_open( (string) $order->get_meta( SyncState::META_STATUS, true ) ) ) {
				continue;
			}
			if ( $reconciler->reconcile( $order, 'poll' ) ) {
				++$checked;
			}
		}
		update_option(
			self::OPTION_LAST,
			[
				'time'    => time(),
				'checked' => $checked,
			],
			false
		);
		$plugin->logger()->debug( 'Poll finished', [ 'checked' => $checked ] );
		return $checked;
	}
}
