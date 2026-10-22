<?php
/**
 * Cart and Checkout block integration: the delivery-time inner block and Store API callbacks.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Checkout;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;
use Fleetbase\WooCommerce\Hookable;
use Fleetbase\WooCommerce\Shipping\NoService;

final class BlocksIntegration implements Hookable, IntegrationInterface {

	public const FRONTEND_HANDLE = 'fleetbase-wc-checkout-frontend';
	public const EDITOR_HANDLE   = 'fleetbase-wc-checkout-editor';

	public const STYLE_HANDLE = 'fleetbase-wc-checkout';

	public function register(): void {
		add_action( 'init', [ $this, 'register_block_type' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_style' ] );
		add_action(
			'woocommerce_blocks_checkout_block_registration',
			static function ( $registry ): void {
				$registry->register( new self() );
			}
		);
		// WooCommerce fires this before plugins_loaded priority 20, so register directly when it already ran.
		if ( did_action( 'woocommerce_blocks_loaded' ) ) {
			$this->register_store_api();
		} else {
			add_action( 'woocommerce_blocks_loaded', [ $this, 'register_store_api' ] );
		}
	}

	public function register_block_type(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		$this->register_scripts();
		register_block_type(
			FLEETBASE_WC_PATH . 'assets/src/checkout',
			[
				'editor_script' => self::EDITOR_HANDLE,
			]
		);
	}

	/**
	 * The block is forced into the checkout at render time, so WordPress never sees it in saved
	 * content and would not enqueue its stylesheet on its own.
	 */
	public function enqueue_style(): void {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}
		$file = FLEETBASE_WC_PATH . 'assets/build/style-checkout-frontend.css';
		if ( ! file_exists( $file ) ) {
			return;
		}
		wp_enqueue_style( self::STYLE_HANDLE, FLEETBASE_WC_URL . 'assets/build/style-checkout-frontend.css', [], (string) filemtime( $file ) );
		wp_style_add_data( self::STYLE_HANDLE, 'rtl', 'replace' );
	}

	public function get_name(): string {
		return 'fleetbase';
	}

	public function initialize(): void {
		$this->register_scripts();
	}

	/**
	 * @return array<int, string>
	 */
	public function get_script_handles(): array {
		return [ self::FRONTEND_HANDLE ];
	}

	/**
	 * @return array<int, string>
	 */
	public function get_editor_script_handles(): array {
		return [ self::EDITOR_HANDLE ];
	}

	/**
	 * Available in JS as getSetting( 'fleetbase_data' ).
	 *
	 * @return array<string, mixed>
	 */
	public function get_script_data(): array {
		$scheduling = Scheduling::from_settings();
		return [
			'schedulingEnabled' => $scheduling->enabled(),
			'asapLabel'         => $scheduling->asap_label(),
			'dates'             => $scheduling->dates(),
			'selected'          => DeliveryChoice::current_value(),
			'noServiceMessage'  => NoService::message(),
			'labels'            => [
				'title'  => __( 'Delivery time', 'fleetbase-for-woocommerce' ),
				'asap'   => __( 'As soon as possible', 'fleetbase-for-woocommerce' ),
				'choose' => __( 'Choose a day and time', 'fleetbase-for-woocommerce' ),
				'day'    => __( 'Day', 'fleetbase-for-woocommerce' ),
				'window' => __( 'Time', 'fleetbase-for-woocommerce' ),
				'none'   => __( 'No delivery times are available right now.', 'fleetbase-for-woocommerce' ),
			],
		];
	}

	private function register_scripts(): void {
		if ( wp_script_is( self::FRONTEND_HANDLE, 'registered' ) ) {
			return;
		}
		foreach ( [
			self::FRONTEND_HANDLE => 'checkout-frontend',
			self::EDITOR_HANDLE   => 'checkout-editor',
		] as $handle => $file ) {
			$asset_file = FLEETBASE_WC_PATH . 'assets/build/' . $file . '.asset.php';
			$asset      = file_exists( $asset_file ) ? include $asset_file : [
				'dependencies' => [],
				'version'      => FLEETBASE_WC_VERSION,
			];
			$deps       = array_values( array_unique( array_merge( (array) $asset['dependencies'], [ 'wc-blocks-checkout', 'wc-settings', 'wp-data' ] ) ) );
			wp_register_script( $handle, FLEETBASE_WC_URL . 'assets/build/' . $file . '.js', $deps, (string) $asset['version'], true );
			wp_set_script_translations( $handle, 'fleetbase-for-woocommerce', FLEETBASE_WC_PATH . 'languages' );
		}
	}

	/**
	 * Store API: react to the customer's choice (so quotes refresh) and persist it on the order.
	 */
	public function register_store_api(): void {
		// Declare the checkout extension data, otherwise the Store API strips it from the checkout request.
		if ( class_exists( \Automattic\WooCommerce\StoreApi\StoreApi::class ) && class_exists( \Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema::class ) ) {
			try {
				\Automattic\WooCommerce\StoreApi\StoreApi::container()->get( \Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema::class )->register_endpoint_data(
					[
						'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema::IDENTIFIER,
						'namespace'       => 'fleetbase',
						'schema_callback' => static function (): array {
							return [
								'delivery' => [
									'description' => 'Delivery time choice: "asap" or a date and window.',
									'type'        => 'string',
									'context'     => [ 'view', 'edit' ],
									'optional'    => true,
								],
							];
						},
						'schema_type'     => ARRAY_A,
					]
				);
			} catch ( \Throwable $e ) {
				\Fleetbase\WooCommerce\Plugin::instance()->logger()->debug( 'Checkout extension schema not registered', [ 'error' => $e->getMessage() ] );
			}
		}

		if ( function_exists( 'woocommerce_store_api_register_update_callback' ) ) {
			woocommerce_store_api_register_update_callback(
				[
					'namespace' => 'fleetbase',
					'callback'  => static function ( $data ): void {
						$value = is_array( $data ) ? (string) ( $data['delivery'] ?? '' ) : '';
						DeliveryChoice::set( $value );
						if ( function_exists( 'WC' ) && WC()->cart ) {
							WC()->cart->calculate_shipping();
							WC()->cart->calculate_totals();
						}
					},
				]
			);
		}

		add_action(
			'woocommerce_store_api_checkout_update_order_from_request',
			static function ( \WC_Order $order, \WP_REST_Request $request ): void {
				$extensions = (array) $request->get_param( 'extensions' );
				$value      = (string) ( $extensions['fleetbase']['delivery'] ?? DeliveryChoice::current_value() );
				DeliveryChoice::save_to_order( $order, $value );
			},
			10,
			2
		);

		add_action(
			'woocommerce_store_api_checkout_order_processed',
			static function (): void {
				if ( function_exists( 'WC' ) && WC()->session ) {
					WC()->session->set( DeliveryChoice::SESSION_VALUE, '' );
					WC()->session->set( DeliveryChoice::SESSION_SCHEDULED_AT, '' );
				}
			}
		);
	}
}
