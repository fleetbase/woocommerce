<?php
/**
 * Admin menu and page containers for the React app.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Admin;

use Fleetbase\WooCommerce\Hookable;

final class Menu implements Hookable {

	public const CAPABILITY = 'manage_woocommerce';

	/** @var array<string, array{title:string, menu:string, page:string}> */
	private array $pages = [];

	public function register(): void {
		$this->pages = [
			'fleetbase'          => [
				'title' => __( 'Fleetbase', 'fleetbase-for-woocommerce' ),
				'menu'  => __( 'Dashboard', 'fleetbase-for-woocommerce' ),
				'page'  => 'dashboard',
			],
			'fleetbase-setup'    => [
				'title' => __( 'Fleetbase setup', 'fleetbase-for-woocommerce' ),
				'menu'  => __( 'Setup', 'fleetbase-for-woocommerce' ),
				'page'  => 'setup',
			],
			'fleetbase-settings' => [
				'title' => __( 'Fleetbase settings', 'fleetbase-for-woocommerce' ),
				'menu'  => __( 'Settings', 'fleetbase-for-woocommerce' ),
				'page'  => 'settings',
			],
		];

		/**
		 * Filter the admin pages registered under the Fleetbase menu.
		 *
		 * @param array<string, array{title:string, menu:string, page:string}> $pages
		 */
		$this->pages = (array) apply_filters( 'fleetbase_wc_admin_pages', $this->pages );

		add_action( 'admin_menu', [ $this, 'add_menu' ], 56 );
	}

	public function add_menu(): void {
		add_menu_page(
			__( 'Fleetbase', 'fleetbase-for-woocommerce' ),
			__( 'Fleetbase', 'fleetbase-for-woocommerce' ),
			self::CAPABILITY,
			'fleetbase',
			[ $this, 'render' ],
			$this->icon(),
			56
		);
		foreach ( $this->pages as $slug => $page ) {
			add_submenu_page( 'fleetbase', $page['title'], $page['menu'], self::CAPABILITY, $slug, [ $this, 'render' ] );
		}
	}

	public function render(): void {
		$slug = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : 'fleetbase'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = $this->pages[ $slug ]['page'] ?? 'dashboard';
		printf(
			'<div class="wrap fleetbase-wc-wrap"><div id="fleetbase-wc-admin" data-page="%s"><div class="fleetbase-wc-loading"><span class="spinner is-active"></span> %s</div></div></div>',
			esc_attr( $page ),
			esc_html__( 'Loading Fleetbase…', 'fleetbase-for-woocommerce' )
		);
	}

	/**
	 * @return array<string, array{title:string, menu:string, page:string}>
	 */
	public function pages(): array {
		return $this->pages;
	}

	public function is_plugin_page( string $hook_suffix ): bool {
		foreach ( array_keys( $this->pages ) as $slug ) {
			if ( str_ends_with( $hook_suffix, '_page_' . $slug ) || 'toplevel_page_' . $slug === $hook_suffix ) {
				return true;
			}
		}
		return false;
	}

	private function icon(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="#a7aaad" d="M3 6.5A2.5 2.5 0 0 1 5.5 4h7A2.5 2.5 0 0 1 15 6.5V8h2.6a2 2 0 0 1 1.6.8l2.4 3.2a2 2 0 0 1 .4 1.2V16a2 2 0 0 1-2 2h-1.1a2.5 2.5 0 0 1-4.8 0H9.9a2.5 2.5 0 0 1-4.8 0H5a2 2 0 0 1-2-2V6.5Zm12 3.5v3h4.2l-2.1-2.8a.5.5 0 0 0-.4-.2H15ZM7.5 19a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm9 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}
}
