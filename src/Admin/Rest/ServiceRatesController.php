<?php
/**
 * Service rate management proxied to Fleetbase.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Admin\Rest;

use Fleetbase\WooCommerce\Admin\ServiceRateForm;
use Fleetbase\WooCommerce\Plugin;
use Fleetbase\WooCommerce\Shipping\QuoteCacheVersion;

final class ServiceRatesController extends RestController {

	public function register_routes(): void {
		$this->route( '/service-rates', 'GET', [ $this, 'index' ] );
		$this->route( '/service-rates/options', 'GET', [ $this, 'options' ] );
		$this->route( '/service-rates', 'POST', [ $this, 'create' ] );
		$this->route( '/service-rates/(?P<id>[A-Za-z0-9_]+)', 'PUT', [ $this, 'update' ] );
		$this->route( '/service-rates/(?P<id>[A-Za-z0-9_]+)', 'DELETE', [ $this, 'delete' ] );
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function index() {
		try {
			$rates = Plugin::instance()->api()->service_rates()->all( [ 'limit' => 100 ] );
		} catch ( \Throwable $e ) {
			return $this->error_from_exception( $e );
		}
		return $this->respond(
			[
				'rates'    => array_map( [ ServiceRateForm::class, 'from_api' ], $rates ),
				'currency' => get_woocommerce_currency(),
			]
		);
	}

	/**
	 * Service areas and zones for the form selects.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function options() {
		$api   = Plugin::instance()->api();
		$areas = [];
		$zones = [];
		try {
			foreach ( $api->service_areas()->all( [ 'limit' => 100 ] ) as $area ) {
				$areas[] = [
					'id'   => (string) ( $area['id'] ?? '' ),
					'name' => (string) ( $area['name'] ?? '' ),
				];
			}
		} catch ( \Throwable $e ) {
			return $this->error_from_exception( $e );
		}
		try {
			foreach ( $api->zones()->all( [ 'limit' => 100 ] ) as $zone ) {
				$zones[] = [
					'id'   => (string) ( $zone['id'] ?? '' ),
					'name' => (string) ( $zone['name'] ?? '' ),
				];
			}
		} catch ( \Throwable $e ) {
			$zones = [];
		}
		$connection = Plugin::instance()->connections()->status();
		$console    = $connection['is_cloud'] ? 'https://console.fleetbase.io' : preg_replace( '#^(https?://)api\.#', '$1console.', (string) $connection['host'] );
		return $this->respond(
			[
				'areas'       => $areas,
				'zones'       => $zones,
				'currency'    => get_woocommerce_currency(),
				'console_url' => rtrim( (string) $console, '/' ) . '/fleet-ops/management/service-areas',
			]
		);
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create( \WP_REST_Request $request ) {
		$mapped = ServiceRateForm::to_request( $this->input( $request ), get_woocommerce_currency() );
		if ( [] !== $mapped['errors'] ) {
			return new \WP_Error(
				'fleetbase_wc_rate',
				implode( ' ', $mapped['errors'] ),
				[
					'status' => 400,
					'fields' => $mapped['errors'],
				]
			);
		}
		try {
			$rate = Plugin::instance()->api()->service_rates()->create( $mapped['request'] );
		} catch ( \Throwable $e ) {
			return $this->error_from_exception( $e );
		}
		QuoteCacheVersion::bump();
		return $this->respond( [ 'rate' => ServiceRateForm::from_api( $rate ) ], 201 );
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update( \WP_REST_Request $request ) {
		$mapped = ServiceRateForm::to_request( $this->input( $request ), get_woocommerce_currency() );
		if ( [] !== $mapped['errors'] ) {
			return new \WP_Error(
				'fleetbase_wc_rate',
				implode( ' ', $mapped['errors'] ),
				[
					'status' => 400,
					'fields' => $mapped['errors'],
				]
			);
		}
		try {
			$rate = Plugin::instance()->api()->service_rates()->update( (string) $request->get_param( 'id' ), $mapped['request'] );
		} catch ( \Throwable $e ) {
			return $this->error_from_exception( $e );
		}
		QuoteCacheVersion::bump();
		return $this->respond( [ 'rate' => ServiceRateForm::from_api( $rate ) ] );
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete( \WP_REST_Request $request ) {
		try {
			Plugin::instance()->api()->service_rates()->delete( (string) $request->get_param( 'id' ) );
		} catch ( \Throwable $e ) {
			return $this->error_from_exception( $e );
		}
		QuoteCacheVersion::bump();
		return $this->respond( [ 'deleted' => true ] );
	}
}
