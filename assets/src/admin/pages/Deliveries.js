/* eslint-disable camelcase -- form fields mirror Fleetbase API keys. */
import { useState } from '@wordpress/element';
import {
	Button,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import Header from '../components/Header';
import DataTable from '../components/DataTable';
import useList from '../components/useList';
import { api, errorMessage, runtimeData } from '../api';

const STATUS_FILTERS = [
	{ value: 'open', label: __( 'Open', 'fleetbase-for-woocommerce' ) },
	{
		value: 'transit',
		label: __( 'Out for delivery', 'fleetbase-for-woocommerce' ),
	},
	{ value: 'done', label: __( 'Delivered', 'fleetbase-for-woocommerce' ) },
	{
		value: 'canceled',
		label: __( 'Cancelled', 'fleetbase-for-woocommerce' ),
	},
	{ value: 'all', label: __( 'All', 'fleetbase-for-woocommerce' ) },
];

function stateClass( row ) {
	if ( row.fleetbase_status === 'completed' ) {
		return 'fbwc-state fbwc-state--synced';
	}
	if (
		[ 'dispatched', 'started', 'enroute' ].includes( row.fleetbase_status )
	) {
		return 'fbwc-state fbwc-state--pending';
	}
	if ( [ 'canceled', 'cancelled' ].includes( row.fleetbase_status ) ) {
		return 'fbwc-state fbwc-state--failed';
	}
	return 'fbwc-state';
}

export default function Deliveries() {
	const [ status, setStatus ] = useState( 'open' );
	const [ driver, setDriver ] = useState( '' );
	const [ search, setSearch ] = useState( '' );
	const [ query, setQuery ] = useState( {
		status: 'open',
		driver: '',
		search: '',
	} );
	const [ busy, setBusy ] = useState( null );
	const [ notice, setNotice ] = useState( null );
	const [ pending, setPending ] = useState( {} );
	const list = useList( 'deliveries', 'deliveries', query );
	const drivers = useList( 'drivers', 'drivers' );

	const applyFilters = () => setQuery( { status, driver, search } );

	const act = async ( row, action, data ) => {
		setBusy( row.id + action );
		setNotice( null );
		try {
			const res = await api( `deliveries/${ row.id }/${ action }`, {
				method: 'POST',
				data,
			} );
			list.setItems(
				list.items.map( ( r ) =>
					r.id === row.id ? res.delivery : r
				)
			);
			setNotice( {
				status: 'success',
				text: sprintf(
					/* translators: %s: order number */
					__( 'Order #%s updated.', 'fleetbase-for-woocommerce' ),
					row.number
				),
			} );
		} catch ( e ) {
			setNotice( { status: 'error', text: errorMessage( e ) } );
		}
		setBusy( null );
	};

	const columns = [
		{
			key: 'number',
			label: __( 'Order', 'fleetbase-for-woocommerce' ),
			render: ( r ) => (
				<>
					<a href={ r.admin_url }>#{ r.number }</a>
					<div className="fbwc-muted">{ r.date }</div>
				</>
			),
		},
		{
			key: 'customer',
			label: __( 'Customer', 'fleetbase-for-woocommerce' ),
			render: ( r ) => (
				<>
					<strong>{ r.customer }</strong>
					<div className="fbwc-muted">{ r.address }</div>
				</>
			),
		},
		{
			key: 'window',
			label: __( 'Delivery time', 'fleetbase-for-woocommerce' ),
			render: ( r ) =>
				r.window || __( 'ASAP', 'fleetbase-for-woocommerce' ),
		},
		{
			key: 'status',
			label: __( 'Status', 'fleetbase-for-woocommerce' ),
			render: ( r ) => (
				<>
					<span className={ stateClass( r ) }>
						{ r.fleetbase_label || r.sync_label }
					</span>
					<div className="fbwc-muted">{ r.wc_status_label }</div>
					{ r.last_error && (
						<div className="fbwc-error">{ r.last_error }</div>
					) }
				</>
			),
		},
		{
			key: 'driver',
			label: __( 'Driver', 'fleetbase-for-woocommerce' ),
			render: ( r ) =>
				r.can_dispatch || ( ! r.driver && r.fleetbase_id ) ? (
					<div className="fbwc-inline">
						<SelectControl
							value={ pending[ r.id ] ?? '' }
							options={ [
								{
									value: '',
									label:
										r.driver ||
										__(
											'Choose driver…',
											'fleetbase-for-woocommerce'
										),
								},
								...drivers.items.map( ( d ) => ( {
									value: d.id,
									label: d.name,
								} ) ),
							] }
							onChange={ ( v ) =>
								setPending( { ...pending, [ r.id ]: v } )
							}
							__nextHasNoMarginBottom
						/>
						<Button
							variant="secondary"
							size="small"
							disabled={
								! pending[ r.id ] || busy === r.id + 'assign'
							}
							isBusy={ busy === r.id + 'assign' }
							onClick={ () =>
								act( r, 'assign', { driver: pending[ r.id ] } )
							}
						>
							{ __( 'Assign', 'fleetbase-for-woocommerce' ) }
						</Button>
					</div>
				) : (
					r.driver || '—'
				),
		},
		{
			key: 'tracking',
			label: __( 'Tracking', 'fleetbase-for-woocommerce' ),
			render: ( r ) =>
				r.tracking_url ? (
					<a href={ r.tracking_url } target="_blank" rel="noreferrer">
						{ r.tracking_number }
					</a>
				) : (
					r.tracking_number || '—'
				),
		},
		{
			key: 'actions',
			label: '',
			render: ( r ) => (
				<div className="fbwc-row-actions">
					{ r.can_dispatch && (
						<Button
							variant="primary"
							size="small"
							disabled={
								! r.driver || busy === r.id + 'dispatch'
							}
							isBusy={ busy === r.id + 'dispatch' }
							onClick={ () => act( r, 'dispatch' ) }
						>
							{ __( 'Dispatch', 'fleetbase-for-woocommerce' ) }
						</Button>
					) }
					{ r.fleetbase_id && (
						<Button
							variant="tertiary"
							size="small"
							isBusy={ busy === r.id + 'refresh' }
							onClick={ () => act( r, 'refresh' ) }
						>
							{ __( 'Refresh', 'fleetbase-for-woocommerce' ) }
						</Button>
					) }
					{ ! r.fleetbase_id && (
						<Button
							variant="secondary"
							size="small"
							isBusy={ busy === r.id + 'resync' }
							onClick={ () => act( r, 'resync' ) }
						>
							{ __(
								'Send to Fleetbase',
								'fleetbase-for-woocommerce'
							) }
						</Button>
					) }
					{ r.can_cancel && (
						<Button
							variant="tertiary"
							size="small"
							isDestructive
							isBusy={ busy === r.id + 'cancel' }
							onClick={ () => {
								if (
									// eslint-disable-next-line no-alert -- destructive action deserves a native confirm.
									window.confirm(
										__(
											'Cancel this delivery in Fleetbase?',
											'fleetbase-for-woocommerce'
										)
									)
								) {
									act( r, 'cancel' );
								}
							} }
						>
							{ __( 'Cancel', 'fleetbase-for-woocommerce' ) }
						</Button>
					) }
				</div>
			),
		},
	];

	return (
		<div className="fbwc-page">
			<Header
				title={ __( 'Deliveries', 'fleetbase-for-woocommerce' ) }
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
				<SelectControl
					label={ __( 'Status', 'fleetbase-for-woocommerce' ) }
					value={ status }
					options={ STATUS_FILTERS }
					onChange={ setStatus }
					__nextHasNoMarginBottom
				/>
				<SelectControl
					label={ __( 'Driver', 'fleetbase-for-woocommerce' ) }
					value={ driver }
					options={ [
						{
							value: '',
							label: __(
								'Any driver',
								'fleetbase-for-woocommerce'
							),
						},
						...drivers.items.map( ( d ) => ( {
							value: d.name,
							label: d.name,
						} ) ),
					] }
					onChange={ setDriver }
					__nextHasNoMarginBottom
				/>
				<TextControl
					label={ __( 'Search', 'fleetbase-for-woocommerce' ) }
					value={ search }
					onChange={ setSearch }
					onKeyDown={ ( e ) => e.key === 'Enter' && applyFilters() }
					__nextHasNoMarginBottom
				/>
				<Button variant="secondary" onClick={ applyFilters }>
					{ __( 'Filter', 'fleetbase-for-woocommerce' ) }
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
					'No deliveries match these filters.',
					'fleetbase-for-woocommerce'
				) }
			/>
			{ list.meta.total > 0 && (
				<p className="fbwc-muted">
					{ sprintf(
						/* translators: 1: shown count, 2: total */
						__(
							'Showing %1$d of %2$d deliveries',
							'fleetbase-for-woocommerce'
						),
						list.items.length,
						list.meta.total
					) }
				</p>
			) }
		</div>
	);
}
