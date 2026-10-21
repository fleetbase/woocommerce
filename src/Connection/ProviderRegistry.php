<?php
/**
 * Registry of connection providers.
 *
 * @package Fleetbase\WooCommerce
 */

declare(strict_types=1);

namespace Fleetbase\WooCommerce\Connection;

final class ProviderRegistry {

	/** @var array<string, ConnectionProvider>|null */
	private ?array $providers = null;

	/**
	 * @return array<string, ConnectionProvider>
	 */
	public function all(): array {
		if ( null === $this->providers ) {
			$providers = [ CloudProvider::ID => new CloudProvider() ];
			if ( CustomHostProvider::enabled() ) {
				$providers[ CustomHostProvider::ID ] = new CustomHostProvider();
			}

			/**
			 * Register additional connection providers (the self-hosted connector add-on uses this).
			 *
			 * @param array<string, ConnectionProvider> $providers
			 */
			$providers = (array) apply_filters( 'fleetbase_wc_connection_providers', $providers );

			$this->providers = array_filter(
				$providers,
				static function ( $provider ): bool {
					return $provider instanceof ConnectionProvider;
				}
			);
		}
		return $this->providers;
	}

	public function get( string $id ): ?ConnectionProvider {
		return $this->all()[ $id ] ?? null;
	}

	/**
	 * Non-secret description of each provider for the admin UI.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function describe(): array {
		$out = [];
		foreach ( $this->all() as $provider ) {
			$out[] = [
				'id'            => $provider->id(),
				'label'         => $provider->label(),
				'description'   => $provider->description(),
				'host_editable' => $provider->host_editable(),
				'default_host'  => $provider->default_host(),
				'status'        => $provider->status(),
			];
		}
		return $out;
	}
}
