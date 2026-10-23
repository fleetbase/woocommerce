import apiFetch from '@wordpress/api-fetch';

const runtime = window.fleetbaseWc || {};

// WordPress registers its own root URL and nonce middleware for wp-api-fetch, so paths only
// need the plugin namespace. Registering a second root middleware would make both apply.
const NAMESPACE = '/fleetbase/v1/';

/**
 * Call a plugin REST route. Paths are relative to fleetbase/v1.
 *
 * @param {string} path    Route path relative to the plugin namespace.
 * @param {Object} options Request options: method, data, query.
 * @return {Promise<any>} Parsed JSON response.
 */
export function api( path, options = {} ) {
	const { method = 'GET', data, query } = options;
	let fullPath = NAMESPACE + path.replace( /^\//, '' );
	if ( query ) {
		const params = new URLSearchParams( query );
		fullPath +=
			( fullPath.includes( '?' ) ? '&' : '?' ) + params.toString();
	}
	return apiFetch( { path: fullPath, method, data } );
}

export function errorMessage( error ) {
	if ( ! error ) {
		return '';
	}
	if ( typeof error === 'string' ) {
		return error;
	}
	if ( error.message ) {
		return error.message;
	}
	return 'Request failed.';
}

export const runtimeData = runtime;
