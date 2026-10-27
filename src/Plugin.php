<?php
/**
 * Plugin container and boot sequence.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce;

use Fleetbase\WooCommerce\Admin\Settings;
use Fleetbase\WooCommerce\Api\Client;
use Fleetbase\WooCommerce\Api\Connection;
use Fleetbase\WooCommerce\Api\Fleetbase;
use Fleetbase\WooCommerce\Api\WpHttpTransport;
use Fleetbase\WooCommerce\Support\ConnectionStore;
use Fleetbase\WooCommerce\Support\Logger;

final class Plugin {

	private static ?Plugin $instance = null;

	/** @var array<string, object> */
	private array $services = [];

	private bool $booted = false;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Classes that attach hooks, in boot order.
	 *
	 * @return array<int, class-string<Hookable>>
	 */
	private function hookables(): array {
		$hookables = [
			Support\Compat::class,
			Support\Capabilities::class,
			Admin\Notices::class,
			Admin\Menu::class,
			Admin\Assets::class,
			Admin\Rest\ConnectionController::class,
			Admin\Rest\PlacesController::class,
			Admin\Rest\SettingsController::class,
			Admin\Rest\DriversController::class,
			Admin\Rest\VehiclesController::class,
			Admin\Rest\ServiceRatesController::class,
			Admin\Rest\DeliveriesController::class,
			Shipping\ShippingMethods::class,
			Shipping\NoService::class,
			Checkout\BlocksIntegration::class,
			Checkout\ClassicCheckout::class,
			Checkout\OrderDelivery::class,
			Frontend\Tracking::class,
			Frontend\Emails\EmailRegistrar::class,
			Sync\OrderSyncer::class,
			Sync\Reconciler::class,
			Sync\WebhookController::class,
			Sync\WebhookRegistrar::class,
			\Fleetbase\WooCommerce\Connection\OAuth\OAuthFlow::class,
			Sync\Poller::class,
			Orders\Statuses::class,
			Orders\OrderActions::class,
			Orders\MetaBox::class,
			Orders\ListColumn::class,
		];

		/**
		 * Filter the list of hookable service classes booted by the plugin.
		 *
		 * @param array<int, class-string<Hookable>> $hookables
		 */
		return (array) apply_filters( 'fleetbase_wc_hookables', $hookables );
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		if ( ! $this->requirements_met() ) {
			add_action( 'admin_notices', [ $this, 'render_requirements_notice' ] );
			return;
		}

		foreach ( $this->hookables() as $class_name ) {
			if ( class_exists( $class_name ) ) {
				$this->get( $class_name )->register();
			}
		}

		/**
		 * Fires once Fleetbase for WooCommerce has booted.
		 *
		 * @param Plugin $plugin
		 */
		do_action( 'fleetbase_wc_booted', $this );
	}

	/**
	 * Resolve a lazily constructed singleton. Services are plain classes with
	 * no constructor arguments, or ones registered explicitly via set().
	 *
	 * @template T of object
	 * @param class-string<T> $class_name
	 * @return T
	 */
	public function get( string $class_name ): object {
		if ( ! isset( $this->services[ $class_name ] ) ) {
			$this->services[ $class_name ] = $this->build( $class_name );
		}
		/** @var T */
		return $this->services[ $class_name ];
	}

	/**
	 * Register an explicit instance (used by tests and add-ons).
	 */
	public function set( string $class_name, object $instance ): void {
		$this->services[ $class_name ] = $instance;
	}

	private function build( string $class_name ): object {
		switch ( $class_name ) {
			case Logger::class:
				return new Logger();
			case Settings::class:
				return new Settings();
			case ConnectionStore::class:
				return new ConnectionStore();
			default:
				return new $class_name();
		}
	}

	public function logger(): Logger {
		return $this->get( Logger::class );
	}

	public function settings(): Settings {
		return $this->get( Settings::class );
	}

	public function connections(): ConnectionStore {
		return $this->get( ConnectionStore::class );
	}

	/**
	 * Build an API facade for the stored connection, or for an explicit one.
	 *
	 * @throws \RuntimeException When no connection is configured.
	 */
	public function api( ?Connection $connection = null ): Fleetbase {
		$connection = $connection ?? $this->connections()->connection();
		if ( null === $connection ) {
			throw new \RuntimeException( 'Fleetbase is not connected.' );
		}

		$client = new Client(
			$connection,
			new WpHttpTransport(),
			$this->logger(),
			[
				'user_agent' => $this->user_agent(),
			]
		);

		return new Fleetbase( $client );
	}

	public function user_agent(): string {
		$wc_version = defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown';
		return sprintf(
			'fleetbase-for-woocommerce/%s (WordPress/%s; WooCommerce/%s; +%s)',
			FLEETBASE_WC_VERSION,
			get_bloginfo( 'version' ),
			$wc_version,
			home_url( '/' )
		);
	}

	public function requirements_met(): bool {
		if ( ! class_exists( 'WooCommerce' ) || ! defined( 'WC_VERSION' ) ) {
			return false;
		}
		return version_compare( WC_VERSION, FLEETBASE_WC_MIN_WC_VERSION, '>=' );
	}

	public function render_requirements_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: minimum WooCommerce version */
					__( 'Fleetbase for WooCommerce requires WooCommerce %s or newer to be installed and active.', 'fleetbase-for-woocommerce' ),
					FLEETBASE_WC_MIN_WC_VERSION
				)
			)
		);
	}

	public static function activate(): void {
		if ( ! get_option( 'fleetbase_wc_site_token' ) ) {
			add_option( 'fleetbase_wc_site_token', wp_generate_password( 40, false, false ), '', false );
		}
		if ( ! get_option( 'fleetbase_wc_settings' ) ) {
			add_option( 'fleetbase_wc_settings', Settings::defaults(), '', true );
		}
		update_option( 'fleetbase_wc_version', FLEETBASE_WC_VERSION, true );
		Support\Capabilities::ensure();
		set_transient( 'fleetbase_wc_activation_redirect', 1, 60 );
	}

	public static function deactivate(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( '', [], 'fleetbase-wc' );
		}
	}
}
