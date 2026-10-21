<?php
/**
 * Shows the chosen delivery time on the order: admin screen, customer pages and emails.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Checkout;

use Fleetbase\WooCommerce\Hookable;

final class OrderDelivery implements Hookable {

	public function register(): void {
		add_action( 'woocommerce_admin_order_data_after_shipping_address', [ $this, 'admin' ] );
		add_action( 'woocommerce_order_details_after_order_table', [ $this, 'customer' ] );
		add_action( 'woocommerce_email_after_order_table', [ $this, 'email' ], 10, 4 );
	}

	/**
	 * @param \WC_Order $order
	 */
	public function admin( $order ): void {
		$label = $order instanceof \WC_Order ? DeliveryChoice::order_label( $order ) : '';
		if ( '' === $label ) {
			return;
		}
		printf( '<p><strong>%s</strong><br>%s</p>', esc_html__( 'Delivery time', 'fleetbase-for-woocommerce' ), esc_html( $label ) );
	}

	/**
	 * @param \WC_Order $order
	 */
	public function customer( $order ): void {
		$label = $order instanceof \WC_Order ? DeliveryChoice::order_label( $order ) : '';
		if ( '' === $label ) {
			return;
		}
		printf( '<p class="fleetbase-wc-delivery"><strong>%s:</strong> %s</p>', esc_html__( 'Delivery time', 'fleetbase-for-woocommerce' ), esc_html( $label ) );
	}

	/**
	 * @param \WC_Order $order
	 * @param bool      $sent_to_admin
	 * @param bool      $plain_text
	 * @param object    $email
	 */
	public function email( $order, $sent_to_admin, $plain_text, $email ): void {
		$label = $order instanceof \WC_Order ? DeliveryChoice::order_label( $order ) : '';
		if ( '' === $label ) {
			return;
		}
		if ( $plain_text ) {
			echo "\n" . esc_html__( 'Delivery time', 'fleetbase-for-woocommerce' ) . ': ' . esc_html( $label ) . "\n";
			return;
		}
		printf( '<p><strong>%s:</strong> %s</p>', esc_html__( 'Delivery time', 'fleetbase-for-woocommerce' ), esc_html( $label ) );
	}
}
