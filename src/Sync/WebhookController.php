<?php
/**
 * Receives Fleetbase webhooks. The random token in the URL is the authentication;
 * the body is treated as a hint and the order is re-fetched from the API.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Sync;

use Fleetbase\WooCommerce\Hookable;
use Fleetbase\WooCommerce\Plugin;
use Fleetbase\WooCommerce\Support\WebhookUrl;

final class WebhookController implements Hookable {

	public const OPTION_LAST = 'fleetbase_wc_webhook_last';

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		register_rest_route(
			'fleetbase/v1',
			'/webhook/(?P<token>[A-Za-z0-9]{16,80})',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'receive' ],
					'permission_callback' => '__return_true',
				],
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'ping' ],
					'permission_callback' => '__return_true',
				],
			]
		);
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function ping( \WP_REST_Request $request ) {
		if ( ! $this->authorized( $request ) ) {
			return new \WP_Error( 'not_found', 'Not found', [ 'status' => 404 ] );
		}
		return new \WP_REST_Response(
			[
				'ok'      => true,
				'plugin'  => 'fleetbase-for-woocommerce',
				'version' => FLEETBASE_WC_VERSION,
			]
		);
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function receive( \WP_REST_Request $request ) {
		if ( ! $this->authorized( $request ) ) {
			return new \WP_Error( 'not_found', 'Not found', [ 'status' => 404 ] );
		}

		$body  = $request->get_json_params();
		$body  = is_array( $body ) ? $body : [];
		$event = (string) ( $body['event'] ?? '' );
		$data  = isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : [];

		update_option(
			self::OPTION_LAST,
			[
				'time'  => time(),
				'event' => $event,
			],
			false
		);

		$order_id = self::order_id_from( $data );
		Plugin::instance()->logger()->debug(
			'Webhook received',
			[
				'event'           => $event,
				'fleetbase_order' => $order_id,
			]
		);

		if ( '' !== $order_id ) {
			Plugin::instance()->get( Reconciler::class )->enqueue( $order_id, 'webhook ' . $event );
		}

		return new \WP_REST_Response(
			[
				'received' => true,
				'event'    => $event,
				'order'    => $order_id,
			],
			200
		);
	}

	private function authorized( \WP_REST_Request $request ): bool {
		return Plugin::instance()->get( WebhookUrl::class )->matches( (string) $request->get_param( 'token' ) );
	}

	/**
	 * Order events carry the order as data.id; entity, waypoint and tracking events
	 * reference it through an "order" field.
	 *
	 * @param array<string, mixed> $data
	 */
	public static function order_id_from( array $data ): string {
		foreach ( [ 'id', 'public_id', 'order', 'order_id' ] as $key ) {
			$value = $data[ $key ] ?? null;
			if ( is_array( $value ) ) {
				$value = $value['id'] ?? $value['public_id'] ?? null;
			}
			if ( is_string( $value ) && str_starts_with( $value, 'order_' ) ) {
				return $value;
			}
		}
		return '';
	}

	/**
	 * @return array{time:int, event:string}|null
	 */
	public static function last(): ?array {
		$last = get_option( self::OPTION_LAST, null );
		return is_array( $last ) && isset( $last['time'] ) ? [
			'time'  => (int) $last['time'],
			'event' => (string) ( $last['event'] ?? '' ),
		] : null;
	}
}
