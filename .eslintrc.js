module.exports = {
	root: true,
	extends: [ 'plugin:@wordpress/eslint-plugin/recommended' ],
	globals: { fleetbaseWc: 'readonly' },
	rules: {
		'@wordpress/i18n-text-domain': [ 'error', { allowedTextDomain: 'fleetbase-for-woocommerce' } ],
	},
};
