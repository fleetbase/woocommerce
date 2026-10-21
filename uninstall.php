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
];

foreach ( $fleetbase_wc_options as $fleetbase_wc_option ) {
	delete_option( $fleetbase_wc_option );
}

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( '', [], 'fleetbase-wc' );
}
