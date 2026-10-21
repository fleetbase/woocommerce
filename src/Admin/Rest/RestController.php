<?php
/**
 * Base for the plugin's admin REST controllers.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Admin\Rest;

use Fleetbase\WooCommerce\Api\Exception\ApiException;
use Fleetbase\WooCommerce\Api\Exception\AuthenticationException;
use Fleetbase\WooCommerce\Api\Exception\TransportException;
use Fleetbase\WooCommerce\Hookable;
use Fleetbase\WooCommerce\Plugin;

abstract class RestController implements Hookable {

	public const NAMESPACE = 'fleetbase/v1';

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	abstract public function register_routes(): void;

	public function permission(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * @param array<string, mixed> $args
	 */
	protected function route( string $path, string $methods, callable $callback, array $args = [] ): void {
		register_rest_route(
			self::NAMESPACE,
			$path,
			[
				'methods'             => $methods,
				'callback'            => $callback,
				'permission_callback' => [ $this, 'permission' ],
				'args'                => $args,
			]
		);
	}

	/**
	 * Translate API failures into REST errors the UI can show.
	 */
	protected function error_from_exception( \Throwable $e ): \WP_Error {
		Plugin::instance()->logger()->warning( 'Admin request failed: ' . $e->getMessage(), [ 'exception' => get_class( $e ) ] );

		if ( $e instanceof AuthenticationException ) {
			return new \WP_Error( 'fleetbase_wc_auth', __( 'Fleetbase rejected this API key. Check that it was copied in full and has not expired.', 'fleetbase-for-woocommerce' ), [ 'status' => 400 ] );
		}
		if ( $e instanceof TransportException ) {
			return new \WP_Error( 'fleetbase_wc_unreachable', sprintf( /* translators: %s: error detail */ __( 'Could not reach the Fleetbase API: %s', 'fleetbase-for-woocommerce' ), $e->getMessage() ), [ 'status' => 502 ] );
		}
		if ( $e instanceof ApiException ) {
			return new \WP_Error(
				'fleetbase_wc_api',
				$e->user_message(),
				[
					'status'     => 400,
					'errors'     => $e->errors(),
					'api_status' => $e->status(),
				]
			);
		}
		return new \WP_Error( 'fleetbase_wc_error', __( 'Something went wrong. Check the Fleetbase log under WooCommerce > Status > Logs.', 'fleetbase-for-woocommerce' ), [ 'status' => 500 ] );
	}

	/**
	 * @param array<string, mixed> $data
	 */
	protected function respond( array $data, int $status = 200 ): \WP_REST_Response {
		return new \WP_REST_Response( $data, $status );
	}
}
