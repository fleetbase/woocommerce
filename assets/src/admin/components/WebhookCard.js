import {
	Card,
	CardBody,
	CardHeader,
	Button,
	ExternalLink,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import CopyField from './CopyField';
import { api, runtimeData } from '../api';

export default function WebhookCard( { webhookUrl, onRotate, compact } ) {
	const [ busy, setBusy ] = useState( false );
	const { consoleUrl, connection } = runtimeData;
	const consoleWebhooks =
		( connection?.is_cloud
			? consoleUrl
			: ( connection?.host || consoleUrl ).replace(
					/api\./,
					'console.'
			  ) ) + '/developers/webhooks';

	const rotate = async () => {
		if (
			// eslint-disable-next-line no-alert -- destructive action deserves a native confirm.
			! window.confirm(
				__(
					'Generate a new webhook URL? The old URL will stop working and must be updated in Fleetbase.',
					'fleetbase-for-woocommerce'
				)
			)
		) {
			return;
		}
		setBusy( true );
		const res = await api( 'webhook/rotate', { method: 'POST' } );
		setBusy( false );
		if ( onRotate ) {
			onRotate( res.webhookUrl );
		}
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
				<p>
					{ __(
						'Fleetbase can push order updates (driver assigned, out for delivery, completed) to your store instantly. Add this URL as a webhook endpoint in your Fleetbase console. Without it, the plugin still checks for updates every few minutes.',
						'fleetbase-for-woocommerce'
					) }
				</p>
				<CopyField
					value={ webhookUrl }
					label={ __( 'Webhook URL', 'fleetbase-for-woocommerce' ) }
				/>
				{ ! compact && (
					<ol className="fbwc-steps">
						<li>
							{ __(
								'Open Developers → Webhooks in the Fleetbase console and click "New webhook".',
								'fleetbase-for-woocommerce'
							) }
						</li>
						<li>
							{ __(
								'Paste the URL above. Choose "Live" or "Test" to match the API key you connected.',
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
				) }
				<div className="fbwc-actions">
					<ExternalLink href={ consoleWebhooks }>
						{ __(
							'Open webhooks in Fleetbase',
							'fleetbase-for-woocommerce'
						) }
					</ExternalLink>
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
