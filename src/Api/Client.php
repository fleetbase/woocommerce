<?php
/**
 * Fleetbase REST client. Thin, dependency free, retry aware.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Api;

use Fleetbase\WooCommerce\Api\Exception\ApiException;
use Fleetbase\WooCommerce\Api\Exception\AuthenticationException;
use Fleetbase\WooCommerce\Api\Exception\AuthorizationException;
use Fleetbase\WooCommerce\Api\Exception\ConflictException;
use Fleetbase\WooCommerce\Api\Exception\DecodingException;
use Fleetbase\WooCommerce\Api\Exception\NotFoundException;
use Fleetbase\WooCommerce\Api\Exception\RateLimitException;
use Fleetbase\WooCommerce\Api\Exception\ServerException;
use Fleetbase\WooCommerce\Api\Exception\TransportException;
use Fleetbase\WooCommerce\Api\Exception\ValidationException;
use Fleetbase\WooCommerce\Support\LoggerInterface;

final class Client {

	private const IDEMPOTENT_METHODS = [ 'GET', 'HEAD', 'PUT', 'DELETE' ];

	private Connection $connection;
	private Transport $transport;
	private ?LoggerInterface $logger;

	/** @var array{timeout:int, retries:int, max_retry_sleep:int, user_agent:string, sleep:callable} */
	private array $options;

	/** @var array<string, string> */
	private array $last_response_headers = [];

	/**
	 * @param array<string, mixed> $options timeout (s), retries, max_retry_sleep (s), user_agent, sleep (callable seconds=>void)
	 */
	public function __construct( Connection $connection, Transport $transport, ?LoggerInterface $logger = null, array $options = [] ) {
		$this->connection = $connection;
		$this->transport  = $transport;
		$this->logger     = $logger;
		$this->options    = [
			'timeout'         => (int) ( $options['timeout'] ?? 15 ),
			'retries'         => (int) ( $options['retries'] ?? 2 ),
			'max_retry_sleep' => (int) ( $options['max_retry_sleep'] ?? 3 ),
			'user_agent'      => (string) ( $options['user_agent'] ?? 'fleetbase-for-woocommerce' ),
			'sleep'           => $options['sleep'] ?? static function ( float $seconds ): void {
				usleep( (int) ( $seconds * 1_000_000 ) );
			},
		];
	}

	public function connection(): Connection {
		return $this->connection;
	}

	/**
	 * @param array<string, mixed> $query
	 * @param array<string, mixed>|null $body
	 * @param array<string, mixed> $options  timeout, retries, idempotent (bool)
	 * @return mixed Decoded JSON (array or scalar), or null for an empty body.
	 * @throws ApiException
	 */
	public function request( string $method, string $path, array $query = [], ?array $body = null, array $options = [] ) {
		$method = strtoupper( $method );
		$url    = $this->connection->url( $path );
		$qs     = $this->build_query( $query );
		if ( '' !== $qs ) {
			$url .= ( str_contains( $url, '?' ) ? '&' : '?' ) . $qs;
		}

		$headers = [
			'Authorization' => 'Bearer ' . $this->connection->key(),
			'Accept'        => 'application/json',
			'User-Agent'    => $this->options['user_agent'],
		];
		$payload = null;
		if ( null !== $body ) {
			$headers['Content-Type'] = 'application/json';
			$payload                 = self::encode( $body );
		}
		if ( ! empty( $options['idempotency_key'] ) ) {
			$headers['Idempotency-Key'] = (string) $options['idempotency_key'];
		}

		$timeout    = (int) ( $options['timeout'] ?? $this->options['timeout'] );
		$max_tries  = 1 + max( 0, (int) ( $options['retries'] ?? $this->options['retries'] ) );
		$idempotent = (bool) ( $options['idempotent'] ?? in_array( $method, self::IDEMPOTENT_METHODS, true ) );
		$attempt    = 0;
		$started    = microtime( true );

		while ( true ) {
			++$attempt;
			try {
				$response = $this->transport->send( $method, $url, $headers, $payload, $timeout );
			} catch ( TransportException $e ) {
				$this->log(
					'warning',
					'Transport failure',
					[
						'method'  => $method,
						'url'     => $url,
						'attempt' => $attempt,
						'error'   => $e->getMessage(),
					]
				);
				if ( $idempotent && $attempt < $max_tries ) {
					$this->backoff( $attempt );
					continue;
				}
				throw $e; // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Re-throwing the transport exception.
			}

			$this->last_response_headers = $response->headers;
			$this->log(
				'debug',
				'Fleetbase API call',
				[
					'method'  => $method,
					'url'     => $url,
					'status'  => $response->status,
					'ms'      => (int) round( ( microtime( true ) - $started ) * 1000 ),
					'attempt' => $attempt,
				]
			);

			if ( $response->ok() ) {
				return $this->decode( $response );
			}

			if ( 429 === $response->status && $attempt < $max_tries ) {
				$wait = min( $this->options['max_retry_sleep'], max( 1, (int) ( $response->header( 'retry-after' ) ?? 1 ) ) );
				( $this->options['sleep'] )( (float) $wait );
				continue;
			}

			if ( $response->status >= 500 && $idempotent && $attempt < $max_tries ) {
				$this->backoff( $attempt );
				continue;
			}

			throw $this->exception_for( $response ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- API messages are shown via WP_Error after escaping in the REST layer.
		}
	}

	/**
	 * @param array<string, mixed> $query
	 * @param array<string, mixed> $options
	 * @return mixed
	 */
	public function get( string $path, array $query = [], array $options = [] ) {
		return $this->request( 'GET', $path, $query, null, $options );
	}

	/**
	 * @param array<string, mixed> $body
	 * @param array<string, mixed> $options
	 * @return mixed
	 */
	public function post( string $path, array $body = [], array $options = [] ) {
		return $this->request( 'POST', $path, [], $body, $options );
	}

	/**
	 * @param array<string, mixed> $body
	 * @param array<string, mixed> $options
	 * @return mixed
	 */
	public function put( string $path, array $body = [], array $options = [] ) {
		return $this->request( 'PUT', $path, [], $body, $options );
	}

	/**
	 * @param array<string, mixed> $body
	 * @param array<string, mixed> $options
	 * @return mixed
	 */
	public function patch( string $path, array $body = [], array $options = [] ) {
		return $this->request( 'PATCH', $path, [], $body, $options );
	}

	/**
	 * @param array<string, mixed> $query
	 * @param array<string, mixed> $options
	 * @return mixed
	 */
	public function delete( string $path, array $query = [], array $options = [] ) {
		return $this->request( 'DELETE', $path, $query, null, $options );
	}

	/**
	 * Headers from the most recent response (rate-limit headroom and so on).
	 *
	 * @return array<string, string>
	 */
	public function last_response_headers(): array {
		return $this->last_response_headers;
	}

	/**
	 * @param array<string, mixed> $query
	 */
	private function build_query( array $query ): string {
		$query = array_filter(
			$query,
			static function ( $value ): bool {
				return null !== $value && '' !== $value;
			}
		);
		foreach ( $query as $key => $value ) {
			if ( is_bool( $value ) ) {
				$query[ $key ] = $value ? '1' : '0';
			}
		}
		return http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
	}

	/**
	 * @return mixed
	 */
	private function decode( TransportResponse $response ) {
		if ( '' === trim( $response->body ) ) {
			return null;
		}
		$decoded = json_decode( $response->body, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			throw new DecodingException( 'Fleetbase returned a response that could not be decoded.', $response->status, [], $response->body ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Message is a literal; body is carried as data.
		}
		return $decoded;
	}

	private function exception_for( TransportResponse $response ): ApiException {
		$decoded = json_decode( $response->body, true );
		$errors  = [];
		$message = '';

		if ( is_array( $decoded ) ) {
			if ( isset( $decoded['errors'] ) && is_array( $decoded['errors'] ) ) {
				$errors  = $decoded['errors'];
				$message = self::first_string( $errors );
			} elseif ( isset( $decoded['error'] ) ) {
				$message = is_string( $decoded['error'] ) ? $decoded['error'] : self::first_string( (array) $decoded['error'] );
				$errors  = [ $message ];
			} elseif ( isset( $decoded['message'] ) && is_string( $decoded['message'] ) ) {
				$message = $decoded['message'];
			}
		}

		switch ( true ) {
			case 401 === $response->status:
				return new AuthenticationException( $message ?: 'Fleetbase rejected the API key.', 401, $errors, $response->body );
			case 403 === $response->status:
				return new AuthorizationException( $message ?: 'This API key is not allowed to perform that action.', 403, $errors, $response->body );
			case 404 === $response->status:
				return new NotFoundException( $message ?: 'The requested Fleetbase resource was not found.', 404, $errors, $response->body );
			case 409 === $response->status:
				return new ConflictException( $message ?: 'Fleetbase reported a conflict.', 409, $errors, $response->body );
			case 422 === $response->status || 400 === $response->status:
				return new ValidationException( $message ?: 'Fleetbase rejected the request.', $response->status, $errors, $response->body );
			case 429 === $response->status:
				$e = new RateLimitException( $message ?: 'Fleetbase rate limit reached.', 429, $errors, $response->body );
				return $e->with_retry_after( (int) ( $response->header( 'retry-after' ) ?? 0 ) );
			case $response->status >= 500:
				return new ServerException( $message ?: 'Fleetbase returned a server error.', $response->status, $errors, $response->body );
			default:
				return new ApiException( $message ?: 'Unexpected response from Fleetbase.', $response->status, $errors, $response->body );
		}
	}

	/**
	 * @param array<int|string, mixed> $errors
	 */
	private static function first_string( array $errors ): string {
		foreach ( $errors as $error ) {
			if ( is_string( $error ) ) {
				return $error;
			}
			if ( is_array( $error ) ) {
				$nested = self::first_string( $error );
				if ( '' !== $nested ) {
					return $nested;
				}
			}
		}
		return '';
	}

	private function backoff( int $attempt ): void {
		$seconds = min( $this->options['max_retry_sleep'], 0.5 * ( 2 ** ( $attempt - 1 ) ) );
		( $this->options['sleep'] )( (float) $seconds );
	}

	/**
	 * @param array<string, mixed> $context
	 */
	private function log( string $level, string $message, array $context ): void {
		if ( null !== $this->logger ) {
			$this->logger->log( $level, $message, $context );
		}
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function encode( array $data ): string {
		$json = function_exists( 'wp_json_encode' ) ? wp_json_encode( $data ) : json_encode( $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		return false === $json ? '{}' : (string) $json;
	}
}
