<?php
/**
 * Keeps this store's Fleetbase webhook endpoint registered: after connecting, after the
 * webhook URL is regenerated, and on request; removes it before disconnecting.
 *
 * When Fleetbase refuses (a store URL it cannot reach, such as localhost, or a Fleetbase
 * version without /v1/webhook-endpoints) the reason is kept, the setup screen shows the
 * manual steps, and the poller still picks up changes.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Sync;

use Fleetbase\WooCommerce\Api\Exception\NotFoundException;
use Fleetbase\WooCommerce\Hookable;
use Fleetbase\WooCommerce\Plugin;
use Fleetbase\WooCommerce\Support\Crypto;
use Fleetbase\WooCommerce\Support\WebhookUrl;

final class WebhookRegistrar implements Hookable {

	public const OPTION = 'fleetbase_wc_webhook_endpoint';

	public function register(): void {
		add_action( 'fleetbase_wc_connected', [ $this, 'on_connected' ] );
		add_action( 'fleetbase_wc_before_disconnect', [ $this, 'unregister' ] );
	}

	public function on_connected(): void {
		$this->sync();
	}

	/**
	 * Register or refresh the endpoint. Never throws.
	 *
	 * @return array{automatic:bool, error:string, checked_at:int|null}
	 */
	public function sync(): array {
		$plugin = Plugin::instance();
		if ( ! $plugin->connections()->is_connected() ) {
			return $this->status();
		}

		$stored  = $this->stored();
		$current = [
			'id'     => (string) ( $stored['id'] ?? '' ),
			'secret' => Crypto::decrypt( (string) ( $stored['secret'] ?? '' ) ),
			'url'    => (string) ( $stored['url'] ?? '' ),
		];
		$url     = $plugin->get( WebhookUrl::class )->url();
		$host    = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		try {
			$result = ( new WebhookRegistration( $plugin->api() ) )->sync(
				$url,
				/* translators: %s: store host name */
				sprintf( __( 'Fleetbase for WooCommerce (%s)', 'fleetbase-for-woocommerce' ), $host ),
				$current
			);
			$this->save(
				[
					'id'         => $result['id'],
					'secret'     => Crypto::encrypt( $result['secret'] ),
					'url'        => $result['url'],
					'error'      => '',
					'checked_at' => time(),
				]
			);
			$plugin->logger()->info( 'Webhook endpoint registered with Fleetbase', [ 'endpoint' => $result['id'] ] );
		} catch ( \Throwable $e ) {
			$message = $e instanceof NotFoundException
				? __( 'This Fleetbase server cannot register webhooks automatically. Add the URL in the Fleetbase console instead.', 'fleetbase-for-woocommerce' )
				: $e->getMessage();
			// Keep an endpoint we registered earlier: it may still work.
			$this->save(
				array_merge(
					$stored,
					[
						'error'      => $message,
						'checked_at' => time(),
					]
				)
			);
			$plugin->logger()->warning( 'Could not register the webhook endpoint with Fleetbase', [ 'error' => $message ] );
		}

		return $this->status();
	}

	/**
	 * Delete the endpoint while the API key still works. Never throws.
	 */
	public function unregister(): void {
		$stored = $this->stored();
		if ( ! empty( $stored['id'] ) && Plugin::instance()->connections()->is_connected() ) {
			try {
				( new WebhookRegistration( Plugin::instance()->api() ) )->remove( (string) $stored['id'] );
			} catch ( \Throwable $e ) {
				Plugin::instance()->logger()->warning( 'Could not remove the webhook endpoint from Fleetbase', [ 'error' => $e->getMessage() ] );
			}
		}
		delete_option( self::OPTION );
	}

	/**
	 * The signing secret, when this store registered its own endpoint.
	 */
	public function secret(): string {
		$stored = $this->stored();
		if ( empty( $stored['id'] ) || ( $stored['url'] ?? '' ) !== Plugin::instance()->get( WebhookUrl::class )->url() ) {
			return '';
		}
		return Crypto::decrypt( (string) ( $stored['secret'] ?? '' ) );
	}

	/**
	 * @return array{automatic:bool, error:string, checked_at:int|null}
	 */
	public function status(): array {
		$stored = $this->stored();
		return [
			'automatic'  => '' !== $this->secret(),
			'error'      => (string) ( $stored['error'] ?? '' ),
			'checked_at' => isset( $stored['checked_at'] ) ? (int) $stored['checked_at'] : null,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function stored(): array {
		$stored = get_option( self::OPTION, [] );
		return is_array( $stored ) ? $stored : [];
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function save( array $data ): void {
		update_option( self::OPTION, $data, false );
	}
}
