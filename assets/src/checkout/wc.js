/**
 * WooCommerce's block packages are exposed as globals by its own scripts; this plugin
 * declares them as script dependencies rather than bundling them.
 */
export const blocksCheckout = window.wc?.blocksCheckout || {};
export const wcSettings = window.wc?.wcSettings || {};

export function getPluginSettings() {
	try {
		return (
			( wcSettings.getSetting &&
				wcSettings.getSetting( 'fleetbase_data', {} ) ) ||
			{}
		);
	} catch ( e ) {
		return {};
	}
}
