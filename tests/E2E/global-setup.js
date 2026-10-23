// @ts-check
const fs = require( 'fs' );
const path = require( 'path' );
const { chromium } = require( '@playwright/test' );

/**
 * Signs in once as the sandbox admin and stores the session for every test.
 */
module.exports = async ( config ) => {
	const baseURL = config.projects[ 0 ].use.baseURL;
	const user = process.env.FBWC_DEV_USER || 'fbwc-admin';
	const pass = process.env.FBWC_DEV_PASS || 'fbwc-sandbox-2026';
	const dir = path.join( __dirname, '.auth' );
	fs.mkdirSync( dir, { recursive: true } );

	const browser = await chromium.launch();
	const page = await browser.newPage();
	await page.goto( `${ baseURL }/wp-login.php` );
	await page.fill( '#user_login', user );
	await page.fill( '#user_pass', pass );
	await page.click( '#wp-submit' );
	await page.waitForURL( /wp-admin|wp-login\.php\?.*confirm_admin_email/ );
	await page.context().storageState( { path: path.join( dir, 'admin.json' ) } );
	await browser.close();
};
