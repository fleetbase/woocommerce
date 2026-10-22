// @ts-check
const { test, expect } = require( '@playwright/test' );
const AxeBuilder = require( '@axe-core/playwright' ).default;
const { addProductToCart, fillBlockAddress } = require( './helpers' );

const SERIOUS = [ 'serious', 'critical' ];

async function scan( page, include ) {
	const builder = new AxeBuilder( { page } ).withTags( [ 'wcag2a', 'wcag2aa' ] );
	if ( include ) {
		builder.include( include );
	}
	const results = await builder.analyze();
	return results.violations.filter( ( v ) => SERIOUS.includes( v.impact || '' ) );
}

for ( const slug of [ 'fleetbase', 'fleetbase-deliveries', 'fleetbase-drivers', 'fleetbase-vehicles', 'fleetbase-service-rates', 'fleetbase-settings' ] ) {
	test( `admin page ${ slug } has no serious accessibility violations`, async ( { page } ) => {
		await page.goto( `/wp-admin/admin.php?page=${ slug }` );
		await page.waitForSelector( '#fleetbase-wc-admin .fbwc-page' );
		await page.waitForTimeout( 2000 );
		const violations = await scan( page, '#fleetbase-wc-admin' );
		expect( violations, JSON.stringify( violations, null, 2 ) ).toEqual( [] );
	} );
}

test( 'the checkout delivery-time control is labelled and keyboard operable', async ( { page } ) => {
	await addProductToCart( page, 15 );
	await fillBlockAddress( page );
	const control = page.locator( '.fleetbase-wc-delivery-time' );
	await expect( control ).toBeVisible();
	const violations = await scan( page, '.fleetbase-wc-delivery-time' );
	expect( violations, JSON.stringify( violations, null, 2 ) ).toEqual( [] );

	await control.getByLabel( 'As soon as possible' ).focus();
	await page.keyboard.press( 'ArrowDown' );
	await expect( control.getByLabel( 'Choose a day and time' ) ).toBeChecked();
	await expect( control.locator( 'select' ).first() ).toBeVisible();
} );
