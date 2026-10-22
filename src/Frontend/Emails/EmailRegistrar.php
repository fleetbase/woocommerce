<?php
/**
 * Registers the plugin's emails with WooCommerce.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Frontend\Emails;

use Fleetbase\WooCommerce\Hookable;

final class EmailRegistrar implements Hookable {

	public function register(): void {
		add_filter( 'woocommerce_email_classes', [ $this, 'add_emails' ] );
	}

	/**
	 * @param array<string, \WC_Email> $emails
	 * @return array<string, \WC_Email>
	 */
	public function add_emails( array $emails ): array {
		if ( class_exists( '\WC_Email' ) ) {
			$emails['WC_Email_Fleetbase_Out_For_Delivery'] = new OutForDeliveryEmail();
		}
		return $emails;
	}
}
