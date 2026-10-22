// @ts-check
const { test, expect } = require( '@playwright/test' );
const { addProductToCart, orderIdFromUrl, SEATTLE } = require( './helpers' );

test( 'classic checkout shows rates with descriptions, a delivery-time select, and places an order', async ( { page } ) => {
	await addProductToCart( page, 16 );
	await page.goto( '/classic-checkout/' );
	await page.fill( '#billing_first_name', SEATTLE.first );
	await page.fill( '#billing_last_name', SEATTLE.last );
	await page.fill( '#billing_address_1', SEATTLE.address );
	await page.fill( '#billing_city', SEATTLE.city );
	await page.fill( '#billing_postcode', SEATTLE.postcode );
	await page.locator( '#billing_postcode' ).blur();

	await expect( page.locator( '#shipping_method' ).getByText( 'Standard Delivery' ) ).toBeVisible( { timeout: 60_000 } );
	await expect( page.locator( '.fleetbase-wc-rate-description' ).first() ).toBeVisible();

	const select = page.locator( '#fleetbase_delivery' );
	await expect( select ).toBeVisible();
	const optionValue = await select.locator( 'option' ).nth( 1 ).getAttribute( 'value' );
	await select.selectOption( optionValue || 'asap' );
	await page.waitForTimeout( 3000 );

	await page.locator( '#payment_method_cod' ).check();
	await page.locator( '#place_order' ).click();
	await page.waitForURL( /order-received/, { timeout: 60_000 } );
	expect( orderIdFromUrl( page.url() ) ).toBeGreaterThan( 0 );
	await expect( page.getByText( 'Delivery time:' ) ).toBeVisible();
} );
