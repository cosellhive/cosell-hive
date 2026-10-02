const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

const entries = {
	'cosell-hive-dashboard': './src/admin/store-dashboard.tsx',
	'cosell-hive-review': './src/admin/approval-queue.tsx',
	'cosell-hive-setup': './src/admin/onboarding.tsx',
	'cosell-hive-feed': './src/affiliate/feed.tsx',
	'cosell-hive-wallet': './src/affiliate/wallet.tsx',
	'cosell-hive-links': './src/affiliate/links.tsx',
	'cosell-hive-product-card': './src/blocks/product-card/index.tsx',
};

module.exports = {
	...defaultConfig,
	entry: entries,
};
