<?php
/**
 * Turns a WooCommerce order into the Fleetbase create-order request.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Sync;

use Fleetbase\WooCommerce\Api\Money;
use Fleetbase\WooCommerce\Checkout\DeliveryChoice;
use Fleetbase\WooCommerce\Support\Address;

final class OrderMapper {

	/** @var array<string, mixed> */
	private array $config;

	/**
	 * @param array<string, mixed> $config pickup_place_id, currency, weight_unit, dimension_unit, admin_url, order_type,
	 *                                     pod_required, pod_method, auto_dispatch, contact_id (optional cached contact id)
	 */
	public function __construct( array $config ) {
		$this->config = $config;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function map( \WC_Order $order ): array {
		$dropoff = $this->dropoff( $order );
		$request = [
			'internal_id' => (string) $order->get_order_number(),
			'payload'     => [
				'pickup'   => (string) $this->config['pickup_place_id'],
				'dropoff'  => $dropoff,
				'entities' => $this->entities( $order ),
			],
			'customer'    => $this->customer( $order ),
			'type'        => (string) ( $this->config['order_type'] ?? 'transport' ),
			'dispatch'    => ! empty( $this->config['auto_dispatch'] ),
			'meta'        => $this->meta( $order ),
		];

		$quote = self::shipping_meta( $order, '_fleetbase_quote_id' );
		if ( '' !== $quote ) {
			$request['service_quote'] = $quote;
		}

		$scheduled_at = self::to_utc( (string) $order->get_meta( DeliveryChoice::META_SCHEDULED_AT, true ) );
		if ( '' !== $scheduled_at ) {
			$request['scheduled_at'] = $scheduled_at;
		}

		$notes = trim( (string) $order->get_customer_note() );
		if ( '' !== $notes ) {
			$request['notes'] = $notes;
		}

		if ( ! empty( $this->config['pod_required'] ) ) {
			$request['pod_required'] = true;
			$request['pod_method']   = (string) ( $this->config['pod_method'] ?? 'photo' );
		}

		return $request;
	}

	/**
	 * Cash on delivery details, when the order was paid that way. Fleetbase only accepts
	 * these on a payload created ahead of the order.
	 *
	 * @return array<string, mixed>|null
	 */
	public function cod( \WC_Order $order ): ?array {
		if ( 'cod' !== $order->get_payment_method() ) {
			return null;
		}
		$currency = strtoupper( (string) $order->get_currency() );
		return [
			'cod_amount'         => Money::to_minor( (string) $order->get_total(), $currency ),
			'cod_currency'       => $currency,
			'cod_payment_method' => 'cash',
		];
	}

	public function is_deliverable( \WC_Order $order ): bool {
		foreach ( $order->get_items() as $item ) {
			$product = $item instanceof \WC_Order_Item_Product ? $item->get_product() : null;
			if ( $product && ! $product->is_virtual() ) {
				return true;
			}
		}
		return false;
	}

	public static function uses_fleetbase_shipping( \WC_Order $order ): bool {
		foreach ( $order->get_shipping_methods() as $shipping ) {
			if ( 'fleetbase' === $shipping->get_method_id() ) {
				return true;
			}
		}
		return false;
	}

	public static function shipping_meta( \WC_Order $order, string $key ): string {
		foreach ( $order->get_shipping_methods() as $shipping ) {
			if ( 'fleetbase' !== $shipping->get_method_id() ) {
				continue;
			}
			$value = $shipping->get_meta( $key, true );
			if ( is_scalar( $value ) && '' !== (string) $value ) {
				return (string) $value;
			}
		}
		return '';
	}

	/**
	 * @return array<string, string>
	 */
	private function dropoff( \WC_Order $order ): array {
		$use_shipping = '' !== trim( (string) $order->get_shipping_address_1() ) || '' !== trim( (string) $order->get_shipping_postcode() );
		$prefix       = $use_shipping ? 'shipping' : 'billing';

		$destination = [
			'address_1' => $order->{"get_{$prefix}_address_1"}(),
			'address_2' => $order->{"get_{$prefix}_address_2"}(),
			'city'      => $order->{"get_{$prefix}_city"}(),
			'state'     => $order->{"get_{$prefix}_state"}(),
			'postcode'  => $order->{"get_{$prefix}_postcode"}(),
			'country'   => $order->{"get_{$prefix}_country"}(),
		];

		$place   = Address::to_place( $destination );
		$name    = trim( $order->{"get_{$prefix}_first_name"}() . ' ' . $order->{"get_{$prefix}_last_name"}() );
		$company = trim( (string) $order->{"get_{$prefix}_company"}() );
		if ( '' !== $company ) {
			$name = '' === $name ? $company : $name . ' (' . $company . ')';
		}
		if ( '' !== $name ) {
			$place['name'] = $name;
		}
		$phone = trim( (string) ( $use_shipping ? $order->get_shipping_phone() : '' ) );
		if ( '' === $phone ) {
			$phone = trim( (string) $order->get_billing_phone() );
		}
		if ( '' !== $phone ) {
			$place['phone'] = $phone;
		}
		return $place;
	}

	/**
	 * @return array<string, mixed>|string
	 */
	private function customer( \WC_Order $order ) {
		$contact_id = (string) ( $this->config['contact_id'] ?? '' );
		if ( '' !== $contact_id ) {
			return $contact_id;
		}
		$name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		if ( '' === $name ) {
			$name = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() );
		}
		$email = trim( (string) $order->get_billing_email() );
		$user  = (int) $order->get_customer_id();
		return [
			'name'        => '' !== $name ? $name : ( '' !== $email ? $email : 'Guest' ),
			'email'       => $email,
			'phone'       => trim( (string) $order->get_billing_phone() ),
			'internal_id' => $user > 0 ? 'wc_user_' . $user : 'wc_guest_' . md5( strtolower( $email ) ),
			'meta'        => [ 'woocommerce_customer_id' => $user ],
		];
	}

	/**
	 * One entity per physical line item. Quantity goes in meta; Fleetbase has no quantity field.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function entities( \WC_Order $order ): array {
		$currency = strtoupper( (string) $order->get_currency() );
		$entities = [];
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}
			$product = $item->get_product();
			if ( $product && $product->is_virtual() ) {
				continue;
			}
			$quantity = max( 1, (int) $item->get_quantity() );
			$entity   = [
				'name'        => (string) $item->get_name(),
				'type'        => 'parcel',
				'internal_id' => (string) ( $product ? $product->get_id() : $item->get_product_id() ),
				'currency'    => $currency,
				'price'       => Money::to_minor( (string) $item->get_total(), $currency ),
				'meta'        => [
					'quantity'     => $quantity,
					'product_id'   => (int) $item->get_product_id(),
					'variation_id' => (int) $item->get_variation_id(),
					'line_total'   => (string) $item->get_total(),
					'unit_price'   => Money::format_minor( (int) round( Money::to_minor( (string) $item->get_total(), $currency ) / $quantity ), $currency ),
				],
			];
			if ( $product ) {
				$sku = (string) $product->get_sku();
				if ( '' !== $sku ) {
					$entity['sku'] = $sku;
				}
				$weight = (float) $product->get_weight();
				if ( $weight > 0 ) {
					$entity['weight']      = $weight;
					$entity['weight_unit'] = self::weight_unit( (string) ( $this->config['weight_unit'] ?? 'kg' ) );
				}
				$length = (float) $product->get_length();
				$width  = (float) $product->get_width();
				$height = (float) $product->get_height();
				if ( $length > 0 && $width > 0 && $height > 0 ) {
					$entity['length']          = $length;
					$entity['width']           = $width;
					$entity['height']          = $height;
					$entity['dimensions_unit'] = self::dimension_unit( (string) ( $this->config['dimension_unit'] ?? 'cm' ) );
				}
				$description = trim( wp_strip_all_tags_compat( (string) $product->get_short_description() ) );
				if ( '' !== $description ) {
					$entity['description'] = mb_substr( $description, 0, 500 );
				}
			}
			$entities[] = $entity;
		}
		return $entities;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function meta( \WC_Order $order ): array {
		$meta  = [
			'source'               => 'woocommerce',
			'order_id'             => $order->get_id(),
			'order_number'         => (string) $order->get_order_number(),
			'order_key'            => (string) $order->get_order_key(),
			'status'               => (string) $order->get_status(),
			'currency'             => (string) $order->get_currency(),
			'subtotal'             => (string) $order->get_subtotal(),
			'shipping_total'       => (string) $order->get_shipping_total(),
			'total'                => (string) $order->get_total(),
			'payment_method'       => (string) $order->get_payment_method(),
			'payment_method_title' => (string) $order->get_payment_method_title(),
			'items_count'          => (int) $order->get_item_count(),
			'admin_url'            => rtrim( (string) ( $this->config['admin_url'] ?? '' ), '/' ) . '/admin.php?page=wc-orders&action=edit&id=' . $order->get_id(),
		];
		$label = (string) $order->get_meta( DeliveryChoice::META_LABEL, true );
		if ( '' !== $label ) {
			$meta['delivery_window'] = $label;
		}
		$service = self::shipping_meta( $order, '_fleetbase_service_name' );
		if ( '' !== $service ) {
			$meta['service'] = $service;
		}
		return $meta;
	}

	/**
	 * Fleetbase stores the wall-clock part of an offset timestamp as UTC (Laravel's datetime
	 * cast does not convert zones), so the instant must be expressed in UTC before sending.
	 */
	public static function to_utc( string $iso ): string {
		if ( '' === $iso ) {
			return '';
		}
		try {
			$moment = new \DateTimeImmutable( $iso );
		} catch ( \Exception $e ) {
			return '';
		}
		return $moment->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' );
	}

	private static function weight_unit( string $wc_unit ): string {
		$map = [
			'kg'  => 'kg',
			'g'   => 'g',
			'lbs' => 'lb',
			'oz'  => 'oz',
		];
		return $map[ $wc_unit ] ?? 'kg';
	}

	private static function dimension_unit( string $wc_unit ): string {
		$map = [
			'cm' => 'cm',
			'm'  => 'm',
			'mm' => 'mm',
			'in' => 'in',
			'yd' => 'yd',
		];
		return $map[ $wc_unit ] ?? 'cm';
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\wp_strip_all_tags_compat' ) ) {
	/**
	 * strip tags with or without WordPress.
	 */
	function wp_strip_all_tags_compat( string $text ): string {
		return function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $text ) : strip_tags( $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
	}
}
