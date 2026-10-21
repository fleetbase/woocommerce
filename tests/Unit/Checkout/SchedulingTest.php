<?php

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Tests\Unit\Checkout;

use Fleetbase\WooCommerce\Checkout\Scheduling;
use PHPUnit\Framework\TestCase;

final class SchedulingTest extends TestCase {

	private function config( array $override = [] ): array {
		$hours = [];
		foreach ( [ 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ] as $day ) {
			$hours[ $day ] = [ 'enabled' => 'sun' !== $day, 'open' => '09:00', 'close' => '18:00' ];
		}
		return array_merge(
			[ 'scheduling_enabled' => true, 'lead_time_minutes' => 60, 'horizon_days' => 3, 'window_minutes' => 120, 'delivery_hours' => $hours ],
			$override
		);
	}

	private function scheduling( array $override = [], string $now = '2026-10-21 10:30:00' ): Scheduling {
		$tz = new \DateTimeZone( 'America/Los_Angeles' );
		return new Scheduling( $this->config( $override ), $tz, new \DateTimeImmutable( $now, $tz ) );
	}

	public function test_today_windows_respect_lead_time_and_horizon(): void {
		$dates = $this->scheduling()->dates();
		$this->assertCount( 3, $dates, 'Wed, Thu, Fri' );
		$this->assertSame( '2026-10-21', $dates[0]['date'] );
		// 10:30 + 60 min lead = 11:30, so 09:00 and 11:00 windows are gone; 13:00 is the first.
		$this->assertSame( [ '13:00', '15:00', '17:00' ], array_column( $dates[0]['windows'], 'start' ) );
		$this->assertSame( '18:00', $dates[0]['windows'][2]['end'], 'last window is clipped to closing time' );
		$this->assertSame( [ '09:00', '11:00', '13:00', '15:00', '17:00' ], array_column( $dates[1]['windows'], 'start' ) );
	}

	public function test_closed_days_are_skipped(): void {
		$dates = $this->scheduling( [ 'horizon_days' => 7 ], '2026-10-24 08:00:00' )->dates(); // Saturday
		$this->assertSame( [ '2026-10-24', '2026-10-26', '2026-10-27', '2026-10-28', '2026-10-29', '2026-10-30' ], array_column( $dates, 'date' ), 'Sunday 25th omitted' );
	}

	public function test_short_tail_windows_are_dropped(): void {
		$dates = $this->scheduling( [ 'window_minutes' => 240, 'delivery_hours' => [ 'wed' => [ 'enabled' => true, 'open' => '09:00', 'close' => '17:15' ] ] ], '2026-10-21 06:00:00' )->dates();
		$this->assertSame( [ '09:00', '13:00' ], array_column( $dates[0]['windows'], 'start' ) );
		$this->assertSame( '17:00', $dates[0]['windows'][1]['end'] );
	}

	public function test_options_and_normalize(): void {
		$s       = $this->scheduling();
		$options = $s->options();
		$this->assertSame( 'asap', $options[0]['value'] );
		$this->assertSame( '2026-10-21|13:00-15:00', $options[1]['value'] );
		$this->assertSame( 'Wed 21 Oct, 1:00 pm – 3:00 pm', $options[1]['label'] );

		$n = $s->normalize( '2026-10-21|13:00-15:00' );
		$this->assertNotNull( $n );
		$this->assertSame( '2026-10-21T13:00:00-07:00', $n['scheduled_at'] );
		$this->assertSame( '15:00', $n['end'] );

		$asap = $s->normalize( '' );
		$this->assertSame( 'asap', $asap['value'] );
		$this->assertSame( '', $asap['scheduled_at'] );

		$this->assertNull( $s->normalize( '2026-10-21|09:00-11:00' ), 'window before lead time is not offered' );
		$this->assertNull( $s->normalize( '2026-10-25|09:00-11:00' ), 'closed day' );
		$this->assertNull( $s->normalize( '2026-11-30|09:00-11:00' ), 'beyond horizon' );
		$this->assertNull( $s->normalize( 'garbage' ) );
	}

	public function test_disabled_scheduling_only_accepts_asap(): void {
		$s = $this->scheduling( [ 'scheduling_enabled' => false ] );
		$this->assertFalse( $s->enabled() );
		$this->assertTrue( $s->is_valid( 'asap' ) );
		$this->assertFalse( $s->is_valid( '2026-10-21|13:00-15:00' ) );
	}

	public function test_invalid_hours_yield_no_windows(): void {
		$s = $this->scheduling( [ 'delivery_hours' => [ 'wed' => [ 'enabled' => true, 'open' => '18:00', 'close' => '09:00' ], 'thu' => [ 'enabled' => true, 'open' => 'x', 'close' => '12:00' ] ] ] );
		$this->assertSame( [], $s->dates() );
	}
}
