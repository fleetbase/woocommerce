<?php
/**
 * Uninstall routine: removes plugin options. Order meta is left in place because it is
 * part of the merchant's order history.
 *
 * @package Fleetbase\WooCommerce
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$fleetbase_wc_options = [
	'fleetbase_wc_version',
	'fleetbase_wc_settings',
	'fleetbase_wc_connection',
	'fleetbase_wc_site_token',
	'fleetbase_wc_pickup',
	'fleetbase_wc_wizard_completed',
	'fleetbase_wc_webhook_endpoint',
	'fleetbase_wc_webhook_last',
	'fleetbase_wc_poll_last',
	'fleetbase_wc_poll_interval',
	'fleetbase_wc_quote_cache_version',
	'fleetbase_wc_caps_version',
];

foreach ( $fleetbase_wc_options as $fleetbase_wc_option ) {
	delete_option( $fleetbase_wc_option );
}

delete_metadata( 'user', 0, 'fleetbase_wc_notice_dismissed', '', true );

// The dispatcher role and capability, as Support\Capabilities::remove() does (the autoloader is not loaded here).
remove_role( 'fleetbase_dispatcher' );
foreach ( [ 'administrator', 'shop_manager' ] as $fleetbase_wc_role_name ) {
	$fleetbase_wc_role = get_role( $fleetbase_wc_role_name );
	if ( $fleetbase_wc_role instanceof WP_Role ) {
		$fleetbase_wc_role->remove_cap( 'manage_fleetbase_deliveries' );
	}
}

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( '', [], 'fleetbase-wc' );
}
