<?php
/**
 * The "not connected yet" notice on the Plugins screen.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Admin;

use Fleetbase\WooCommerce\Hookable;
use Fleetbase\WooCommerce\Plugin;

defined( 'ABSPATH' ) || exit;

final class Notices implements Hookable {

	public const DISMISS_ACTION = 'fleetbase_wc_dismiss_notice';

	private const DISMISSED_META = 'fleetbase_wc_notice_dismissed';

	public function register(): void {
		add_action( 'admin_notices', [ $this, 'maybe_render_not_connected' ] );
		add_action( 'admin_post_' . self::DISMISS_ACTION, [ $this, 'dismiss' ] );
	}

	/**
	 * Shown on the Plugins screen only, until the store is connected or the notice is dismissed.
	 */
	public function maybe_render_not_connected(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) || get_user_meta( get_current_user_id(), self::DISMISSED_META, true ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( null === $screen || 'plugins' !== $screen->id ) {
			return;
		}
		if ( Plugin::instance()->connections()->is_connected() ) {
			return;
		}
		$dismiss = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::DISMISS_ACTION ), self::DISMISS_ACTION );
		printf(
			'<div class="notice notice-info"><p><strong>%1$s</strong> %2$s <a href="%3$s">%4$s</a> | <a href="%5$s">%6$s</a></p></div>',
			esc_html__( 'Fleetbase for WooCommerce', 'fleetbase-for-woocommerce' ),
			esc_html__( 'is not connected yet.', 'fleetbase-for-woocommerce' ),
			esc_url( admin_url( 'admin.php?page=fleetbase-setup' ) ),
			esc_html__( 'Run setup', 'fleetbase-for-woocommerce' ),
			esc_url( $dismiss ),
			esc_html__( 'Dismiss', 'fleetbase-for-woocommerce' )
		);
	}

	public function dismiss(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'fleetbase-for-woocommerce' ), 403 );
		}
		check_admin_referer( self::DISMISS_ACTION );
		update_user_meta( get_current_user_id(), self::DISMISSED_META, 1 );
		wp_safe_redirect( wp_get_referer() ?: admin_url( 'plugins.php' ) );
		exit;
	}
}
