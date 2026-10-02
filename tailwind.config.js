/** @type {import('tailwindcss').Config} */
module.exports = {
	content: ['./src/**/*.{tsx,ts}', './includes/**/*.php'],
	corePlugins: {
		preflight: false
	},
	important: '#cosell-hive-root',
	theme: {
		extend: {}
	},
	plugins: []
};
