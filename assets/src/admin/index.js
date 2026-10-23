import { createRoot } from '@wordpress/element';
import Dashboard from './pages/Dashboard';
import Setup from './pages/Setup';
import Settings from './pages/Settings';
import Deliveries from './pages/Deliveries';
import Drivers from './pages/Drivers';
import Vehicles from './pages/Vehicles';
import ServiceRates from './pages/ServiceRates';
import './style.scss';

const pages = {
	dashboard: Dashboard,
	setup: Setup,
	settings: Settings,
	deliveries: Deliveries,
	drivers: Drivers,
	vehicles: Vehicles,
	rates: ServiceRates,
};

document.addEventListener( 'DOMContentLoaded', () => {
	const container = document.getElementById( 'fleetbase-wc-admin' );
	if ( ! container ) {
		return;
	}
	const Page = pages[ container.dataset.page ] || Dashboard;
	createRoot( container ).render( <Page /> );
} );
