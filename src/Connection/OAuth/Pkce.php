<?php
/**
 * PKCE (RFC 7636) and state values for the OAuth connect flow.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Connection\OAuth;

final class Pkce {

	/**
	 * A high-entropy code verifier: 64 URL-safe characters.
	 */
	public static function verifier(): string {
		return self::base64url( random_bytes( 48 ) );
	}

	/**
	 * The S256 challenge for a verifier.
	 */
	public static function challenge( string $verifier ): string {
		return self::base64url( hash( 'sha256', $verifier, true ) );
	}

	/**
	 * An opaque, unguessable state value.
	 */
	public static function state(): string {
		return bin2hex( random_bytes( 16 ) );
	}

	private static function base64url( string $bytes ): string {
		return rtrim( strtr( base64_encode( $bytes ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- RFC 7636 encoding, not obfuscation.
	}
}
