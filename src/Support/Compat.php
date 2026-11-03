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

	private const LEGACY_META_QUERY = 'fleetbase_wc_meta_query';

	public function register(): void {
		add_filter( 'plugin_action_links_' . FLEETBASE_WC_BASENAME, [ $this, 'action_links' ] );
		add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', [ $this, 'apply_legacy_meta_query' ], 10, 2 );
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

	/**
	 * Arguments for wc_get_orders() that filter on meta and search on every store. Legacy order
	 * storage ignores meta_query and would return every order, so there it travels under our
	 * own name and apply_legacy_meta_query() hands it to WP_Query. It has no order search in
	 * the query either, so the search runs first and limits the query to what it found.
	 *
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>
	 */
	public static function order_query( array $args ): array {
		if ( self::hpos_enabled() ) {
			return $args;
		}
		if ( ! empty( $args['meta_query'] ) ) {
			$args[ self::LEGACY_META_QUERY ] = $args['meta_query'];
			unset( $args['meta_query'] );
		}
		if ( ! empty( $args['s'] ) && function_exists( 'wc_order_search' ) ) {
			$found            = wc_order_search( (string) $args['s'] );
			$args['post__in'] = [] === $found ? [ 0 ] : $found;
			unset( $args['s'] );
		}
		return $args;
	}

	/**
	 * @param array<string, mixed> $wp_args
	 * @param array<string, mixed> $query_vars
	 * @return array<string, mixed>
	 */
	public function apply_legacy_meta_query( array $wp_args, array $query_vars ): array {
		if ( empty( $query_vars[ self::LEGACY_META_QUERY ] ) ) {
			return $wp_args;
		}
		unset( $wp_args[ self::LEGACY_META_QUERY ] );
		$wp_args['meta_query']   = (array) ( $wp_args['meta_query'] ?? [] ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		$wp_args['meta_query'][] = $query_vars[ self::LEGACY_META_QUERY ];
		return $wp_args;
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
