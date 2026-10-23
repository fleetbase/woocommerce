/* eslint-disable camelcase -- form fields mirror Fleetbase API keys. */
import { useEffect, useState } from '@wordpress/element';
import {
	Button,
	ExternalLink,
	Modal,
	Notice,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import Header from '../components/Header';
import DataTable from '../components/DataTable';
import useList from '../components/useList';
import { api, errorMessage, runtimeData } from '../api';

const METHODS = [
	{
		value: 'per_meter',
		label: __(
			'Base fee plus a fee per distance unit',
			'fleetbase-for-woocommerce'
		),
	},
	{
		value: 'fixed_rate',
		label: __( 'Flat price by distance tier', 'fleetbase-for-woocommerce' ),
	},
	{
		value: 'fixed_meter',
		label: __( 'Fee per distance tier', 'fleetbase-for-woocommerce' ),
	},
];
const UNITS = [ 'km', 'mi', 'm', 'ft', 'yd' ].map( ( u ) => ( {
	value: u,
	label: u,
} ) );

const EMPTY = {
	service_name: '',
	service_type: 'standard',
	rate_calculation_method: 'per_meter',
	base_fee: '0.00',
	per_meter_unit: 'km',
	per_meter_flat_rate_fee: '',
	meter_fees: [ { distance: '', fee: '' } ],
	service_area: '',
	zone: '',
	has_cod_fee: false,
	cod_calculation_method: 'flat',
	cod_flat_fee: '',
	cod_percent: 0,
	has_peak_hours_fee: false,
	peak_hours_calculation_method: 'flat',
	peak_hours_flat_fee: '',
	peak_hours_percent: 0,
	peak_hours_start: '17:00',
	peak_hours_end: '19:00',
	duration_terms: '',
	estimated_days: 0,
};

function describe( r, currency ) {
	if ( r.rate_calculation_method === 'per_meter' ) {
		return sprintf(
			/* translators: 1: currency, 2: base fee, 3: per-unit fee, 4: unit */
			__( '%1$s %2$s + %1$s %3$s per %4$s', 'fleetbase-for-woocommerce' ),
			currency,
			r.base_fee,
			r.per_meter_flat_rate_fee,
			r.per_meter_unit
		);
	}
	return ( r.meter_fees || [] )
		.map(
			( t ) =>
				`≤${ t.distance }${ r.per_meter_unit }: ${ currency } ${ t.fee }`
		)
		.join( ', ' );
}

export default function ServiceRates() {
	const list = useList( 'service-rates', 'rates' );
	const [ options, setOptions ] = useState( {
		areas: [],
		zones: [],
		currency: runtimeData.store.currency,
		console_url: runtimeData.consoleUrl,
	} );
	const [ editing, setEditing ] = useState( null );
	const [ form, setForm ] = useState( EMPTY );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ notice, setNotice ] = useState( null );
	const currency = options.currency || runtimeData.store.currency;

	useEffect( () => {
		api( 'service-rates/options' )
			.then( setOptions )
			.catch( () => {} );
	}, [] );

	const open = ( rate ) => {
		setEditing( rate || 'new' );
		setForm(
			rate
				? {
						...EMPTY,
						...rate,
						meter_fees:
							rate.meter_fees && rate.meter_fees.length
								? rate.meter_fees
								: EMPTY.meter_fees,
				  }
				: EMPTY
		);
		setError( null );
	};

	const save = async () => {
		setBusy( true );
		setError( null );
		try {
			const isNew = editing === 'new';
			const res = await api(
				isNew ? 'service-rates' : `service-rates/${ editing.id }`,
				{ method: isNew ? 'POST' : 'PUT', data: form }
			);
			list.setItems(
				isNew
					? [ res.rate, ...list.items ]
					: list.items.map( ( r ) =>
							r.id === res.rate.id ? res.rate : r
					  )
			);
			setNotice( {
				status: 'success',
				text: __(
					'Service rate saved. Checkout quotes refresh immediately.',
					'fleetbase-for-woocommerce'
				),
			} );
			setEditing( null );
		} catch ( e ) {
			setError( errorMessage( e ) );
		}
		setBusy( false );
	};

	const remove = async ( rate ) => {
		if (
			// eslint-disable-next-line no-alert -- destructive action deserves a native confirm.
			! window.confirm(
				__(
					'Delete this service rate? Customers will no longer see it at checkout.',
					'fleetbase-for-woocommerce'
				)
			)
		) {
			return;
		}
		try {
			await api( `service-rates/${ rate.id }`, { method: 'DELETE' } );
			list.setItems( list.items.filter( ( r ) => r.id !== rate.id ) );
		} catch ( e ) {
			setNotice( { status: 'error', text: errorMessage( e ) } );
		}
	};

	const setTier = ( i, patch ) => {
		const tiers = form.meter_fees.map( ( t, j ) =>
			j === i ? { ...t, ...patch } : t
		);
		setForm( { ...form, meter_fees: tiers } );
	};

	const areaName = ( id ) =>
		( options.areas.find( ( a ) => a.id === id ) || {} ).name || '';

	const columns = [
		{
			key: 'service_name',
			label: __( 'Rate', 'fleetbase-for-woocommerce' ),
			render: ( r ) => (
				<>
					<strong>{ r.service_name }</strong>
					<div className="fbwc-muted">
						{ r.service_type }
						{ r.duration_terms ? ` · ${ r.duration_terms }` : '' }
					</div>
				</>
			),
		},
		{
			key: 'pricing',
			label: __( 'Pricing', 'fleetbase-for-woocommerce' ),
			render: ( r ) => describe( r, r.currency || currency ),
		},
		{
			key: 'area',
			label: __( 'Where', 'fleetbase-for-woocommerce' ),
			render: ( r ) =>
				areaName( r.service_area ) ||
				( r.service_area
					? r.service_area
					: __( 'Everywhere', 'fleetbase-for-woocommerce' ) ),
		},
		{
			key: 'extras',
			label: __( 'Extras', 'fleetbase-for-woocommerce' ),
			render: ( r ) =>
				[
					r.has_cod_fee &&
						__( 'COD fee', 'fleetbase-for-woocommerce' ),
					r.has_peak_hours_fee &&
						__( 'Peak hours', 'fleetbase-for-woocommerce' ),
				]
					.filter( Boolean )
					.join( ', ' ) || '—',
		},
		{
			key: 'actions',
			label: '',
			render: ( r ) => (
				<div className="fbwc-row-actions">
					<Button
						variant="secondary"
						size="small"
						onClick={ () => open( r ) }
					>
						{ __( 'Edit', 'fleetbase-for-woocommerce' ) }
					</Button>
					<Button
						variant="tertiary"
						size="small"
						isDestructive
						onClick={ () => remove( r ) }
					>
						{ __( 'Delete', 'fleetbase-for-woocommerce' ) }
					</Button>
				</div>
			),
		},
	];

	return (
		<div className="fbwc-page">
			<Header
				title={ __( 'Service rates', 'fleetbase-for-woocommerce' ) }
				connection={ runtimeData.connection }
			/>
			{ notice && (
				<Notice
					status={ notice.status }
					isDismissible
					onRemove={ () => setNotice( null ) }
				>
					{ notice.text }
				</Notice>
			) }
			{ list.error && (
				<Notice status="error" isDismissible={ false }>
					{ list.error }
				</Notice>
			) }
			<p>
				{ __(
					'Service rates are the delivery prices customers see at checkout. A rate with a service area only applies to addresses inside it.',
					'fleetbase-for-woocommerce'
				) }{ ' ' }
				<ExternalLink href={ options.console_url }>
					{ __(
						'Draw service areas and zones in the Fleetbase console',
						'fleetbase-for-woocommerce'
					) }
				</ExternalLink>
			</p>
			<div className="fbwc-toolbar">
				<Button variant="primary" onClick={ () => open( null ) }>
					{ __( 'Add service rate', 'fleetbase-for-woocommerce' ) }
				</Button>
				<Button variant="tertiary" onClick={ list.reload }>
					{ __( 'Reload', 'fleetbase-for-woocommerce' ) }
				</Button>
			</div>
			<DataTable
				columns={ columns }
				rows={ list.items }
				loading={ list.loading }
				empty={ __(
					'No service rates yet. Add one to offer delivery at checkout.',
					'fleetbase-for-woocommerce'
				) }
			/>
			{ editing && (
				<Modal
					title={
						editing === 'new'
							? __(
									'Add service rate',
									'fleetbase-for-woocommerce'
							  )
							: __(
									'Edit service rate',
									'fleetbase-for-woocommerce'
							  )
					}
					onRequestClose={ () => setEditing( null ) }
					className="fbwc-modal--wide"
				>
					{ error && (
						<Notice status="error" isDismissible={ false }>
							{ error }
						</Notice>
					) }
					<div className="fbwc-field-row">
						<TextControl
							label={ __(
								'Name shown at checkout',
								'fleetbase-for-woocommerce'
							) }
							value={ form.service_name }
							onChange={ ( service_name ) =>
								setForm( { ...form, service_name } )
							}
							__nextHasNoMarginBottom
						/>
						<TextControl
							label={ __(
								'Service type',
								'fleetbase-for-woocommerce'
							) }
							help={ __(
								'A short key such as standard or express. The shipping method can filter by it.',
								'fleetbase-for-woocommerce'
							) }
							value={ form.service_type }
							onChange={ ( service_type ) =>
								setForm( { ...form, service_type } )
							}
							__nextHasNoMarginBottom
						/>
					</div>
					<div className="fbwc-field-row">
						<SelectControl
							label={ __(
								'Pricing',
								'fleetbase-for-woocommerce'
							) }
							value={ form.rate_calculation_method }
							options={ METHODS }
							onChange={ ( rate_calculation_method ) =>
								setForm( { ...form, rate_calculation_method } )
							}
							__nextHasNoMarginBottom
						/>
						<SelectControl
							label={ __(
								'Distance unit',
								'fleetbase-for-woocommerce'
							) }
							value={ form.per_meter_unit }
							options={ UNITS }
							onChange={ ( per_meter_unit ) =>
								setForm( { ...form, per_meter_unit } )
							}
							__nextHasNoMarginBottom
						/>
						<TextControl
							label={ sprintf(
								/* translators: %s: currency */ __(
									'Base fee (%s)',
									'fleetbase-for-woocommerce'
								),
								currency
							) }
							value={ form.base_fee }
							onChange={ ( base_fee ) =>
								setForm( { ...form, base_fee } )
							}
							__nextHasNoMarginBottom
						/>
					</div>
					{ form.rate_calculation_method === 'per_meter' ? (
						<TextControl
							label={ sprintf(
								/* translators: 1: currency, 2: unit */ __(
									'Fee per %2$s (%1$s)',
									'fleetbase-for-woocommerce'
								),
								currency,
								form.per_meter_unit
							) }
							value={ form.per_meter_flat_rate_fee }
							onChange={ ( per_meter_flat_rate_fee ) =>
								setForm( { ...form, per_meter_flat_rate_fee } )
							}
							__nextHasNoMarginBottom
						/>
					) : (
						<div className="fbwc-tiers">
							<p className="fbwc-copy__label">
								{ __(
									'Distance tiers',
									'fleetbase-for-woocommerce'
								) }
							</p>
							{ form.meter_fees.map( ( t, i ) => (
								<div className="fbwc-field-row" key={ i }>
									<TextControl
										label={ sprintf(
											/* translators: %s: unit */ __(
												'Up to (%s)',
												'fleetbase-for-woocommerce'
											),
											form.per_meter_unit
										) }
										type="number"
										value={ t.distance }
										onChange={ ( distance ) =>
											setTier( i, { distance } )
										}
										__nextHasNoMarginBottom
									/>
									<TextControl
										label={ sprintf(
											/* translators: %s: currency */ __(
												'Price (%s)',
												'fleetbase-for-woocommerce'
											),
											currency
										) }
										value={ t.fee }
										onChange={ ( fee ) =>
											setTier( i, { fee } )
										}
										__nextHasNoMarginBottom
									/>
									<Button
										variant="tertiary"
										isDestructive
										onClick={ () =>
											setForm( {
												...form,
												meter_fees:
													form.meter_fees.filter(
														( _, j ) => j !== i
													),
											} )
										}
										disabled={
											form.meter_fees.length === 1
										}
									>
										{ __(
											'Remove',
											'fleetbase-for-woocommerce'
										) }
									</Button>
								</div>
							) ) }
							<Button
								variant="secondary"
								size="small"
								onClick={ () =>
									setForm( {
										...form,
										meter_fees: [
											...form.meter_fees,
											{ distance: '', fee: '' },
										],
									} )
								}
							>
								{ __(
									'Add tier',
									'fleetbase-for-woocommerce'
								) }
							</Button>
						</div>
					) }
					<div className="fbwc-field-row">
						<SelectControl
							label={ __(
								'Service area',
								'fleetbase-for-woocommerce'
							) }
							value={ form.service_area }
							options={ [
								{
									value: '',
									label: __(
										'Everywhere',
										'fleetbase-for-woocommerce'
									),
								},
								...options.areas.map( ( a ) => ( {
									value: a.id,
									label: a.name,
								} ) ),
							] }
							onChange={ ( service_area ) =>
								setForm( { ...form, service_area } )
							}
							__nextHasNoMarginBottom
						/>
						<SelectControl
							label={ __( 'Zone', 'fleetbase-for-woocommerce' ) }
							value={ form.zone }
							options={ [
								{
									value: '',
									label: __(
										'Whole area',
										'fleetbase-for-woocommerce'
									),
								},
								...options.zones.map( ( z ) => ( {
									value: z.id,
									label: z.name,
								} ) ),
							] }
							onChange={ ( zone ) =>
								setForm( { ...form, zone } )
							}
							__nextHasNoMarginBottom
						/>
					</div>
					<div className="fbwc-field-row">
						<TextControl
							label={ __(
								'Delivery promise shown at checkout',
								'fleetbase-for-woocommerce'
							) }
							placeholder={ __(
								'Same day, 2–4 hours',
								'fleetbase-for-woocommerce'
							) }
							value={ form.duration_terms }
							onChange={ ( duration_terms ) =>
								setForm( { ...form, duration_terms } )
							}
							__nextHasNoMarginBottom
						/>
						<TextControl
							label={ __(
								'Estimated days',
								'fleetbase-for-woocommerce'
							) }
							type="number"
							value={ form.estimated_days }
							onChange={ ( estimated_days ) =>
								setForm( { ...form, estimated_days } )
							}
							__nextHasNoMarginBottom
						/>
					</div>
					<ToggleControl
						label={ __(
							'Charge a cash-on-delivery fee',
							'fleetbase-for-woocommerce'
						) }
						checked={ !! form.has_cod_fee }
						onChange={ ( has_cod_fee ) =>
							setForm( { ...form, has_cod_fee } )
						}
						__nextHasNoMarginBottom
					/>
					{ form.has_cod_fee && (
						<div className="fbwc-field-row">
							<SelectControl
								label={ __(
									'COD fee type',
									'fleetbase-for-woocommerce'
								) }
								value={ form.cod_calculation_method }
								options={ [
									{
										value: 'flat',
										label: __(
											'Flat',
											'fleetbase-for-woocommerce'
										),
									},
									{
										value: 'percentage',
										label: __(
											'Percentage',
											'fleetbase-for-woocommerce'
										),
									},
								] }
								onChange={ ( cod_calculation_method ) =>
									setForm( {
										...form,
										cod_calculation_method,
									} )
								}
								__nextHasNoMarginBottom
							/>
							{ form.cod_calculation_method === 'flat' ? (
								<TextControl
									label={ sprintf(
										/* translators: %s: currency */ __(
											'COD fee (%s)',
											'fleetbase-for-woocommerce'
										),
										currency
									) }
									value={ form.cod_flat_fee }
									onChange={ ( cod_flat_fee ) =>
										setForm( { ...form, cod_flat_fee } )
									}
									__nextHasNoMarginBottom
								/>
							) : (
								<TextControl
									label={ __(
										'COD fee (%)',
										'fleetbase-for-woocommerce'
									) }
									type="number"
									value={ form.cod_percent }
									onChange={ ( cod_percent ) =>
										setForm( { ...form, cod_percent } )
									}
									__nextHasNoMarginBottom
								/>
							) }
						</div>
					) }
					<ToggleControl
						label={ __(
							'Charge more during peak hours',
							'fleetbase-for-woocommerce'
						) }
						checked={ !! form.has_peak_hours_fee }
						onChange={ ( has_peak_hours_fee ) =>
							setForm( { ...form, has_peak_hours_fee } )
						}
						__nextHasNoMarginBottom
					/>
					{ form.has_peak_hours_fee && (
						<div className="fbwc-field-row">
							<SelectControl
								label={ __(
									'Peak fee type',
									'fleetbase-for-woocommerce'
								) }
								value={ form.peak_hours_calculation_method }
								options={ [
									{
										value: 'flat',
										label: __(
											'Flat',
											'fleetbase-for-woocommerce'
										),
									},
									{
										value: 'percentage',
										label: __(
											'Percentage',
											'fleetbase-for-woocommerce'
										),
									},
								] }
								onChange={ ( peak_hours_calculation_method ) =>
									setForm( {
										...form,
										peak_hours_calculation_method,
									} )
								}
								__nextHasNoMarginBottom
							/>
							{ form.peak_hours_calculation_method === 'flat' ? (
								<TextControl
									label={ sprintf(
										/* translators: %s: currency */ __(
											'Peak fee (%s)',
											'fleetbase-for-woocommerce'
										),
										currency
									) }
									value={ form.peak_hours_flat_fee }
									onChange={ ( peak_hours_flat_fee ) =>
										setForm( {
											...form,
											peak_hours_flat_fee,
										} )
									}
									__nextHasNoMarginBottom
								/>
							) : (
								<TextControl
									label={ __(
										'Peak fee (%)',
										'fleetbase-for-woocommerce'
									) }
									type="number"
									value={ form.peak_hours_percent }
									onChange={ ( peak_hours_percent ) =>
										setForm( {
											...form,
											peak_hours_percent,
										} )
									}
									__nextHasNoMarginBottom
								/>
							) }
							<TextControl
								label={ __(
									'From',
									'fleetbase-for-woocommerce'
								) }
								type="time"
								value={ form.peak_hours_start }
								onChange={ ( peak_hours_start ) =>
									setForm( { ...form, peak_hours_start } )
								}
								__nextHasNoMarginBottom
							/>
							<TextControl
								label={ __(
									'Until',
									'fleetbase-for-woocommerce'
								) }
								type="time"
								value={ form.peak_hours_end }
								onChange={ ( peak_hours_end ) =>
									setForm( { ...form, peak_hours_end } )
								}
								__nextHasNoMarginBottom
							/>
						</div>
					) }
					<div className="fbwc-actions">
						<Button
							variant="primary"
							onClick={ save }
							isBusy={ busy }
							disabled={ busy }
						>
							{ __( 'Save rate', 'fleetbase-for-woocommerce' ) }
						</Button>
						<Button
							variant="tertiary"
							onClick={ () => setEditing( null ) }
						>
							{ __( 'Cancel', 'fleetbase-for-woocommerce' ) }
						</Button>
					</div>
				</Modal>
			) }
		</div>
	);
}
