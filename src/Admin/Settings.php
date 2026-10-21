<?php
/**
 * Plugin settings: schema, defaults, sanitisation and storage.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Admin;

final class Settings {

	public const OPTION = 'fleetbase_wc_settings';

	/** @var array<string, mixed>|null */
	private ?array $cache = null;

	/**
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return [
			'schema_version' => 1,
			'debug_logging'  => false,
			'checkout'       => [
				'scheduling_enabled' => true,
				'lead_time_minutes'  => 60,
				'horizon_days'       => 7,
				'delivery_hours'     => self::default_hours(),
				'quote_cache_ttl'    => 600,
				'fallback_mode'      => 'hide',
				'fallback_fee'       => '',
				'fallback_label'     => '',
			],
			'sync'           => [
				'trigger_statuses'    => [ 'processing', 'completed' ],
				'sync_all_orders'     => false,
				'auto_dispatch'       => false,
				'pod_required'        => false,
				'pod_method'          => 'photo',
				'order_type'          => 'transport',
				'cancel_on_wc_cancel' => true,
			],
			'statuses'       => [
				'use_out_for_delivery' => true,
				'on_completed'         => 'completed',
				'on_canceled'          => 'note',
			],
			'tracking'       => [
				'show_in_emails'         => true,
				'show_in_account'        => true,
				'out_for_delivery_email' => true,
			],
			'polling'        => [
				'enabled'          => true,
				'interval_minutes' => 5,
			],
		];
	}

	/**
	 * @return array<string, array{open:string, close:string, enabled:bool}>
	 */
	private static function default_hours(): array {
		$hours = [];
		foreach ( [ 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ] as $day ) {
			$hours[ $day ] = [
				'enabled' => 'sun' !== $day,
				'open'    => '09:00',
				'close'   => '18:00',
			];
		}
		return $hours;
	}

	/**
	 * Leaf schema used for sanitisation: dot path => type[:allowed values].
	 *
	 * @return array<string, string>
	 */
	public static function schema(): array {
		return [
			'debug_logging'                   => 'bool',
			'checkout.scheduling_enabled'     => 'bool',
			'checkout.lead_time_minutes'      => 'int:0:10080',
			'checkout.horizon_days'           => 'int:1:60',
			'checkout.delivery_hours'         => 'hours',
			'checkout.quote_cache_ttl'        => 'int:0:86400',
			'checkout.fallback_mode'          => 'enum:hide,flat',
			'checkout.fallback_fee'           => 'decimal',
			'checkout.fallback_label'         => 'text',
			'sync.trigger_statuses'           => 'slugs',
			'sync.sync_all_orders'            => 'bool',
			'sync.auto_dispatch'              => 'bool',
			'sync.pod_required'               => 'bool',
			'sync.pod_method'                 => 'enum:photo,signature,scan',
			'sync.order_type'                 => 'slug',
			'sync.cancel_on_wc_cancel'        => 'bool',
			'statuses.use_out_for_delivery'   => 'bool',
			'statuses.on_completed'           => 'enum:completed,note,none',
			'statuses.on_canceled'            => 'enum:cancelled,note,none',
			'tracking.show_in_emails'         => 'bool',
			'tracking.show_in_account'        => 'bool',
			'tracking.out_for_delivery_email' => 'bool',
			'polling.enabled'                 => 'bool',
			'polling.interval_minutes'        => 'int:1:60',
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function all(): array {
		if ( null === $this->cache ) {
			$stored      = get_option( self::OPTION, [] );
			$this->cache = self::merge( self::defaults(), is_array( $stored ) ? $stored : [] );
		}
		return $this->cache;
	}

	/**
	 * @param mixed $fallback
	 * @return mixed
	 */
	public function get( string $path, $fallback = null ) {
		$value = $this->all();
		foreach ( explode( '.', $path ) as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return $fallback;
			}
			$value = $value[ $segment ];
		}
		return $value;
	}

	/**
	 * Merge a partial update into the stored settings, sanitising every known leaf.
	 * Unknown keys are dropped.
	 *
	 * @param array<string, mixed> $partial
	 * @return array<string, mixed> The full settings after the update.
	 */
	public function update( array $partial ): array {
		$flat    = self::flatten( $partial );
		$current = $this->all();
		foreach ( self::schema() as $path => $type ) {
			if ( ! array_key_exists( $path, $flat ) ) {
				continue;
			}
			self::set_path( $current, $path, self::sanitize( $type, $flat[ $path ] ) );
		}
		$current['schema_version'] = (int) self::defaults()['schema_version'];

		/**
		 * Filter settings before they are saved.
		 *
		 * @param array<string, mixed> $current
		 * @param array<string, mixed> $partial
		 */
		$current = (array) apply_filters( 'fleetbase_wc_settings_before_save', $current, $partial );

		update_option( self::OPTION, $current, true );
		$this->cache = null;

		/**
		 * Fires after settings have been saved.
		 *
		 * @param array<string, mixed> $current
		 */
		do_action( 'fleetbase_wc_settings_saved', $current );

		return $this->all();
	}

	public function flush(): void {
		$this->cache = null;
	}

	/**
	 * @param mixed $value
	 * @return mixed
	 */
	private static function sanitize( string $type, $value ) {
		$parts = explode( ':', $type );
		switch ( $parts[0] ) {
			case 'bool':
				return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
			case 'int':
				$min = isset( $parts[1] ) ? (int) $parts[1] : PHP_INT_MIN;
				$max = isset( $parts[2] ) ? (int) $parts[2] : PHP_INT_MAX;
				return max( $min, min( $max, (int) $value ) );
			case 'decimal':
				$value = is_scalar( $value ) ? trim( (string) $value ) : '';
				return '' === $value ? '' : self::format_decimal( $value );
			case 'enum':
				$allowed = explode( ',', $parts[1] ?? '' );
				$value   = is_scalar( $value ) ? (string) $value : '';
				return in_array( $value, $allowed, true ) ? $value : $allowed[0];
			case 'slug':
				return sanitize_key( is_scalar( $value ) ? (string) $value : '' );
			case 'slugs':
				$list = is_array( $value ) ? $value : explode( ',', (string) $value );
				return array_values( array_unique( array_filter( array_map( 'sanitize_key', array_map( 'strval', $list ) ) ) ) );
			case 'text':
				return sanitize_text_field( is_scalar( $value ) ? (string) $value : '' );
			case 'hours':
				$hours = [];
				foreach ( self::default_hours() as $day => $default ) {
					$row           = is_array( $value ) && isset( $value[ $day ] ) && is_array( $value[ $day ] ) ? $value[ $day ] : [];
					$hours[ $day ] = [
						'enabled' => filter_var( $row['enabled'] ?? $default['enabled'], FILTER_VALIDATE_BOOLEAN ),
						'open'    => self::sanitize_time( (string) ( $row['open'] ?? $default['open'] ), $default['open'] ),
						'close'   => self::sanitize_time( (string) ( $row['close'] ?? $default['close'] ), $default['close'] ),
					];
				}
				return $hours;
			default:
				return $value;
		}
	}

	private static function sanitize_time( string $value, string $fallback ): string {
		return 1 === preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $value ) ? $value : $fallback;
	}

	/**
	 * @param array<string, mixed> $base
	 * @param array<string, mixed> $override
	 * @return array<string, mixed>
	 */
	private static function merge( array $base, array $override ): array {
		foreach ( $override as $key => $value ) {
			if ( is_array( $value ) && isset( $base[ $key ] ) && is_array( $base[ $key ] ) && ! array_is_list( $base[ $key ] ) ) {
				$base[ $key ] = self::merge( $base[ $key ], $value );
			} else {
				$base[ $key ] = $value;
			}
		}
		return $base;
	}

	/**
	 * Flatten nested arrays to dot paths, stopping at leaves listed in the schema.
	 *
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	private static function flatten( array $data, string $prefix = '' ): array {
		$schema = self::schema();
		$out    = [];
		foreach ( $data as $key => $value ) {
			$path = '' === $prefix ? (string) $key : $prefix . '.' . $key;
			if ( isset( $schema[ $path ] ) || ! is_array( $value ) ) {
				$out[ $path ] = $value;
			} else {
				$out += self::flatten( $value, $path );
			}
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $target
	 * @param mixed $value
	 */
	private static function set_path( array &$target, string $path, $value ): void {
		$ref = &$target;
		foreach ( explode( '.', $path ) as $segment ) {
			if ( ! isset( $ref[ $segment ] ) || ! is_array( $ref[ $segment ] ) ) {
				$ref[ $segment ] = [];
			}
			$ref = &$ref[ $segment ];
		}
		$ref = $value;
	}

	private static function format_decimal( string $value ): string {
		if ( function_exists( 'wc_format_decimal' ) ) {
			return (string) wc_format_decimal( $value );
		}
		return (string) (float) str_replace( ',', '.', $value );
	}
}
