const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		admin: path.resolve( __dirname, 'assets/src/admin/index.js' ),
		'checkout-frontend': path.resolve( __dirname, 'assets/src/checkout/frontend.js' ),
		'checkout-editor': path.resolve( __dirname, 'assets/src/checkout/editor.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'assets/build' ),
	},
};
