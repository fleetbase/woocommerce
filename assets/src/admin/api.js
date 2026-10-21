import apiFetch from '@wordpress/api-fetch';

const runtime = window.fleetbaseWc || {};

apiFetch.use( apiFetch.createRootURLMiddleware( runtime.restRoot ? runtime.restRoot.replace( /\/$/, '' ) + '/' : '/wp-json/fleetbase/v1/' ) );
apiFetch.use( apiFetch.createNonceMiddleware( runtime.nonce || '' ) );

/**
 * Call a plugin REST route. Paths are relative to fleetbase/v1.
 */
export function api( path, options = {} ) {
	const { method = 'GET', data, query } = options;
	let url = path.replace( /^\//, '' );
	if ( query ) {
		const params = new URLSearchParams( query );
		url += ( url.includes( '?' ) ? '&' : '?' ) + params.toString();
	}
	return apiFetch( { path: url, method, data } );
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
