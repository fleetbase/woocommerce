<?php
/**
 * Order meta that records the Fleetbase side of a WooCommerce order.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Sync;

final class SyncState {

	public const META_ORDER_ID        = '_fleetbase_order_id';
	public const META_TRACKING_NUMBER = '_fleetbase_tracking_number';
	public const META_TRACKING_URL    = '_fleetbase_tracking_url';
	public const META_STATUS          = '_fleetbase_status';
	public const META_DRIVER          = '_fleetbase_driver';
	public const META_SYNC_STATE      = '_fleetbase_sync_state';
	public const META_SYNC_LOG        = '_fleetbase_sync_log';
	public const META_SYNCED_AT       = '_fleetbase_synced_at';
	public const META_ATTEMPTS        = '_fleetbase_sync_attempts';
	public const META_LAST_ERROR      = '_fleetbase_last_error';

	public const PENDING   = 'pending';
	public const SYNCING   = 'syncing';
	public const SYNCED    = 'synced';
	public const ATTENTION = 'attention';
	public const FAILED    = 'failed';
	public const SKIPPED   = 'skipped';

	private const LOG_LIMIT = 25;

	public static function state( \WC_Order $order ): string {
		return (string) $order->get_meta( self::META_SYNC_STATE, true );
	}

	public static function fleetbase_id( \WC_Order $order ): string {
		return (string) $order->get_meta( self::META_ORDER_ID, true );
	}

	public static function is_synced( \WC_Order $order ): bool {
		return '' !== self::fleetbase_id( $order );
	}

	public static function set_state( \WC_Order $order, string $state, string $error = '' ): void {
		$order->update_meta_data( self::META_SYNC_STATE, $state );
		if ( '' !== $error || self::FAILED !== $state ) {
			$order->update_meta_data( self::META_LAST_ERROR, $error );
		}
	}

	/**
	 * Append a line to the capped sync log stored on the order.
	 */
	public static function log( \WC_Order $order, string $level, string $message ): void {
		$log   = $order->get_meta( self::META_SYNC_LOG, true );
		$log   = is_array( $log ) ? $log : [];
		$log[] = [
			'time'    => time(),
			'level'   => $level,
			'message' => $message,
		];
		if ( count( $log ) > self::LOG_LIMIT ) {
			$log = array_slice( $log, -self::LOG_LIMIT );
		}
		$order->update_meta_data( self::META_SYNC_LOG, $log );
	}

	/**
	 * @return array<int, array{time:int, level:string, message:string}>
	 */
	public static function entries( \WC_Order $order ): array {
		$log = $order->get_meta( self::META_SYNC_LOG, true );
		return is_array( $log ) ? array_values( array_filter( $log, 'is_array' ) ) : [];
	}

	/**
	 * Record a successful create.
	 *
	 * @param array<string, mixed> $fleetbase_order
	 */
	public static function record_created( \WC_Order $order, array $fleetbase_order ): void {
		$tracking = isset( $fleetbase_order['tracking_number'] ) && is_array( $fleetbase_order['tracking_number'] ) ? $fleetbase_order['tracking_number'] : [];
		$order->update_meta_data( self::META_ORDER_ID, (string) ( $fleetbase_order['id'] ?? '' ) );
		$order->update_meta_data( self::META_TRACKING_NUMBER, (string) ( $tracking['tracking_number'] ?? '' ) );
		$order->update_meta_data( self::META_TRACKING_URL, (string) ( $tracking['url'] ?? '' ) );
		$order->update_meta_data( self::META_STATUS, (string) ( $fleetbase_order['status'] ?? 'created' ) );
		$order->update_meta_data( self::META_SYNCED_AT, (string) time() );
		$driver = $fleetbase_order['driver_assigned'] ?? null;
		if ( is_array( $driver ) && ! empty( $driver['name'] ) ) {
			$order->update_meta_data( self::META_DRIVER, (string) $driver['name'] );
		}
	}

	public static function attempts( \WC_Order $order ): int {
		return (int) $order->get_meta( self::META_ATTEMPTS, true );
	}

	public static function set_attempts( \WC_Order $order, int $attempts ): void {
		$order->update_meta_data( self::META_ATTEMPTS, (string) $attempts );
	}

	/**
	 * Human label for a state.
	 */
	public static function label( string $state ): string {
		switch ( $state ) {
			case self::PENDING:
				return __( 'Queued', 'fleetbase-for-woocommerce' );
			case self::SYNCING:
				return __( 'Sending…', 'fleetbase-for-woocommerce' );
			case self::SYNCED:
				return __( 'Sent to Fleetbase', 'fleetbase-for-woocommerce' );
			case self::ATTENTION:
				return __( 'Needs attention', 'fleetbase-for-woocommerce' );
			case self::FAILED:
				return __( 'Failed', 'fleetbase-for-woocommerce' );
			case self::SKIPPED:
				return __( 'Not a delivery', 'fleetbase-for-woocommerce' );
			default:
				return __( 'Not sent', 'fleetbase-for-woocommerce' );
		}
	}
}
