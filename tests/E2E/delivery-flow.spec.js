// @ts-check
const { test, expect } = require( '@playwright/test' );
const { addProductToCart, fillBlockAddress, orderIdFromUrl, waitForSync } = require( './helpers' );

test.describe.configure( { mode: 'serial' } );

let orderId = 0;

test( 'block checkout quotes a live rate, takes a delivery window and places a COD order', async ( { page } ) => {
	await addProductToCart( page, 15 );
	await fillBlockAddress( page );

	// Both seeded rates appear with their delivery promise.
	await expect( page.getByText( 'Same day, 2-4 hours' ).first() ).toBeVisible();

	// Scheduling control: choose a day and window, which refreshes quotes.
	await page.getByText( 'Choose a day and time' ).click();
	const day = page.locator( '.fleetbase-wc-delivery-time__pickers select' ).first();
	await expect( day ).toBeVisible();
	const windowSelect = page.locator( '.fleetbase-wc-delivery-time__pickers select' ).nth( 1 );
	const firstWindow = await windowSelect.locator( 'option' ).first().getAttribute( 'value' );
	expect( firstWindow ).toMatch( /^\d{4}-\d{2}-\d{2}\|\d{2}:\d{2}-\d{2}:\d{2}$/ );

	await page.getByText( 'Cash on delivery', { exact: true } ).click();
	await page.getByRole( 'button', { name: 'Place Order' } ).click();
	await page.waitForURL( /order-received/, { timeout: 60_000 } );

	orderId = orderIdFromUrl( page.url() );
	expect( orderId ).toBeGreaterThan( 0 );
	await expect( page.getByText( 'Delivery time:' ) ).toBeVisible();
	await expect( page.getByText( 'via Standard Delivery' ).or( page.getByText( 'via Eco Delivery' ) ).first() ).toBeVisible();
} );

test( 'the order is sent to Fleetbase and gets a tracking number', async ( { page } ) => {
	test.skip( ! orderId, 'depends on the checkout test' );
	const row = await waitForSync( page, orderId );
	expect( row.fleetbase_id ).toMatch( /^order_/ );
	expect( row.tracking_number ).not.toEqual( '' );
	expect( row.window ).not.toEqual( '' );
} );

test( 'a dispatcher assigns a driver and dispatches from the Deliveries page', async ( { page } ) => {
	test.skip( ! orderId, 'depends on the checkout test' );
	await page.goto( '/wp-admin/admin.php?page=fleetbase-deliveries' );
	const row = page.locator( 'tr', { hasText: `#${ orderId }` } );
	await expect( row ).toBeVisible( { timeout: 60_000 } );

	const select = row.locator( 'select' );
	await select.selectOption( { index: 1 } );
	await row.getByRole( 'button', { name: 'Assign' } ).click();
	await expect( row.getByRole( 'button', { name: 'Dispatch' } ) ).toBeEnabled( { timeout: 60_000 } );
	await row.getByRole( 'button', { name: 'Dispatch' } ).click();
	await expect( row.getByText( 'Dispatched' ) ).toBeVisible( { timeout: 60_000 } );
	await expect( row.getByText( 'Out for delivery' ) ).toBeVisible();
} );

test( 'the customer sees tracking on the order page', async ( { page } ) => {
	test.skip( ! orderId, 'depends on the checkout test' );
	await page.goto( `/my-account/view-order/${ orderId }/` );
	await expect( page.getByText( 'Tracking number' ) ).toBeVisible();
	await expect( page.getByText( 'Driver on the way' ).or( page.getByText( 'Dispatched' ) ).first() ).toBeVisible();
} );
