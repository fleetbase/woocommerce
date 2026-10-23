<?php
/**
 * Delivery-time control for the shortcode checkout.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Checkout;

use Fleetbase\WooCommerce\Hookable;

final class ClassicCheckout implements Hookable {

	public const FIELD = 'fleetbase_delivery';

	public function register(): void {
		add_action( 'woocommerce_review_order_after_shipping', [ $this, 'render' ] );
		add_action( 'woocommerce_checkout_update_order_review', [ $this, 'capture_from_review' ] );
		add_action( 'woocommerce_checkout_process', [ $this, 'validate' ] );
		add_action( 'woocommerce_checkout_create_order', [ $this, 'save' ], 10, 2 );
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'reset_session' ] );
	}

	public function render(): void {
		if ( ! DeliveryChoice::cart_uses_fleetbase() ) {
			return;
		}
		$scheduling = Scheduling::from_settings();
		if ( ! $scheduling->enabled() ) {
			return;
		}
		$options  = $scheduling->options();
		$selected = DeliveryChoice::current_value();
		?>
		<tr class="fleetbase-wc-delivery-time">
			<th><label for="<?php echo esc_attr( self::FIELD ); ?>"><?php esc_html_e( 'Delivery time', 'fleetbase-for-woocommerce' ); ?></label></th>
			<td>
				<select name="<?php echo esc_attr( self::FIELD ); ?>" id="<?php echo esc_attr( self::FIELD ); ?>" class="fleetbase-wc-delivery-select" style="width:100%">
					<?php foreach ( $options as $option ) : ?>
						<option value="<?php echo esc_attr( $option['value'] ); ?>" <?php selected( $selected, $option['value'] ); ?>><?php echo esc_html( $option['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<script>
					( function () {
						var select = document.getElementById( <?php echo wp_json_encode( self::FIELD ); ?> );
						if ( select && window.jQuery && ! select.dataset.bound ) {
							select.dataset.bound = '1';
							select.addEventListener( 'change', function () {
								window.jQuery( document.body ).trigger( 'update_checkout' );
							} );
						}
					} )();
				</script>
			</td>
		</tr>
		<?php
	}

	/**
	 * The review refresh posts the serialised form; pull our field out so rates refresh with the new time.
	 *
	 * @param string $post_data
	 */
	public function capture_from_review( $post_data ): void {
		$fields = [];
		parse_str( (string) $post_data, $fields );
		if ( isset( $fields[ self::FIELD ] ) ) {
			DeliveryChoice::set( sanitize_text_field( (string) $fields[ self::FIELD ] ) );
		}
	}

	public function validate(): void {
		if ( ! DeliveryChoice::cart_uses_fleetbase() ) {
			return;
		}
		$value = isset( $_POST[ self::FIELD ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ self::FIELD ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce before this hook.
		if ( '' !== $value && ! Scheduling::from_settings()->is_valid( $value ) ) {
			wc_add_notice( __( 'That delivery time is no longer available. Please choose another.', 'fleetbase-for-woocommerce' ), 'error' );
		}
	}

	/**
	 * @param \WC_Order            $order
	 * @param array<string, mixed> $data
	 */
	public function save( $order, $data ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		$value = isset( $_POST[ self::FIELD ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ self::FIELD ] ) ) : DeliveryChoice::current_value(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce before this hook.
		DeliveryChoice::save_to_order( $order, $value );
	}

	public function reset_session(): void {
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( DeliveryChoice::SESSION_VALUE, '' );
			WC()->session->set( DeliveryChoice::SESSION_SCHEDULED_AT, '' );
		}
	}
}
