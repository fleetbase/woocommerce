<?php
/**
 * "Delivery" column on the orders list (HPOS and legacy screens).
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Orders;

use Fleetbase\WooCommerce\Hookable;
use Fleetbase\WooCommerce\Sync\SyncState;

final class ListColumn implements Hookable {

	public const COLUMN = 'fleetbase_delivery';

	public function register(): void {
		add_filter( 'manage_woocommerce_page_wc-orders_columns', [ $this, 'columns' ] );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', [ $this, 'render' ], 10, 2 );
		add_filter( 'manage_edit-shop_order_columns', [ $this, 'columns' ] );
		add_action( 'manage_shop_order_posts_custom_column', [ $this, 'render_legacy' ], 10, 2 );
	}

	/**
	 * @param array<string, string> $columns
	 * @return array<string, string>
	 */
	public function columns( array $columns ): array {
		$out = [];
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'order_status' === $key ) {
				$out[ self::COLUMN ] = __( 'Delivery', 'fleetbase-for-woocommerce' );
			}
		}
		if ( ! isset( $out[ self::COLUMN ] ) ) {
			$out[ self::COLUMN ] = __( 'Delivery', 'fleetbase-for-woocommerce' );
		}
		return $out;
	}

	/**
	 * @param string              $column
	 * @param \WC_Order|int|mixed $order
	 */
	public function render( $column, $order ): void {
		if ( self::COLUMN !== $column ) {
			return;
		}
		$order = $order instanceof \WC_Order ? $order : wc_get_order( (int) $order );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		$state  = SyncState::state( $order );
		$status = (string) $order->get_meta( SyncState::META_STATUS, true );
		if ( '' === $state && '' === $status ) {
			echo '<span class="fbwc-muted">—</span>';
			return;
		}
		printf( '<span class="fbwc-state fbwc-state--%s">%s</span>', esc_attr( $state ), esc_html( SyncState::label( $state ) ) );
		if ( '' !== $status && SyncState::SYNCED === $state ) {
			printf( '<br><small>%s</small>', esc_html( ucfirst( str_replace( '_', ' ', $status ) ) ) );
		}
	}

	/**
	 * @param string $column
	 * @param int    $post_id
	 */
	public function render_legacy( $column, $post_id ): void {
		$this->render( (string) $column, (int) $post_id );
	}
}
