<?php
/**
 * "Connect with Fleetbase": the browser round trip that ends with an API key saved.
 *
 * 1. Start (admin-post.php?action=fleetbase_wc_oauth&step=start): register this store as
 *    an OAuth client (idempotent), keep a PKCE verifier and state for ten minutes, and
 *    send the merchant to Fleetbase's consent screen.
 * 2. Callback (the same URL with code and state): exchange the code, ask Fleetbase for
 *    this store's API key, check it, and save the connection exactly as a pasted key is
 *    saved. The OAuth tokens are dropped; they are never revoked, because revoking the
 *    grant expires the key. Removing the store under Connected apps in Fleetbase does that.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are logged and rendered as text by the admin app, never echoed.

namespace Fleetbase\WooCommerce\Connection\OAuth;

use Fleetbase\WooCommerce\Api\Connection;
use Fleetbase\WooCommerce\Api\WpHttpTransport;
use Fleetbase\WooCommerce\Connection\ProviderRegistry;
use Fleetbase\WooCommerce\Hookable;
use Fleetbase\WooCommerce\Plugin;

final class OAuthFlow implements Hookable {

	public const ACTION        = 'fleetbase_wc_oauth';
	public const NONCE         = 'fleetbase_wc_oauth_start';
	public const TRANSIENT     = 'fleetbase_wc_oauth_';
	public const ERROR         = 'fleetbase_wc_oauth_error_';
	public const STATE_SECONDS = 600;

	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, [ $this, 'handle' ] );
		add_action( 'admin_post_nopriv_' . self::ACTION, [ $this, 'handle_signed_out' ] );
	}

	/**
	 * The fixed redirect URI this store registers with Fleetbase.
	 */
	public static function redirect_uri(): string {
		return admin_url( 'admin-post.php?action=' . self::ACTION );
	}

	/**
	 * Where the "Connect with Fleetbase" button points; provider and host are added by the admin app.
	 */
	public static function start_url(): string {
		return add_query_arg(
			[
				'step'     => 'start',
				'_wpnonce' => wp_create_nonce( self::NONCE ),
			],
			self::redirect_uri()
		);
	}

	/**
	 * The last failure for the current user, read once.
	 */
	public static function take_error(): string {
		$key     = self::ERROR . get_current_user_id();
		$message = (string) get_transient( $key );
		if ( '' !== $message ) {
			delete_transient( $key );
		}
		return $message;
	}

	public function handle_signed_out(): void {
		$current = add_query_arg( $this->query_args(), self::redirect_uri() );
		wp_safe_redirect( wp_login_url( $current ) );
		exit;
	}

	public function handle(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to connect this store to Fleetbase.', 'fleetbase-for-woocommerce' ), '', [ 'response' => 403 ] );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- The start step checks a nonce; the callback is bound to its state value.
		$step = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		try {
			if ( 'start' === $step ) {
				$this->start();
			} else {
				$this->callback();
			}
		} catch ( OAuthException $e ) {
			$this->finish( $e->reason(), $e->getMessage() );
		} catch ( \Throwable $e ) {
			$this->finish( OAuthException::KEY, $e->getMessage() );
		}
	}

	/**
	 * @throws OAuthException
	 */
	private function start(): void {
		check_admin_referer( self::NONCE );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Verified above.
		$provider_id = isset( $_GET['provider'] ) ? sanitize_key( wp_unslash( $_GET['provider'] ) ) : 'cloud';
		$host_input  = isset( $_GET['host'] ) ? sanitize_text_field( wp_unslash( $_GET['host'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$provider = Plugin::instance()->get( ProviderRegistry::class )->get( $provider_id );
		if ( null === $provider || ! $provider->supports_oauth() ) {
			throw new OAuthException( OAuthException::INVALID_REQUEST, __( 'Unknown connection type.', 'fleetbase-for-woocommerce' ) );
		}
		$host = $provider->validate_host( $provider->host_editable() ? $host_input : $provider->default_host() );
		if ( is_wp_error( $host ) ) {
			throw new OAuthException( OAuthException::INVALID_REQUEST, $host->get_error_message() );
		}

		$client    = new OAuthClient( new WpHttpTransport(), $host );
		$endpoint  = $client->authorization_endpoint();
		$client_id = $client->register( self::redirect_uri() );
		$verifier  = Pkce::verifier();
		$state     = Pkce::state();

		set_transient(
			self::TRANSIENT . $state,
			[
				'user'      => get_current_user_id(),
				'provider'  => $provider->id(),
				'host'      => $client->host(),
				'client_id' => $client_id,
				'verifier'  => $verifier,
			],
			self::STATE_SECONDS
		);

		$url = OAuthClient::authorize_url( $endpoint, $client_id, self::redirect_uri(), $state, Pkce::challenge( $verifier ) );
		$this->redirect_to( $url );
	}

	/**
	 * @throws OAuthException
	 */
	private function callback(): void {
		$args  = $this->query_args();
		$state = (string) ( $args['state'] ?? '' );
		$saved = '' !== $state ? get_transient( self::TRANSIENT . $state ) : false;
		if ( '' !== $state ) {
			delete_transient( self::TRANSIENT . $state );
		}
		if ( ! is_array( $saved ) || (int) ( $saved['user'] ?? 0 ) !== get_current_user_id() ) {
			throw new OAuthException( OAuthException::STATE, __( 'This connection attempt expired or was started by someone else. Start again.', 'fleetbase-for-woocommerce' ) );
		}

		if ( ! empty( $args['error'] ) ) {
			$reason = 'access_denied' === $args['error'] ? OAuthException::DENIED : OAuthException::TOKEN;
			throw new OAuthException( $reason, (string) ( $args['error_description'] ?? $args['error'] ) );
		}
		if ( empty( $args['code'] ) ) {
			throw new OAuthException( OAuthException::INVALID_REQUEST, __( 'Fleetbase did not send an authorization code.', 'fleetbase-for-woocommerce' ) );
		}

		$provider = Plugin::instance()->get( ProviderRegistry::class )->get( (string) $saved['provider'] );
		if ( null === $provider ) {
			throw new OAuthException( OAuthException::INVALID_REQUEST, __( 'Unknown connection type.', 'fleetbase-for-woocommerce' ) );
		}

		$client = new OAuthClient( new WpHttpTransport(), (string) $saved['host'] );
		$tokens = $client->exchange( (string) $saved['client_id'], (string) $args['code'], self::redirect_uri(), (string) $saved['verifier'] );
		$minted = $client->api_key( $tokens['access_token'] );

		$connection   = new Connection( $client->host(), $minted['key'] );
		$organization = Plugin::instance()->api( $connection )->organizations()->current();
		if ( empty( $organization['id'] ) && empty( $organization['name'] ) ) {
			throw new OAuthException( OAuthException::KEY, __( 'Fleetbase did not return an organisation for the new API key.', 'fleetbase-for-woocommerce' ) );
		}
		$authorized = $provider->authorize( $connection->host(), $organization );
		if ( is_wp_error( $authorized ) ) {
			throw new OAuthException( OAuthException::INVALID_REQUEST, $authorized->get_error_message() );
		}

		Plugin::instance()->connections()->save( $provider->id(), $connection->host(), $connection->key(), $organization );
		Plugin::instance()->logger()->info(
			'Connected to Fleetbase with OAuth',
			[
				'provider'    => $provider->id(),
				'host'        => $connection->host(),
				'key_created' => $minted['created'],
			]
		);

		$this->finish( 'connected' );
	}

	/**
	 * Back to the setup screen with a result code; a failure's detail waits in a transient.
	 */
	private function finish( string $result, string $message = '' ): void {
		if ( 'connected' !== $result ) {
			Plugin::instance()->logger()->warning(
				'Connect with Fleetbase failed',
				[
					'reason' => $result,
					'error'  => $message,
				]
			);
			if ( '' !== $message ) {
				set_transient( self::ERROR . get_current_user_id(), $message, 5 * MINUTE_IN_SECONDS );
			}
		}
		wp_safe_redirect( add_query_arg( 'fleetbase_oauth', $result, admin_url( 'admin.php?page=fleetbase-setup' ) ) );
		exit;
	}

	/**
	 * Send the browser to Fleetbase. wp_safe_redirect() only allows hosts it is told about.
	 */
	private function redirect_to( string $url ): void {
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		add_filter(
			'allowed_redirect_hosts',
			static function ( array $hosts ) use ( $host ): array {
				$hosts[] = $host;
				return $hosts;
			}
		);
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * The OAuth response parameters.
	 *
	 * @return array<string, string>
	 */
	private function query_args(): array {
		$out = [];
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- An OAuth response, bound to its state value instead.
		foreach ( [ 'code', 'state', 'error', 'error_description', 'iss' ] as $name ) {
			if ( isset( $_GET[ $name ] ) && is_string( $_GET[ $name ] ) ) {
				$out[ $name ] = sanitize_text_field( wp_unslash( $_GET[ $name ] ) );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		return $out;
	}
}
