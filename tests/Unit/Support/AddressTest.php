<?php

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Tests\Unit\Support;

use Fleetbase\WooCommerce\Support\Address;
use PHPUnit\Framework\TestCase;

final class AddressTest extends TestCase {

	public function test_to_place_maps_and_drops_empty_fields(): void {
		$place = Address::to_place( [ 'address_1' => '1124 Pike St', 'address_2' => '', 'city' => 'Seattle', 'state' => 'WA', 'postcode' => '98101', 'country' => 'us' ] );
		$this->assertSame( [ 'street1' => '1124 Pike St', 'city' => 'Seattle', 'province' => 'WA', 'postal_code' => '98101', 'country' => 'US' ], $place );
	}

	public function test_is_quotable_requires_country_and_postcode_or_street_and_city(): void {
		$this->assertTrue( Address::is_quotable( [ 'country' => 'US', 'postcode' => '98101' ] ) );
		$this->assertTrue( Address::is_quotable( [ 'country' => 'US', 'address_1' => '1 Main', 'city' => 'Seattle' ] ) );
		$this->assertFalse( Address::is_quotable( [ 'country' => 'US', 'city' => 'Seattle' ] ) );
		$this->assertFalse( Address::is_quotable( [ 'postcode' => '98101' ] ) );
		$this->assertFalse( Address::is_quotable( [] ) );
	}

	public function test_fingerprint_is_stable_across_formatting(): void {
		$a = Address::fingerprint( [ 'address_1' => '1124 Pike St', 'city' => 'Seattle', 'postcode' => '98101', 'country' => 'US' ] );
		$b = Address::fingerprint( [ 'address_1' => '  1124   pike st', 'address_2' => '', 'city' => 'SEATTLE', 'postcode' => '98101 ', 'country' => 'us' ] );
		$this->assertSame( $a, $b );
		$this->assertNotSame( $a, Address::fingerprint( [ 'address_1' => '1124 Pike St', 'city' => 'Seattle', 'postcode' => '98109', 'country' => 'US' ] ) );
	}
}
