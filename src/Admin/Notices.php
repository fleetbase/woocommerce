<?php
/**
 * Admin notices and the post-activation redirect.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Admin;

use Fleetbase\WooCommerce\Hookable;
use Fleetbase\WooCommerce\Plugin;

final class Notices implements Hookable {

	public function register(): void {
		add_action( 'admin_init', [ $this, 'maybe_redirect_to_setup' ] );
		add_action( 'admin_notices', [ $this, 'maybe_render_not_connected' ] );
	}

	public function maybe_redirect_to_setup(): void {
		if ( ! get_transient( 'fleetbase_wc_activation_redirect' ) ) {
			return;
		}
		delete_transient( 'fleetbase_wc_activation_redirect' );

		if ( wp_doing_ajax() || is_network_admin() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		// Bulk activations should not hijack the screen.
		if ( isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( get_option( 'fleetbase_wc_wizard_completed' ) ) {
			return;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=fleetbase-setup' ) );
		exit;
	}

	public function maybe_render_not_connected(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( null === $screen ) {
			return;
		}
		$is_plugin_screen = str_contains( (string) $screen->id, 'fleetbase' );
		$is_wc_screen     = str_contains( (string) $screen->id, 'woocommerce' ) || str_contains( (string) $screen->id, 'shop_order' ) || 'plugins' === $screen->id;
		if ( $is_plugin_screen || ! $is_wc_screen ) {
			return;
		}
		if ( Plugin::instance()->connections()->is_connected() ) {
			return;
		}
		printf(
			'<div class="notice notice-info is-dismissible"><p><strong>%1$s</strong> %2$s <a href="%3$s" class="button button-primary" style="margin-left:8px">%4$s</a></p></div>',
			esc_html__( 'Fleetbase for WooCommerce', 'fleetbase-for-woocommerce' ),
			esc_html__( 'is installed but not connected yet. Connect your Fleetbase account to start offering delivery at checkout.', 'fleetbase-for-woocommerce' ),
			esc_url( admin_url( 'admin.php?page=fleetbase-setup' ) ),
			esc_html__( 'Run setup', 'fleetbase-for-woocommerce' )
		);
	}
}
