<?php
/**
 * Manual sync from the order screen, the orders list and the bulk actions menu.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Orders;

use Fleetbase\WooCommerce\Hookable;
use Fleetbase\WooCommerce\Plugin;
use Fleetbase\WooCommerce\Sync\OrderSyncer;
use Fleetbase\WooCommerce\Sync\SyncState;

final class OrderActions implements Hookable {

	public const ACTION_SYNC   = 'fleetbase_sync';
	public const ACTION_CANCEL = 'fleetbase_cancel';
	public const ADMIN_POST    = 'fleetbase_wc_sync_order';

	public function register(): void {
		add_filter( 'woocommerce_order_actions', [ $this, 'order_actions' ], 10, 2 );
		add_action( 'woocommerce_order_action_' . self::ACTION_SYNC, [ $this, 'sync_now' ] );
		add_action( 'woocommerce_order_action_' . self::ACTION_CANCEL, [ $this, 'cancel_now' ] );
		add_action( 'admin_post_' . self::ADMIN_POST, [ $this, 'admin_post_sync' ] );

		foreach ( [ 'woocommerce_page_wc-orders', 'edit-shop_order' ] as $screen ) {
			add_filter( 'bulk_actions-' . $screen, [ $this, 'bulk_actions' ] );
			add_filter( 'handle_bulk_actions-' . $screen, [ $this, 'handle_bulk' ], 10, 3 );
		}
		add_action( 'admin_notices', [ $this, 'bulk_notice' ] );
	}

	/**
	 * @param array<string, string> $actions
	 * @param \WC_Order|null        $order
	 * @return array<string, string>
	 */
	public function order_actions( array $actions, $order = null ): array {
		$actions[ self::ACTION_SYNC ] = __( 'Send to Fleetbase', 'fleetbase-for-woocommerce' );
		if ( $order instanceof \WC_Order && SyncState::is_synced( $order ) ) {
			$actions[ self::ACTION_SYNC ]   = __( 'Re-send to Fleetbase', 'fleetbase-for-woocommerce' );
			$actions[ self::ACTION_CANCEL ] = __( 'Cancel delivery in Fleetbase', 'fleetbase-for-woocommerce' );
		}
		return $actions;
	}

	/**
	 * @param \WC_Order $order
	 */
	public function sync_now( $order ): void {
		if ( $order instanceof \WC_Order ) {
			$this->syncer()->sync( $order, 1, true );
		}
	}

	/**
	 * @param \WC_Order $order
	 */
	public function cancel_now( $order ): void {
		if ( $order instanceof \WC_Order ) {
			$this->syncer()->cancel( $order, 'cancelled by staff' );
		}
	}

	public function admin_post_sync(): void {
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		if ( ! current_user_can( 'manage_woocommerce' ) || ! $order_id || ! check_admin_referer( self::ADMIN_POST . '_' . $order_id ) ) {
			wp_die( esc_html__( 'Not allowed.', 'fleetbase-for-woocommerce' ) );
		}
		$order = wc_get_order( $order_id );
		if ( $order instanceof \WC_Order ) {
			$this->syncer()->sync( $order, 1, true );
		}
		wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order_id ) );
		exit;
	}

	/**
	 * @param array<string, string> $actions
	 * @return array<string, string>
	 */
	public function bulk_actions( array $actions ): array {
		$actions[ self::ACTION_SYNC ] = __( 'Send to Fleetbase', 'fleetbase-for-woocommerce' );
		return $actions;
	}

	/**
	 * @param string     $redirect
	 * @param string     $action
	 * @param array<int> $ids
	 */
	public function handle_bulk( $redirect, $action, $ids ): string {
		if ( self::ACTION_SYNC !== $action ) {
			return (string) $redirect;
		}
		$queued = 0;
		foreach ( (array) $ids as $id ) {
			$order = wc_get_order( (int) $id );
			if ( ! $order instanceof \WC_Order ) {
				continue;
			}
			if ( SyncState::is_synced( $order ) ) {
				continue;
			}
			if ( $this->syncer()->maybe_enqueue( (int) $id, 'bulk action' ) ) {
				++$queued;
			}
		}
		return add_query_arg( [ 'fleetbase_queued' => $queued ], (string) $redirect );
	}

	public function bulk_notice(): void {
		if ( ! isset( $_GET['fleetbase_queued'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
			return;
		}
		$count = absint( $_GET['fleetbase_queued'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %d: number of orders */
					_n( '%d order queued for Fleetbase.', '%d orders queued for Fleetbase.', $count, 'fleetbase-for-woocommerce' ),
					$count
				)
			)
		);
	}

	private function syncer(): OrderSyncer {
		return Plugin::instance()->get( OrderSyncer::class );
	}
}
