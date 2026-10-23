<?php
/**
 * Place search and the pickup location.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Admin\Rest;

use Fleetbase\WooCommerce\Plugin;
use Fleetbase\WooCommerce\Support\Pickup;

final class PlacesController extends RestController {

	public function register_routes(): void {
		$this->route(
			'/places/search',
			'GET',
			[ $this, 'search' ],
			[
				'query' => [
					'type'              => 'string',
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
				],
			]
		);
		$this->route( '/places', 'GET', [ $this, 'saved' ] );
		$this->route( '/pickup', 'GET', [ $this, 'get_pickup' ] );
		$this->route( '/pickup', 'POST', [ $this, 'set_pickup' ] );
		$this->route( '/pickup', 'DELETE', [ $this, 'clear_pickup' ] );
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function search( \WP_REST_Request $request ) {
		$query = trim( (string) $request->get_param( 'query' ) );
		if ( strlen( $query ) < 3 ) {
			return $this->respond( [ 'results' => [] ] );
		}
		try {
			$results = Plugin::instance()->api()->places()->search( $query );
		} catch ( \Throwable $e ) {
			return $this->error_from_exception( $e );
		}
		return $this->respond( [ 'results' => array_map( [ Pickup::class, 'normalize_place' ], $results ) ] );
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function saved() {
		try {
			$places = Plugin::instance()->api()->places()->all(
				[
					'limit' => 50,
					'sort'  => '-created_at',
				]
			);
		} catch ( \Throwable $e ) {
			return $this->error_from_exception( $e );
		}
		return $this->respond( [ 'results' => array_map( [ Pickup::class, 'normalize_place' ], $places ) ] );
	}

	public function get_pickup(): \WP_REST_Response {
		return $this->respond( [ 'pickup' => Pickup::get() ] );
	}

	/**
	 * Accepts either {place_id} for an existing place, or address fields with coordinates
	 * (as returned by search) to create a new place.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function set_pickup( \WP_REST_Request $request ) {
		$api      = Plugin::instance()->api();
		$place_id = sanitize_text_field( (string) $request->get_param( 'place_id' ) );

		try {
			if ( '' !== $place_id ) {
				$place = $api->places()->find( $place_id );
			} else {
				$input = $this->place_input( $request );
				if ( is_wp_error( $input ) ) {
					return $input;
				}
				$place = $api->places()->create( $input );
			}
		} catch ( \Throwable $e ) {
			return $this->error_from_exception( $e );
		}

		if ( empty( $place['id'] ) ) {
			return new \WP_Error( 'fleetbase_wc_place', __( 'Fleetbase did not return a place.', 'fleetbase-for-woocommerce' ), [ 'status' => 400 ] );
		}

		$pickup = Pickup::save_from_place( $place );
		if ( empty( $pickup['has_location'] ) ) {
			Plugin::instance()->logger()->warning( 'Pickup place saved without coordinates', [ 'place_id' => $pickup['place_id'] ] );
		}
		return $this->respond( [ 'pickup' => $pickup ] );
	}

	public function clear_pickup(): \WP_REST_Response {
		Pickup::clear();
		return $this->respond( [ 'pickup' => null ] );
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	private function place_input( \WP_REST_Request $request ) {
		$fields = [ 'name', 'street1', 'street2', 'city', 'province', 'postal_code', 'country', 'phone' ];
		$input  = [];
		foreach ( $fields as $field ) {
			$value = $request->get_param( $field );
			if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				$input[ $field ] = sanitize_text_field( (string) $value );
			}
		}
		if ( empty( $input['street1'] ) && empty( $request->get_param( 'address' ) ) ) {
			return new \WP_Error( 'fleetbase_wc_place_input', __( 'A street address is required for the pickup location.', 'fleetbase-for-woocommerce' ), [ 'status' => 400 ] );
		}
		if ( ! empty( $request->get_param( 'address' ) ) && empty( $input['street1'] ) ) {
			$input['address'] = sanitize_text_field( (string) $request->get_param( 'address' ) );
		}
		if ( empty( $input['name'] ) ) {
			$input['name'] = get_bloginfo( 'name' );
		}
		$lat = $request->get_param( 'latitude' );
		$lng = $request->get_param( 'longitude' );
		if ( is_numeric( $lat ) && is_numeric( $lng ) && ( 0.0 !== (float) $lat || 0.0 !== (float) $lng ) ) {
			$input['latitude']  = (float) $lat;
			$input['longitude'] = (float) $lng;
		}
		$input['type'] = 'store';
		return $input;
	}
}
