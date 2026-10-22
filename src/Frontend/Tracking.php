<?php
/**
 * Customer-facing tracking: order pages, emails, a shortcode and a block.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Frontend;

use Fleetbase\WooCommerce\Hookable;
use Fleetbase\WooCommerce\Plugin;
use Fleetbase\WooCommerce\Sync\StatusMap;
use Fleetbase\WooCommerce\Sync\SyncState;

final class Tracking implements Hookable {

	public const SHORTCODE = 'fleetbase_tracking';
	public const QUERY_VAR = 'tracking';

	public function register(): void {
		add_action( 'woocommerce_order_details_after_order_table', [ $this, 'order_details' ], 20 );
		add_action( 'woocommerce_email_after_order_table', [ $this, 'email' ], 20, 4 );
		add_shortcode( self::SHORTCODE, [ $this, 'shortcode' ] );
		add_action( 'init', [ $this, 'register_block' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'styles' ] );
	}

	/**
	 * @param \WC_Order $order
	 */
	public function order_details( $order ): void {
		if ( ! $order instanceof \WC_Order || ! Plugin::instance()->settings()->get( 'tracking.show_in_account', true ) ) {
			return;
		}
		$html = $this->summary_html( $order );
		if ( '' !== $html ) {
			echo '<section class="fleetbase-wc-tracking">' . $html . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
		}
	}

	/**
	 * @param \WC_Order $order
	 * @param bool      $sent_to_admin
	 * @param bool      $plain_text
	 * @param object    $email
	 */
	public function email( $order, $sent_to_admin, $plain_text, $email ): void {
		if ( ! $order instanceof \WC_Order || $sent_to_admin || ! Plugin::instance()->settings()->get( 'tracking.show_in_emails', true ) ) {
			return;
		}
		$number = (string) $order->get_meta( SyncState::META_TRACKING_NUMBER, true );
		$url    = (string) $order->get_meta( SyncState::META_TRACKING_URL, true );
		if ( '' === $number ) {
			return;
		}
		if ( $plain_text ) {
			echo "\n" . esc_html__( 'Track your delivery', 'fleetbase-for-woocommerce' ) . ': ' . esc_html( $number ) . ( '' !== $url ? ' ' . esc_url( $url ) : '' ) . "\n";
			return;
		}
		printf(
			'<p><strong>%s:</strong> %s</p>',
			esc_html__( 'Track your delivery', 'fleetbase-for-woocommerce' ),
			'' !== $url ? sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( $number ) ) : esc_html( $number )
		);
	}

	/**
	 * @param array<string, mixed>|string $atts
	 */
	public function shortcode( $atts = [] ): string {
		$atts   = shortcode_atts( [ 'number' => '' ], is_array( $atts ) ? $atts : [] );
		$number = sanitize_text_field( (string) $atts['number'] );
		if ( '' === $number && isset( $_GET[ self::QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public lookup by tracking number.
			$number = sanitize_text_field( wp_unslash( (string) $_GET[ self::QUERY_VAR ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		ob_start();
		echo '<div class="fleetbase-wc-tracking fleetbase-wc-tracking--page">';
		$this->form( $number );
		if ( '' !== $number ) {
			$order = self::find_by_tracking_number( $number );
			if ( null === $order ) {
				echo '<p class="fleetbase-wc-tracking__notfound">' . esc_html__( 'We could not find a delivery with that tracking number.', 'fleetbase-for-woocommerce' ) . '</p>';
			} else {
				echo $this->summary_html( $order, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
			}
		}
		echo '</div>';
		return (string) ob_get_clean();
	}

	public function register_block(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		$asset_file = FLEETBASE_WC_PATH . 'assets/build/tracking-editor.asset.php';
		$asset      = file_exists( $asset_file ) ? include $asset_file : [
			'dependencies' => [],
			'version'      => FLEETBASE_WC_VERSION,
		];
		wp_register_script( 'fleetbase-wc-tracking-editor', FLEETBASE_WC_URL . 'assets/build/tracking-editor.js', (array) $asset['dependencies'], (string) $asset['version'], true );
		register_block_type(
			FLEETBASE_WC_PATH . 'assets/src/tracking',
			[
				'editor_script'   => 'fleetbase-wc-tracking-editor',
				'render_callback' => function ( array $attributes ): string {
					return $this->shortcode( [ 'number' => (string) ( $attributes['number'] ?? '' ) ] );
				},
			]
		);
	}

	public function styles(): void {
		wp_register_style( 'fleetbase-wc-tracking', false, [], FLEETBASE_WC_VERSION ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_enqueue_style( 'fleetbase-wc-tracking' );
		wp_add_inline_style(
			'fleetbase-wc-tracking',
			'.fleetbase-wc-tracking{margin:1.5em 0}.fleetbase-wc-tracking__status{font-weight:600}.fleetbase-wc-tracking__form{display:flex;gap:8px;max-width:480px;margin-bottom:1em}.fleetbase-wc-tracking__form input{flex:1}.fleetbase-wc-timeline{list-style:none;margin:1em 0 0;padding:0 0 0 18px;border-left:2px solid #e0e0e0}.fleetbase-wc-timeline li{position:relative;padding:0 0 14px 12px}.fleetbase-wc-timeline li::before{content:"";position:absolute;left:-25px;top:4px;width:10px;height:10px;border-radius:50%;background:#c3c4c7}.fleetbase-wc-timeline li.is-current::before{background:#1e7e34}.fleetbase-wc-timeline time{display:block;font-size:.85em;opacity:.7}.fleetbase-wc-proofs a{margin-right:8px}'
		);
	}

	private function form( string $number ): void {
		?>
		<form class="fleetbase-wc-tracking__form" method="get" action="">
			<label class="screen-reader-text" for="fleetbase-wc-tracking-number"><?php esc_html_e( 'Tracking number', 'fleetbase-for-woocommerce' ); ?></label>
			<input type="text" id="fleetbase-wc-tracking-number" name="<?php echo esc_attr( self::QUERY_VAR ); ?>" value="<?php echo esc_attr( $number ); ?>" placeholder="<?php esc_attr_e( 'Tracking number', 'fleetbase-for-woocommerce' ); ?>" />
			<button type="submit" class="button"><?php esc_html_e( 'Track', 'fleetbase-for-woocommerce' ); ?></button>
		</form>
		<?php
	}

	public static function find_by_tracking_number( string $number ): ?\WC_Order {
		$orders = wc_get_orders(
			[
				'limit'      => 1,
				'type'       => 'shop_order',
				'meta_key'   => SyncState::META_TRACKING_NUMBER, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $number, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			]
		);
		$order  = is_array( $orders ) ? reset( $orders ) : null;
		return $order instanceof \WC_Order ? $order : null;
	}

	/**
	 * Tracking number, current status, driver, timeline and proof links. Safe HTML.
	 */
	public function summary_html( \WC_Order $order, bool $with_timeline = false ): string {
		$number = (string) $order->get_meta( SyncState::META_TRACKING_NUMBER, true );
		if ( '' === $number ) {
			return '';
		}
		$url    = (string) $order->get_meta( SyncState::META_TRACKING_URL, true );
		$status = (string) $order->get_meta( SyncState::META_STATUS, true );
		$driver = (string) $order->get_meta( SyncState::META_DRIVER, true );
		$window = (string) $order->get_meta( '_fleetbase_delivery_label', true );

		$html  = '<h3>' . esc_html__( 'Delivery', 'fleetbase-for-woocommerce' ) . '</h3>';
		$html .= '<p>' . esc_html__( 'Tracking number', 'fleetbase-for-woocommerce' ) . ': ';
		$html .= '' !== $url ? sprintf( '<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( $url ), esc_html( $number ) ) : esc_html( $number );
		$html .= '</p>';
		if ( '' !== $status ) {
			$html .= '<p class="fleetbase-wc-tracking__status">' . esc_html( StatusMap::label( $status ) ) . '</p>';
		}
		if ( '' !== $driver && StatusMap::is_in_transit( $status ) ) {
			/* translators: %s: driver first name */
			$html .= '<p>' . esc_html( sprintf( __( 'Your driver is %s.', 'fleetbase-for-woocommerce' ), strtok( $driver, ' ' ) ) ) . '</p>';
		}
		if ( '' !== $window ) {
			$html .= '<p>' . esc_html__( 'Delivery time', 'fleetbase-for-woocommerce' ) . ': ' . esc_html( $window ) . '</p>';
		}

		if ( $with_timeline ) {
			$html .= $this->timeline_html( $order, $status );
		}

		$proofs = $order->get_meta( '_fleetbase_proofs', true );
		if ( is_array( $proofs ) && [] !== $proofs ) {
			$html .= '<p class="fleetbase-wc-proofs">' . esc_html__( 'Proof of delivery', 'fleetbase-for-woocommerce' ) . ': ';
			foreach ( $proofs as $i => $proof ) {
				$html .= sprintf( '<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( (string) ( $proof['url'] ?? '' ) ), esc_html( (string) ( $i + 1 ) ) );
			}
			$html .= '</p>';
		}
		return $html;
	}

	private function timeline_html( \WC_Order $order, string $current ): string {
		$fleetbase_id = SyncState::fleetbase_id( $order );
		if ( '' === $fleetbase_id ) {
			return '';
		}
		$key   = 'fbwc_track_' . md5( $fleetbase_id );
		$items = get_transient( $key );
		if ( ! is_array( $items ) ) {
			$items = [];
			try {
				$data = Plugin::instance()->api()->orders()->find( $fleetbase_id );
				foreach ( (array) ( $data['tracking_statuses'] ?? [] ) as $ts ) {
					if ( ! is_array( $ts ) ) {
						continue;
					}
					$items[] = [
						'status'  => (string) ( $ts['status'] ?? '' ),
						'details' => (string) ( $ts['details'] ?? '' ),
						'code'    => (string) ( $ts['code'] ?? '' ),
						'time'    => (string) ( $ts['created_at'] ?? '' ),
					];
				}
				set_transient( $key, $items, MINUTE_IN_SECONDS );
			} catch ( \Throwable $e ) {
				return '';
			}
		}
		if ( [] === $items ) {
			return '';
		}
		usort(
			$items,
			static function ( array $a, array $b ): int {
				return strcmp( (string) $a['time'], (string) $b['time'] );
			}
		);
		$html = '<ul class="fleetbase-wc-timeline">';
		$last = array_key_last( $items );
		foreach ( $items as $i => $item ) {
			$time  = '' !== $item['time'] ? strtotime( $item['time'] ) : false;
			$html .= sprintf(
				'<li class="%s"><strong>%s</strong>%s%s</li>',
				$i === $last ? 'is-current' : '',
				esc_html( $item['status'] ),
				'' !== $item['details'] ? '<br>' . esc_html( $item['details'] ) : '',
				false !== $time ? '<time>' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $time ) ) . '</time>' : ''
			);
		}
		return $html . '</ul>';
	}
}
