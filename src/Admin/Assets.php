<?php
/**
 * Enqueues the admin app and exposes runtime data to it.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Admin;

use Fleetbase\WooCommerce\Connection\ProviderRegistry;
use Fleetbase\WooCommerce\Hookable;
use Fleetbase\WooCommerce\Plugin;
use Fleetbase\WooCommerce\Support\Pickup;
use Fleetbase\WooCommerce\Support\StoreInfo;

final class Assets implements Hookable {

	public const HANDLE = 'fleetbase-wc-admin';

	public function register(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	public function enqueue( string $hook_suffix ): void {
		$menu = Plugin::instance()->get( Menu::class );
		if ( ! $menu->is_plugin_page( $hook_suffix ) ) {
			return;
		}

		$asset_file = FLEETBASE_WC_PATH . 'assets/build/admin.asset.php';
		$asset      = file_exists( $asset_file ) ? include $asset_file : [
			'dependencies' => [],
			'version'      => FLEETBASE_WC_VERSION,
		];

		wp_enqueue_script( self::HANDLE, FLEETBASE_WC_URL . 'assets/build/admin.js', $asset['dependencies'], (string) $asset['version'], true );
		wp_set_script_translations( self::HANDLE, 'fleetbase-for-woocommerce', FLEETBASE_WC_PATH . 'languages' );

		wp_enqueue_style( 'wp-components' );
		if ( file_exists( FLEETBASE_WC_PATH . 'assets/build/style-admin.css' ) ) {
			wp_enqueue_style( self::HANDLE, FLEETBASE_WC_URL . 'assets/build/style-admin.css', [ 'wp-components' ], (string) $asset['version'] );
			wp_style_add_data( self::HANDLE, 'rtl', 'replace' );
		}

		wp_add_inline_script( self::HANDLE, 'window.fleetbaseWc = ' . (string) wp_json_encode( $this->runtime_data() ) . ';', 'before' );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function runtime_data(): array {
		$plugin = Plugin::instance();
		$data   = [
			'version'                  => FLEETBASE_WC_VERSION,
			'restRoot'                 => esc_url_raw( rest_url( 'fleetbase/v1' ) ),
			'nonce'                    => wp_create_nonce( 'wp_rest' ),
			'adminUrl'                 => admin_url(),
			'siteUrl'                  => home_url( '/' ),
			'pages'                    => [
				'dashboard'  => admin_url( 'admin.php?page=fleetbase' ),
				'deliveries' => admin_url( 'admin.php?page=fleetbase-deliveries' ),
				'drivers'    => admin_url( 'admin.php?page=fleetbase-drivers' ),
				'vehicles'   => admin_url( 'admin.php?page=fleetbase-vehicles' ),
				'rates'      => admin_url( 'admin.php?page=fleetbase-service-rates' ),
				'setup'      => admin_url( 'admin.php?page=fleetbase-setup' ),
				'settings'   => admin_url( 'admin.php?page=fleetbase-settings' ),
				'shipping'   => admin_url( 'admin.php?page=wc-settings&tab=shipping' ),
				'logs'       => admin_url( 'admin.php?page=wc-status&tab=logs&source=fleetbase' ),
			],
			'consoleUrl'               => 'https://console.fleetbase.io',
			'signupUrl'                => 'https://console.fleetbase.io/onboard',
			'docsUrl'                  => 'https://fleetbase.io/docs/woocommerce',
			'providers'                => $plugin->get( ProviderRegistry::class )->describe(),
			'connection'               => $plugin->connections()->status(),
			'store'                    => StoreInfo::describe(),
			'pickup'                   => Pickup::get(),
			'webhookUrl'               => Plugin::instance()->get( \Fleetbase\WooCommerce\Support\WebhookUrl::class )->url(),
			'wizardCompleted'          => (bool) get_option( 'fleetbase_wc_wizard_completed' ),
			'shippingMethodConfigured' => StoreInfo::fleetbase_shipping_method_configured(),
			'webhookLast'              => \Fleetbase\WooCommerce\Sync\WebhookController::last(),
			'pollLast'                 => get_option( \Fleetbase\WooCommerce\Sync\Poller::OPTION_LAST, null ),
			'settings'                 => $plugin->settings()->all(),
			'capabilities'             => [
				'manage'   => \Fleetbase\WooCommerce\Support\Capabilities::can_manage(),
				'dispatch' => \Fleetbase\WooCommerce\Support\Capabilities::can_dispatch(),
			],
			'navigator'                => [
				'ios'     => 'https://apps.apple.com/app/fleetbase-navigator/id1554208255',
				'android' => 'https://play.google.com/store/apps/details?id=io.fleetbase.navigator',
			],
		];

		/**
		 * Filter the runtime data handed to the admin app.
		 *
		 * @param array<string, mixed> $data
		 */
		return (array) apply_filters( 'fleetbase_wc_admin_runtime_data', $data );
	}
}
