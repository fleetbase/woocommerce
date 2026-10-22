// @ts-check
const { defineConfig } = require( '@playwright/test' );

/**
 * End-to-end tests run against the local sandbox described in docs/dev-setup.md.
 * Override with FBWC_BASE_URL, FBWC_DEV_USER and FBWC_DEV_PASS.
 */
module.exports = defineConfig( {
	testDir: './tests/E2E',
	timeout: 180_000,
	expect: { timeout: 20_000 },
	fullyParallel: false,
	workers: 1,
	retries: 0,
	reporter: [ [ 'list' ] ],
	globalSetup: require.resolve( './tests/E2E/global-setup.js' ),
	use: {
		baseURL: process.env.FBWC_BASE_URL || 'http://localhost:8800',
		storageState: 'tests/E2E/.auth/admin.json',
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		viewport: { width: 1280, height: 900 },
	},
	projects: [ { name: 'chromium', use: { browserName: 'chromium' } } ],
} );
