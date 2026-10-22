<?php
/**
 * Brings a WooCommerce order in line with its Fleetbase order. Webhooks and the poller
 * both end here, and everything is re-fetched from the API so repeated runs are harmless.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Sync;

use Fleetbase\WooCommerce\Hookable;
use Fleetbase\WooCommerce\Plugin;
use Fleetbase\WooCommerce\Support\Compat;

final class Reconciler implements Hookable {

	public const HOOK  = 'fleetbase_wc_reconcile_order';
	public const GROUP = 'fleetbase-wc';

	public const META_NOTIFIED       = '_fleetbase_notified';
	public const META_RECONCILED_AT  = '_fleetbase_reconciled_at';
	public const META_FULFILLMENT_ID = '_fleetbase_fulfillment_id';
	public const META_PROOFS         = '_fleetbase_proofs';

	public function register(): void {
		add_action( self::HOOK, [ $this, 'handle' ], 10, 2 );
	}

	public function enqueue( string $fleetbase_id, string $source ): void {
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::HOOK, [ $fleetbase_id, $source ], self::GROUP, true );
			return;
		}
		$this->handle( $fleetbase_id, $source );
	}

	/**
	 * @param string $fleetbase_id
	 * @param string $source
	 */
	public function handle( $fleetbase_id, $source = 'webhook' ): void {
		$this->reconcile_by_fleetbase_id( (string) $fleetbase_id, (string) $source );
	}

	public static function find_order( string $fleetbase_id ): ?\WC_Order {
		$orders = wc_get_orders(
			[
				'limit'      => 1,
				'meta_key'   => SyncState::META_ORDER_ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $fleetbase_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'type'       => 'shop_order',
			]
		);
		$order  = is_array( $orders ) ? reset( $orders ) : null;
		return $order instanceof \WC_Order ? $order : null;
	}

	public function reconcile_by_fleetbase_id( string $fleetbase_id, string $source ): ?\WC_Order {
		$order = self::find_order( $fleetbase_id );
		if ( null === $order ) {
			Plugin::instance()->logger()->debug(
				'Reconcile skipped: no WooCommerce order for Fleetbase id',
				[
					'fleetbase_order' => $fleetbase_id,
					'source'          => $source,
				]
			);
			return null;
		}
		$this->reconcile( $order, $source );
		return $order;
	}

	public function reconcile( \WC_Order $order, string $source ): bool {
		$fleetbase_id = SyncState::fleetbase_id( $order );
		if ( '' === $fleetbase_id ) {
			return false;
		}
		try {
			$data = Plugin::instance()->api()->orders()->find( $fleetbase_id );
		} catch ( \Throwable $e ) {
			Plugin::instance()->logger()->warning(
				'Reconcile failed to fetch order',
				[
					'order_id'        => $order->get_id(),
					'fleetbase_order' => $fleetbase_id,
					'error'           => $e->getMessage(),
				]
			);
			return false;
		}
		$this->apply( $order, $data, $source );
		return true;
	}

	/**
	 * Apply a fetched Fleetbase order to the WooCommerce order.
	 *
	 * @param array<string, mixed> $data
	 */
	public function apply( \WC_Order $order, array $data, string $source = 'api' ): void {
		$settings = Plugin::instance()->settings();
		$statuses = (array) $settings->get( 'statuses', [] );

		$fb_status   = strtolower( (string) ( $data['status'] ?? '' ) );
		$prev_status = strtolower( (string) $order->get_meta( SyncState::META_STATUS, true ) );
		$driver      = is_array( $data['driver_assigned'] ?? null ) ? $data['driver_assigned'] : [];
		$driver_name = (string) ( $driver['name'] ?? '' );
		$driver_id   = (string) ( $driver['id'] ?? '' );
		$tracking    = is_array( $data['tracking_number'] ?? null ) ? $data['tracking_number'] : [];
		$track_no    = (string) ( $tracking['tracking_number'] ?? $order->get_meta( SyncState::META_TRACKING_NUMBER, true ) );
		$track_url   = (string) ( $tracking['url'] ?? $order->get_meta( SyncState::META_TRACKING_URL, true ) );

		$order->update_meta_data( SyncState::META_STATUS, $fb_status );
		$order->update_meta_data( SyncState::META_DRIVER, $driver_name );
		$order->update_meta_data( SyncState::META_TRACKING_NUMBER, $track_no );
		$order->update_meta_data( SyncState::META_TRACKING_URL, $track_url );
		$order->update_meta_data( self::META_RECONCILED_AT, (string) time() );

		$notified  = $order->get_meta( self::META_NOTIFIED, true );
		$notified  = is_array( $notified ) ? $notified : [];
		$note_once = static function ( string $key, string $note ) use ( $order, &$notified ): void {
			if ( in_array( $key, $notified, true ) ) {
				return;
			}
			$notified[] = $key;
			$order->add_order_note( $note );
		};

		if ( '' !== $driver_name ) {
			/* translators: %s: driver name */
			$note_once( 'driver:' . ( '' !== $driver_id ? $driver_id : $driver_name ), sprintf( __( 'Driver assigned: %s', 'fleetbase-for-woocommerce' ), $driver_name ) );
		}

		$target      = StatusMap::target( $fb_status, $statuses );
		$current     = (string) $order->get_status();
		$went_out    = false;
		$status_note = '';
		if ( StatusMap::is_in_transit( $fb_status ) ) {
			$status_note = '' !== $driver_name
				/* translators: 1: driver name, 2: tracking link */
				? sprintf( __( 'Out for delivery with %1$s. Track it: %2$s', 'fleetbase-for-woocommerce' ), $driver_name, $track_url )
				/* translators: %s: tracking link */
				: sprintf( __( 'Out for delivery. Track it: %s', 'fleetbase-for-woocommerce' ), $track_url );
		} elseif ( StatusMap::is_done( $fb_status ) ) {
			$status_note = __( 'Delivered by Fleetbase.', 'fleetbase-for-woocommerce' );
		} elseif ( StatusMap::is_canceled( $fb_status ) ) {
			$status_note = __( 'Delivery was cancelled in Fleetbase.', 'fleetbase-for-woocommerce' );
		}

		$status_key = 'status:' . ( StatusMap::is_in_transit( $fb_status ) ? 'transit' : $fb_status );
		if ( null !== $target && StatusMap::may_transition( $current, $target ) ) {
			$notified[] = $status_key;
			$order->update_meta_data( self::META_NOTIFIED, array_values( array_unique( $notified ) ) );
			$order->update_status( $target, $status_note );
			$went_out = StatusMap::OUT_FOR_DELIVERY === $target;
		} elseif ( '' !== $status_note ) {
			$note_once( $status_key, $status_note );
		}

		if ( StatusMap::is_done( $fb_status ) ) {
			$this->attach_proofs( $order, (string) ( $data['id'] ?? '' ), $note_once );
		}

		$order->update_meta_data( self::META_NOTIFIED, array_values( array_unique( $notified ) ) );
		$this->update_fulfillment( $order, $fb_status, $track_no, $track_url );
		$order->save();

		SyncState::log( $order, 'info', sprintf( 'Reconciled from %s: Fleetbase status %s.', $source, '' !== $fb_status ? $fb_status : 'unknown' ) );
		$order->save();

		if ( $went_out ) {
			/**
			 * Fires when a WooCommerce order moves to "Out for delivery" because of Fleetbase.
			 *
			 * @param \WC_Order $order
			 */
			do_action( 'fleetbase_wc_order_out_for_delivery', $order );
		}

		/**
		 * Fires after a WooCommerce order was reconciled with its Fleetbase order.
		 *
		 * @param \WC_Order            $order
		 * @param array<string, mixed> $data
		 * @param string               $previous_status
		 */
		do_action( 'fleetbase_wc_order_reconciled', $order, $data, $prev_status );
	}

	/**
	 * @param callable(string, string): void $note_once
	 */
	private function attach_proofs( \WC_Order $order, string $fleetbase_id, callable $note_once ): void {
		if ( '' === $fleetbase_id ) {
			return;
		}
		try {
			$proofs = Plugin::instance()->api()->orders()->proofs( $fleetbase_id );
		} catch ( \Throwable $e ) {
			return;
		}
		$stored = [];
		foreach ( $proofs as $proof ) {
			$id  = (string) ( $proof['id'] ?? '' );
			$url = (string) ( $proof['url'] ?? '' );
			if ( '' === $url ) {
				continue;
			}
			$stored[] = [
				'id'      => $id,
				'url'     => $url,
				'remarks' => (string) ( $proof['remarks'] ?? '' ),
			];
			/* translators: %s: proof of delivery link */
			$note_once( 'proof:' . ( '' !== $id ? $id : md5( $url ) ), sprintf( __( 'Proof of delivery: %s', 'fleetbase-for-woocommerce' ), $url ) );
		}
		if ( [] !== $stored ) {
			$order->update_meta_data( self::META_PROOFS, $stored );
		}
	}

	/**
	 * Mirror tracking into WooCommerce's Fulfillments feature when the merchant has it on.
	 */
	private function update_fulfillment( \WC_Order $order, string $fb_status, string $track_no, string $track_url ): void {
		if ( '' === $track_no || ! Compat::fulfillments_enabled() ) {
			return;
		}
		$class = '\\Automattic\\WooCommerce\\Internal\\Fulfillments\\Fulfillment';
		if ( ! class_exists( $class ) ) {
			return;
		}
		try {
			$existing_id = (int) $order->get_meta( self::META_FULFILLMENT_ID, true );
			$fulfillment = $existing_id > 0 ? new $class( $existing_id ) : new $class();
			if ( $existing_id <= 0 ) {
				$fulfillment->set_entity_type( 'shop_order' );
				$fulfillment->set_entity_id( (string) $order->get_id() );
				$items = [];
				foreach ( $order->get_items() as $item_id => $item ) {
					$items[] = [
						'item_id' => (int) $item_id,
						'qty'     => (int) $item->get_quantity(),
					];
				}
				$fulfillment->set_items( $items );
			}
			$fulfillment->update_meta_data( '_tracking_number', $track_no );
			$fulfillment->update_meta_data( '_tracking_url', $track_url );
			$fulfillment->update_meta_data( '_shipment_provider', 'Fleetbase' );
			$fulfillment->set_status( StatusMap::is_done( $fb_status ) ? 'fulfilled' : 'unfulfilled' );
			$fulfillment->save();
			if ( $existing_id <= 0 && method_exists( $fulfillment, 'get_id' ) && $fulfillment->get_id() > 0 ) {
				$order->update_meta_data( self::META_FULFILLMENT_ID, (string) $fulfillment->get_id() );
			}
		} catch ( \Throwable $e ) {
			Plugin::instance()->logger()->debug(
				'Fulfillment update skipped',
				[
					'order_id' => $order->get_id(),
					'error'    => $e->getMessage(),
				]
			);
		}
	}
}
