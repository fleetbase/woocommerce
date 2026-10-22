import { useEffect, useId, useMemo, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { blocksCheckout, getPluginSettings } from './wc';

const { extensionCartUpdate } = blocksCheckout;

function usesFleetbase( shippingRates ) {
	return ( shippingRates || [] ).some( ( pkg ) =>
		( pkg.shipping_rates || [] ).some(
			( rate ) => rate.method_id === 'fleetbase' && rate.selected
		)
	);
}

function splitValue( value ) {
	if ( ! value || value === 'asap' ) {
		return { mode: 'asap', date: '', window: '' };
	}
	const [ date ] = value.split( '|' );
	return { mode: 'scheduled', date, window: value };
}

export default function DeliveryTime( { checkoutExtensionData } ) {
	const settings = useMemo( () => getPluginSettings(), [] );
	const labels = settings.labels || {};
	const dates = useMemo( () => settings.dates || [], [ settings ] );
	const idBase = useId();
	const { setExtensionData } = checkoutExtensionData || {};

	const initial = splitValue( settings.selected );
	const [ mode, setMode ] = useState( initial.mode );
	const [ date, setDate ] = useState(
		initial.date || ( dates[ 0 ] ? dates[ 0 ].date : '' )
	);
	const [ windowValue, setWindowValue ] = useState( initial.window );

	const { shippingRates, noService, noServiceMessage, cartChoice } =
		useSelect(
			( select ) => {
				const store = select( 'wc/store/cart' );
				const data = store.getCartData ? store.getCartData() : {};
				const ext =
					( data.extensions && data.extensions.fleetbase ) || {};
				return {
					shippingRates: store.getShippingRates
						? store.getShippingRates()
						: [],
					noService: !! ext.no_service,
					cartChoice: ext.delivery_choice || '',
					noServiceMessage:
						ext.no_service_message || settings.noServiceMessage,
				};
			},
			[ settings.noServiceMessage ]
		);

	const selectedDate = useMemo(
		() => dates.find( ( d ) => d.date === date ) || dates[ 0 ],
		[ dates, date ]
	);

	const value = mode === 'asap' ? 'asap' : windowValue;

	// A remount (WooCommerce re-renders shipping options when rates change) must not
	// discard a choice the session already holds.
	useEffect( () => {
		if ( ! cartChoice || cartChoice === value ) {
			return;
		}
		const fromCart = splitValue( cartChoice );
		setMode( fromCart.mode );
		if ( fromCart.date ) {
			setDate( fromCart.date );
			setWindowValue( fromCart.window );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps -- only react to the cart changing.
	}, [ cartChoice ] );

	useEffect( () => {
		if ( setExtensionData ) {
			setExtensionData( 'fleetbase', 'delivery', value );
		}
	}, [ value, setExtensionData ] );

	const commit = ( next ) => {
		if ( setExtensionData ) {
			setExtensionData( 'fleetbase', 'delivery', next );
		}
		if ( extensionCartUpdate ) {
			extensionCartUpdate( {
				namespace: 'fleetbase',
				data: { delivery: next },
			} );
		}
	};

	const chooseMode = ( next ) => {
		setMode( next );
		if ( next === 'asap' ) {
			commit( 'asap' );
		} else if ( selectedDate && selectedDate.windows.length ) {
			const first = windowValue || selectedDate.windows[ 0 ].value;
			setWindowValue( first );
			commit( first );
		}
	};

	const chooseDate = ( nextDate ) => {
		setDate( nextDate );
		const d = dates.find( ( x ) => x.date === nextDate );
		if ( d && d.windows.length ) {
			setWindowValue( d.windows[ 0 ].value );
			commit( d.windows[ 0 ].value );
		}
	};

	const chooseWindow = ( next ) => {
		setWindowValue( next );
		commit( next );
	};

	if ( noService ) {
		return (
			<div className="fleetbase-wc-delivery-time fleetbase-wc-delivery-time--no-service wc-block-components-notice-banner is-info">
				<div className="wc-block-components-notice-banner__content">
					{ noServiceMessage }
				</div>
			</div>
		);
	}

	if ( ! settings.schedulingEnabled || ! usesFleetbase( shippingRates ) ) {
		return null;
	}

	return (
		<div className="fleetbase-wc-delivery-time wc-block-components-checkout-step__content">
			<fieldset className="fleetbase-wc-delivery-time__fieldset">
				<legend className="fleetbase-wc-delivery-time__title">
					{ labels.title }
				</legend>
				<label
					className="fleetbase-wc-delivery-time__option"
					htmlFor={ idBase + '-asap' }
				>
					<input
						id={ idBase + '-asap' }
						type="radio"
						name="fleetbase_delivery_mode"
						value="asap"
						checked={ mode === 'asap' }
						onChange={ () => chooseMode( 'asap' ) }
					/>
					<span>{ labels.asap }</span>
				</label>
				<label
					className="fleetbase-wc-delivery-time__option"
					htmlFor={ idBase + '-scheduled' }
				>
					<input
						id={ idBase + '-scheduled' }
						type="radio"
						name="fleetbase_delivery_mode"
						value="scheduled"
						checked={ mode === 'scheduled' }
						onChange={ () => chooseMode( 'scheduled' ) }
						disabled={ ! dates.length }
					/>
					<span>{ dates.length ? labels.choose : labels.none }</span>
				</label>
				{ mode === 'scheduled' && selectedDate && (
					<div className="fleetbase-wc-delivery-time__pickers">
						<label htmlFor={ idBase + '-day' }>
							<span>{ labels.day }</span>
							<select
								id={ idBase + '-day' }
								value={ selectedDate.date }
								onChange={ ( e ) =>
									chooseDate( e.target.value )
								}
							>
								{ dates.map( ( d ) => (
									<option key={ d.date } value={ d.date }>
										{ d.label }
									</option>
								) ) }
							</select>
						</label>
						<label htmlFor={ idBase + '-window' }>
							<span>{ labels.window }</span>
							<select
								id={ idBase + '-window' }
								value={ windowValue }
								onChange={ ( e ) =>
									chooseWindow( e.target.value )
								}
							>
								{ selectedDate.windows.map( ( w ) => (
									<option key={ w.value } value={ w.value }>
										{ w.label }
									</option>
								) ) }
							</select>
						</label>
					</div>
				) }
			</fieldset>
		</div>
	);
}
