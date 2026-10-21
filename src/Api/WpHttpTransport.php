<?php
/**
 * Transport backed by the WordPress HTTP API.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Api;

use Fleetbase\WooCommerce\Api\Exception\TransportException;

final class WpHttpTransport implements Transport {

	/**
	 * @param array<string, string> $headers
	 */
	public function send( string $method, string $url, array $headers, ?string $body, int $timeout ): TransportResponse {
		$args = [
			'method'      => $method,
			'headers'     => $headers,
			'timeout'     => $timeout,
			'redirection' => 0,
			'httpversion' => '1.1',
			'blocking'    => true,
			/**
			 * Filter whether SSL certificates are verified for Fleetbase API calls.
			 *
			 * @param bool   $verify
			 * @param string $url
			 */
			'sslverify'   => (bool) apply_filters( 'fleetbase_wc_sslverify', true, $url ),
		];
		if ( null !== $body ) {
			$args['body'] = $body;
		}

		/**
		 * Filter the arguments passed to wp_remote_request for Fleetbase API calls.
		 *
		 * @param array<string, mixed> $args
		 * @param string               $url
		 */
		$args = (array) apply_filters( 'fleetbase_wc_http_request_args', $args, $url );

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			throw new TransportException( $response->get_error_message(), 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is logged, never printed.
		}

		$status  = (int) wp_remote_retrieve_response_code( $response );
		$raw     = wp_remote_retrieve_headers( $response );
		$headers = [];
		foreach ( ( is_object( $raw ) && method_exists( $raw, 'getAll' ) ) ? $raw->getAll() : (array) $raw as $name => $value ) {
			$headers[ strtolower( (string) $name ) ] = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
		}

		return new TransportResponse( $status, $headers, (string) wp_remote_retrieve_body( $response ) );
	}
}
