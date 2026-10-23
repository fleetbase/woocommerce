<?php
/**
 * Vehicle management proxied to Fleetbase.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Admin\Rest;

use Fleetbase\WooCommerce\Plugin;

final class VehiclesController extends RestController {

	public const STATUSES = [ 'active', 'available', 'in_use', 'maintenance', 'out_of_service', 'inactive', 'decommissioned' ];
	public const TYPES    = [ 'car', 'van', 'truck', 'motorcycle', 'bicycle', 'scooter', 'other' ];

	public function register_routes(): void {
		$this->route( '/vehicles', 'GET', [ $this, 'index' ], [], true );
		$this->route( '/vehicles', 'POST', [ $this, 'create' ], [], true );
		$this->route( '/vehicles/(?P<id>[A-Za-z0-9_]+)', 'PUT', [ $this, 'update' ], [], true );
		$this->route( '/vehicles/(?P<id>[A-Za-z0-9_]+)', 'DELETE', [ $this, 'delete' ], [], true );
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function index() {
		try {
			$vehicles = Plugin::instance()->api()->vehicles()->all(
				[
					'limit' => 100,
					'sort'  => '-created_at',
				]
			);
		} catch ( \Throwable $e ) {
			return $this->error_from_exception( $e );
		}
		return $this->respond( [ 'vehicles' => array_map( [ self::class, 'shape' ], $vehicles ) ] );
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
			$vehicle = Plugin::instance()->api()->vehicles()->create( $input );
		} catch ( \Throwable $e ) {
			return $this->error_from_exception( $e );
		}
		return $this->respond( [ 'vehicle' => self::shape( $vehicle ) ], 201 );
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
			$vehicle = Plugin::instance()->api()->vehicles()->update( (string) $request->get_param( 'id' ), $input );
		} catch ( \Throwable $e ) {
			return $this->error_from_exception( $e );
		}
		return $this->respond( [ 'vehicle' => self::shape( $vehicle ) ] );
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete( \WP_REST_Request $request ) {
		try {
			Plugin::instance()->api()->vehicles()->delete( (string) $request->get_param( 'id' ) );
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
		$out = [];
		foreach ( [ 'name', 'plate_number', 'make', 'model', 'year', 'internal_id', 'color' ] as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$out[ $key ] = sanitize_text_field( (string) $input[ $key ] );
			}
		}
		if ( $creating && '' === trim( (string) ( $out['plate_number'] ?? '' ) ) && '' === trim( (string) ( $out['name'] ?? '' ) ) ) {
			return new \WP_Error( 'fleetbase_wc_vehicle', __( 'Give the vehicle a plate number or a name.', 'fleetbase-for-woocommerce' ), [ 'status' => 400 ] );
		}
		$type = sanitize_key( (string) ( $input['type'] ?? '' ) );
		if ( '' !== $type ) {
			$out['type'] = $type;
		}
		$status = sanitize_key( (string) ( $input['status'] ?? '' ) );
		if ( in_array( $status, self::STATUSES, true ) ) {
			$out['status'] = $status;
		}
		if ( array_key_exists( 'driver', $input ) ) {
			$driver = sanitize_text_field( (string) $input['driver'] );
			if ( '' !== $driver ) {
				$out['driver'] = $driver;
			}
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $vehicle
	 */
	public static function label( array $vehicle ): string {
		$parts = array_filter( [ (string) ( $vehicle['make'] ?? '' ), (string) ( $vehicle['model'] ?? '' ) ] );
		$label = implode( ' ', $parts );
		$plate = (string) ( $vehicle['plate_number'] ?? '' );
		$name  = (string) ( $vehicle['name'] ?? '' );
		if ( '' === $label ) {
			$label = '' !== $name ? $name : $plate;
		}
		if ( '' !== $plate && $label !== $plate ) {
			$label .= ' · ' . $plate;
		}
		return '' !== $label ? $label : (string) ( $vehicle['id'] ?? '' );
	}

	/**
	 * @param array<string, mixed> $vehicle
	 * @return array<string, mixed>
	 */
	public static function shape( array $vehicle ): array {
		$location = $vehicle['location']['coordinates'] ?? null;
		return [
			'id'           => (string) ( $vehicle['id'] ?? '' ),
			'label'        => self::label( $vehicle ),
			'name'         => (string) ( $vehicle['name'] ?? '' ),
			'plate_number' => (string) ( $vehicle['plate_number'] ?? '' ),
			'make'         => (string) ( $vehicle['make'] ?? '' ),
			'model'        => (string) ( $vehicle['model'] ?? '' ),
			'year'         => (string) ( $vehicle['year'] ?? '' ),
			'type'         => (string) ( $vehicle['type'] ?? '' ),
			'color'        => (string) ( $vehicle['color'] ?? '' ),
			'status'       => (string) ( $vehicle['status'] ?? '' ),
			'online'       => (bool) ( $vehicle['online'] ?? false ),
			'driver_id'    => (string) ( $vehicle['driver_id'] ?? '' ),
			'internal_id'  => (string) ( $vehicle['internal_id'] ?? '' ),
			'avatar_url'   => (string) ( $vehicle['avatar_url'] ?? '' ),
			'location'     => is_array( $location ) && count( $location ) >= 2 ? [
				'lat' => (float) $location[1],
				'lng' => (float) $location[0],
			] : null,
			'updated_at'   => (string) ( $vehicle['updated_at'] ?? '' ),
		];
	}
}
