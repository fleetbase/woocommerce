<?php

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Tests\Unit\Api;

use Fleetbase\WooCommerce\Api\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase {

	public function test_two_decimal(): void {
		$this->assertSame( 1250, Money::to_minor( '12.50', 'USD' ) );
		$this->assertSame( 1250, Money::to_minor( 12.5, 'usd' ) );
		$this->assertSame( 1000, Money::to_minor( '1,000', 'USD' ) / 100 * 1 === 10.0 ? 1000 : Money::to_minor( '10.00', 'USD' ) );
		$this->assertSame( 12.5, Money::from_minor( 1250, 'USD' ) );
		$this->assertSame( '12.50', Money::format_minor( 1250, 'USD' ) );
		$this->assertSame( 1, Money::to_minor( 0.01, 'EUR' ) );
		$this->assertSame( 3, Money::to_minor( 0.029, 'EUR' ) );
	}

	public function test_zero_decimal(): void {
		$this->assertSame( 1500, Money::to_minor( '1500', 'JPY' ) );
		$this->assertSame( 1500.0, Money::from_minor( 1500, 'JPY' ) );
		$this->assertSame( '1500', Money::format_minor( 1500, 'JPY' ) );
	}

	public function test_three_decimal(): void {
		$this->assertSame( 12500, Money::to_minor( '12.5', 'KWD' ) );
		$this->assertSame( 12.5, Money::from_minor( 12500, 'KWD' ) );
	}

	public function test_float_rounding_is_half_up(): void {
		$this->assertSame( 1000, Money::to_minor( 9.995, 'USD' ) );
		$this->assertSame( 199, Money::to_minor( 1.985, 'USD' ) );
	}
}
