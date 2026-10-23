<?php
/**
 * WooCommerce logger adapter.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Support;

final class Logger implements LoggerInterface {

	public const SOURCE = 'fleetbase';

	/** @var \WC_Logger_Interface|null */
	private $wc_logger = null;

	/**
	 * @param array<string, mixed> $context
	 */
	public function log( string $level, string $message, array $context = [] ): void {
		if ( ! $this->enabled( $level ) ) {
			return;
		}
		$logger = $this->wc_logger();
		if ( null === $logger ) {
			return;
		}
		$context['source'] = self::SOURCE;
		$logger->log( $level, $message, $this->redact( $context ) );
	}

	/**
	 * @param array<string, mixed> $context
	 */
	public function debug( string $message, array $context = [] ): void {
		$this->log( 'debug', $message, $context );
	}

	/**
	 * @param array<string, mixed> $context
	 */
	public function info( string $message, array $context = [] ): void {
		$this->log( 'info', $message, $context );
	}

	/**
	 * @param array<string, mixed> $context
	 */
	public function warning( string $message, array $context = [] ): void {
		$this->log( 'warning', $message, $context );
	}

	/**
	 * @param array<string, mixed> $context
	 */
	public function error( string $message, array $context = [] ): void {
		$this->log( 'error', $message, $context );
	}

	private function enabled( string $level ): bool {
		if ( 'debug' !== $level ) {
			return true;
		}
		$settings = get_option( 'fleetbase_wc_settings', [] );
		return is_array( $settings ) && ! empty( $settings['debug_logging'] );
	}

	/**
	 * @return \WC_Logger_Interface|null
	 */
	private function wc_logger() {
		if ( null === $this->wc_logger && function_exists( 'wc_get_logger' ) ) {
			$this->wc_logger = wc_get_logger();
		}
		return $this->wc_logger;
	}

	/**
	 * Strip API keys and authorization headers from anything that reaches the log.
	 *
	 * @param array<string, mixed> $context
	 * @return array<string, mixed>
	 */
	private function redact( array $context ): array {
		array_walk_recursive(
			$context,
			static function ( &$value, $key ): void {
				if ( is_string( $key ) && in_array( strtolower( (string) $key ), [ 'authorization', 'key', 'api_key', 'secret', 'password' ], true ) ) {
					$value = '[redacted]';
					return;
				}
				if ( is_string( $value ) && preg_match( '/flb_(live|test)_[A-Za-z0-9]+/', $value ) ) {
					$value = preg_replace( '/flb_(live|test)_[A-Za-z0-9]+/', 'flb_$1_[redacted]', $value );
				}
			}
		);
		return $context;
	}
}
