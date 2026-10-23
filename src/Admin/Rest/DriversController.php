<?php
/**
 * Driver management proxied to Fleetbase.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Admin\Rest;

use Fleetbase\WooCommerce\Plugin;

final class DriversController extends RestController {

	public function register_routes(): void {
		$this->route( '/drivers', 'GET', [ $this, 'index' ], [], true );
		$this->route( '/drivers', 'POST', [ $this, 'create' ], [], true );
		$this->route( '/drivers/(?P<id>[A-Za-z0-9_]+)', 'PUT', [ $this, 'update' ], [], true );
		$this->route( '/drivers/(?P<id>[A-Za-z0-9_]+)', 'DELETE', [ $this, 'delete' ], [], true );
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function index() {
		try {
			$drivers = Plugin::instance()->api()->drivers()->all( [ 'limit' => 100 ] );
		} catch ( \Throwable $e ) {
			return $this->error_from_exception( $e );
		}
		return $this->respond( [ 'drivers' => array_map( [ self::class, 'shape' ], $drivers ) ] );
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create( \WP_REST_Request $request ) {
		$input = $this->validate( $this->input( $request ), true );
		if ( is_wp_error( $input ) ) {
			return $input;
		}
		try {
			$driver = Plugin::instance()->api()->drivers()->create( $input );
		} catch ( \Throwable $e ) {
			return $this->error_from_exception( $e );
		}
		return $this->respond( [ 'driver' => self::shape( $driver ) ], 201 );
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update( \WP_REST_Request $request ) {
		$input = $this->validate( $this->input( $request ), false );
		if ( is_wp_error( $input ) ) {
			return $input;
		}
		try {
			$driver = Plugin::instance()->api()->drivers()->update( (string) $request->get_param( 'id' ), $input );
		} catch ( \Throwable $e ) {
			return $this->error_from_exception( $e );
		}
		return $this->respond( [ 'driver' => self::shape( $driver ) ] );
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete( \WP_REST_Request $request ) {
		try {
			Plugin::instance()->api()->drivers()->delete( (string) $request->get_param( 'id' ) );
		} catch ( \Throwable $e ) {
			return $this->error_from_exception( $e );
		}
		return $this->respond( [ 'deleted' => true ] );
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>|\WP_Error
	 */
	private function validate( array $input, bool $creating ) {
		$out  = [];
		$name = sanitize_text_field( (string) ( $input['name'] ?? '' ) );
		if ( $creating && '' === $name ) {
			return new \WP_Error( 'fleetbase_wc_driver', __( 'A driver name is required.', 'fleetbase-for-woocommerce' ), [ 'status' => 400 ] );
		}
		if ( '' !== $name ) {
			$out['name'] = $name;
		}
		if ( array_key_exists( 'email', $input ) ) {
			$email = sanitize_email( (string) $input['email'] );
			if ( '' !== (string) $input['email'] && ! is_email( $email ) ) {
				return new \WP_Error( 'fleetbase_wc_driver', __( 'That email address is not valid.', 'fleetbase-for-woocommerce' ), [ 'status' => 400 ] );
			}
			$out['email'] = $email;
		}
		if ( array_key_exists( 'phone', $input ) ) {
			$out['phone'] = preg_replace( '/[^0-9+()\-\s]/', '', (string) $input['phone'] ) ?? '';
		}
		if ( $creating && empty( $out['email'] ) && empty( $out['phone'] ) ) {
			return new \WP_Error( 'fleetbase_wc_driver', __( 'Add an email address or phone number so the driver can sign in to the Navigator app.', 'fleetbase-for-woocommerce' ), [ 'status' => 400 ] );
		}
		$password = (string) ( $input['password'] ?? '' );
		if ( '' !== $password ) {
			if ( strlen( $password ) < 8 ) {
				return new \WP_Error( 'fleetbase_wc_driver', __( 'Passwords need at least 8 characters.', 'fleetbase-for-woocommerce' ), [ 'status' => 400 ] );
			}
			$out['password'] = $password;
		}
		$status = sanitize_key( (string) ( $input['status'] ?? '' ) );
		if ( in_array( $status, [ 'active', 'available', 'inactive' ], true ) ) {
			$out['status'] = $status;
		}
		if ( array_key_exists( 'vehicle', $input ) ) {
			$vehicle = sanitize_text_field( (string) $input['vehicle'] );
			if ( '' !== $vehicle ) {
				$out['vehicle'] = $vehicle;
			}
		}
		if ( array_key_exists( 'internal_id', $input ) ) {
			$out['internal_id'] = sanitize_text_field( (string) $input['internal_id'] );
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $driver
	 * @return array<string, mixed>
	 */
	public static function shape( array $driver ): array {
		$vehicle  = is_array( $driver['vehicle'] ?? null ) ? $driver['vehicle'] : null;
		$location = $driver['location']['coordinates'] ?? null;
		return [
			'id'             => (string) ( $driver['id'] ?? '' ),
			'name'           => (string) ( $driver['name'] ?? '' ),
			'email'          => (string) ( $driver['email'] ?? '' ),
			'phone'          => (string) ( $driver['phone'] ?? '' ),
			'status'         => (string) ( $driver['status'] ?? '' ),
			'online'         => (bool) ( $driver['online'] ?? false ),
			'current_status' => (string) ( $driver['current_status'] ?? '' ),
			'internal_id'    => (string) ( $driver['internal_id'] ?? '' ),
			'photo_url'      => (string) ( $driver['photo_url'] ?? '' ),
			'vehicle_id'     => (string) ( $driver['vehicle_id'] ?? ( $vehicle['id'] ?? '' ) ),
			'vehicle'        => null !== $vehicle ? VehiclesController::label( $vehicle ) : '',
			'location'       => is_array( $location ) && count( $location ) >= 2 ? [
				'lat' => (float) $location[1],
				'lng' => (float) $location[0],
			] : null,
			'updated_at'     => (string) ( $driver['updated_at'] ?? '' ),
		];
	}
}
