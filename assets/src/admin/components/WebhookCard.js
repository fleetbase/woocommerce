import {
	Card,
	CardBody,
	CardHeader,
	Button,
	ExternalLink,
	Notice,
	Spinner,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import CopyField from './CopyField';
import { api, errorMessage, runtimeData } from '../api';

/**
 * Status updates from Fleetbase.
 *
 * When the plugin registered its own webhook endpoint with the API key, the card only
 * says so. Otherwise it offers to set it up automatically and keeps the manual steps:
 * Fleetbase refuses URLs it cannot reach (localhost, a private network), and older
 * Fleetbase versions have no endpoint API. The poller covers updates either way.
 *
 * @param {Object}   props
 * @param {string}   props.webhookUrl The store's webhook URL.
 * @param {Object}   props.status     { automatic, error, checked_at } from the registrar.
 * @param {Function} props.onChange   Receives { webhookUrl?, webhook? } after a change.
 * @param {boolean}  props.compact    Hide the manual steps.
 */
export default function WebhookCard( {
	webhookUrl,
	status,
	onChange,
	compact,
} ) {
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( null );
	const { consoleUrl, connection } = runtimeData;
	const automatic = !! status?.automatic;
	const consoleWebhooks =
		( connection?.is_cloud
			? consoleUrl
			: ( connection?.host || consoleUrl ).replace(
					/api\./,
					'console.'
			  ) ) + '/developers/webhooks';

	const setUp = async () => {
		setBusy( true );
		setError( null );
		try {
			const res = await api( 'webhook/register', { method: 'POST' } );
			if ( onChange ) {
				onChange( { webhook: res.webhook } );
			}
		} catch ( e ) {
			setError( errorMessage( e ) );
		}
		setBusy( false );
	};

	const rotate = async () => {
		if (
			// eslint-disable-next-line no-alert -- destructive action deserves a native confirm.
			! window.confirm(
				automatic
					? __(
							'Generate a new webhook URL? Fleetbase is updated automatically.',
							'fleetbase-for-woocommerce'
					  )
					: __(
							'Generate a new webhook URL? The old URL will stop working and must be updated in Fleetbase.',
							'fleetbase-for-woocommerce'
					  )
			)
		) {
			return;
		}
		setBusy( true );
		setError( null );
		try {
			const res = await api( 'webhook/rotate', { method: 'POST' } );
			if ( onChange ) {
				onChange( {
					webhookUrl: res.webhookUrl,
					webhook: res.webhook,
				} );
			}
		} catch ( e ) {
			setError( errorMessage( e ) );
		}
		setBusy( false );
	};

	return (
		<Card className="fbwc-card">
			<CardHeader>
				<strong>
					{ __(
						'Status updates from Fleetbase',
						'fleetbase-for-woocommerce'
					) }
				</strong>
			</CardHeader>
			<CardBody>
				{ error && (
					<Notice
						status="error"
						isDismissible
						onRemove={ () => setError( null ) }
					>
						{ error }
					</Notice>
				) }
				{ automatic ? (
					<Notice status="success" isDismissible={ false }>
						{ __(
							'Status updates are on. Fleetbase sends driver assignments, dispatches and deliveries to this store as they happen, signed so only Fleetbase can send them.',
							'fleetbase-for-woocommerce'
						) }
					</Notice>
				) : (
					<>
						<p>
							{ __(
								'Fleetbase can push order updates (driver assigned, out for delivery, completed) to your store as they happen. Without them, the plugin still checks for updates every few minutes.',
								'fleetbase-for-woocommerce'
							) }
						</p>
						{ status?.error && (
							<Notice status="warning" isDismissible={ false }>
								{ __(
									'Fleetbase could not register this store automatically:',
									'fleetbase-for-woocommerce'
								) }{ ' ' }
								{ status.error }
							</Notice>
						) }
						{ connection?.connected && (
							<div className="fbwc-actions">
								<Button
									variant="primary"
									onClick={ setUp }
									disabled={ busy }
								>
									{ busy ? (
										<Spinner />
									) : (
										__(
											'Set up automatically',
											'fleetbase-for-woocommerce'
										)
									) }
								</Button>
							</div>
						) }
						{ ! compact && (
							<>
								<p className="fbwc-muted">
									{ __(
										'Or add it yourself:',
										'fleetbase-for-woocommerce'
									) }
								</p>
								<ol className="fbwc-steps">
									<li>
										{ __(
											'Open Developers → Webhooks in the Fleetbase console and click "New webhook".',
											'fleetbase-for-woocommerce'
										) }
									</li>
									<li>
										{ __(
											'Paste the URL below. Choose "Live" or "Test" to match the API key you connected.',
											'fleetbase-for-woocommerce'
										) }
									</li>
									<li>
										{ __(
											'Select all order events (or leave events empty to receive everything), then save.',
											'fleetbase-for-woocommerce'
										) }
									</li>
								</ol>
							</>
						) }
					</>
				) }
				<CopyField
					value={ webhookUrl }
					label={ __( 'Webhook URL', 'fleetbase-for-woocommerce' ) }
				/>
				<div className="fbwc-actions">
					{ ! automatic && (
						<ExternalLink href={ consoleWebhooks }>
							{ __(
								'Open webhooks in Fleetbase',
								'fleetbase-for-woocommerce'
							) }
						</ExternalLink>
					) }
					<Button
						variant="tertiary"
						onClick={ rotate }
						disabled={ busy }
					>
						{ __(
							'Generate new URL',
							'fleetbase-for-woocommerce'
						) }
					</Button>
				</div>
			</CardBody>
		</Card>
	);
}
