<?php
/**
 * Webhook endpoints the API key manages itself (core-api 1.6.74+).
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Api\Resources;

use Fleetbase\WooCommerce\Api\ApiResource;

final class WebhookEndpoints extends ApiResource {

	protected string $path = 'webhook-endpoints';

	/**
	 * The endpoint registered for a URL in this key's environment, if any.
	 *
	 * @return array<string, mixed>|null
	 */
	public function find_by_url( string $url ): ?array {
		$items = $this->all( [ 'url' => $url ] );
		foreach ( $items as $item ) {
			if ( ( $item['url'] ?? '' ) === $url ) {
				return $item;
			}
		}
		return null;
	}

	/**
	 * Issue a new signing secret. The response carries it once.
	 *
	 * @return array<string, mixed>
	 */
	public function rotate_secret( string $id ): array {
		$result = $this->client->post( $this->path . '/' . rawurlencode( $id ) . '/rotate-secret' );
		return is_array( $result ) ? $result : [];
	}
}
