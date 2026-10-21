<?php
/**
 * PHPUnit bootstrap for unit tests (no WordPress runtime).
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'FLEETBASE_WC_VERSION' ) ) {
	define( 'FLEETBASE_WC_VERSION', '0.0.0-test' );
}
if ( ! defined( 'FLEETBASE_WC_PATH' ) ) {
	define( 'FLEETBASE_WC_PATH', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'FLEETBASE_WC_URL' ) ) {
	define( 'FLEETBASE_WC_URL', 'https://example.test/wp-content/plugins/fleetbase-for-woocommerce/' );
}
if ( ! defined( 'FLEETBASE_WC_FILE' ) ) {
	define( 'FLEETBASE_WC_FILE', dirname( __DIR__ ) . '/fleetbase-for-woocommerce.php' );
}
if ( ! defined( 'FLEETBASE_WC_BASENAME' ) ) {
	define( 'FLEETBASE_WC_BASENAME', 'fleetbase-for-woocommerce/fleetbase-for-woocommerce.php' );
}
if ( ! defined( 'FLEETBASE_WC_MIN_WC_VERSION' ) ) {
	define( 'FLEETBASE_WC_MIN_WC_VERSION', '9.5' );
}
