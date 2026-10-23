<?php
/**
 * Runtime compatibility helpers.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Support;

use Fleetbase\WooCommerce\Hookable;

final class Compat implements Hookable {

	public function register(): void {
		add_filter( 'plugin_action_links_' . FLEETBASE_WC_BASENAME, [ $this, 'action_links' ] );
	}

	/**
	 * @param array<string, string> $links
	 * @return array<string, string>
	 */
	public function action_links( array $links ): array {
		$settings = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=fleetbase-settings' ) ),
			esc_html__( 'Settings', 'fleetbase-for-woocommerce' )
		);
		return [ 'settings' => $settings ] + $links;
	}

	public static function hpos_enabled(): bool {
		if ( ! class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class ) ) {
			return false;
		}
		return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}

	public static function fulfillments_enabled(): bool {
		if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			return false;
		}
		return \Automattic\WooCommerce\Utilities\FeaturesUtil::feature_is_enabled( 'fulfillments' );
	}
}
