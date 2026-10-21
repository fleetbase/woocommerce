<?php
/**
 * Transport that replays queued responses and records requests.
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Tests\Support;

use Fleetbase\WooCommerce\Api\Exception\TransportException;
use Fleetbase\WooCommerce\Api\Transport;
use Fleetbase\WooCommerce\Api\TransportResponse;

final class FakeTransport implements Transport {

	/** @var array<int, TransportResponse|\Throwable> */
	private array $queue = [];

	/** @var array<int, array{method:string,url:string,headers:array<string,string>,body:?string,timeout:int}> */
	public array $requests = [];

	public function queue( int $status, string|array $body = '', array $headers = [] ): self {
		$this->queue[] = new TransportResponse( $status, array_change_key_case( $headers, CASE_LOWER ), is_array( $body ) ? (string) json_encode( $body ) : $body );
		return $this;
	}

	public function queue_failure( string $message = 'connection refused' ): self {
		$this->queue[] = new TransportException( $message );
		return $this;
	}

	public function send( string $method, string $url, array $headers, ?string $body, int $timeout ): TransportResponse {
		$this->requests[] = compact( 'method', 'url', 'headers', 'body', 'timeout' );
		if ( [] === $this->queue ) {
			throw new \LogicException( 'FakeTransport queue is empty for ' . $method . ' ' . $url );
		}
		$next = array_shift( $this->queue );
		if ( $next instanceof \Throwable ) {
			throw $next;
		}
		return $next;
	}

	public function last(): array {
		return $this->requests[ array_key_last( $this->requests ) ];
	}
}
