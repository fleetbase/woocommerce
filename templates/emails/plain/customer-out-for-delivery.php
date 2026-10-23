<?php
/**
 * Customer "out for delivery" email (plain text).
 *
 * @package Fleetbase\WooCommerce
 * @version 1.0.0
 *
 * @var \WC_Order $order
 * @var string    $email_heading
 * @var string    $additional_content
 * @var bool      $sent_to_admin
 * @var bool      $plain_text
 * @var \WC_Email $email
 * @var string    $tracking_number
 * @var string    $tracking_url
 * @var string    $driver
 * @var string    $delivery_window
 */

defined( 'ABSPATH' ) || exit;

echo '= ' . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";

if ( '' !== $driver ) {
	/* translators: 1: customer first name, 2: driver first name */
	printf( esc_html__( 'Hi %1$s, %2$s is on the way with your order.', 'fleetbase-for-woocommerce' ), esc_html( $order->get_billing_first_name() ), esc_html( $driver ) );
} else {
	/* translators: %s: customer first name */
	printf( esc_html__( 'Hi %s, your order is on the way.', 'fleetbase-for-woocommerce' ), esc_html( $order->get_billing_first_name() ) );
}
echo "\n\n";
if ( '' !== $delivery_window ) {
	echo esc_html__( 'Delivery time', 'fleetbase-for-woocommerce' ) . ': ' . esc_html( $delivery_window ) . "\n";
}
if ( '' !== $tracking_number ) {
	echo esc_html__( 'Track your delivery', 'fleetbase-for-woocommerce' ) . ': ' . esc_html( $tracking_number ) . ( '' !== $tracking_url ? ' ' . esc_url( $tracking_url ) : '' ) . "\n";
}
echo "\n----------------------------------------\n\n";

do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce email template hook.
echo "\n----------------------------------------\n\n";
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce email template hook.

if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n\n";
}

echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce email template hook.
