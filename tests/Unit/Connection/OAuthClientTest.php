<?php

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Tests\Unit\Connection;

use Fleetbase\WooCommerce\Connection\OAuth\OAuthClient;
use Fleetbase\WooCommerce\Connection\OAuth\OAuthException;
use Fleetbase\WooCommerce\Connection\OAuth\Pkce;
use Fleetbase\WooCommerce\Tests\Support\FakeTransport;
use PHPUnit\Framework\TestCase;

final class OAuthClientTest extends TestCase {

	private const REDIRECT = 'https://shop.example.com/wp-admin/admin-post.php?action=fleetbase_wc_oauth';

	private FakeTransport $transport;

	protected function setUp(): void {
		$this->transport = new FakeTransport();
	}

	private function client( string $host = 'http://host.docker.internal:8000/' ): OAuthClient {
		return new OAuthClient( $this->transport, $host );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function discovery( array $overrides = [] ): array {
		return array_merge(
			[
				'issuer'                 => 'http://localhost:8000',
				'authorization_endpoint' => 'http://localhost:8000/oauth/authorize',
				'token_endpoint'         => 'http://localhost:8000/oauth/token',
				'registration_endpoint'  => 'http://localhost:8000/oauth/register',
				'scopes_supported'       => [ 'openid', 'organization:read', 'api-key:create' ],
			],
			$overrides
		);
	}

	public function test_pkce_challenge_is_the_s256_of_the_verifier(): void {
		$verifier = Pkce::verifier();

		$this->assertMatchesRegularExpression( '/^[A-Za-z0-9_-]{64}$/', $verifier );
		$this->assertSame( rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' ), Pkce::challenge( $verifier ) );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{32}$/', Pkce::state() );
		$this->assertNotSame( Pkce::state(), Pkce::state() );
	}

	public function test_authorization_endpoint_comes_from_discovery_on_the_connection_host(): void {
		$this->transport->queue( 200, $this->discovery() );

		$this->assertSame( 'http://localhost:8000/oauth/authorize', $this->client()->authorization_endpoint() );
		$this->assertSame( 'http://host.docker.internal:8000/.well-known/openid-configuration', $this->transport->last()['url'] );
	}

	public function test_a_server_without_registration_or_the_scopes_is_not_supported(): void {
		foreach ( [
			[ 'registration_endpoint' => null ],
			[ 'scopes_supported' => [ 'openid', 'organization:read' ] ],
			[ 'authorization_endpoint' => 'javascript:alert(1)' ],
		] as $overrides ) {
			$this->transport->queue( 200, $this->discovery( $overrides ) );
			try {
				$this->client()->authorization_endpoint();
				$this->fail( 'Expected an OAuthException' );
			} catch ( OAuthException $e ) {
				$this->assertSame( OAuthException::NOT_SUPPORTED, $e->reason() );
			}
		}

		$this->transport->queue( 404, '<html>Not found</html>' );
		$this->expectExceptionObject( new OAuthException( OAuthException::NOT_SUPPORTED, 'Fleetbase answered with HTTP 404 and no JSON.' ) );
		$this->client()->authorization_endpoint();
	}

	public function test_an_unreachable_host_is_reported_as_such(): void {
		$this->transport->queue_failure( 'Could not resolve host' );

		try {
			$this->client()->authorization_endpoint();
			$this->fail( 'Expected an OAuthException' );
		} catch ( OAuthException $e ) {
			$this->assertSame( OAuthException::UNREACHABLE, $e->reason() );
			$this->assertSame( 'Could not resolve host', $e->getMessage() );
		}
	}

	public function test_register_sends_the_software_id_and_one_redirect(): void {
		$this->transport->queue( 201, [ 'client_id' => 'client-123' ] );

		$this->assertSame( 'client-123', $this->client()->register( self::REDIRECT ) );

		$request = $this->transport->last();
		$this->assertSame( 'POST', $request['method'] );
		$this->assertSame( 'http://host.docker.internal:8000/oauth/register', $request['url'] );
		$this->assertSame( 'application/json', $request['headers']['Content-Type'] );
		$this->assertSame(
			[
				'software_id'   => 'fleetbase-woocommerce',
				'redirect_uris' => [ self::REDIRECT ],
			],
			json_decode( (string) $request['body'], true )
		);
	}

	public function test_register_failure_carries_the_server_description(): void {
		$this->transport->queue(
			400,
			[
				'error'             => 'invalid_redirect_uri',
				'error_description' => 'The redirect URI must use https.',
			]
		);

		try {
			$this->client()->register( 'http://shop.example.com/cb' );
			$this->fail( 'Expected an OAuthException' );
		} catch ( OAuthException $e ) {
			$this->assertSame( OAuthException::REGISTRATION, $e->reason() );
			$this->assertSame( 'The redirect URI must use https.', $e->getMessage() );
		}
	}

	public function test_authorize_url_asks_for_both_scopes_with_pkce_and_consent(): void {
		$url = OAuthClient::authorize_url( 'http://localhost:8000/oauth/authorize', 'client-123', self::REDIRECT, 'state-1', 'challenge-1' );

		$this->assertStringStartsWith( 'http://localhost:8000/oauth/authorize?', $url );
		parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $query ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		$this->assertSame(
			[
				'response_type'         => 'code',
				'client_id'             => 'client-123',
				'redirect_uri'          => self::REDIRECT,
				'scope'                 => 'organization:read api-key:create',
				'state'                 => 'state-1',
				'code_challenge'        => 'challenge-1',
				'code_challenge_method' => 'S256',
				'prompt'                => 'consent',
			],
			$query
		);
		$this->assertStringContainsString( 'scope=organization%3Aread%20api-key%3Acreate', $url );
	}

	public function test_exchange_posts_a_form_as_a_public_client(): void {
		$this->transport->queue(
			200,
			[
				'access_token'  => 'access-1',
				'refresh_token' => 'refresh-1',
				'scope'         => 'organization:read api-key:create',
			]
		);

		$tokens = $this->client()->exchange( 'client-123', 'code-1', self::REDIRECT, 'verifier-1' );

		$this->assertSame( 'access-1', $tokens['access_token'] );
		$this->assertSame( [ 'organization:read', 'api-key:create' ], $tokens['scope'] );
		$request = $this->transport->last();
		$this->assertSame( 'http://host.docker.internal:8000/oauth/token', $request['url'] );
		$this->assertSame( 'application/x-www-form-urlencoded', $request['headers']['Content-Type'] );
		parse_str( (string) $request['body'], $form );
		$this->assertSame(
			[
				'grant_type'    => 'authorization_code',
				'client_id'     => 'client-123',
				'code'          => 'code-1',
				'redirect_uri'  => self::REDIRECT,
				'code_verifier' => 'verifier-1',
			],
			$form
		);
		$this->assertArrayNotHasKey( 'client_secret', $form );
	}

	public function test_exchange_refuses_tokens_without_the_api_key_scope(): void {
		$this->transport->queue(
			200,
			[
				'access_token' => 'access-1',
				'scope'        => 'organization:read',
			]
		);

		try {
			$this->client()->exchange( 'client-123', 'code-1', self::REDIRECT, 'verifier-1' );
			$this->fail( 'Expected an OAuthException' );
		} catch ( OAuthException $e ) {
			$this->assertSame( OAuthException::MISSING_SCOPE, $e->reason() );
		}
	}

	public function test_exchange_errors_distinguish_an_unknown_client(): void {
		$this->transport->queue(
			401,
			[
				'error'             => 'invalid_client',
				'error_description' => 'Client authentication failed',
			]
		);
		$this->transport->queue(
			400,
			[
				'error'             => 'invalid_grant',
				'error_description' => 'The authorization code has expired',
			]
		);

		foreach ( [ OAuthException::INVALID_CLIENT, OAuthException::TOKEN ] as $expected ) {
			try {
				$this->client()->exchange( 'client-123', 'code-1', self::REDIRECT, 'verifier-1' );
				$this->fail( 'Expected an OAuthException' );
			} catch ( OAuthException $e ) {
				$this->assertSame( $expected, $e->reason() );
			}
		}
	}

	public function test_api_key_is_fetched_with_the_access_token(): void {
		$this->transport->queue(
			201,
			[
				'api_key' => [
					'key'     => 'flb_live_abcdefghijklmnopqrstuvwxyz012345',
					'name'    => 'Fleetbase for WooCommerce (shop.example.com)',
					'created' => true,
				],
			]
		);

		$minted = $this->client()->api_key( 'access-1' );

		$this->assertSame( 'flb_live_abcdefghijklmnopqrstuvwxyz012345', $minted['key'] );
		$this->assertTrue( $minted['created'] );
		$request = $this->transport->last();
		$this->assertSame( 'POST', $request['method'] );
		$this->assertSame( 'http://host.docker.internal:8000/oauth/v1/api-key', $request['url'] );
		$this->assertSame( 'Bearer access-1', $request['headers']['Authorization'] );
	}

	public function test_api_key_failures(): void {
		$this->transport->queue(
			403,
			[
				'error'             => 'access_denied',
				'error_description' => 'You no longer administer this organization.',
			]
		);
		$this->transport->queue( 200, [ 'api_key' => [ 'key' => 'not-a-key' ] ] );

		foreach ( [ OAuthException::NOT_ADMIN, OAuthException::KEY ] as $expected ) {
			try {
				$this->client()->api_key( 'access-1' );
				$this->fail( 'Expected an OAuthException' );
			} catch ( OAuthException $e ) {
				$this->assertSame( $expected, $e->reason() );
			}
		}
	}
}
