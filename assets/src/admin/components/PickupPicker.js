import { useState } from '@wordpress/element';
import { Button, Notice, Spinner, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { api, errorMessage, runtimeData } from '../api';

/**
 * Lets the merchant search Fleetbase's geocoder (seeded with the store address),
 * pick a result or an existing saved place, and store it as the pickup location.
 */
export default function PickupPicker( { pickup, onSaved } ) {
	const { store } = runtimeData;
	const initialQuery = store?.address?.formatted || '';
	const [ query, setQuery ] = useState( initialQuery );
	const [ results, setResults ] = useState( [] );
	const [ saved, setSaved ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ saving, setSaving ] = useState( null );
	const [ error, setError ] = useState( null );

	const search = async () => {
		setBusy( true );
		setError( null );
		try {
			const res = await api( 'places/search', { query: { query } } );
			setResults( res.results || [] );
			if ( ! res.results?.length ) {
				setError( __( 'No matches. Try a shorter query, such as street and city.', 'fleetbase-for-woocommerce' ) );
			}
		} catch ( e ) {
			setError( errorMessage( e ) );
		}
		setBusy( false );
	};

	const loadSaved = async () => {
		setBusy( true );
		setError( null );
		try {
			const res = await api( 'places' );
			setSaved( res.results || [] );
		} catch ( e ) {
			setError( errorMessage( e ) );
		}
		setBusy( false );
	};

	const choose = async ( place, isExisting ) => {
		setSaving( place.place_id || place.address );
		setError( null );
		try {
			const body = isExisting && place.place_id
				? { place_id: place.place_id }
				: {
					name: store?.name,
					street1: place.street1 || place.address,
					street2: place.street2,
					city: place.city,
					province: place.province,
					postal_code: place.postal_code,
					country: place.country,
					latitude: place.latitude,
					longitude: place.longitude,
				};
			const res = await api( 'pickup', { method: 'POST', data: body } );
			onSaved( res.pickup );
		} catch ( e ) {
			setError( errorMessage( e ) );
		}
		setSaving( null );
	};

	const PlaceList = ( { items, existing } ) => (
		<ul className="fbwc-places">
			{ items.map( ( p ) => (
				<li key={ p.place_id || p.address }>
					<div>
						<strong>{ p.name || p.street1 || p.address }</strong>
						<div className="fbwc-muted">{ p.address }</div>
						{ ! p.has_location && <div className="fbwc-muted fbwc-warn">{ __( 'No coordinates', 'fleetbase-for-woocommerce' ) }</div> }
					</div>
					<Button variant="secondary" onClick={ () => choose( p, existing ) } disabled={ !! saving }>
						{ saving === ( p.place_id || p.address ) ? <Spinner /> : __( 'Use as pickup', 'fleetbase-for-woocommerce' ) }
					</Button>
				</li>
			) ) }
		</ul>
	);

	return (
		<div className="fbwc-pickup">
			{ pickup && (
				<Notice status="success" isDismissible={ false }>
					<strong>{ __( 'Current pickup location:', 'fleetbase-for-woocommerce' ) }</strong> { pickup.address }
					{ ! pickup.has_location && ' — ' + __( 'warning: this place has no coordinates, quotes will fail.', 'fleetbase-for-woocommerce' ) }
				</Notice>
			) }
			<p>{ __( 'Orders are picked up from this address. It is prefilled from your WooCommerce store address; search to confirm the exact location.', 'fleetbase-for-woocommerce' ) }</p>
			<div className="fbwc-search-row">
				<TextControl label={ __( 'Search address', 'fleetbase-for-woocommerce' ) } value={ query } onChange={ setQuery } onKeyDown={ ( e ) => e.key === 'Enter' && search() } __nextHasNoMarginBottom />
				<Button variant="primary" onClick={ search } disabled={ busy || query.length < 3 }>{ busy ? <Spinner /> : __( 'Search', 'fleetbase-for-woocommerce' ) }</Button>
			</div>
			{ error && <Notice status="warning" isDismissible onRemove={ () => setError( null ) }>{ error }</Notice> }
			{ results.length > 0 && <PlaceList items={ results } existing={ false } /> }
			<div className="fbwc-actions">
				<Button variant="link" onClick={ loadSaved }>{ __( 'Or choose a place already saved in Fleetbase', 'fleetbase-for-woocommerce' ) }</Button>
			</div>
			{ saved && ( saved.length ? <PlaceList items={ saved } existing /> : <p className="fbwc-muted">{ __( 'No saved places yet.', 'fleetbase-for-woocommerce' ) }</p> ) }
		</div>
	);
}
