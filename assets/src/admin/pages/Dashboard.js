import { useState } from '@wordpress/element';
import { Card, CardBody, CardHeader, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import Header from '../components/Header';
import StatusCard from '../components/StatusCard';
import WebhookCard from '../components/WebhookCard';
import { runtimeData } from '../api';

export default function Dashboard() {
	const [ state, setState ] = useState( runtimeData );
	const { pages, docsUrl } = state;

	return (
		<div className="fbwc-page">
			<Header title={ __( 'Fleetbase', 'fleetbase-for-woocommerce' ) } />
			<div className="fbwc-grid">
				<div className="fbwc-grid__main">
					<StatusCard state={ state } onChange={ setState } />
					{ state.connection.connected && (
						<WebhookCard webhookUrl={ state.webhookUrl } onRotate={ ( webhookUrl ) => setState( { ...state, webhookUrl } ) } compact />
					) }
				</div>
				<div className="fbwc-grid__side">
					<Card className="fbwc-card">
						<CardHeader><strong>{ __( 'Next steps', 'fleetbase-for-woocommerce' ) }</strong></CardHeader>
						<CardBody>
							<ul className="fbwc-links">
								<li><a href={ pages.shipping }>{ __( 'Add "Fleetbase Delivery" to a shipping zone', 'fleetbase-for-woocommerce' ) }</a></li>
								<li><a href={ pages.settings }>{ __( 'Review delivery and sync settings', 'fleetbase-for-woocommerce' ) }</a></li>
								<li><a href={ pages.logs }>{ __( 'View the Fleetbase log', 'fleetbase-for-woocommerce' ) }</a></li>
								<li><a href={ docsUrl } target="_blank" rel="noreferrer">{ __( 'Read the documentation', 'fleetbase-for-woocommerce' ) }</a></li>
							</ul>
							<Button variant="secondary" href={ pages.setup }>{ __( 'Re-run setup wizard', 'fleetbase-for-woocommerce' ) }</Button>
						</CardBody>
					</Card>
					<Card className="fbwc-card">
						<CardHeader><strong>{ __( 'Store', 'fleetbase-for-woocommerce' ) }</strong></CardHeader>
						<CardBody>
							<p><strong>{ state.store.name }</strong></p>
							<p className="fbwc-muted">{ state.store.address.formatted || __( 'No store address set in WooCommerce → Settings → General.', 'fleetbase-for-woocommerce' ) }</p>
							<p className="fbwc-muted">{ __( 'Currency:', 'fleetbase-for-woocommerce' ) } { state.store.currency } · { __( 'Units:', 'fleetbase-for-woocommerce' ) } { state.store.weightUnit }/{ state.store.dimensionUnit } · HPOS { state.store.hposEnabled ? __( 'on', 'fleetbase-for-woocommerce' ) : __( 'off', 'fleetbase-for-woocommerce' ) }</p>
						</CardBody>
					</Card>
				</div>
			</div>
		</div>
	);
}
