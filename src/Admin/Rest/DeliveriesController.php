<?php
/**
 * The Deliveries page: WooCommerce orders with a Fleetbase delivery, and dispatcher actions.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Admin\Rest;

use Fleetbase\WooCommerce\Admin\DeliveriesQuery;
use Fleetbase\WooCommerce\Checkout\DeliveryChoice;
use Fleetbase\WooCommerce\Plugin;
use Fleetbase\WooCommerce\Sync\OrderSyncer;
use Fleetbase\WooCommerce\Sync\Reconciler;
use Fleetbase\WooCommerce\Sync\StatusMap;
use Fleetbase\WooCommerce\Sync\SyncState;

final class DeliveriesController extends RestController {

	public function register_routes(): void {
		$this->route( '/deliveries', 'GET', [ $this, 'index' ], [], true );
		$this->route( '/deliveries/(?P<id>\d+)/assign', 'POST', [ $this, 'assign' ], [], true );
		$this->route( '/deliveries/(?P<id>\d+)/dispatch', 'POST', [ $this, 'dispatch' ], [], true );
		$this->route( '/deliveries/(?P<id>\d+)/cancel', 'POST', [ $this, 'cancel' ], [], true );
		$this->route( '/deliveries/(?P<id>\d+)/resync', 'POST', [ $this, 'resync' ], [], true );
		$this->route( '/deliveries/(?P<id>\d+)/refresh', 'POST', [ $this, 'refresh' ], [], true );
	}

	public function index( \WP_REST_Request $request ): \WP_REST_Response {
		$result = wc_get_orders( DeliveriesQuery::args( (array) $request->get_params() ) );
		$orders = is_object( $result ) && isset( $result->orders ) ? (array) $result->orders : (array) $result;
		$total  = is_object( $result ) && isset( $result->total ) ? (int) $result->total : count( $orders );
		$pages  = is_object( $result ) && isset( $result->max_num_pages ) ? (int) $result->max_num_pages : 1;

		$rows = [];
		foreach ( $orders as $order ) {
			if ( $order instanceof \WC_Order ) {
				$rows[] = self::shape( $order );
			}
		}
		return $this->respond(
			[
				'deliveries' => $rows,
				'total'      => $total,
				'pages'      => $pages,
			]
		);
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function assign( \WP_REST_Request $request ) {
		$order = $this->order( $request );
		if ( is_wp_error( $order ) ) {
			return $order;
		}
		$driver = sanitize_text_field( (string) ( $this->input( $request )['driver'] ?? '' ) );
		if ( '' === $driver ) {
			return new \WP_Error( 'fleetbase_wc_driver', __( 'Choose a driver.', 'fleetbase-for-woocommerce' ), [ 'status' => 400 ] );
		}
		try {
			$data = Plugin::instance()->api()->orders()->update( SyncState::fleetbase_id( $order ), [ 'driver' => $driver ] );
		} catch ( \Throwable $e ) {
			return $this->error_from_exception( $e );
		}
		Plugin::instance()->get( Reconciler::class )->apply( $order, $data, 'assign' );
		return $this->respond( [ 'delivery' => self::shape( wc_get_order( $order->get_id() ) ?: $order ) ] );
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function dispatch( \WP_REST_Request $request ) {
		$order = $this->order( $request );
		if ( is_wp_error( $order ) ) {
			return $order;
		}
		try {
			$data = Plugin::instance()->api()->orders()->dispatch( SyncState::fleetbase_id( $order ) );
		} catch ( \Throwable $e ) {
			return $this->error_from_exception( $e );
		}
		Plugin::instance()->get( Reconciler::class )->apply( $order, $data, 'dispatch' );
		return $this->respond( [ 'delivery' => self::shape( wc_get_order( $order->get_id() ) ?: $order ) ] );
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function cancel( \WP_REST_Request $request ) {
		$order = $this->order( $request );
		if ( is_wp_error( $order ) ) {
			return $order;
		}
		$ok = Plugin::instance()->get( OrderSyncer::class )->cancel( $order, 'cancelled by dispatcher' );
		if ( ! $ok ) {
			return new \WP_Error( 'fleetbase_wc_cancel', __( 'The delivery could not be cancelled. It may already be completed.', 'fleetbase-for-woocommerce' ), [ 'status' => 400 ] );
		}
		return $this->respond( [ 'delivery' => self::shape( wc_get_order( $order->get_id() ) ?: $order ) ] );
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function resync( \WP_REST_Request $request ) {
		$order = wc_get_order( (int) $request->get_param( 'id' ) );
		if ( ! $order instanceof \WC_Order ) {
			return new \WP_Error( 'fleetbase_wc_order', __( 'Order not found.', 'fleetbase-for-woocommerce' ), [ 'status' => 404 ] );
		}
		$ok    = Plugin::instance()->get( OrderSyncer::class )->sync( $order, 1, true );
		$order = wc_get_order( $order->get_id() ) ?: $order;
		if ( ! $ok ) {
			return new \WP_Error( 'fleetbase_wc_sync', (string) $order->get_meta( SyncState::META_LAST_ERROR, true ) ?: __( 'Sync failed.', 'fleetbase-for-woocommerce' ), [ 'status' => 400 ] );
		}
		return $this->respond( [ 'delivery' => self::shape( $order ) ] );
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function refresh( \WP_REST_Request $request ) {
		$order = $this->order( $request );
		if ( is_wp_error( $order ) ) {
			return $order;
		}
		Plugin::instance()->get( Reconciler::class )->reconcile( $order, 'manual' );
		return $this->respond( [ 'delivery' => self::shape( wc_get_order( $order->get_id() ) ?: $order ) ] );
	}

	/**
	 * @return \WC_Order|\WP_Error
	 */
	private function order( \WP_REST_Request $request ) {
		$order = wc_get_order( (int) $request->get_param( 'id' ) );
		if ( ! $order instanceof \WC_Order ) {
			return new \WP_Error( 'fleetbase_wc_order', __( 'Order not found.', 'fleetbase-for-woocommerce' ), [ 'status' => 404 ] );
		}
		if ( '' === SyncState::fleetbase_id( $order ) ) {
			return new \WP_Error( 'fleetbase_wc_order', __( 'This order has not been sent to Fleetbase yet.', 'fleetbase-for-woocommerce' ), [ 'status' => 400 ] );
		}
		return $order;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function shape( \WC_Order $order ): array {
		$fb_status = (string) $order->get_meta( SyncState::META_STATUS, true );
		$name      = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() );
		if ( '' === $name ) {
			$name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		}
		return [
			'id'               => $order->get_id(),
			'number'           => (string) $order->get_order_number(),
			'customer'         => $name,
			'address'          => trim( implode( ', ', array_filter( [ $order->get_shipping_address_1(), $order->get_shipping_city(), $order->get_shipping_postcode() ] ) ) ),
			'wc_status'        => (string) $order->get_status(),
			'wc_status_label'  => wc_get_order_status_name( $order->get_status() ),
			'fleetbase_id'     => SyncState::fleetbase_id( $order ),
			'fleetbase_status' => $fb_status,
			'fleetbase_label'  => '' !== $fb_status ? StatusMap::label( $fb_status ) : '',
			'sync_state'       => SyncState::state( $order ),
			'sync_label'       => SyncState::label( SyncState::state( $order ) ),
			'driver'           => (string) $order->get_meta( SyncState::META_DRIVER, true ),
			'tracking_number'  => (string) $order->get_meta( SyncState::META_TRACKING_NUMBER, true ),
			'tracking_url'     => (string) $order->get_meta( SyncState::META_TRACKING_URL, true ),
			'window'           => DeliveryChoice::order_label( $order ),
			'total'            => wp_strip_all_tags( (string) $order->get_formatted_order_total() ),
			'date'             => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'M j, H:i' ) : '',
			'admin_url'        => admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order->get_id() ),
			'can_dispatch'     => '' !== SyncState::fleetbase_id( $order ) && StatusMap::is_open( $fb_status ) && ! StatusMap::is_in_transit( $fb_status ),
			'can_cancel'       => '' !== SyncState::fleetbase_id( $order ) && StatusMap::is_open( $fb_status ),
			'last_error'       => (string) $order->get_meta( SyncState::META_LAST_ERROR, true ),
		];
	}
}
