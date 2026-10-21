import { __ } from '@wordpress/i18n';
import { runtimeData } from '../api';

export default function Header( {
	title,
	connection: liveConnection,
	children,
} ) {
	const { pages } = runtimeData;
	const connection = liveConnection || runtimeData.connection;
	return (
		<div className="fbwc-header">
			<div className="fbwc-header__brand">
				<span className="fbwc-logo" aria-hidden="true">
					F
				</span>
				<div>
					<h1>{ title }</h1>
					<p className="fbwc-muted">
						{ connection?.connected
							? connection.organization?.name ||
							  __( 'Connected', 'fleetbase-for-woocommerce' )
							: __(
									'Not connected',
									'fleetbase-for-woocommerce'
							  ) }
						{ connection?.connected &&
							connection.mode === 'test' && (
								<span className="fbwc-badge fbwc-badge--test">
									{ __(
										'Test mode',
										'fleetbase-for-woocommerce'
									) }
								</span>
							) }
					</p>
				</div>
			</div>
			<nav className="fbwc-header__nav">
				<a href={ pages.dashboard }>
					{ __( 'Dashboard', 'fleetbase-for-woocommerce' ) }
				</a>
				<a href={ pages.settings }>
					{ __( 'Settings', 'fleetbase-for-woocommerce' ) }
				</a>
				<a href={ pages.setup }>
					{ __( 'Setup', 'fleetbase-for-woocommerce' ) }
				</a>
				{ children }
			</nav>
		</div>
	);
}
