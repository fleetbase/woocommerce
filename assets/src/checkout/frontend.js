import metadata from './block.json';
import DeliveryTime from './DeliveryTime';
import { blocksCheckout } from './wc';
import './style.scss';

if ( blocksCheckout.registerCheckoutBlock ) {
	blocksCheckout.registerCheckoutBlock( {
		metadata,
		component: DeliveryTime,
	} );
}
