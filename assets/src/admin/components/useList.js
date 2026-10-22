import { useCallback, useEffect, useState } from '@wordpress/element';
import { api, errorMessage } from '../api';

/**
 * Loads a list from a plugin REST route and exposes reload.
 *
 * @param {string} path    Route path relative to fleetbase/v1.
 * @param {string} key     Response key holding the array.
 * @param {Object} [query] Query params.
 */
export default function useList( path, key, query ) {
	const [ items, setItems ] = useState( [] );
	const [ meta, setMeta ] = useState( {} );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const serialized = JSON.stringify( query || {} );

	const reload = useCallback( async () => {
		setLoading( true );
		setError( null );
		try {
			const res = await api( path, { query: JSON.parse( serialized ) } );
			setItems( res[ key ] || [] );
			const { [ key ]: omit, ...rest } = res; // eslint-disable-line no-unused-vars
			setMeta( rest );
		} catch ( e ) {
			setError( errorMessage( e ) );
		}
		setLoading( false );
	}, [ path, key, serialized ] );

	useEffect( () => {
		reload();
	}, [ reload ] );

	return { items, setItems, meta, loading, error, setError, reload };
}
