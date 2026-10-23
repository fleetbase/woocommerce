/* eslint-disable camelcase -- form fields mirror Fleetbase API keys. */
import { useState } from '@wordpress/element';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	ExternalLink,
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
	email: '',
	phone: '',
	password: '',
	status: 'active',
	vehicle: '',
	internal_id: '',
};

const STATUSES = [
	{ value: 'active', label: __( 'Active', 'fleetbase-for-woocommerce' ) },
	{
		value: 'available',
		label: __( 'Available', 'fleetbase-for-woocommerce' ),
	},
	{ value: 'inactive', label: __( 'Inactive', 'fleetbase-for-woocommerce' ) },
];

export default function Drivers() {
	const list = useList( 'drivers', 'drivers' );
	const vehicles = useList( 'vehicles', 'vehicles' );
	const [ editing, setEditing ] = useState( null );
	const [ form, setForm ] = useState( EMPTY );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ notice, setNotice ] = useState( null );

	const open = ( driver ) => {
		setEditing( driver || 'new' );
		setForm(
			driver
				? {
						...EMPTY,
						...driver,
						vehicle: driver.vehicle_id || '',
						password: '',
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
			const data = { ...form };
			if ( ! data.password ) {
				delete data.password;
			}
			const res = await api(
				isNew ? 'drivers' : `drivers/${ editing.id }`,
				{ method: isNew ? 'POST' : 'PUT', data }
			);
			list.setItems(
				isNew
					? [ res.driver, ...list.items ]
					: list.items.map( ( d ) =>
							d.id === res.driver.id ? res.driver : d
					  )
			);
			setNotice( {
				status: 'success',
				text: isNew
					? __(
							'Driver created. They can sign in to the Navigator app now.',
							'fleetbase-for-woocommerce'
					  )
					: __( 'Driver updated.', 'fleetbase-for-woocommerce' ),
			} );
			setEditing( null );
		} catch ( e ) {
			setError( errorMessage( e ) );
		}
		setBusy( false );
	};

	const remove = async ( driver ) => {
		if (
			// eslint-disable-next-line no-alert -- destructive action deserves a native confirm.
			! window.confirm(
				__(
					'Remove this driver from Fleetbase?',
					'fleetbase-for-woocommerce'
				)
			)
		) {
			return;
		}
		try {
			await api( `drivers/${ driver.id }`, { method: 'DELETE' } );
			list.setItems( list.items.filter( ( d ) => d.id !== driver.id ) );
		} catch ( e ) {
			setNotice( { status: 'error', text: errorMessage( e ) } );
		}
	};

	const columns = [
		{
			key: 'name',
			label: __( 'Driver', 'fleetbase-for-woocommerce' ),
			render: ( d ) => (
				<>
					<strong>{ d.name }</strong>
					<div className="fbwc-muted">
						{ [ d.email, d.phone ].filter( Boolean ).join( ' · ' ) }
					</div>
				</>
			),
		},
		{
			key: 'status',
			label: __( 'Status', 'fleetbase-for-woocommerce' ),
			render: ( d ) => (
				<>
					<span
						className={
							'fbwc-dot ' + ( d.online ? 'fbwc-dot--ok' : '' )
						}
					/>{ ' ' }
					{ d.online
						? __( 'Online', 'fleetbase-for-woocommerce' )
						: __( 'Offline', 'fleetbase-for-woocommerce' ) }
					<div className="fbwc-muted">
						{ d.current_status || d.status }
					</div>
				</>
			),
		},
		{
			key: 'vehicle',
			label: __( 'Vehicle', 'fleetbase-for-woocommerce' ),
			render: ( d ) => d.vehicle || '—',
		},
		{
			key: 'location',
			label: __( 'Last location', 'fleetbase-for-woocommerce' ),
			render: ( d ) =>
				d.location ? (
					<a
						href={ `https://www.google.com/maps?q=${ d.location.lat },${ d.location.lng }` }
						target="_blank"
						rel="noreferrer"
					>
						{ d.location.lat.toFixed( 4 ) },{ ' ' }
						{ d.location.lng.toFixed( 4 ) }
					</a>
				) : (
					'—'
				),
		},
		{
			key: 'actions',
			label: '',
			render: ( d ) => (
				<div className="fbwc-row-actions">
					<Button
						variant="secondary"
						size="small"
						onClick={ () => open( d ) }
					>
						{ __( 'Edit', 'fleetbase-for-woocommerce' ) }
					</Button>
					<Button
						variant="tertiary"
						size="small"
						isDestructive
						onClick={ () => remove( d ) }
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
				title={ __( 'Drivers', 'fleetbase-for-woocommerce' ) }
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
			<div className="fbwc-grid">
				<div className="fbwc-grid__main">
					<div className="fbwc-toolbar">
						<Button
							variant="primary"
							onClick={ () => open( null ) }
						>
							{ __( 'Add driver', 'fleetbase-for-woocommerce' ) }
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
							'No drivers yet. Add your first driver.',
							'fleetbase-for-woocommerce'
						) }
					/>
				</div>
				<div className="fbwc-grid__side">
					<Card className="fbwc-card">
						<CardHeader>
							<strong>
								{ __(
									'Navigator driver app',
									'fleetbase-for-woocommerce'
								) }
							</strong>
						</CardHeader>
						<CardBody>
							<p>
								{ __(
									'Drivers get their jobs, turn-by-turn navigation and proof of delivery in the free Fleetbase Navigator app. They sign in with the email or phone number and password you set here.',
									'fleetbase-for-woocommerce'
								) }
							</p>
							<ul className="fbwc-links">
								<li>
									<ExternalLink
										href={ runtimeData.navigator.ios }
									>
										{ __(
											'Download for iPhone',
											'fleetbase-for-woocommerce'
										) }
									</ExternalLink>
								</li>
								<li>
									<ExternalLink
										href={ runtimeData.navigator.android }
									>
										{ __(
											'Download for Android',
											'fleetbase-for-woocommerce'
										) }
									</ExternalLink>
								</li>
							</ul>
							{ ! runtimeData.connection.is_cloud && (
								<p className="fbwc-muted">
									{ __(
										'Self-hosted: drivers link the app to your instance from its sign-in screen first.',
										'fleetbase-for-woocommerce'
									) }
								</p>
							) }
						</CardBody>
					</Card>
				</div>
			</div>
			{ editing && (
				<Modal
					title={
						editing === 'new'
							? __( 'Add driver', 'fleetbase-for-woocommerce' )
							: __( 'Edit driver', 'fleetbase-for-woocommerce' )
					}
					onRequestClose={ () => setEditing( null ) }
				>
					{ error && (
						<Notice status="error" isDismissible={ false }>
							{ error }
						</Notice>
					) }
					<TextControl
						label={ __( 'Name', 'fleetbase-for-woocommerce' ) }
						value={ form.name }
						onChange={ ( name ) => setForm( { ...form, name } ) }
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __( 'Email', 'fleetbase-for-woocommerce' ) }
						type="email"
						value={ form.email }
						onChange={ ( email ) => setForm( { ...form, email } ) }
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __( 'Phone', 'fleetbase-for-woocommerce' ) }
						type="tel"
						value={ form.phone }
						onChange={ ( phone ) => setForm( { ...form, phone } ) }
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={
							editing === 'new'
								? __(
										'Password for the Navigator app',
										'fleetbase-for-woocommerce'
								  )
								: __(
										'New password (leave blank to keep)',
										'fleetbase-for-woocommerce'
								  )
						}
						type="password"
						value={ form.password }
						onChange={ ( password ) =>
							setForm( { ...form, password } )
						}
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={ __( 'Status', 'fleetbase-for-woocommerce' ) }
						value={ form.status }
						options={ STATUSES }
						onChange={ ( status ) =>
							setForm( { ...form, status } )
						}
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={ __( 'Vehicle', 'fleetbase-for-woocommerce' ) }
						value={ form.vehicle }
						options={ [
							{
								value: '',
								label: __(
									'No vehicle',
									'fleetbase-for-woocommerce'
								),
							},
							...vehicles.items.map( ( v ) => ( {
								value: v.id,
								label: v.label,
							} ) ),
						] }
						onChange={ ( vehicle ) =>
							setForm( { ...form, vehicle } )
						}
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __(
							'Internal ID (optional)',
							'fleetbase-for-woocommerce'
						) }
						value={ form.internal_id }
						onChange={ ( internal_id ) =>
							setForm( { ...form, internal_id } )
						}
						__nextHasNoMarginBottom
					/>
					<div className="fbwc-actions">
						<Button
							variant="primary"
							onClick={ save }
							isBusy={ busy }
							disabled={ busy }
						>
							{ __( 'Save driver', 'fleetbase-for-woocommerce' ) }
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
