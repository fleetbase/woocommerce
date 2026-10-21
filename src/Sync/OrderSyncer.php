<?php
/**
 * Sends paid WooCommerce orders to Fleetbase through Action Scheduler, with retries and
 * a per-order state machine stored in order meta.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Sync;

use Fleetbase\WooCommerce\Api\Exception\ApiException;
use Fleetbase\WooCommerce\Api\Exception\AuthenticationException;
use Fleetbase\WooCommerce\Api\Exception\AuthorizationException;
use Fleetbase\WooCommerce\Api\Exception\ValidationException;
use Fleetbase\WooCommerce\Hookable;
use Fleetbase\WooCommerce\Plugin;
use Fleetbase\WooCommerce\Support\Pickup;

final class OrderSyncer implements Hookable {

	public const HOOK_SYNC   = 'fleetbase_wc_sync_order';
	public const HOOK_CANCEL = 'fleetbase_wc_cancel_order';
	public const GROUP       = 'fleetbase-wc';

	public const USER_META_CONTACT = '_fleetbase_contact_id';

	/** Seconds before each retry attempt (attempt 2, 3, 4, 5, 6). */
	public const RETRY_DELAYS = [ 60, 300, 1800, 7200, 28800 ];

	public function register(): void {
		add_action( 'woocommerce_payment_complete', [ $this, 'on_payment_complete' ] );
		add_action( 'woocommerce_order_status_changed', [ $this, 'on_status_changed' ], 10, 4 );
		add_action( self::HOOK_SYNC, [ $this, 'handle_sync' ], 10, 2 );
		add_action( self::HOOK_CANCEL, [ $this, 'handle_cancel' ], 10, 2 );
	}

	public function on_payment_complete( int $order_id ): void {
		$this->maybe_enqueue( $order_id, 'payment complete' );
	}

	/**
	 * @param int|string $order_id
	 * @param string     $from
	 * @param string     $to
	 * @param \WC_Order  $order
	 */
	public function on_status_changed( $order_id, $from, $to, $order ): void {
		$settings = Plugin::instance()->settings();
		$triggers = (array) $settings->get( 'sync.trigger_statuses', [ 'processing', 'completed' ] );
		if ( in_array( (string) $to, $triggers, true ) ) {
			$this->maybe_enqueue( (int) $order_id, 'status ' . $to );
			return;
		}
		if ( in_array( (string) $to, [ 'cancelled', 'refunded' ], true ) && $settings->get( 'sync.cancel_on_wc_cancel', true ) ) {
			if ( $order instanceof \WC_Order && SyncState::is_synced( $order ) ) {
				$this->enqueue_cancel( (int) $order_id, (string) $to );
			}
		}
	}

	/**
	 * Decide whether an order should go to Fleetbase and queue it once.
	 */
	public function maybe_enqueue( int $order_id, string $reason ): bool {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return false;
		}
		$verdict = $this->eligibility( $order );
		if ( true !== $verdict ) {
			if ( SyncState::SKIPPED === $verdict && '' === SyncState::state( $order ) ) {
				SyncState::set_state( $order, SyncState::SKIPPED );
				$order->save();
			}
			return false;
		}
		if ( SyncState::is_synced( $order ) ) {
			return false;
		}

		SyncState::set_state( $order, SyncState::PENDING );
		SyncState::log( $order, 'info', sprintf( 'Queued for Fleetbase (%s).', $reason ) );
		$order->save();

		$this->schedule( self::HOOK_SYNC, [ $order_id, 1 ], 0 );
		return true;
	}

	/**
	 * @return true|string True when eligible, otherwise a SyncState constant explaining why not.
	 */
	public function eligibility( \WC_Order $order ): bool|string {
		$plugin = Plugin::instance();
		if ( ! $plugin->connections()->is_connected() || '' === Pickup::place_id() ) {
			return SyncState::PENDING;
		}
		$settings = $plugin->settings();
		if ( ! OrderMapper::uses_fleetbase_shipping( $order ) && ! $settings->get( 'sync.sync_all_orders', false ) ) {
			return SyncState::SKIPPED;
		}
		if ( ! $this->mapper()->is_deliverable( $order ) ) {
			return SyncState::SKIPPED;
		}

		/**
		 * Filter whether an order should be sent to Fleetbase.
		 *
		 * @param bool      $eligible
		 * @param \WC_Order $order
		 */
		return apply_filters( 'fleetbase_wc_order_eligible', true, $order ) ? true : SyncState::SKIPPED;
	}

	/**
	 * Action Scheduler callback.
	 *
	 * @param int|string $order_id
	 * @param int|string $attempt
	 */
	public function handle_sync( $order_id, $attempt = 1 ): void {
		$order = wc_get_order( (int) $order_id );
		if ( $order instanceof \WC_Order ) {
			$this->sync( $order, (int) $attempt );
		}
	}

	/**
	 * @param int|string $order_id
	 * @param string     $reason
	 */
	public function handle_cancel( $order_id, $reason = '' ): void {
		$order = wc_get_order( (int) $order_id );
		if ( $order instanceof \WC_Order ) {
			$this->cancel( $order, (string) $reason );
		}
	}

	/**
	 * Create the Fleetbase order. Safe to call repeatedly: an order that already has a
	 * Fleetbase id is left alone unless $force is set, and a lookup by internal id runs
	 * before every create.
	 *
	 * @return bool True when the order is now synced.
	 */
	public function sync( \WC_Order $order, int $attempt = 1, bool $force = false ): bool {
		$plugin = Plugin::instance();
		$logger = $plugin->logger();

		if ( SyncState::is_synced( $order ) && ! $force ) {
			return true;
		}
		if ( ! $plugin->connections()->is_connected() ) {
			SyncState::set_state( $order, SyncState::FAILED, 'Fleetbase is not connected.' );
			SyncState::log( $order, 'error', 'Fleetbase is not connected.' );
			$order->save();
			return false;
		}

		SyncState::set_state( $order, SyncState::SYNCING );
		SyncState::set_attempts( $order, $attempt );
		$order->save();

		try {
			$api      = $plugin->api();
			$mapper   = $this->mapper( $this->cached_contact( $order ) );
			$existing = $this->find_existing( $order );
			if ( null !== $existing ) {
				SyncState::record_created( $order, $existing );
				SyncState::set_state( $order, SyncState::SYNCED );
				SyncState::log( $order, 'info', sprintf( 'Found existing Fleetbase order %s.', $existing['id'] ) );
				$order->save();
				return true;
			}

			$request = $mapper->map( $order );
			$cod     = $mapper->cod( $order );
			if ( null !== $cod ) {
				$payload            = $api->payloads()->create( array_merge( $request['payload'], $cod, [ 'type' => 'woocommerce' ] ) );
				$request['payload'] = (string) ( $payload['id'] ?? '' );
				if ( '' === $request['payload'] ) {
					throw new ApiException( 'Fleetbase did not return a payload id.' );
				}
			}

			/**
			 * Filter the Fleetbase order request before it is sent.
			 *
			 * @param array<string, mixed> $request
			 * @param \WC_Order            $order
			 */
			$request = (array) apply_filters( 'fleetbase_wc_order_request', $request, $order );

			$created = $api->orders()->create( $request );
			if ( empty( $created['id'] ) ) {
				throw new ApiException( 'Fleetbase did not return an order id.' );
			}

			SyncState::record_created( $order, $created );
			$this->remember_contact( $order, $created );

			$dropoff_ok = self::has_coordinates( $created );
			SyncState::set_state( $order, $dropoff_ok ? SyncState::SYNCED : SyncState::ATTENTION );
			SyncState::log( $order, 'info', sprintf( 'Created Fleetbase order %s.', $created['id'] ) );

			$tracking = (string) $order->get_meta( SyncState::META_TRACKING_URL, true );
			$note     = sprintf(
				/* translators: 1: Fleetbase order id, 2: tracking number */
				__( 'Sent to Fleetbase as %1$s. Tracking number %2$s.', 'fleetbase-for-woocommerce' ),
				(string) $created['id'],
				(string) $order->get_meta( SyncState::META_TRACKING_NUMBER, true )
			);
			if ( '' !== $tracking ) {
				$note .= ' ' . $tracking;
			}
			if ( ! $dropoff_ok ) {
				$note .= ' ' . __( 'Warning: Fleetbase could not locate the delivery address. Fix the dropoff in Fleetbase before dispatching.', 'fleetbase-for-woocommerce' );
				SyncState::log( $order, 'warning', 'Dropoff has no coordinates.' );
			}
			$order->add_order_note( $note );
			$order->save();

			$logger->info(
				'Order synced to Fleetbase',
				[
					'order_id'        => $order->get_id(),
					'fleetbase_order' => $created['id'],
				]
			);

			/**
			 * Fires after a WooCommerce order has been created in Fleetbase.
			 *
			 * @param \WC_Order            $order
			 * @param array<string, mixed> $created
			 */
			do_action( 'fleetbase_wc_order_synced', $order, $created );
			return true;
		} catch ( \Throwable $e ) {
			return $this->handle_failure( $order, $attempt, $e );
		}
	}

	private function handle_failure( \WC_Order $order, int $attempt, \Throwable $e ): bool {
		$message   = $e->getMessage();
		$permanent = $e instanceof ValidationException || $e instanceof AuthenticationException || $e instanceof AuthorizationException;
		$has_retry = ! $permanent && $attempt <= count( self::RETRY_DELAYS );

		Plugin::instance()->logger()->warning(
			'Order sync failed',
			[
				'order_id'  => $order->get_id(),
				'attempt'   => $attempt,
				'error'     => $message,
				'permanent' => $permanent,
			]
		);

		if ( $has_retry ) {
			$delay = self::RETRY_DELAYS[ $attempt - 1 ];
			SyncState::set_state( $order, SyncState::PENDING, $message );
			SyncState::log( $order, 'warning', sprintf( 'Attempt %d failed: %s. Retrying in %d minutes.', $attempt, $message, (int) ( $delay / 60 ) ) );
			$order->save();
			$this->schedule( self::HOOK_SYNC, [ $order->get_id(), $attempt + 1 ], $delay );
			return false;
		}

		SyncState::set_state( $order, SyncState::FAILED, $message );
		SyncState::log( $order, 'error', sprintf( 'Giving up after attempt %d: %s', $attempt, $message ) );
		$order->add_order_note(
			sprintf(
				/* translators: %s: error message */
				__( 'Could not send this order to Fleetbase: %s. Use "Send to Fleetbase" in order actions to retry.', 'fleetbase-for-woocommerce' ),
				$message
			)
		);
		$order->save();
		return false;
	}

	public function cancel( \WC_Order $order, string $reason = '' ): bool {
		$fleetbase_id = SyncState::fleetbase_id( $order );
		if ( '' === $fleetbase_id ) {
			return false;
		}
		$status = (string) $order->get_meta( SyncState::META_STATUS, true );
		if ( in_array( $status, [ 'completed', 'canceled', 'cancelled' ], true ) ) {
			return false;
		}
		try {
			Plugin::instance()->api()->orders()->cancel( $fleetbase_id, '' !== $reason ? sprintf( 'WooCommerce order %s', $reason ) : 'Cancelled in WooCommerce' );
			$order->update_meta_data( SyncState::META_STATUS, 'canceled' );
			SyncState::log( $order, 'info', 'Cancelled in Fleetbase.' );
			$order->add_order_note( __( 'Delivery cancelled in Fleetbase.', 'fleetbase-for-woocommerce' ) );
			$order->save();
			return true;
		} catch ( \Throwable $e ) {
			SyncState::log( $order, 'error', 'Cancel failed: ' . $e->getMessage() );
			$order->save();
			Plugin::instance()->logger()->warning(
				'Fleetbase cancel failed',
				[
					'order_id' => $order->get_id(),
					'error'    => $e->getMessage(),
				]
			);
			return false;
		}
	}

	public function enqueue_cancel( int $order_id, string $reason ): void {
		$this->schedule( self::HOOK_CANCEL, [ $order_id, $reason ], 0 );
	}

	/**
	 * Look for a Fleetbase order already carrying this order number.
	 *
	 * @return array<string, mixed>|null
	 */
	private function find_existing( \WC_Order $order ): ?array {
		$number = (string) $order->get_order_number();
		try {
			$matches = Plugin::instance()->api()->orders()->all(
				[
					'internal_id' => $number,
					'limit'       => 5,
				]
			);
		} catch ( \Throwable $e ) {
			return null;
		}
		foreach ( $matches as $match ) {
			if ( (string) ( $match['internal_id'] ?? '' ) === $number && ! empty( $match['id'] ) ) {
				return $match;
			}
		}
		return null;
	}

	private function cached_contact( \WC_Order $order ): string {
		$user = (int) $order->get_customer_id();
		if ( $user <= 0 ) {
			return '';
		}
		$contact = (string) get_user_meta( $user, self::USER_META_CONTACT, true );
		return str_starts_with( $contact, 'contact_' ) ? $contact : '';
	}

	/**
	 * @param array<string, mixed> $created
	 */
	private function remember_contact( \WC_Order $order, array $created ): void {
		$user = (int) $order->get_customer_id();
		if ( $user <= 0 || ! isset( $created['customer'] ) || ! is_array( $created['customer'] ) ) {
			return;
		}
		$id = (string) ( $created['customer']['id'] ?? '' );
		if ( str_starts_with( $id, 'contact_' ) ) {
			update_user_meta( $user, self::USER_META_CONTACT, $id );
		}
	}

	/**
	 * @param array<string, mixed> $created
	 */
	public static function has_coordinates( array $created ): bool {
		$dropoff = $created['payload']['dropoff'] ?? null;
		if ( ! is_array( $dropoff ) ) {
			return true;
		}
		$location = $dropoff['location'] ?? null;
		if ( ! is_array( $location ) ) {
			return true;
		}
		$coords = $location['coordinates'] ?? null;
		if ( is_array( $coords ) && count( $coords ) >= 2 ) {
			return 0.0 !== (float) $coords[0] || 0.0 !== (float) $coords[1];
		}
		if ( isset( $location['latitude'], $location['longitude'] ) ) {
			return 0.0 !== (float) $location['latitude'] || 0.0 !== (float) $location['longitude'];
		}
		return true;
	}

	public function mapper( string $contact_id = '' ): OrderMapper {
		$settings = Plugin::instance()->settings();
		return new OrderMapper(
			[
				'pickup_place_id' => Pickup::place_id(),
				'currency'        => get_woocommerce_currency(),
				'weight_unit'     => (string) get_option( 'woocommerce_weight_unit', 'kg' ),
				'dimension_unit'  => (string) get_option( 'woocommerce_dimension_unit', 'cm' ),
				'admin_url'       => admin_url(),
				'order_type'      => (string) $settings->get( 'sync.order_type', 'transport' ),
				'pod_required'    => (bool) $settings->get( 'sync.pod_required', false ),
				'pod_method'      => (string) $settings->get( 'sync.pod_method', 'photo' ),
				'auto_dispatch'   => (bool) $settings->get( 'sync.auto_dispatch', false ),
				'contact_id'      => $contact_id,
			]
		);
	}

	/**
	 * Queue through Action Scheduler when available, otherwise run inline.
	 *
	 * @param array<int, mixed> $args
	 */
	private function schedule( string $hook, array $args, int $delay ): void {
		if ( function_exists( 'as_schedule_single_action' ) && function_exists( 'as_has_scheduled_action' ) ) {
			if ( $delay <= 0 && function_exists( 'as_enqueue_async_action' ) ) {
				if ( ! as_has_scheduled_action( $hook, $args, self::GROUP ) ) {
					as_enqueue_async_action( $hook, $args, self::GROUP, true );
				}
				return;
			}
			as_schedule_single_action( time() + $delay, $hook, $args, self::GROUP, true );
			return;
		}
		/** Runs the plugin's own sync or cancel hook inline when Action Scheduler is unavailable. */
		do_action( $hook, ...$args ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Own hook.
	}
}
