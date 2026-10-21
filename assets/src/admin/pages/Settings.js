import { useState } from '@wordpress/element';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
	Spinner,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import Header from '../components/Header';
import StatusCard from '../components/StatusCard';
import PickupPicker from '../components/PickupPicker';
import DeliveryHours from '../components/DeliveryHours';
import { api, errorMessage, runtimeData } from '../api';

export default function Settings() {
	const [ state, setState ] = useState( runtimeData );
	const [ settings, setSettings ] = useState( runtimeData.settings );
	const [ busy, setBusy ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const checkout = settings.checkout || {};
	const setCheckout = ( patch ) =>
		setSettings( { ...settings, checkout: { ...checkout, ...patch } } );

	const save = async () => {
		setBusy( true );
		setNotice( null );
		try {
			const res = await api( 'settings', {
				method: 'POST',
				data: { settings },
			} );
			setSettings( res.settings );
			setNotice( {
				status: 'success',
				text: __( 'Settings saved.', 'fleetbase-for-woocommerce' ),
			} );
		} catch ( e ) {
			setNotice( { status: 'error', text: errorMessage( e ) } );
		}
		setBusy( false );
	};

	return (
		<div className="fbwc-page">
			<Header
				title={ __(
					'Fleetbase settings',
					'fleetbase-for-woocommerce'
				) }
				connection={ state.connection }
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
			<div className="fbwc-grid">
				<div className="fbwc-grid__main">
					<StatusCard state={ state } onChange={ setState } />
					{ state.connection.connected && (
						<Card className="fbwc-card">
							<CardHeader>
								<strong>
									{ __(
										'Pickup location',
										'fleetbase-for-woocommerce'
									) }
								</strong>
							</CardHeader>
							<CardBody>
								<PickupPicker
									pickup={ state.pickup }
									onSaved={ ( pickup ) =>
										setState( { ...state, pickup } )
									}
								/>
							</CardBody>
						</Card>
					) }
					<Card className="fbwc-card">
						<CardHeader>
							<strong>
								{ __(
									'Delivery scheduling',
									'fleetbase-for-woocommerce'
								) }
							</strong>
						</CardHeader>
						<CardBody>
							<ToggleControl
								label={ __(
									'Let customers choose a delivery time at checkout',
									'fleetbase-for-woocommerce'
								) }
								help={ __(
									'When off, every order is delivered as soon as possible.',
									'fleetbase-for-woocommerce'
								) }
								checked={ !! checkout.scheduling_enabled }
								onChange={ ( v ) =>
									setCheckout( { scheduling_enabled: v } )
								}
								__nextHasNoMarginBottom
							/>
							<div className="fbwc-field-row">
								<TextControl
									type="number"
									label={ __(
										'Lead time (minutes)',
										'fleetbase-for-woocommerce'
									) }
									help={ __(
										'Earliest a window may start after the order is placed.',
										'fleetbase-for-woocommerce'
									) }
									value={ checkout.lead_time_minutes }
									onChange={ ( v ) =>
										setCheckout( { lead_time_minutes: v } )
									}
									__nextHasNoMarginBottom
								/>
								<TextControl
									type="number"
									label={ __(
										'Days ahead',
										'fleetbase-for-woocommerce'
									) }
									help={ __(
										'How many days customers may book in advance.',
										'fleetbase-for-woocommerce'
									) }
									value={ checkout.horizon_days }
									onChange={ ( v ) =>
										setCheckout( { horizon_days: v } )
									}
									__nextHasNoMarginBottom
								/>
								<TextControl
									type="number"
									label={ __(
										'Window length (minutes)',
										'fleetbase-for-woocommerce'
									) }
									help={ __(
										'Each delivery window lasts this long.',
										'fleetbase-for-woocommerce'
									) }
									value={ checkout.window_minutes }
									onChange={ ( v ) =>
										setCheckout( { window_minutes: v } )
									}
									__nextHasNoMarginBottom
								/>
							</div>
							<DeliveryHours
								hours={ checkout.delivery_hours || {} }
								onChange={ ( hours ) =>
									setCheckout( { delivery_hours: hours } )
								}
							/>
							<TextControl
								label={ __(
									'Message when an address is outside your delivery area',
									'fleetbase-for-woocommerce'
								) }
								value={ checkout.no_service_message || '' }
								placeholder={ __(
									"Sorry, we don't deliver to this address yet.",
									'fleetbase-for-woocommerce'
								) }
								onChange={ ( v ) =>
									setCheckout( { no_service_message: v } )
								}
								__nextHasNoMarginBottom
							/>
							<div className="fbwc-actions">
								<Button
									variant="primary"
									onClick={ save }
									disabled={ busy }
								>
									{ busy ? (
										<Spinner />
									) : (
										__(
											'Save settings',
											'fleetbase-for-woocommerce'
										)
									) }
								</Button>
							</div>
						</CardBody>
					</Card>
					<Card className="fbwc-card">
						<CardHeader>
							<strong>
								{ __(
									'Advanced',
									'fleetbase-for-woocommerce'
								) }
							</strong>
						</CardHeader>
						<CardBody>
							<ToggleControl
								label={ __(
									'Debug logging',
									'fleetbase-for-woocommerce'
								) }
								help={ __(
									'Log every Fleetbase API call to WooCommerce → Status → Logs (source "fleetbase"). API keys are never written to the log.',
									'fleetbase-for-woocommerce'
								) }
								checked={ !! settings.debug_logging }
								onChange={ ( v ) =>
									setSettings( {
										...settings,
										debug_logging: v,
									} )
								}
								__nextHasNoMarginBottom
							/>
							<div className="fbwc-actions">
								<Button
									variant="primary"
									onClick={ save }
									disabled={ busy }
								>
									{ busy ? (
										<Spinner />
									) : (
										__(
											'Save settings',
											'fleetbase-for-woocommerce'
										)
									) }
								</Button>
							</div>
						</CardBody>
					</Card>
				</div>
			</div>
		</div>
	);
}
