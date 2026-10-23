<?php
/**
 * Customer "out for delivery" email (HTML).
 *
 * Override by copying to yourtheme/woocommerce/emails/customer-out-for-delivery.php.
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

do_action( 'woocommerce_email_header', $email_heading, $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce email template hook.
?>
<p>
	<?php
	if ( '' !== $driver ) {
		/* translators: 1: customer first name, 2: driver first name */
		printf( esc_html__( 'Hi %1$s, %2$s is on the way with your order.', 'fleetbase-for-woocommerce' ), esc_html( $order->get_billing_first_name() ), esc_html( $driver ) );
	} else {
		/* translators: %s: customer first name */
		printf( esc_html__( 'Hi %s, your order is on the way.', 'fleetbase-for-woocommerce' ), esc_html( $order->get_billing_first_name() ) );
	}
	?>
</p>
<?php if ( '' !== $delivery_window ) : ?>
	<p><?php esc_html_e( 'Delivery time', 'fleetbase-for-woocommerce' ); ?>: <strong><?php echo esc_html( $delivery_window ); ?></strong></p>
<?php endif; ?>
<?php if ( '' !== $tracking_number ) : ?>
	<p>
		<?php esc_html_e( 'Track your delivery', 'fleetbase-for-woocommerce' ); ?>:
		<?php if ( '' !== $tracking_url ) : ?>
			<a href="<?php echo esc_url( $tracking_url ); ?>"><?php echo esc_html( $tracking_number ); ?></a>
		<?php else : ?>
			<?php echo esc_html( $tracking_number ); ?>
		<?php endif; ?>
	</p>
<?php endif; ?>
<?php
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce email template hook.
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce email template hook.

if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce email template hook.
