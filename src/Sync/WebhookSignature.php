<?php
/**
 * Verifies Fleetbase's per-endpoint webhook signature:
 * `X-Fleetbase-Signature: t=<unix time>,v1=<hex hmac_sha256("<t>.<body>", secret)>`.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Sync;

final class WebhookSignature {

	public const HEADER    = 'X-Fleetbase-Signature';
	public const TOLERANCE = 300;

	public static function sign( string $body, string $secret, int $timestamp ): string {
		return 't=' . $timestamp . ',v1=' . hash_hmac( 'sha256', $timestamp . '.' . $body, $secret );
	}

	/**
	 * Whether the header is a valid signature of the body, made within the tolerance.
	 */
	public static function verify( string $header, string $body, string $secret, ?int $now = null, int $tolerance = self::TOLERANCE ): bool {
		if ( '' === $header || '' === $secret ) {
			return false;
		}

		$timestamp  = null;
		$signatures = [];
		foreach ( explode( ',', $header ) as $part ) {
			$pair = explode( '=', trim( $part ), 2 );
			if ( 2 !== count( $pair ) ) {
				continue;
			}
			if ( 't' === $pair[0] && ctype_digit( $pair[1] ) ) {
				$timestamp = (int) $pair[1];
			} elseif ( 'v1' === $pair[0] ) {
				$signatures[] = $pair[1];
			}
		}

		if ( null === $timestamp || [] === $signatures ) {
			return false;
		}
		if ( abs( ( $now ?? time() ) - $timestamp ) > $tolerance ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', $timestamp . '.' . $body, $secret );
		foreach ( $signatures as $signature ) {
			if ( hash_equals( $expected, $signature ) ) {
				return true;
			}
		}
		return false;
	}
}
