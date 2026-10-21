<?php
/**
 * Base class for Fleetbase API resources.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Api;

abstract class ApiResource {

	protected string $path = '';

	public function __construct( protected Client $client ) {}

	public function client(): Client {
		return $this->client;
	}

	/**
	 * @param array<string, mixed> $query
	 * @return array<int, array<string, mixed>>
	 */
	public function all( array $query = [] ): array {
		$result = $this->client->get( $this->path, $query );
		return is_array( $result ) ? array_values( array_filter( $result, 'is_array' ) ) : [];
	}

	/**
	 * Iterate through every page. Fleetbase returns bare arrays, so paging stops
	 * when a page comes back shorter than the limit.
	 *
	 * @param array<string, mixed> $query
	 * @return \Generator<int, array<string, mixed>>
	 */
	public function each( array $query = [], int $limit = 100 ): \Generator {
		$page = 1;
		do {
			$items = $this->all(
				array_merge(
					$query,
					[
						'limit' => $limit,
						'page'  => $page,
					]
				)
			);
			foreach ( $items as $item ) {
				yield $item;
			}
			++$page;
			$fetched = count( $items );
		} while ( $fetched >= $limit );
	}

	/**
	 * @param array<string, mixed> $query
	 * @return array<string, mixed>
	 */
	public function find( string $id, array $query = [] ): array {
		$result = $this->client->get( $this->path . '/' . rawurlencode( $id ), $query );
		return is_array( $result ) ? $result : [];
	}

	/**
	 * @param array<string, mixed> $data
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>
	 */
	public function create( array $data, array $options = [] ): array {
		$result = $this->client->post( $this->path, $data, $options );
		return is_array( $result ) ? $result : [];
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	public function update( string $id, array $data ): array {
		$result = $this->client->put( $this->path . '/' . rawurlencode( $id ), $data );
		return is_array( $result ) ? $result : [];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function delete( string $id ): array {
		$result = $this->client->delete( $this->path . '/' . rawurlencode( $id ) );
		return is_array( $result ) ? $result : [];
	}
}
