<?php
/**
 * A step of the OAuth connect flow failed. The reason is a short code the setup
 * screen turns into a sentence.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Connection\OAuth;

final class OAuthException extends \RuntimeException {

	public const UNREACHABLE     = 'unreachable';
	public const NOT_SUPPORTED   = 'not_supported';
	public const REGISTRATION    = 'registration_failed';
	public const STATE           = 'state_mismatch';
	public const DENIED          = 'access_denied';
	public const TOKEN           = 'token_failed';
	public const INVALID_CLIENT  = 'invalid_client';
	public const MISSING_SCOPE   = 'missing_scope';
	public const KEY             = 'key_failed';
	public const NOT_ADMIN       = 'no_permission';
	public const INVALID_REQUEST = 'invalid_request';

	public function __construct( private string $reason, string $message = '' ) {
		parent::__construct( '' !== $message ? $message : $reason );
	}

	public function reason(): string {
		return $this->reason;
	}
}
