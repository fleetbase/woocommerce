import { useState } from '@wordpress/element';
import { Button, Card, CardBody, CardHeader, Notice, Spinner, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import Header from '../components/Header';
import StatusCard from '../components/StatusCard';
import PickupPicker from '../components/PickupPicker';
import { api, errorMessage, runtimeData } from '../api';

export default function Settings() {
	const [ state, setState ] = useState( runtimeData );
	const [ settings, setSettings ] = useState( runtimeData.settings );
	const [ busy, setBusy ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	const save = async () => {
		setBusy( true );
		setNotice( null );
		try {
			const res = await api( 'settings', { method: 'POST', data: { settings } } );
			setSettings( res.settings );
			setNotice( { status: 'success', text: __( 'Settings saved.', 'fleetbase-for-woocommerce' ) } );
		} catch ( e ) {
			setNotice( { status: 'error', text: errorMessage( e ) } );
		}
		setBusy( false );
	};

	return (
		<div className="fbwc-page">
			<Header title={ __( 'Fleetbase settings', 'fleetbase-for-woocommerce' ) } connection={ state.connection } />
			{ notice && <Notice status={ notice.status } isDismissible onRemove={ () => setNotice( null ) }>{ notice.text }</Notice> }
			<div className="fbwc-grid">
				<div className="fbwc-grid__main">
					<StatusCard state={ state } onChange={ setState } />
					{ state.connection.connected && (
						<Card className="fbwc-card">
							<CardHeader><strong>{ __( 'Pickup location', 'fleetbase-for-woocommerce' ) }</strong></CardHeader>
							<CardBody>
								<PickupPicker pickup={ state.pickup } onSaved={ ( pickup ) => setState( { ...state, pickup } ) } />
							</CardBody>
						</Card>
					) }
					<Card className="fbwc-card">
						<CardHeader><strong>{ __( 'Advanced', 'fleetbase-for-woocommerce' ) }</strong></CardHeader>
						<CardBody>
							<ToggleControl
								label={ __( 'Debug logging', 'fleetbase-for-woocommerce' ) }
								help={ __( 'Log every Fleetbase API call to WooCommerce → Status → Logs (source "fleetbase"). API keys are never written to the log.', 'fleetbase-for-woocommerce' ) }
								checked={ !! settings.debug_logging }
								onChange={ ( v ) => setSettings( { ...settings, debug_logging: v } ) }
								__nextHasNoMarginBottom
							/>
							<div className="fbwc-actions">
								<Button variant="primary" onClick={ save } disabled={ busy }>{ busy ? <Spinner /> : __( 'Save settings', 'fleetbase-for-woocommerce' ) }</Button>
							</div>
						</CardBody>
					</Card>
				</div>
			</div>
		</div>
	);
}
