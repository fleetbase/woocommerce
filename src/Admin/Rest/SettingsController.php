<?php
/**
 * Settings read/write and wizard completion.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Admin\Rest;

use Fleetbase\WooCommerce\Plugin;
use Fleetbase\WooCommerce\Support\WebhookUrl;

final class SettingsController extends RestController {

	public function register_routes(): void {
		$this->route( '/settings', 'GET', [ $this, 'get_settings' ] );
		$this->route( '/settings', 'POST', [ $this, 'update_settings' ] );
		$this->route( '/wizard/complete', 'POST', [ $this, 'complete_wizard' ] );
		$this->route( '/wizard/reset', 'POST', [ $this, 'reset_wizard' ] );
		$this->route( '/webhook/rotate', 'POST', [ $this, 'rotate_webhook' ] );
		$this->route( '/webhook/register', 'POST', [ $this, 'register_webhook' ] );
	}

	public function get_settings(): \WP_REST_Response {
		return $this->respond( [ 'settings' => Plugin::instance()->settings()->all() ] );
	}

	public function update_settings( \WP_REST_Request $request ): \WP_REST_Response {
		$partial = $request->get_json_params();
		$partial = is_array( $partial ) ? ( $partial['settings'] ?? $partial ) : [];
		$all     = Plugin::instance()->settings()->update( is_array( $partial ) ? $partial : [] );
		return $this->respond( [ 'settings' => $all ] );
	}

	public function complete_wizard(): \WP_REST_Response {
		update_option( 'fleetbase_wc_wizard_completed', time(), false );
		return $this->respond( [ 'wizardCompleted' => true ] );
	}

	public function reset_wizard(): \WP_REST_Response {
		delete_option( 'fleetbase_wc_wizard_completed' );
		return $this->respond( [ 'wizardCompleted' => false ] );
	}

	public function rotate_webhook(): \WP_REST_Response {
		$webhook = Plugin::instance()->get( \Fleetbase\WooCommerce\Support\WebhookUrl::class );
		$webhook->rotate();
		$status = Plugin::instance()->get( \Fleetbase\WooCommerce\Sync\WebhookRegistrar::class )->sync();
		return $this->respond(
			[
				'webhookUrl' => $webhook->url(),
				'webhook'    => $status,
			]
		);
	}

	/**
	 * Register the webhook URL with Fleetbase through the API key.
	 */
	public function register_webhook(): \WP_REST_Response {
		return $this->respond( [ 'webhook' => Plugin::instance()->get( \Fleetbase\WooCommerce\Sync\WebhookRegistrar::class )->sync() ] );
	}
}
