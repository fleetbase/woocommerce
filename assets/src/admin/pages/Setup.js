import { useEffect, useState } from '@wordpress/element';
import {
	Button,
	Card,
	CardBody,
	Notice,
	RadioControl,
	Spinner,
	TextControl,
	ExternalLink,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import Header from '../components/Header';
import PickupPicker from '../components/PickupPicker';
import WebhookCard from '../components/WebhookCard';
import { api, errorMessage, runtimeData } from '../api';

const STEPS = [ 'mode', 'key', 'pickup', 'shipping', 'webhook' ];

function Stepper( { current } ) {
	const labels = {
		mode: __( 'Account', 'fleetbase-for-woocommerce' ),
		key: __( 'Connect', 'fleetbase-for-woocommerce' ),
		pickup: __( 'Pickup location', 'fleetbase-for-woocommerce' ),
		shipping: __( 'Checkout', 'fleetbase-for-woocommerce' ),
		webhook: __( 'Status updates', 'fleetbase-for-woocommerce' ),
	};
	return (
		<ol className="fbwc-stepper">
			{ STEPS.map( ( s, i ) => (
				<li key={ s } className={ stepClass( STEPS, current, s, i ) }>
					<span className="fbwc-stepper__num">{ i + 1 }</span>{ ' ' }
					{ labels[ s ] }
				</li>
			) ) }
		</ol>
	);
}

/**
 * The result of "Connect with Fleetbase", from the URL Fleetbase sent the merchant back to.
 */
const OAUTH_RESULT = new URLSearchParams( window.location.search ).get(
	'fleetbase_oauth'
);

function oauthMessage( result, detail ) {
	const messages = {
		access_denied: __(
			'You cancelled the connection in Fleetbase. Nothing was changed.',
			'fleetbase-for-woocommerce'
		),
		state_mismatch: __(
			'That connection attempt expired. Start again.',
			'fleetbase-for-woocommerce'
		),
		unreachable: __(
			'WordPress could not reach Fleetbase. Check the host and try again.',
			'fleetbase-for-woocommerce'
		),
		not_supported: __(
			'This Fleetbase server does not support connecting with OAuth yet. Paste an API key instead.',
			'fleetbase-for-woocommerce'
		),
		no_permission: __(
			'Only an administrator of the Fleetbase organization can connect a store.',
			'fleetbase-for-woocommerce'
		),
		missing_scope: __(
			'Fleetbase did not allow creating an API key for this store.',
			'fleetbase-for-woocommerce'
		),
	};
	const sentence =
		messages[ result ] ||
		__(
			'Connecting with Fleetbase did not finish.',
			'fleetbase-for-woocommerce'
		);
	return detail && ! messages[ result ] ? sentence + ' ' + detail : sentence;
}

function initialStep( data ) {
	if ( ! data.connection.connected ) {
		return OAUTH_RESULT && OAUTH_RESULT !== 'connected' ? 'key' : 'mode';
	}
	return data.pickup ? 'shipping' : 'pickup';
}

function stepClass( steps, current, step, index ) {
	if ( step === current ) {
		return 'is-current';
	}
	return steps.indexOf( current ) > index ? 'is-done' : '';
}

export default function Setup() {
	const initial = runtimeData;
	const [ state, setState ] = useState( initial );
	const [ step, setStep ] = useState( initialStep( initial ) );
	const [ provider, setProvider ] = useState(
		initial.connection.provider || initial.providers[ 0 ]?.id || 'cloud'
	);
	const providerDef =
		state.providers.find( ( p ) => p.id === provider ) ||
		state.providers[ 0 ];
	const [ host, setHost ] = useState(
		initial.connection.host || providerDef?.default_host || ''
	);
	const [ key, setKey ] = useState( '' );
	const [ tested, setTested ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState(
		OAUTH_RESULT && OAUTH_RESULT !== 'connected'
			? oauthMessage( OAUTH_RESULT, initial.oauth?.error )
			: null
	);
	const [ justConnected ] = useState(
		OAUTH_RESULT === 'connected' && initial.connection.connected
	);
	const [ pasteKey, setPasteKey ] = useState( false );
	const canOAuth = !! providerDef?.oauth && !! state.oauth?.startUrl;

	// Drop the result from the address bar so a reload doesn't repeat the message.
	useEffect( () => {
		if ( OAUTH_RESULT && window.history?.replaceState ) {
			const url = new URL( window.location.href );
			url.searchParams.delete( 'fleetbase_oauth' );
			window.history.replaceState( null, '', url.toString() );
		}
	}, [] );

	const oauthUrl = () => {
		const url = new URL( state.oauth.startUrl, window.location.href );
		url.searchParams.set( 'provider', provider );
		if ( providerDef?.host_editable ) {
			url.searchParams.set( 'host', host );
		}
		return url.toString();
	};

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
			const res = await api( 'connection/test', {
				method: 'POST',
				data: { provider, host, key },
			} );
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
			const res = await api( 'connection', {
				method: 'POST',
				data: { provider, host, key },
			} );
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
			<Header
				title={ __( 'Set up Fleetbase', 'fleetbase-for-woocommerce' ) }
				connection={ state.connection }
			/>
			<Stepper current={ step } />
			{ justConnected && step !== 'webhook' && (
				<Notice status="success" isDismissible={ false }>
					{ sprintf(
						/* translators: %s: organisation name */
						__(
							'Connected to %s. Fleetbase created an API key for this store; you can see it under Developers → API Keys.',
							'fleetbase-for-woocommerce'
						),
						state.connection.organization?.name || 'Fleetbase'
					) }
				</Notice>
			) }
			{ error && (
				<Notice
					status="error"
					isDismissible
					onRemove={ () => setError( null ) }
				>
					{ error }
				</Notice>
			) }

			{ step === 'mode' && (
				<Card className="fbwc-card">
					<CardBody>
						<h2>
							{ __(
								'Where is your Fleetbase account?',
								'fleetbase-for-woocommerce'
							) }
						</h2>
						<RadioControl
							selected={ provider }
							onChange={ ( id ) => {
								setProvider( id );
								const d = state.providers.find(
									( p ) => p.id === id
								);
								setHost( d?.default_host || '' );
							} }
							options={ state.providers.map( ( p ) => ( {
								value: p.id,
								label: p.label,
							} ) ) }
						/>
						<p className="fbwc-muted">
							{ providerDef?.description }
						</p>
						{ provider === 'cloud' && (
							<p>
								{ __(
									"Don't have an account yet?",
									'fleetbase-for-woocommerce'
								) }{ ' ' }
								<ExternalLink href={ state.signupUrl }>
									{ __(
										'Create a free Fleetbase Cloud trial',
										'fleetbase-for-woocommerce'
									) }
								</ExternalLink>{ ' ' }
								{ __(
									'(7 days, every module included; after that $29/month plus $5 per driver and vehicle, with no per-order fees).',
									'fleetbase-for-woocommerce'
								) }
							</p>
						) }
						{ state.providers.length === 1 && (
							<p className="fbwc-muted">
								{ __(
									'Running your own Fleetbase instance? Install the Fleetbase Self-Hosted Connector add-on to connect to a custom host.',
									'fleetbase-for-woocommerce'
								) }
							</p>
						) }
						<div className="fbwc-actions">
							<Button
								variant="primary"
								onClick={ () => go( 'key' ) }
							>
								{ __(
									'Continue',
									'fleetbase-for-woocommerce'
								) }
							</Button>
						</div>
					</CardBody>
				</Card>
			) }

			{ step === 'key' && (
				<Card className="fbwc-card">
					<CardBody>
						<h2>
							{ __(
								'Connect your store',
								'fleetbase-for-woocommerce'
							) }
						</h2>
						{ canOAuth && (
							<>
								<p>
									{ __(
										'Sign in to Fleetbase and pick your organization. Fleetbase creates an API key for this store and the plugin registers for status updates, so there is nothing to copy.',
										'fleetbase-for-woocommerce'
									) }
								</p>
								{ providerDef?.host_editable && (
									<TextControl
										label={ __(
											'API host',
											'fleetbase-for-woocommerce'
										) }
										value={ host }
										onChange={ setHost }
										help={ __(
											'For example https://api.yourcompany.com',
											'fleetbase-for-woocommerce'
										) }
										__nextHasNoMarginBottom
									/>
								) }
								<div className="fbwc-actions">
									<Button
										variant="secondary"
										onClick={ () => go( 'mode' ) }
									>
										{ __(
											'Back',
											'fleetbase-for-woocommerce'
										) }
									</Button>
									<Button
										variant="primary"
										href={ oauthUrl() }
									>
										{ __(
											'Connect with Fleetbase',
											'fleetbase-for-woocommerce'
										) }
									</Button>
								</div>
								<p>
									<Button
										variant="link"
										onClick={ () =>
											setPasteKey( ! pasteKey )
										}
										aria-expanded={ pasteKey }
									>
										{ __(
											'Paste an API key instead',
											'fleetbase-for-woocommerce'
										) }
									</Button>
								</p>
							</>
						) }
						{ ( ! canOAuth || pasteKey ) && (
							<>
								<p>
									{ __(
										'In the Fleetbase console open Developers → API Keys and create a key. Use a live key for real orders, or a test key to try things out safely.',
										'fleetbase-for-woocommerce'
									) }
								</p>
								{ providerDef?.host_editable && ! canOAuth && (
									<TextControl
										label={ __(
											'API host',
											'fleetbase-for-woocommerce'
										) }
										value={ host }
										onChange={ setHost }
										help={ __(
											'For example https://api.yourcompany.com',
											'fleetbase-for-woocommerce'
										) }
										__nextHasNoMarginBottom
									/>
								) }
								<TextControl
									label={ __(
										'API key',
										'fleetbase-for-woocommerce'
									) }
									value={ key }
									onChange={ ( v ) => {
										setKey( v );
										setTested( null );
									} }
									placeholder="flb_live_…"
									type="password"
									__nextHasNoMarginBottom
								/>
								{ tested && (
									<Notice
										status="success"
										isDismissible={ false }
									>
										{ sprintf(
											/* translators: 1: organisation name, 2: live or test */
											__(
												'Connected to %1$s (%2$s mode).',
												'fleetbase-for-woocommerce'
											),
											tested.organization?.name || '',
											tested.mode
										) }
									</Notice>
								) }
								<div className="fbwc-actions">
									<Button
										variant="secondary"
										onClick={ () => go( 'mode' ) }
									>
										{ __(
											'Back',
											'fleetbase-for-woocommerce'
										) }
									</Button>
									<Button
										variant="secondary"
										onClick={ test }
										disabled={ busy || key.length < 10 }
									>
										{ busy && ! tested ? (
											<Spinner />
										) : (
											__(
												'Test connection',
												'fleetbase-for-woocommerce'
											)
										) }
									</Button>
									<Button
										variant="primary"
										onClick={ connect }
										disabled={ busy || ! tested }
									>
										{ __(
											'Connect',
											'fleetbase-for-woocommerce'
										) }
									</Button>
								</div>
							</>
						) }
					</CardBody>
				</Card>
			) }

			{ step === 'pickup' && (
				<Card className="fbwc-card">
					<CardBody>
						<h2>
							{ __(
								'Where do drivers pick up orders?',
								'fleetbase-for-woocommerce'
							) }
						</h2>
						<PickupPicker
							pickup={ state.pickup }
							onSaved={ ( pickup ) =>
								setState( { ...state, pickup } )
							}
						/>
						<div className="fbwc-actions">
							<Button
								variant="primary"
								onClick={ () => go( 'shipping' ) }
								disabled={ ! state.pickup }
							>
								{ __(
									'Continue',
									'fleetbase-for-woocommerce'
								) }
							</Button>
						</div>
					</CardBody>
				</Card>
			) }

			{ step === 'shipping' && (
				<Card className="fbwc-card">
					<CardBody>
						<h2>
							{ __(
								'Offer delivery at checkout',
								'fleetbase-for-woocommerce'
							) }
						</h2>
						<p>
							{ __(
								'Fleetbase Delivery is a WooCommerce shipping method. Add it to the shipping zone that covers your delivery area and customers will see a live delivery price at checkout.',
								'fleetbase-for-woocommerce'
							) }
						</p>
						<ol className="fbwc-steps">
							<li>
								{ __(
									'Go to WooCommerce → Settings → Shipping and open (or create) the zone for your delivery area.',
									'fleetbase-for-woocommerce'
								) }
							</li>
							<li>
								{ __(
									'Click "Add shipping method" and choose "Fleetbase Delivery".',
									'fleetbase-for-woocommerce'
								) }
							</li>
							<li>
								{ __(
									'Prices come from your Fleetbase service rates. Create at least one rate in Fleetbase → Service Rates.',
									'fleetbase-for-woocommerce'
								) }
							</li>
						</ol>
						{ state.shippingMethodConfigured ? (
							<Notice status="success" isDismissible={ false }>
								{ __(
									'Fleetbase Delivery is already added to a shipping zone.',
									'fleetbase-for-woocommerce'
								) }
							</Notice>
						) : (
							<Notice status="info" isDismissible={ false }>
								{ __(
									'Not added to any zone yet. You can do this now or later.',
									'fleetbase-for-woocommerce'
								) }
							</Notice>
						) }
						<div className="fbwc-actions">
							<Button
								variant="secondary"
								href={ state.pages.shipping }
								target="_blank"
							>
								{ __(
									'Open shipping zones',
									'fleetbase-for-woocommerce'
								) }
							</Button>
							<Button
								variant="primary"
								onClick={ () => go( 'webhook' ) }
							>
								{ __(
									'Continue',
									'fleetbase-for-woocommerce'
								) }
							</Button>
						</div>
					</CardBody>
				</Card>
			) }

			{ step === 'webhook' && (
				<>
					<WebhookCard
						webhookUrl={ state.webhookUrl }
						status={ state.webhook }
						onChange={ ( patch ) =>
							setState( { ...state, ...patch } )
						}
					/>
					<Card className="fbwc-card">
						<CardBody>
							<div className="fbwc-actions">
								<Button
									variant="secondary"
									onClick={ () => go( 'shipping' ) }
								>
									{ __(
										'Back',
										'fleetbase-for-woocommerce'
									) }
								</Button>
								<Button
									variant="primary"
									onClick={ finish }
									disabled={ busy }
								>
									{ busy ? (
										<Spinner />
									) : (
										__(
											'Finish setup',
											'fleetbase-for-woocommerce'
										)
									) }
								</Button>
							</div>
						</CardBody>
					</Card>
				</>
			) }
		</div>
	);
}
