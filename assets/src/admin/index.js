import { createRoot } from '@wordpress/element';
import Dashboard from './pages/Dashboard';
import Setup from './pages/Setup';
import Settings from './pages/Settings';
import './style.scss';

const pages = { dashboard: Dashboard, setup: Setup, settings: Settings };

document.addEventListener( 'DOMContentLoaded', () => {
	const container = document.getElementById( 'fleetbase-wc-admin' );
	if ( ! container ) {
		return;
	}
	const Page = pages[ container.dataset.page ] || Dashboard;
	createRoot( container ).render( <Page /> );
} );
