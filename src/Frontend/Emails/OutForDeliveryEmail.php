<?php
/**
 * "Your order is out for delivery" customer email.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Frontend\Emails;

use Fleetbase\WooCommerce\Plugin;
use Fleetbase\WooCommerce\Sync\SyncState;

final class OutForDeliveryEmail extends \WC_Email {

	public function __construct() {
		$this->id             = 'fleetbase_out_for_delivery';
		$this->customer_email = true;
		$this->title          = __( 'Out for delivery', 'fleetbase-for-woocommerce' );
		$this->description    = __( 'Sent to the customer when a Fleetbase driver is on the way with their order.', 'fleetbase-for-woocommerce' );
		$this->template_html  = 'emails/customer-out-for-delivery.php';
		$this->template_plain = 'emails/plain/customer-out-for-delivery.php';
		$this->template_base  = FLEETBASE_WC_PATH . 'templates/';
		$this->placeholders   = [
			'{order_number}'    => '',
			'{tracking_number}' => '',
		];

		add_action( 'fleetbase_wc_order_out_for_delivery', [ $this, 'trigger_for_order' ] );

		parent::__construct();
	}

	public function get_default_subject(): string {
		return __( 'Your {site_title} order #{order_number} is out for delivery', 'fleetbase-for-woocommerce' );
	}

	public function get_default_heading(): string {
		return __( 'Your order is on its way', 'fleetbase-for-woocommerce' );
	}

	public function trigger_for_order( \WC_Order $order ): void {
		$this->trigger( $order->get_id(), $order );
	}

	/**
	 * @param int            $order_id
	 * @param \WC_Order|null $order
	 */
	public function trigger( $order_id, $order = null ): void {
		$this->setup_locale();
		if ( ! Plugin::instance()->settings()->get( 'tracking.out_for_delivery_email', true ) ) {
			$this->restore_locale();
			return;
		}
		$order = $order instanceof \WC_Order ? $order : wc_get_order( (int) $order_id );
		if ( $order instanceof \WC_Order ) {
			$this->object                            = $order;
			$this->recipient                         = $order->get_billing_email();
			$this->placeholders['{order_number}']    = $order->get_order_number();
			$this->placeholders['{tracking_number}'] = (string) $order->get_meta( SyncState::META_TRACKING_NUMBER, true );
		}
		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}
		$this->restore_locale();
	}

	/**
	 * @return array<string, mixed>
	 */
	private function template_args( bool $plain ): array {
		$order = $this->object;
		return [
			'order'              => $order,
			'email_heading'      => $this->get_heading(),
			'additional_content' => $this->get_additional_content(),
			'sent_to_admin'      => false,
			'plain_text'         => $plain,
			'email'              => $this,
			'tracking_number'    => $order instanceof \WC_Order ? (string) $order->get_meta( SyncState::META_TRACKING_NUMBER, true ) : '',
			'tracking_url'       => $order instanceof \WC_Order ? (string) $order->get_meta( SyncState::META_TRACKING_URL, true ) : '',
			'driver'             => $order instanceof \WC_Order ? strtok( (string) $order->get_meta( SyncState::META_DRIVER, true ), ' ' ) : '',
			'delivery_window'    => $order instanceof \WC_Order ? (string) $order->get_meta( '_fleetbase_delivery_label', true ) : '',
		];
	}

	public function get_content_html(): string {
		return (string) wc_get_template_html( $this->template_html, $this->template_args( false ), '', $this->template_base );
	}

	public function get_content_plain(): string {
		return (string) wc_get_template_html( $this->template_plain, $this->template_args( true ), '', $this->template_base );
	}
}
