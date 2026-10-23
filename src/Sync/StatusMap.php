<?php
/**
 * Pure mapping from Fleetbase order statuses to WooCommerce order statuses.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Sync;

final class StatusMap {

	public const OUT_FOR_DELIVERY = 'out-for-delivery';

	/** Fleetbase statuses that mean the driver is on the way. */
	public const IN_TRANSIT = [ 'dispatched', 'started', 'enroute', 'en_route', 'in_progress', 'picked_up', 'driver_enroute' ];

	public const DONE = [ 'completed', 'delivered' ];

	public const CANCELED = [ 'canceled', 'cancelled' ];

	/**
	 * The WooCommerce status a Fleetbase status should move the order to, or null for none.
	 *
	 * @param array<string, mixed> $settings The statuses.* settings group.
	 */
	public static function target( string $fleetbase_status, array $settings ): ?string {
		$status = strtolower( trim( $fleetbase_status ) );
		if ( in_array( $status, self::IN_TRANSIT, true ) ) {
			return ! empty( $settings['use_out_for_delivery'] ) ? self::OUT_FOR_DELIVERY : null;
		}
		if ( in_array( $status, self::DONE, true ) ) {
			return 'completed' === ( $settings['on_completed'] ?? 'completed' ) ? 'completed' : null;
		}
		if ( in_array( $status, self::CANCELED, true ) ) {
			return 'cancelled' === ( $settings['on_canceled'] ?? 'note' ) ? 'cancelled' : null;
		}
		return null;
	}

	/**
	 * Whether WooCommerce may move from its current status to the target. Never reopens
	 * finished orders and never moves backwards.
	 */
	public static function may_transition( string $current, string $target ): bool {
		$current = str_replace( 'wc-', '', $current );
		if ( $current === $target ) {
			return false;
		}
		$closed = [ 'completed', 'cancelled', 'refunded', 'failed', 'trash', 'checkout-draft' ];
		if ( in_array( $current, $closed, true ) ) {
			return false;
		}
		switch ( $target ) {
			case self::OUT_FOR_DELIVERY:
				return in_array( $current, [ 'processing', 'on-hold', 'pending' ], true );
			case 'completed':
				return in_array( $current, [ 'processing', 'on-hold', 'pending', self::OUT_FOR_DELIVERY ], true );
			case 'cancelled':
				return in_array( $current, [ 'processing', 'on-hold', 'pending', self::OUT_FOR_DELIVERY ], true );
			default:
				return false;
		}
	}

	public static function is_in_transit( string $fleetbase_status ): bool {
		return in_array( strtolower( trim( $fleetbase_status ) ), self::IN_TRANSIT, true );
	}

	public static function is_done( string $fleetbase_status ): bool {
		return in_array( strtolower( trim( $fleetbase_status ) ), self::DONE, true );
	}

	public static function is_canceled( string $fleetbase_status ): bool {
		return in_array( strtolower( trim( $fleetbase_status ) ), self::CANCELED, true );
	}

	public static function is_open( string $fleetbase_status ): bool {
		return ! self::is_done( $fleetbase_status ) && ! self::is_canceled( $fleetbase_status );
	}

	public static function label( string $fleetbase_status ): string {
		$status = strtolower( trim( $fleetbase_status ) );
		$labels = [
			'created'    => self::t( 'Created' ),
			'dispatched' => self::t( 'Dispatched' ),
			'started'    => self::t( 'Driver on the way' ),
			'enroute'    => self::t( 'En route' ),
			'completed'  => self::t( 'Delivered' ),
			'canceled'   => self::t( 'Cancelled' ),
			'cancelled'  => self::t( 'Cancelled' ),
		];
		return $labels[ $status ] ?? ucfirst( str_replace( '_', ' ', $status ) );
	}

	/**
	 * Translate when WordPress is loaded; the mapping itself has no WordPress dependency.
	 */
	private static function t( string $text ): string {
		return function_exists( '__' ) ? __( $text, 'fleetbase-for-woocommerce' ) : $text; // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Literal strings above.
	}
}
