<?php
/**
 * "Fleetbase delivery" box on the order edit screen (HPOS and legacy).
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Orders;

use Fleetbase\WooCommerce\Checkout\DeliveryChoice;
use Fleetbase\WooCommerce\Hookable;
use Fleetbase\WooCommerce\Support\Compat;
use Fleetbase\WooCommerce\Sync\OrderMapper;
use Fleetbase\WooCommerce\Sync\SyncState;

final class MetaBox implements Hookable {

	public function register(): void {
		add_action( 'add_meta_boxes', [ $this, 'add' ] );
		add_action( 'admin_head', [ $this, 'styles' ] );
	}

	public function add(): void {
		$screen = Compat::hpos_enabled() && function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
		add_meta_box( 'fleetbase-wc-delivery', __( 'Fleetbase delivery', 'fleetbase-for-woocommerce' ), [ $this, 'render' ], $screen, 'side', 'high' );
	}

	/**
	 * @param \WP_Post|\WC_Order $post_or_order
	 */
	public function render( $post_or_order ): void {
		$order = $post_or_order instanceof \WC_Order ? $post_or_order : wc_get_order( $post_or_order instanceof \WP_Post ? $post_or_order->ID : 0 );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$state    = SyncState::state( $order );
		$fb_id    = SyncState::fleetbase_id( $order );
		$status   = (string) $order->get_meta( SyncState::META_STATUS, true );
		$track    = (string) $order->get_meta( SyncState::META_TRACKING_NUMBER, true );
		$url      = (string) $order->get_meta( SyncState::META_TRACKING_URL, true );
		$driver   = (string) $order->get_meta( SyncState::META_DRIVER, true );
		$error    = (string) $order->get_meta( SyncState::META_LAST_ERROR, true );
		$window   = DeliveryChoice::order_label( $order );
		$service  = OrderMapper::shipping_meta( $order, '_fleetbase_service_name' );
		$amount   = OrderMapper::shipping_meta( $order, '_fleetbase_amount' );
		$currency = OrderMapper::shipping_meta( $order, '_fleetbase_currency' );

		echo '<div class="fbwc-box">';
		printf( '<p><span class="fbwc-state fbwc-state--%s">%s</span></p>', esc_attr( '' === $state ? 'none' : $state ), esc_html( SyncState::label( $state ) ) );

		$rows = [];
		if ( '' !== $fb_id ) {
			$rows[ __( 'Fleetbase order', 'fleetbase-for-woocommerce' ) ] = esc_html( $fb_id );
		}
		if ( '' !== $status ) {
			$rows[ __( 'Status', 'fleetbase-for-woocommerce' ) ] = esc_html( ucfirst( str_replace( '_', ' ', $status ) ) );
		}
		if ( '' !== $driver ) {
			$rows[ __( 'Driver', 'fleetbase-for-woocommerce' ) ] = esc_html( $driver );
		}
		if ( '' !== $track ) {
			$rows[ __( 'Tracking', 'fleetbase-for-woocommerce' ) ] = '' !== $url
				? sprintf( '<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( $url ), esc_html( $track ) )
				: esc_html( $track );
		}
		if ( '' !== $window ) {
			$rows[ __( 'Delivery time', 'fleetbase-for-woocommerce' ) ] = esc_html( $window );
		}
		if ( '' !== $service ) {
			$price = '' !== $amount && '' !== $currency && function_exists( 'wc_price' ) ? ' · ' . wc_price( \Fleetbase\WooCommerce\Api\Money::from_minor( (int) $amount, $currency ), [ 'currency' => $currency ] ) : '';
			$rows[ __( 'Service', 'fleetbase-for-woocommerce' ) ] = esc_html( $service ) . wp_kses_post( $price );
		}
		if ( '' !== $error && in_array( $state, [ SyncState::FAILED, SyncState::PENDING ], true ) ) {
			$rows[ __( 'Last error', 'fleetbase-for-woocommerce' ) ] = '<span class="fbwc-error">' . esc_html( $error ) . '</span>';
		}

		if ( [] !== $rows ) {
			echo '<table class="fbwc-table">';
			foreach ( $rows as $label => $value ) {
				printf( '<tr><th>%s</th><td>%s</td></tr>', esc_html( $label ), $value ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
			}
			echo '</table>';
		}

		$button_label = '' === $fb_id ? __( 'Send to Fleetbase now', 'fleetbase-for-woocommerce' ) : __( 'Re-send to Fleetbase', 'fleetbase-for-woocommerce' );
		$action_url   = wp_nonce_url( admin_url( 'admin-post.php?action=' . OrderActions::ADMIN_POST . '&order_id=' . $order->get_id() ), OrderActions::ADMIN_POST . '_' . $order->get_id() );
		printf( '<p><a class="button" href="%s">%s</a></p>', esc_url( $action_url ), esc_html( $button_label ) );

		$entries = array_slice( array_reverse( SyncState::entries( $order ) ), 0, 8 );
		if ( [] !== $entries ) {
			echo '<details class="fbwc-log"><summary>' . esc_html__( 'Sync log', 'fleetbase-for-woocommerce' ) . '</summary><ul>';
			foreach ( $entries as $entry ) {
				printf(
					'<li class="fbwc-log__%s"><time>%s</time> %s</li>',
					esc_attr( (string) $entry['level'] ),
					esc_html( wp_date( 'M j, H:i', (int) $entry['time'] ) ),
					esc_html( (string) $entry['message'] )
				);
			}
			echo '</ul></details>';
		}
		echo '</div>';
	}

	public function styles(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( null === $screen || ( ! str_contains( (string) $screen->id, 'shop_order' ) && ! str_contains( (string) $screen->id, 'wc-orders' ) ) ) {
			return;
		}
		echo '<style>
			.fbwc-box{font-size:12px}
			.fbwc-state{display:inline-block;padding:2px 10px;border-radius:999px;font-weight:600;background:#f0f0f1;color:#50575e}
			.fbwc-state--synced{background:#e6f4ea;color:#1e7e34}
			.fbwc-state--pending,.fbwc-state--syncing{background:#fcf0e4;color:#996800}
			.fbwc-state--failed,.fbwc-state--attention{background:#fbeaea;color:#b32d2e}
			.fbwc-table{width:100%;border-collapse:collapse;margin:0 0 8px}
			.fbwc-table th{text-align:left;color:#646970;font-weight:500;padding:3px 6px 3px 0;vertical-align:top;white-space:nowrap}
			.fbwc-table td{padding:3px 0;word-break:break-word}
			.fbwc-error{color:#b32d2e}
			.fbwc-log summary{cursor:pointer;color:#2271b1}
			.fbwc-log ul{margin:6px 0 0;padding:0;list-style:none}
			.fbwc-log li{padding:2px 0;border-top:1px solid #f0f0f1}
			.fbwc-log time{color:#8c8f94;margin-right:4px}
			.fbwc-log__error{color:#b32d2e}
			.fbwc-log__warning{color:#996800}
			.column-fleetbase_delivery .fbwc-state{font-size:11px}
		</style>';
	}
}
