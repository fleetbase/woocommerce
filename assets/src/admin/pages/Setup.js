import { useState } from '@wordpress/element';
import { Button, Card, CardBody, Notice, RadioControl, Spinner, TextControl, ExternalLink } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import Header from '../components/Header';
import PickupPicker from '../components/PickupPicker';
import WebhookCard from '../components/WebhookCard';
import { api, errorMessage, runtimeData } from '../api';

const STEPS = [ 'mode', 'key', 'pickup', 'shipping', 'webhook' ];

function Stepper( { current } ) {
	const labels = {
		mode: __( 'Account', 'fleetbase-for-woocommerce' ),
		key: __( 'API key', 'fleetbase-for-woocommerce' ),
		pickup: __( 'Pickup location', 'fleetbase-for-woocommerce' ),
		shipping: __( 'Checkout', 'fleetbase-for-woocommerce' ),
		webhook: __( 'Status updates', 'fleetbase-for-woocommerce' ),
	};
	return (
		<ol className="fbwc-stepper">
			{ STEPS.map( ( s, i ) => (
				<li key={ s } className={ s === current ? 'is-current' : STEPS.indexOf( current ) > i ? 'is-done' : '' }>
					<span className="fbwc-stepper__num">{ i + 1 }</span> { labels[ s ] }
				</li>
			) ) }
		</ol>
	);
}

