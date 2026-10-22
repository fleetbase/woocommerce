<?php
/**
 * The "Out for delivery" order status.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Orders;

use Fleetbase\WooCommerce\Hookable;

final class Statuses implements Hookable {

	public const OUT_FOR_DELIVERY = 'out-for-delivery';

	public function register(): void {
		add_action( 'init', [ $this, 'register_status' ] );
		add_filter( 'wc_order_statuses', [ $this, 'add_to_list' ] );
		add_filter( 'woocommerce_order_is_paid_statuses', [ $this, 'paid_statuses' ] );
		add_filter( 'woocommerce_reports_order_statuses', [ $this, 'paid_statuses' ] );
		add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', [ $this, 'paid_statuses' ] );
	}

	public function register_status(): void {
		register_post_status(
			'wc-' . self::OUT_FOR_DELIVERY,
			[
				'label'                     => _x( 'Out for delivery', 'Order status', 'fleetbase-for-woocommerce' ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of orders */
				'label_count'               => _n_noop( 'Out for delivery <span class="count">(%s)</span>', 'Out for delivery <span class="count">(%s)</span>', 'fleetbase-for-woocommerce' ),
			]
		);
	}

	/**
	 * Insert after "Processing" so the list reads in delivery order.
	 *
	 * @param array<string, string> $statuses
	 * @return array<string, string>
	 */
	public function add_to_list( array $statuses ): array {
		$out = [];
		foreach ( $statuses as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'wc-processing' === $key ) {
				$out[ 'wc-' . self::OUT_FOR_DELIVERY ] = _x( 'Out for delivery', 'Order status', 'fleetbase-for-woocommerce' );
			}
		}
		if ( ! isset( $out[ 'wc-' . self::OUT_FOR_DELIVERY ] ) ) {
			$out[ 'wc-' . self::OUT_FOR_DELIVERY ] = _x( 'Out for delivery', 'Order status', 'fleetbase-for-woocommerce' );
		}
		return $out;
	}

	/**
	 * @param array<int, string> $statuses
	 * @return array<int, string>
	 */
	public function paid_statuses( array $statuses ): array {
		if ( ! in_array( self::OUT_FOR_DELIVERY, $statuses, true ) ) {
			$statuses[] = self::OUT_FOR_DELIVERY;
		}
		return $statuses;
	}
}
