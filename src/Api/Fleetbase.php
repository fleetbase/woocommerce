<?php
/**
 * Facade over the resources.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Api;

use Fleetbase\WooCommerce\Api\Resources\Contacts;
use Fleetbase\WooCommerce\Api\Resources\Drivers;
use Fleetbase\WooCommerce\Api\Resources\OrderConfigs;
use Fleetbase\WooCommerce\Api\Resources\Orders;
use Fleetbase\WooCommerce\Api\Resources\Organizations;
use Fleetbase\WooCommerce\Api\Resources\Payloads;
use Fleetbase\WooCommerce\Api\Resources\Places;
use Fleetbase\WooCommerce\Api\Resources\ServiceAreas;
use Fleetbase\WooCommerce\Api\Resources\ServiceQuotes;
use Fleetbase\WooCommerce\Api\Resources\ServiceRates;
use Fleetbase\WooCommerce\Api\Resources\TrackingStatuses;
use Fleetbase\WooCommerce\Api\Resources\Vehicles;
use Fleetbase\WooCommerce\Api\Resources\Zones;

final class Fleetbase {

	/** @var array<string, ApiResource> */
	private array $resources = [];

	public function __construct( private Client $client ) {}

	public function client(): Client {
		return $this->client;
	}

	public function connection(): Connection {
		return $this->client->connection();
	}

	public function orders(): Orders {
		return $this->resource( Orders::class );
	}

	public function service_quotes(): ServiceQuotes {
		return $this->resource( ServiceQuotes::class );
	}

	public function service_rates(): ServiceRates {
		return $this->resource( ServiceRates::class );
	}

	public function service_areas(): ServiceAreas {
		return $this->resource( ServiceAreas::class );
	}

	public function zones(): Zones {
		return $this->resource( Zones::class );
	}

	public function drivers(): Drivers {
		return $this->resource( Drivers::class );
	}

	public function vehicles(): Vehicles {
		return $this->resource( Vehicles::class );
	}

	public function places(): Places {
		return $this->resource( Places::class );
	}

	public function contacts(): Contacts {
		return $this->resource( Contacts::class );
	}

	public function payloads(): Payloads {
		return $this->resource( Payloads::class );
	}

	public function order_configs(): OrderConfigs {
		return $this->resource( OrderConfigs::class );
	}

	public function tracking_statuses(): TrackingStatuses {
		return $this->resource( TrackingStatuses::class );
	}

	public function organizations(): Organizations {
		return $this->resource( Organizations::class );
	}

	/**
	 * @template T of ApiResource
	 * @param class-string<T> $class_name
	 * @return T
	 */
	private function resource( string $class_name ): ApiResource {
		if ( ! isset( $this->resources[ $class_name ] ) ) {
			$this->resources[ $class_name ] = new $class_name( $this->client );
		}
		/** @var T */
		return $this->resources[ $class_name ];
	}
}
