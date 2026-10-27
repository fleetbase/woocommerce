<?php
/**
 * Talks to a Fleetbase OAuth server for "Connect with Fleetbase".
 *
 * Every server-to-server call goes to the connection host. Only the authorization
 * endpoint comes from discovery, because that URL is opened in the merchant's browser:
 * locally WordPress reaches the API as host.docker.internal while the browser uses
 * localhost, and discovery describes the browser's view.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are logged and rendered as text by the admin app, never echoed.

namespace Fleetbase\WooCommerce\Connection\OAuth;

use Fleetbase\WooCommerce\Api\Connection;
use Fleetbase\WooCommerce\Api\Exception\TransportException;
use Fleetbase\WooCommerce\Api\Transport;
use Fleetbase\WooCommerce\Api\TransportResponse;

final class OAuthClient {

	public const SOFTWARE_ID = 'fleetbase-woocommerce';
	public const SCOPES      = [ 'organization:read', 'api-key:create' ];

	private string $host;

	public function __construct( private Transport $transport, string $host, private int $timeout = 15 ) {
		$this->host = Connection::normalize_host( $host );
	}

	public function host(): string {
		return $this->host;
	}

	/**
	 * The browser-facing authorization endpoint, from discovery.
	 *
	 * @throws OAuthException When the host has no OAuth server or it cannot register clients.
	 */
	public function authorization_endpoint(): string {
		$discovery = $this->json( $this->send( 'GET', '/.well-known/openid-configuration' ), OAuthException::NOT_SUPPORTED );
		$endpoint  = is_string( $discovery['authorization_endpoint'] ?? null ) ? $discovery['authorization_endpoint'] : '';
		$scheme    = strtolower( (string) ( function_exists( 'wp_parse_url' ) ? wp_parse_url( $endpoint, PHP_URL_SCHEME ) : parse_url( $endpoint, PHP_URL_SCHEME ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		if ( empty( $discovery['registration_endpoint'] ) || ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
			throw new OAuthException( OAuthException::NOT_SUPPORTED, 'This Fleetbase server does not support connecting a store with OAuth.' );
		}
		$supported = (array) ( $discovery['scopes_supported'] ?? [] );
		foreach ( self::SCOPES as $scope ) {
			if ( ! in_array( $scope, $supported, true ) ) {
				throw new OAuthException( OAuthException::NOT_SUPPORTED, sprintf( 'This Fleetbase server does not offer the %s scope.', $scope ) );
			}
		}
		return $endpoint;
	}

	/**
	 * Register this store as a client (RFC 7591). Registering again returns the same client.
	 *
	 * @throws OAuthException
	 */
	public function register( string $redirect_uri ): string {
		$response = $this->send(
			'POST',
			'/oauth/register',
			[
				'software_id'   => self::SOFTWARE_ID,
				'redirect_uris' => [ $redirect_uri ],
			],
			'json'
		);
		$body     = $this->json( $response, OAuthException::REGISTRATION );
		if ( ! $response->ok() || empty( $body['client_id'] ) || ! is_string( $body['client_id'] ) ) {
			throw new OAuthException( OAuthException::REGISTRATION, self::describe( $body, 'Fleetbase did not register this store.' ) );
		}
		return $body['client_id'];
	}

	/**
	 * The URL to send the merchant's browser to.
	 */
	public static function authorize_url( string $endpoint, string $client_id, string $redirect_uri, string $state, string $challenge ): string {
		$query = http_build_query(
			[
				'response_type'         => 'code',
				'client_id'             => $client_id,
				'redirect_uri'          => $redirect_uri,
				'scope'                 => implode( ' ', self::SCOPES ),
				'state'                 => $state,
				'code_challenge'        => $challenge,
				'code_challenge_method' => 'S256',
				'prompt'                => 'consent',
			],
			'',
			'&',
			PHP_QUERY_RFC3986
		);
		return $endpoint . ( str_contains( $endpoint, '?' ) ? '&' : '?' ) . $query;
	}

	/**
	 * Swap the authorization code for tokens, as a public client.
	 *
	 * @return array{access_token:string, scope:array<int, string>}
	 * @throws OAuthException
	 */
	public function exchange( string $client_id, string $code, string $redirect_uri, string $verifier ): array {
		$response = $this->send(
			'POST',
			'/oauth/token',
			[
				'grant_type'    => 'authorization_code',
				'client_id'     => $client_id,
				'code'          => $code,
				'redirect_uri'  => $redirect_uri,
				'code_verifier' => $verifier,
			],
			'form'
		);
		$body     = $this->json( $response, OAuthException::TOKEN );
		if ( ! $response->ok() || empty( $body['access_token'] ) || ! is_string( $body['access_token'] ) ) {
			$reason = 'invalid_client' === ( $body['error'] ?? '' ) ? OAuthException::INVALID_CLIENT : OAuthException::TOKEN;
			throw new OAuthException( $reason, self::describe( $body, 'Fleetbase did not issue a token.' ) );
		}

		$scopes = array_values( array_filter( explode( ' ', (string) ( $body['scope'] ?? '' ) ) ) );
		foreach ( self::SCOPES as $scope ) {
			if ( ! in_array( $scope, $scopes, true ) ) {
				throw new OAuthException( OAuthException::MISSING_SCOPE, sprintf( 'Fleetbase did not grant the %s scope.', $scope ) );
			}
		}

		return [
			'access_token' => $body['access_token'],
			'scope'        => $scopes,
		];
	}

	/**
	 * The live API key Fleetbase made for this store, created on first call.
	 *
	 * @return array{key:string, name:string, created:bool}
	 * @throws OAuthException
	 */
	public function api_key( string $access_token ): array {
		$response = $this->send( 'POST', '/oauth/v1/api-key', [], 'json', $access_token );
		$body     = $this->json( $response, OAuthException::KEY );
		$key      = $body['api_key']['key'] ?? null;
		if ( ! $response->ok() || ! is_string( $key ) || ! Connection::is_valid_key( $key ) ) {
			$reason = 403 === $response->status ? OAuthException::NOT_ADMIN : OAuthException::KEY;
			throw new OAuthException( $reason, self::describe( $body, 'Fleetbase did not create an API key.' ) );
		}
		return [
			'key'     => $key,
			'name'    => (string) ( $body['api_key']['name'] ?? '' ),
			'created' => (bool) ( $body['api_key']['created'] ?? false ),
		];
	}

	/**
	 * @param array<string, mixed>|null $body
	 * @param string                    $encoding 'json' or 'form'.
	 * @throws OAuthException When the server cannot be reached.
	 */
	private function send( string $method, string $path, ?array $body = null, string $encoding = 'json', ?string $bearer = null ): TransportResponse {
		$headers = [
			'Accept'     => 'application/json',
			'User-Agent' => 'fleetbase-for-woocommerce',
		];
		$payload = null;
		if ( null !== $body ) {
			if ( 'form' === $encoding ) {
				$headers['Content-Type'] = 'application/x-www-form-urlencoded';
				$payload                 = http_build_query( $body, '', '&' );
			} else {
				$headers['Content-Type'] = 'application/json';
				$payload                 = (string) ( function_exists( 'wp_json_encode' ) ? wp_json_encode( $body ) : json_encode( $body ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
			}
		}
		if ( null !== $bearer ) {
			$headers['Authorization'] = 'Bearer ' . $bearer;
		}

		try {
			return $this->transport->send( $method, $this->host . $path, $headers, $payload, $this->timeout );
		} catch ( TransportException $e ) {
			throw new OAuthException( OAuthException::UNREACHABLE, $e->getMessage() );
		}
	}

	/**
	 * @return array<string, mixed>
	 * @throws OAuthException When the body is not JSON.
	 */
	private function json( TransportResponse $response, string $reason ): array {
		$decoded = json_decode( $response->body, true );
		if ( ! is_array( $decoded ) ) {
			throw new OAuthException( $reason, sprintf( 'Fleetbase answered with HTTP %d and no JSON.', $response->status ) );
		}
		return $decoded;
	}

	/**
	 * @param array<string, mixed> $body
	 */
	private static function describe( array $body, string $fallback ): string {
		foreach ( [ 'error_description', 'message', 'error' ] as $field ) {
			if ( ! empty( $body[ $field ] ) && is_string( $body[ $field ] ) ) {
				return $body[ $field ];
			}
		}
		return $fallback;
	}
}