export default function Setup() {
	const initial = runtimeData;
	const alreadyConnected = initial.connection.connected;
	const [ state, setState ] = useState( initial );
	const [ step, setStep ] = useState( alreadyConnected ? ( initial.pickup ? 'shipping' : 'pickup' ) : 'mode' );
	const [ provider, setProvider ] = useState( initial.connection.provider || initial.providers[ 0 ]?.id || 'cloud' );
	const providerDef = state.providers.find( ( p ) => p.id === provider ) || state.providers[ 0 ];
	const [ host, setHost ] = useState( initial.connection.host || providerDef?.default_host || '' );
	const [ key, setKey ] = useState( '' );
	const [ tested, setTested ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( null );

	const go = ( next ) => {
		setError( null );
		setStep( next );
		window.scrollTo( { top: 0 } );
	};

	const test = async () => {
		setBusy( true );
		setError( null );
		setTested( null );
		try {
			const res = await api( 'connection/test', { method: 'POST', data: { provider, host, key } } );
			setTested( res );
		} catch ( e ) {
			setError( errorMessage( e ) );
		}
		setBusy( false );
	};

	const connect = async () => {
		setBusy( true );
		setError( null );
		try {
			const res = await api( 'connection', { method: 'POST', data: { provider, host, key } } );
			setState( res );
			setKey( '' );
			go( res.pickup ? 'shipping' : 'pickup' );
		} catch ( e ) {
			setError( errorMessage( e ) );
		}
		setBusy( false );
	};

	const finish = async () => {
		setBusy( true );
		try {
			await api( 'wizard/complete', { method: 'POST' } );
			window.location.href = state.pages.dashboard;
		} catch ( e ) {
			setError( errorMessage( e ) );
			setBusy( false );
		}
	};

	return (
		<div className="fbwc-page fbwc-page--narrow">
			<Header title={ __( 'Set up Fleetbase', 'fleetbase-for-woocommerce' ) } />
			<Stepper current={ step } />
			{ error && <Notice status="error" isDismissible onRemove={ () => setError( null ) }>{ error }</Notice> }

			{ step === 'mode' && (
				<Card className="fbwc-card"><CardBody>
					<h2>{ __( 'Where is your Fleetbase account?', 'fleetbase-for-woocommerce' ) }</h2>
					<RadioControl
						selected={ provider }
						onChange={ ( id ) => { setProvider( id ); const d = state.providers.find( ( p ) => p.id === id ); setHost( d?.default_host || '' ); } }
						options={ state.providers.map( ( p ) => ( { value: p.id, label: p.label } ) ) }
					/>
					<p className="fbwc-muted">{ providerDef?.description }</p>
					{ provider === 'cloud' && (
						<p>
							{ __( "Don't have an account yet?", 'fleetbase-for-woocommerce' ) }{ ' ' }
							<ExternalLink href={ state.signupUrl }>{ __( 'Create a free Fleetbase Cloud trial', 'fleetbase-for-woocommerce' ) }</ExternalLink>
							{ ' ' }{ __( '(7 days, every module included; after that $29/month plus $5 per driver and vehicle, with no per-order fees).', 'fleetbase-for-woocommerce' ) }
						</p>
					) }
					{ state.providers.length === 1 && (
						<p className="fbwc-muted">{ __( 'Running your own Fleetbase instance? Install the Fleetbase Self-Hosted Connector add-on to connect to a custom host.', 'fleetbase-for-woocommerce' ) }</p>
					) }
					<div className="fbwc-actions"><Button variant="primary" onClick={ () => go( 'key' ) }>{ __( 'Continue', 'fleetbase-for-woocommerce' ) }</Button></div>
				</CardBody></Card>
			) }

			{ step === 'key' && (
				<Card className="fbwc-card"><CardBody>
					<h2>{ __( 'Connect with an API key', 'fleetbase-for-woocommerce' ) }</h2>
					<p>{ __( 'In the Fleetbase console open Developers → API Keys and create a key. Use a live key for real orders, or a test key to try things out safely.', 'fleetbase-for-woocommerce' ) }</p>
					{ providerDef?.host_editable && (
						<TextControl label={ __( 'API host', 'fleetbase-for-woocommerce' ) } value={ host } onChange={ setHost } help={ __( 'For example https://api.yourcompany.com', 'fleetbase-for-woocommerce' ) } __nextHasNoMarginBottom />
					) }
					<TextControl label={ __( 'API key', 'fleetbase-for-woocommerce' ) } value={ key } onChange={ ( v ) => { setKey( v ); setTested( null ); } } placeholder="flb_live_…" type="password" __nextHasNoMarginBottom />
					{ tested && (
						<Notice status="success" isDismissible={ false }>
							{ sprintf( __( 'Connected to %1$s (%2$s mode).', 'fleetbase-for-woocommerce' ), tested.organization?.name || '', tested.mode ) }
						</Notice>
					) }
					<div className="fbwc-actions">
						<Button variant="secondary" onClick={ () => go( 'mode' ) }>{ __( 'Back', 'fleetbase-for-woocommerce' ) }</Button>
						<Button variant="secondary" onClick={ test } disabled={ busy || key.length < 10 }>{ busy && ! tested ? <Spinner /> : __( 'Test connection', 'fleetbase-for-woocommerce' ) }</Button>
						<Button variant="primary" onClick={ connect } disabled={ busy || ! tested }>{ __( 'Connect', 'fleetbase-for-woocommerce' ) }</Button>
					</div>
				</CardBody></Card>
			) }

			{ step === 'pickup' && (
				<Card className="fbwc-card"><CardBody>
					<h2>{ __( 'Where do drivers pick up orders?', 'fleetbase-for-woocommerce' ) }</h2>
					<PickupPicker pickup={ state.pickup } onSaved={ ( pickup ) => setState( { ...state, pickup } ) } />
					<div className="fbwc-actions">
						<Button variant="primary" onClick={ () => go( 'shipping' ) } disabled={ ! state.pickup }>{ __( 'Continue', 'fleetbase-for-woocommerce' ) }</Button>
					</div>
				</CardBody></Card>
			) }

			{ step === 'shipping' && (
				<Card className="fbwc-card"><CardBody>
					<h2>{ __( 'Offer delivery at checkout', 'fleetbase-for-woocommerce' ) }</h2>
					<p>{ __( 'Fleetbase Delivery is a WooCommerce shipping method. Add it to the shipping zone that covers your delivery area and customers will see a live delivery price at checkout.', 'fleetbase-for-woocommerce' ) }</p>
					<ol className="fbwc-steps">
						<li>{ __( 'Go to WooCommerce → Settings → Shipping and open (or create) the zone for your delivery area.', 'fleetbase-for-woocommerce' ) }</li>
						<li>{ __( 'Click "Add shipping method" and choose "Fleetbase Delivery".', 'fleetbase-for-woocommerce' ) }</li>
						<li>{ __( 'Prices come from your Fleetbase service rates. Create at least one rate in Fleetbase → Service Rates.', 'fleetbase-for-woocommerce' ) }</li>
					</ol>
					{ state.shippingMethodConfigured
						? <Notice status="success" isDismissible={ false }>{ __( 'Fleetbase Delivery is already added to a shipping zone.', 'fleetbase-for-woocommerce' ) }</Notice>
						: <Notice status="info" isDismissible={ false }>{ __( 'Not added to any zone yet. You can do this now or later.', 'fleetbase-for-woocommerce' ) }</Notice> }
					<div className="fbwc-actions">
						<Button variant="secondary" href={ state.pages.shipping } target="_blank">{ __( 'Open shipping zones', 'fleetbase-for-woocommerce' ) }</Button>
						<Button variant="primary" onClick={ () => go( 'webhook' ) }>{ __( 'Continue', 'fleetbase-for-woocommerce' ) }</Button>
					</div>
				</CardBody></Card>
			) }

			{ step === 'webhook' && (
				<>
					<WebhookCard webhookUrl={ state.webhookUrl } onRotate={ ( webhookUrl ) => setState( { ...state, webhookUrl } ) } />
					<Card className="fbwc-card"><CardBody>
						<div className="fbwc-actions">
							<Button variant="secondary" onClick={ () => go( 'shipping' ) }>{ __( 'Back', 'fleetbase-for-woocommerce' ) }</Button>
							<Button variant="primary" onClick={ finish } disabled={ busy }>{ busy ? <Spinner /> : __( 'Finish setup', 'fleetbase-for-woocommerce' ) }</Button>
						</div>
					</CardBody></Card>
				</>
			) }
		</div>
	);
}
