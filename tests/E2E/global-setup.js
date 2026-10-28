// @ts-check
const fs = require( 'fs' );
const path = require( 'path' );
const { chromium } = require( '@playwright/test' );

/**
 * FBWC_DEV_USER / FBWC_DEV_PASS from the environment, else from .sandbox.env, which
 * scripts/seed-store.sh writes with a generated password.
 *
 * @return {{user: string, pass: string}} The sandbox admin's credentials.
 */
function readSandboxEnv() {
	const values = {};
	const file = path.join( __dirname, '..', '..', '.sandbox.env' );
	if ( fs.existsSync( file ) ) {
		for ( const line of fs.readFileSync( file, 'utf8' ).split( '\n' ) ) {
			const match = line.match( /^\s*([A-Z_]+)=(.*)$/ );
			if ( match ) {
				values[ match[ 1 ] ] = match[ 2 ].trim();
			}
		}
	}
	return {
		user:
			process.env.FBWC_DEV_USER || values.FBWC_DEV_USER || 'fbwc-admin',
		pass: process.env.FBWC_DEV_PASS || values.FBWC_DEV_PASS || '',
	};
}

/**
 * Signs in once as the sandbox admin and stores the session for every test.
 */
module.exports = async ( config ) => {
	const baseURL = config.projects[ 0 ].use.baseURL;
	const { user, pass } = readSandboxEnv();
	if ( ! pass ) {
		throw new Error(
			'No sandbox admin password. Run scripts/seed-store.sh (it writes .sandbox.env) or set FBWC_DEV_PASS.'
		);
	}
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
