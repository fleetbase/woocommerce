import {
	Card,
	CardBody,
	CardHeader,
	Button,
	Notice,
	Spinner,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { api, errorMessage } from '../api';

function dotClass( ok ) {
	if ( ok === undefined ) {
		return 'fbwc-dot';
	}
	return ok ? 'fbwc-dot fbwc-dot--ok' : 'fbwc-dot fbwc-dot--warn';
}

function Row( { label, value, ok } ) {
	return (
		<div className="fbwc-status-row">
			<span className={ dotClass( ok ) } aria-hidden="true" />
			<span className="fbwc-status-row__label">{ label }</span>
			<span className="fbwc-status-row__value">{ value }</span>
		</div>
	);
}

export default function StatusCard( { state, onChange } ) {
	const [ busy, setBusy ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const { connection, pickup, shippingMethodConfigured, pages, consoleUrl } =
		state;

	const verify = async () => {
		setBusy( true );
		setNotice( null );
		try {
			const res = await api( 'connection/verify', { method: 'POST' } );
			setNotice( {
				status: 'success',
				text: sprintf(
					/* translators: %s: organisation name */
					__(
						'Connection verified. Organisation: %s',
						'fleetbase-for-woocommerce'
					),
					res.organization?.name || ''
				),
			} );
			onChange( { ...state, connection: res.connection } );
		} catch ( e ) {
			setNotice( { status: 'error', text: errorMessage( e ) } );
		}
		setBusy( false );
	};

	const disconnect = async () => {
		if (
			// eslint-disable-next-line no-alert -- destructive action deserves a native confirm.
			! window.confirm(
				__(
					'Disconnect from Fleetbase? Orders will stop syncing until you reconnect.',
					'fleetbase-for-woocommerce'
				)
			)
		) {
			return;
		}
		setBusy( true );
		try {
			const res = await api( 'connection', { method: 'DELETE' } );
			onChange( res );
		} catch ( e ) {
			setNotice( { status: 'error', text: errorMessage( e ) } );
		}
		setBusy( false );
	};

	return (
		<Card className="fbwc-card">
			<CardHeader>
				<strong>
					{ __( 'Connection', 'fleetbase-for-woocommerce' ) }
				</strong>
				{ connection.connected && (
					<span
						className={
							'fbwc-badge ' +
							( connection.mode === 'test'
								? 'fbwc-badge--test'
								: 'fbwc-badge--live' )
						}
					>
						{ connection.mode === 'test'
							? __( 'Test', 'fleetbase-for-woocommerce' )
							: __( 'Live', 'fleetbase-for-woocommerce' ) }
					</span>
				) }
			</CardHeader>
			<CardBody>
				{ notice && (
					<Notice
						status={ notice.status }
						isDismissible
						onRemove={ () => setNotice( null ) }
					>
						{ notice.text }
					</Notice>
				) }
				{ connection.connected ? (
					<>
						<Row
							label={ __(
								'Organisation',
								'fleetbase-for-woocommerce'
							) }
							value={ connection.organization?.name || '—' }
							ok
						/>
						<Row
							label={ __( 'Host', 'fleetbase-for-woocommerce' ) }
							value={
								connection.is_cloud
									? __(
											'Fleetbase Cloud',
											'fleetbase-for-woocommerce'
									  )
									: connection.host
							}
							ok
						/>
						<Row
							label={ __(
								'API key',
								'fleetbase-for-woocommerce'
							) }
							value={ connection.masked_key }
							ok
						/>
						<Row
							label={ __(
								'Pickup location',
								'fleetbase-for-woocommerce'
							) }
							value={
								pickup
									? pickup.address
									: __(
											'Not set',
											'fleetbase-for-woocommerce'
									  )
							}
							ok={ !! pickup && pickup.has_location }
						/>
						<Row
							label={ __(
								'Shipping method',
								'fleetbase-for-woocommerce'
							) }
							value={
								shippingMethodConfigured
									? __(
											'Added to a shipping zone',
											'fleetbase-for-woocommerce'
									  )
									: __(
											'Not added to any zone yet',
											'fleetbase-for-woocommerce'
									  )
							}
							ok={ shippingMethodConfigured }
						/>
						<Row
							label={ __(
								'Last verified',
								'fleetbase-for-woocommerce'
							) }
							value={
								connection.last_verified_at
									? new Date(
											connection.last_verified_at * 1000
									  ).toLocaleString()
									: '—'
							}
							ok={ ! connection.last_error }
						/>
						{ connection.last_error && (
							<Notice status="warning" isDismissible={ false }>
								{ connection.last_error }
							</Notice>
						) }
						<div className="fbwc-actions">
							<Button
								variant="secondary"
								onClick={ verify }
								disabled={ busy }
							>
								{ busy ? (
									<Spinner />
								) : (
									__(
										'Verify connection',
										'fleetbase-for-woocommerce'
									)
								) }
							</Button>
							<Button
								variant="tertiary"
								href={ consoleUrl }
								target="_blank"
								rel="noreferrer"
							>
								{ __(
									'Open Fleetbase Console',
									'fleetbase-for-woocommerce'
								) }
							</Button>
							<Button
								variant="tertiary"
								isDestructive
								onClick={ disconnect }
								disabled={ busy }
							>
								{ __(
									'Disconnect',
									'fleetbase-for-woocommerce'
								) }
							</Button>
						</div>
					</>
				) : (
					<>
						<p>
							{ __(
								'Fleetbase is not connected yet. Run the setup wizard to connect your account, set your pickup location and add the delivery method to checkout.',
								'fleetbase-for-woocommerce'
							) }
						</p>
						<Button variant="primary" href={ pages.setup }>
							{ __( 'Run setup', 'fleetbase-for-woocommerce' ) }
						</Button>
					</>
				) }
			</CardBody>
		</Card>
	);
}
