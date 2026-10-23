<?php
/**
 * The customer's current delivery-time choice, kept in the WooCommerce session during checkout.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Checkout;

final class DeliveryChoice {

	public const SESSION_VALUE        = 'fleetbase_wc_delivery_choice';
	public const SESSION_SCHEDULED_AT = 'fleetbase_wc_scheduled_at';

	public const META_SCHEDULED_AT = '_fleetbase_scheduled_at';
	public const META_CHOICE       = '_fleetbase_delivery_choice';
	public const META_LABEL        = '_fleetbase_delivery_label';

	/**
	 * Validate and store the choice in the session. Returns the normalised choice, or null when invalid.
	 *
	 * @return array{value:string, scheduled_at:string, label:string, date:string, start:string, end:string}|null
	 */
	public static function set( string $value ): ?array {
		$normalized = Scheduling::from_settings()->normalize( $value );
		if ( null === $normalized || ! function_exists( 'WC' ) || ! WC()->session ) {
			return $normalized;
		}
		if ( WC()->session->get( self::SESSION_VALUE ) !== $normalized['value'] ) {
			WC()->session->set( self::SESSION_VALUE, $normalized['value'] );
			WC()->session->set( self::SESSION_SCHEDULED_AT, $normalized['scheduled_at'] );
		}
		return $normalized;
	}

	public static function current_value(): string {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return Scheduling::ASAP;
		}
		$value = (string) WC()->session->get( self::SESSION_VALUE, '' );
		return '' === $value ? Scheduling::ASAP : $value;
	}

	/**
	 * Persist a choice on an order. Falls back to ASAP when the value is no longer offered.
	 */
	public static function save_to_order( \WC_Order $order, string $value ): void {
		$normalized = Scheduling::from_settings()->normalize( $value ) ?? Scheduling::from_settings()->normalize( Scheduling::ASAP );
		if ( null === $normalized ) {
			return;
		}
		$order->update_meta_data( self::META_CHOICE, $normalized['value'] );
		$order->update_meta_data( self::META_SCHEDULED_AT, $normalized['scheduled_at'] );
		$order->update_meta_data( self::META_LABEL, $normalized['label'] );
	}

	/**
	 * Human label stored on an order, or an empty string.
	 */
	public static function order_label( \WC_Order $order ): string {
		return (string) $order->get_meta( self::META_LABEL, true );
	}

	/**
	 * Whether the order (or current cart) uses the Fleetbase shipping method.
	 */
	public static function order_uses_fleetbase( \WC_Order $order ): bool {
		foreach ( $order->get_shipping_methods() as $item ) {
			if ( 'fleetbase' === $item->get_method_id() ) {
				return true;
			}
		}
		return false;
	}

	public static function cart_uses_fleetbase(): bool {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return false;
		}
		$chosen = (array) WC()->session->get( 'chosen_shipping_methods', [] );
		foreach ( $chosen as $rate_id ) {
			if ( is_string( $rate_id ) && str_starts_with( $rate_id, 'fleetbase:' ) ) {
				return true;
			}
		}
		return false;
	}
}
