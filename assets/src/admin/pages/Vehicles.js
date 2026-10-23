/* eslint-disable camelcase -- form fields mirror Fleetbase API keys. */
import { useState } from '@wordpress/element';
import {
	Button,
	Modal,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import Header from '../components/Header';
import DataTable from '../components/DataTable';
import useList from '../components/useList';
import { api, errorMessage, runtimeData } from '../api';

const EMPTY = {
	name: '',
	plate_number: '',
	make: '',
	model: '',
	year: '',
	type: 'van',
	status: 'active',
	driver: '',
	color: '',
};

const TYPES = [
	'car',
	'van',
	'truck',
	'motorcycle',
	'bicycle',
	'scooter',
	'other',
].map( ( t ) => ( {
	value: t,
	label: t.charAt( 0 ).toUpperCase() + t.slice( 1 ),
} ) );
const STATUSES = [
	'active',
	'available',
	'in_use',
	'maintenance',
	'out_of_service',
	'inactive',
].map( ( s ) => ( { value: s, label: s.replace( /_/g, ' ' ) } ) );

export default function Vehicles() {
	const list = useList( 'vehicles', 'vehicles' );
	const drivers = useList( 'drivers', 'drivers' );
	const [ editing, setEditing ] = useState( null );
	const [ form, setForm ] = useState( EMPTY );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ notice, setNotice ] = useState( null );

	const driverName = ( id ) =>
		( drivers.items.find( ( d ) => d.id === id ) || {} ).name || '';

	const open = ( vehicle ) => {
		setEditing( vehicle || 'new' );
		setForm(
			vehicle
				? { ...EMPTY, ...vehicle, driver: vehicle.driver_id || '' }
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
				isNew ? 'vehicles' : `vehicles/${ editing.id }`,
				{ method: isNew ? 'POST' : 'PUT', data: form }
			);
			list.setItems(
				isNew
					? [ res.vehicle, ...list.items ]
					: list.items.map( ( v ) =>
							v.id === res.vehicle.id ? res.vehicle : v
					  )
			);
			setNotice( {
				status: 'success',
				text: isNew
					? __( 'Vehicle added.', 'fleetbase-for-woocommerce' )
					: __( 'Vehicle updated.', 'fleetbase-for-woocommerce' ),
			} );
			setEditing( null );
		} catch ( e ) {
			setError( errorMessage( e ) );
		}
		setBusy( false );
	};

	const remove = async ( vehicle ) => {
		if (
			// eslint-disable-next-line no-alert -- destructive action deserves a native confirm.
			! window.confirm(
				__(
					'Remove this vehicle from Fleetbase?',
					'fleetbase-for-woocommerce'
				)
			)
		) {
			return;
		}
		try {
			await api( `vehicles/${ vehicle.id }`, { method: 'DELETE' } );
			list.setItems( list.items.filter( ( v ) => v.id !== vehicle.id ) );
		} catch ( e ) {
			setNotice( { status: 'error', text: errorMessage( e ) } );
		}
	};

	const columns = [
		{
			key: 'label',
			label: __( 'Vehicle', 'fleetbase-for-woocommerce' ),
			render: ( v ) => (
				<>
					<strong>{ v.label }</strong>
					<div className="fbwc-muted">
						{ [ v.year, v.type, v.color ]
							.filter( Boolean )
							.join( ' · ' ) }
					</div>
				</>
			),
		},
		{
			key: 'status',
			label: __( 'Status', 'fleetbase-for-woocommerce' ),
			render: ( v ) => v.status.replace( /_/g, ' ' ),
		},
		{
			key: 'driver',
			label: __( 'Driver', 'fleetbase-for-woocommerce' ),
			render: ( v ) => driverName( v.driver_id ) || '—',
		},
		{
			key: 'actions',
			label: '',
			render: ( v ) => (
				<div className="fbwc-row-actions">
					<Button
						variant="secondary"
						size="small"
						onClick={ () => open( v ) }
					>
						{ __( 'Edit', 'fleetbase-for-woocommerce' ) }
					</Button>
					<Button
						variant="tertiary"
						size="small"
						isDestructive
						onClick={ () => remove( v ) }
					>
						{ __( 'Remove', 'fleetbase-for-woocommerce' ) }
					</Button>
				</div>
			),
		},
	];

	return (
		<div className="fbwc-page">
			<Header
				title={ __( 'Vehicles', 'fleetbase-for-woocommerce' ) }
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
			<div className="fbwc-toolbar">
				<Button variant="primary" onClick={ () => open( null ) }>
					{ __( 'Add vehicle', 'fleetbase-for-woocommerce' ) }
				</Button>
				<Button variant="tertiary" onClick={ list.reload }>
					{ __( 'Reload', 'fleetbase-for-woocommerce' ) }
				</Button>
			</div>
			<DataTable
				columns={ columns }
				rows={ list.items }
				loading={ list.loading }
				empty={ __( 'No vehicles yet.', 'fleetbase-for-woocommerce' ) }
			/>
			{ editing && (
				<Modal
					title={
						editing === 'new'
							? __( 'Add vehicle', 'fleetbase-for-woocommerce' )
							: __( 'Edit vehicle', 'fleetbase-for-woocommerce' )
					}
					onRequestClose={ () => setEditing( null ) }
				>
					{ error && (
						<Notice status="error" isDismissible={ false }>
							{ error }
						</Notice>
					) }
					<TextControl
						label={ __(
							'Plate number',
							'fleetbase-for-woocommerce'
						) }
						value={ form.plate_number }
						onChange={ ( plate_number ) =>
							setForm( { ...form, plate_number } )
						}
						__nextHasNoMarginBottom
					/>
					<div className="fbwc-field-row">
						<TextControl
							label={ __( 'Make', 'fleetbase-for-woocommerce' ) }
							value={ form.make }
							onChange={ ( make ) =>
								setForm( { ...form, make } )
							}
							__nextHasNoMarginBottom
						/>
						<TextControl
							label={ __( 'Model', 'fleetbase-for-woocommerce' ) }
							value={ form.model }
							onChange={ ( model ) =>
								setForm( { ...form, model } )
							}
							__nextHasNoMarginBottom
						/>
						<TextControl
							label={ __( 'Year', 'fleetbase-for-woocommerce' ) }
							value={ form.year }
							onChange={ ( year ) =>
								setForm( { ...form, year } )
							}
							__nextHasNoMarginBottom
						/>
					</div>
					<div className="fbwc-field-row">
						<SelectControl
							label={ __( 'Type', 'fleetbase-for-woocommerce' ) }
							value={ form.type }
							options={ TYPES }
							onChange={ ( type ) =>
								setForm( { ...form, type } )
							}
							__nextHasNoMarginBottom
						/>
						<SelectControl
							label={ __(
								'Status',
								'fleetbase-for-woocommerce'
							) }
							value={ form.status }
							options={ STATUSES }
							onChange={ ( status ) =>
								setForm( { ...form, status } )
							}
							__nextHasNoMarginBottom
						/>
						<TextControl
							label={ __(
								'Colour',
								'fleetbase-for-woocommerce'
							) }
							value={ form.color }
							onChange={ ( color ) =>
								setForm( { ...form, color } )
							}
							__nextHasNoMarginBottom
						/>
					</div>
					<SelectControl
						label={ __(
							'Assigned driver',
							'fleetbase-for-woocommerce'
						) }
						value={ form.driver }
						options={ [
							{
								value: '',
								label: __(
									'Unassigned',
									'fleetbase-for-woocommerce'
								),
							},
							...drivers.items.map( ( d ) => ( {
								value: d.id,
								label: d.name,
							} ) ),
						] }
						onChange={ ( driver ) =>
							setForm( { ...form, driver } )
						}
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __(
							'Name (optional)',
							'fleetbase-for-woocommerce'
						) }
						value={ form.name }
						onChange={ ( name ) => setForm( { ...form, name } ) }
						__nextHasNoMarginBottom
					/>
					<div className="fbwc-actions">
						<Button
							variant="primary"
							onClick={ save }
							isBusy={ busy }
							disabled={ busy }
						>
							{ __(
								'Save vehicle',
								'fleetbase-for-woocommerce'
							) }
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
