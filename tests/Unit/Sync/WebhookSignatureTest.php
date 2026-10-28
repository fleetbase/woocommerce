<?php

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Tests\Unit\Sync;

use Fleetbase\WooCommerce\Sync\WebhookSignature;
use PHPUnit\Framework\TestCase;

final class WebhookSignatureTest extends TestCase {

	private const BODY   = '{"event":"order.completed","data":{"id":"order_abc"}}';
	private const SECRET = 'whsec_test_secret';
	private const NOW    = 1793084156;

	public function test_matches_core_api_format(): void {
		// The format core-api's EndpointSignature produces: t=<time>,v1=hmac_sha256("<t>.<body>").
		$expected = 't=' . self::NOW . ',v1=' . hash_hmac( 'sha256', self::NOW . '.' . self::BODY, self::SECRET );

		$this->assertSame( $expected, WebhookSignature::sign( self::BODY, self::SECRET, self::NOW ) );
		$this->assertTrue( WebhookSignature::verify( $expected, self::BODY, self::SECRET, self::NOW ) );
	}

	public function test_accepts_any_matching_v1_and_spaces(): void {
		$good   = hash_hmac( 'sha256', self::NOW . '.' . self::BODY, self::SECRET );
		$header = 't=' . self::NOW . ', v1=' . str_repeat( '0', 64 ) . ', v1=' . $good;

		$this->assertTrue( WebhookSignature::verify( $header, self::BODY, self::SECRET, self::NOW ) );
	}

	public function test_rejects_wrong_tampered_stale_and_missing(): void {
		$header = WebhookSignature::sign( self::BODY, self::SECRET, self::NOW );

		$this->assertFalse( WebhookSignature::verify( $header, self::BODY, 'whsec_other', self::NOW ), 'wrong secret' );
		$this->assertFalse( WebhookSignature::verify( $header, self::BODY . ' ', self::SECRET, self::NOW ), 'tampered body' );
		$this->assertFalse( WebhookSignature::verify( $header, self::BODY, self::SECRET, self::NOW + 301 ), 'too old' );
		$this->assertFalse( WebhookSignature::verify( $header, self::BODY, self::SECRET, self::NOW - 301 ), 'from the future' );
		$this->assertTrue( WebhookSignature::verify( $header, self::BODY, self::SECRET, self::NOW + 300 ), 'at the tolerance' );
		$this->assertFalse( WebhookSignature::verify( '', self::BODY, self::SECRET, self::NOW ), 'missing header' );
		$this->assertFalse( WebhookSignature::verify( $header, self::BODY, '', self::NOW ), 'no secret' );
		$this->assertFalse( WebhookSignature::verify( 'v1=' . hash_hmac( 'sha256', self::NOW . '.' . self::BODY, self::SECRET ), self::BODY, self::SECRET, self::NOW ), 'no timestamp' );
		$this->assertFalse( WebhookSignature::verify( 't=' . self::NOW, self::BODY, self::SECRET, self::NOW ), 'no signature' );
		$this->assertFalse( WebhookSignature::verify( 't=abc,v1=def', self::BODY, self::SECRET, self::NOW ), 'garbage' );
	}
}
