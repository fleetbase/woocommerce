<?php
/**
 * The dispatcher capability and role.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Support;

use Fleetbase\WooCommerce\Hookable;

final class Capabilities implements Hookable {

	public const CAP  = 'manage_fleetbase_deliveries';
	public const ROLE = 'fleetbase_dispatcher';

	private const VERSION_OPTION = 'fleetbase_wc_caps_version';
	private const VERSION        = '1';

	public function register(): void {
		add_action( 'init', [ self::class, 'ensure' ], 5 );
	}

	/**
	 * Create the role and grant the capability to store managers. Idempotent and cheap after the first run.
	 */
	public static function ensure(): void {
		if ( get_option( self::VERSION_OPTION ) === self::VERSION && null !== get_role( self::ROLE ) ) {
			return;
		}
		if ( null === get_role( self::ROLE ) ) {
			add_role(
				self::ROLE,
				__( 'Fleetbase dispatcher', 'fleetbase-for-woocommerce' ),
				[
					'read'    => true,
					self::CAP => true,
				]
			);
		}
		foreach ( [ 'administrator', 'shop_manager', self::ROLE ] as $role_name ) {
			$role = get_role( $role_name );
			if ( $role instanceof \WP_Role && ! $role->has_cap( self::CAP ) ) {
				$role->add_cap( self::CAP );
			}
		}
		update_option( self::VERSION_OPTION, self::VERSION, true );

		// The current user's capability cache was built before the role gained the capability.
		$user = wp_get_current_user();
		if ( $user instanceof \WP_User && $user->exists() ) {
			$user->get_role_caps();
		}
	}

	public static function remove(): void {
		remove_role( self::ROLE );
		foreach ( [ 'administrator', 'shop_manager' ] as $role_name ) {
			$role = get_role( $role_name );
			if ( $role instanceof \WP_Role ) {
				$role->remove_cap( self::CAP );
			}
		}
		delete_option( self::VERSION_OPTION );
	}

	public static function can_dispatch(): bool {
		return current_user_can( self::CAP ) || current_user_can( 'manage_woocommerce' );
	}

	public static function can_manage(): bool {
		return current_user_can( 'manage_woocommerce' );
	}
}
