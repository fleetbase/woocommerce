// @ts-check
const { expect } = require( '@playwright/test' );

const SEATTLE = {
	first: 'E2E',
	last: 'Shopper',
	address: '1124 Pike St',
	city: 'Seattle',
	postcode: '98101',
};

async function addProductToCart( page, productId = 15 ) {
	await page.goto( `/?post_type=product&add-to-cart=${ productId }` );
	await page.waitForLoadState( 'networkidle' );
}

/**
 * Fill the block checkout shipping address and wait for Fleetbase rates.
 */
async function fillBlockAddress( page ) {
	await page.goto( '/checkout/' );
	await page.getByLabel( 'First name' ).first().fill( SEATTLE.first );
	await page.getByLabel( 'Last name' ).first().fill( SEATTLE.last );
	await page.getByLabel( 'Address', { exact: true } ).first().fill( SEATTLE.address );
	await page.getByLabel( 'City' ).first().fill( SEATTLE.city );
	await page.getByLabel( 'ZIP Code' ).first().fill( SEATTLE.postcode );
	await expect( page.getByText( 'Standard Delivery' ).first() ).toBeVisible( { timeout: 60_000 } );
}

/**
 * Reads plugin REST data through the browser session (nonce is injected by WordPress).
 */
async function restGet( page, path ) {
	return page.evaluate( async ( p ) => {
		const nonce = window.wpApiSettings ? window.wpApiSettings.nonce : '';
		const res = await fetch( `/wp-json/fleetbase/v1/${ p }`, { headers: { 'X-WP-Nonce': nonce } } );
		return res.json();
	}, path );
}

/**
 * Wait until the order has a Fleetbase id, nudging WP-Cron by visiting admin pages.
 */
async function waitForSync( page, orderId, attempts = 24 ) {
	for ( let i = 0; i < attempts; i++ ) {
		await page.goto( '/wp-admin/admin.php?page=fleetbase-deliveries' );
		const data = await restGet( page, `deliveries?status=all&per_page=50` );
		const row = ( data.deliveries || [] ).find( ( d ) => d.id === orderId );
		if ( row && row.fleetbase_id ) {
			return row;
		}
		await page.waitForTimeout( 5000 );
	}
	throw new Error( `Order ${ orderId } was not synced to Fleetbase in time` );
}

function orderIdFromUrl( url ) {
	const m = url.match( /order-received\/(\d+)/ );
	return m ? Number( m[ 1 ] ) : 0;
}

module.exports = { SEATTLE, addProductToCart, fillBlockAddress, restGet, waitForSync, orderIdFromUrl };
