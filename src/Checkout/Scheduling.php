<?php
/**
 * Delivery scheduling rules: which dates and time windows a customer may pick.
 * Pure PHP so it can be unit tested; WordPress specifics live in from_settings().
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Checkout;

final class Scheduling {

	public const ASAP = 'asap';

	private const DAYS = [ 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ];

	/** @var array<string, mixed> */
	private array $config;

	private \DateTimeZone $timezone;

	private \DateTimeImmutable $now;

	/** @var callable(\DateTimeImmutable, string): string */
	private $formatter;

	/**
	 * @param array<string, mixed> $config scheduling_enabled, lead_time_minutes, horizon_days, window_minutes, delivery_hours
	 * @param callable|null        $formatter (DateTimeImmutable $moment, string $kind 'date'|'time') => string
	 */
	public function __construct( array $config, \DateTimeZone $timezone, ?\DateTimeImmutable $now = null, ?callable $formatter = null ) {
		$this->config    = $config;
		$this->timezone  = $timezone;
		$this->now       = ( $now ?? new \DateTimeImmutable( 'now' ) )->setTimezone( $timezone );
		$this->formatter = $formatter ?? static function ( \DateTimeImmutable $moment, string $kind ): string {
			return 'date' === $kind ? $moment->format( 'D j M' ) : $moment->format( 'g:i a' );
		};
	}

	public static function from_settings(): self {
		$settings = \Fleetbase\WooCommerce\Plugin::instance()->settings();
		return new self(
			(array) $settings->get( 'checkout', [] ),
			wp_timezone(),
			null,
			static function ( \DateTimeImmutable $moment, string $kind ): string {
				$format = 'date' === $kind ? (string) get_option( 'date_format', 'D j M' ) : (string) get_option( 'time_format', 'g:i a' );
				return wp_date( $format, $moment->getTimestamp(), $moment->getTimezone() );
			}
		);
	}

	public function enabled(): bool {
		return ! empty( $this->config['scheduling_enabled'] );
	}

	public function lead_time_minutes(): int {
		return max( 0, (int) ( $this->config['lead_time_minutes'] ?? 60 ) );
	}

	public function horizon_days(): int {
		return max( 1, min( 60, (int) ( $this->config['horizon_days'] ?? 7 ) ) );
	}

	public function window_minutes(): int {
		return max( 30, min( 720, (int) ( $this->config['window_minutes'] ?? 120 ) ) );
	}

	/**
	 * Dates with their available windows. Dates without any window are omitted.
	 *
	 * @return array<int, array{date:string, label:string, windows:array<int, array{value:string, start:string, end:string, label:string}>}>
	 */
	public function dates(): array {
		$earliest = $this->now->modify( '+' . $this->lead_time_minutes() . ' minutes' );
		$out      = [];
		$day      = $this->now->setTime( 0, 0, 0 );
		$horizon  = $this->horizon_days();

		for ( $i = 0; $i < $horizon; $i++ ) {
			$date    = $day->modify( '+' . $i . ' days' );
			$windows = $this->windows_for( $date, $earliest );
			if ( [] !== $windows ) {
				$out[] = [
					'date'    => $date->format( 'Y-m-d' ),
					'label'   => ( $this->formatter )( $date, 'date' ),
					'windows' => $windows,
				];
			}
		}
		return $out;
	}

	/**
	 * Flat list for a <select>: ASAP first, then every window.
	 *
	 * @return array<int, array{value:string, label:string}>
	 */
	public function options(): array {
		$options = [
			[
				'value' => self::ASAP,
				'label' => $this->asap_label(),
			],
		];
		foreach ( $this->dates() as $date ) {
			foreach ( $date['windows'] as $window ) {
				$options[] = [
					'value' => $window['value'],
					'label' => $date['label'] . ', ' . $window['label'],
				];
			}
		}
		return $options;
	}

	public function asap_label(): string {
		return function_exists( '__' ) ? __( 'As soon as possible', 'fleetbase-for-woocommerce' ) : 'As soon as possible';
	}

	public function is_valid( string $value ): bool {
		return null !== $this->normalize( $value );
	}

	/**
	 * Resolve a submitted value to its canonical form, or null if it is not offered.
	 *
	 * @return array{value:string, scheduled_at:string, label:string, date:string, start:string, end:string}|null
	 */
	public function normalize( string $value ): ?array {
		$value = trim( $value );
		if ( '' === $value || self::ASAP === $value ) {
			return [
				'value'        => self::ASAP,
				'scheduled_at' => '',
				'label'        => $this->asap_label(),
				'date'         => '',
				'start'        => '',
				'end'          => '',
			];
		}
		if ( ! $this->enabled() ) {
			return null;
		}
		foreach ( $this->dates() as $date ) {
			foreach ( $date['windows'] as $window ) {
				if ( $window['value'] === $value ) {
					$start = new \DateTimeImmutable( $date['date'] . ' ' . $window['start'], $this->timezone );
					return [
						'value'        => $value,
						'scheduled_at' => $start->format( \DateTimeInterface::ATOM ),
						'label'        => $date['label'] . ', ' . $window['label'],
						'date'         => $date['date'],
						'start'        => $window['start'],
						'end'          => $window['end'],
					];
				}
			}
		}
		return null;
	}

	/**
	 * @return array<int, array{value:string, start:string, end:string, label:string}>
	 */
	private function windows_for( \DateTimeImmutable $date, \DateTimeImmutable $earliest ): array {
		$key   = self::DAYS[ (int) $date->format( 'N' ) - 1 ];
		$hours = $this->config['delivery_hours'][ $key ] ?? null;
		if ( ! is_array( $hours ) || empty( $hours['enabled'] ) ) {
			return [];
		}
		$open  = self::minutes( (string) ( $hours['open'] ?? '09:00' ) );
		$close = self::minutes( (string) ( $hours['close'] ?? '18:00' ) );
		if ( null === $open || null === $close || $close <= $open ) {
			return [];
		}

		$windows = [];
		$step    = $this->window_minutes();
		for ( $start = $open; $start < $close; $start += $step ) {
			$end = min( $start + $step, $close );
			if ( $end - $start < 30 ) {
				continue;
			}
			$start_at = $date->setTime( intdiv( $start, 60 ), $start % 60 );
			if ( $start_at < $earliest ) {
				continue;
			}
			$end_at    = $date->setTime( intdiv( $end, 60 ), $end % 60 );
			$windows[] = [
				'value' => $date->format( 'Y-m-d' ) . '|' . $start_at->format( 'H:i' ) . '-' . $end_at->format( 'H:i' ),
				'start' => $start_at->format( 'H:i' ),
				'end'   => $end_at->format( 'H:i' ),
				'label' => ( $this->formatter )( $start_at, 'time' ) . ' – ' . ( $this->formatter )( $end_at, 'time' ),
			];
		}
		return $windows;
	}

	private static function minutes( string $hhmm ): ?int {
		if ( 1 !== preg_match( '/^(\d{1,2}):(\d{2})$/', $hhmm, $m ) ) {
			return null;
		}
		$h = (int) $m[1];
		$i = (int) $m[2];
		if ( $h > 23 || $i > 59 ) {
			return null;
		}
		return $h * 60 + $i;
	}
}
