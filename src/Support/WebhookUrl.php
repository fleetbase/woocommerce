<?php
/**
 * The per-site webhook receiver URL. The random token is the authentication.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Support;

final class WebhookUrl {

	public const OPTION = 'fleetbase_wc_site_token';

	public function token(): string {
		$token = (string) get_option( self::OPTION, '' );
		if ( '' === $token ) {
			$token = wp_generate_password( 40, false, false );
			add_option( self::OPTION, $token, '', false );
		}
		return $token;
	}

	public function rotate(): string {
		$token = wp_generate_password( 40, false, false );
		update_option( self::OPTION, $token, false );
		return $token;
	}

	public function url(): string {
		return rest_url( 'fleetbase/v1/webhook/' . $this->token() );
	}

	public function matches( string $candidate ): bool {
		return hash_equals( $this->token(), $candidate );
	}
}
