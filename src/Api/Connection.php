<?php
/**
 * Immutable description of where and how to reach a Fleetbase API.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Api;

final class Connection {

	public const CLOUD_HOST = 'https://api.fleetbase.io';

	private string $host;
	private string $key;
	private string $namespace;

	public function __construct( string $host, string $key, string $api_namespace = 'v1' ) {
		$this->host      = self::normalize_host( $host );
		$this->key       = trim( $key );
		$this->namespace = trim( $api_namespace, '/' );
	}

	public static function cloud( string $key ): self {
		return new self( self::CLOUD_HOST, $key );
	}

	public function host(): string {
		return $this->host;
	}

	public function key(): string {
		return $this->key;
	}

	public function namespace(): string {
		return $this->namespace;
	}

	public function base_url(): string {
		return '' === $this->namespace ? $this->host : $this->host . '/' . $this->namespace;
	}

	public function url( string $path ): string {
		return $this->base_url() . '/' . ltrim( $path, '/' );
	}

	/**
	 * "live" or "test", derived from the key prefix.
	 */
	public function mode(): string {
		return str_starts_with( $this->key, 'flb_test_' ) ? 'test' : 'live';
	}

	public function is_test_mode(): bool {
		return 'test' === $this->mode();
	}

	public function is_cloud(): bool {
		return self::CLOUD_HOST === $this->host;
	}

	public function masked_key(): string {
		if ( strlen( $this->key ) < 14 ) {
			return str_repeat( '*', strlen( $this->key ) );
		}
		return substr( $this->key, 0, 9 ) . str_repeat( '*', max( 4, strlen( $this->key ) - 13 ) ) . substr( $this->key, -4 );
	}

	public static function is_valid_key( string $key ): bool {
		return 1 === preg_match( '/^flb_(live|test)_[A-Za-z0-9]{16,64}$/', trim( $key ) );
	}

	public static function normalize_host( string $host ): string {
		$host = trim( $host );
		$host = rtrim( $host, '/' );
		if ( '' === $host ) {
			return self::CLOUD_HOST;
		}
		if ( ! preg_match( '#^[a-z][a-z0-9+.-]*://#i', $host ) ) {
			$host = 'https://' . $host;
		}
		return $host;
	}

	public static function is_valid_host( string $host ): bool {
		$host  = self::normalize_host( $host );
		$parts = wp_parse_url_compat( $host );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return false;
		}
		if ( ! in_array( strtolower( (string) ( $parts['scheme'] ?? '' ) ), [ 'http', 'https' ], true ) ) {
			return false;
		}
		if ( isset( $parts['query'] ) || isset( $parts['fragment'] ) || isset( $parts['user'] ) ) {
			return false;
		}
		$hostname = (string) $parts['host'];
		$is_ipv6  = str_starts_with( $hostname, '[' ) && false !== filter_var( trim( $hostname, '[]' ), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 );
		return $is_ipv6 || 1 === preg_match( '/^[A-Za-z0-9]([A-Za-z0-9-]*[A-Za-z0-9])?(\.[A-Za-z0-9]([A-Za-z0-9-]*[A-Za-z0-9])?)*$/', $hostname );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\wp_parse_url_compat' ) ) {
	/**
	 * parse_url that works with or without WordPress loaded.
	 *
	 * @return array<string, mixed>|false
	 */
	function wp_parse_url_compat( string $url ) {
		return function_exists( 'wp_parse_url' ) ? wp_parse_url( $url ) : parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
	}
}
