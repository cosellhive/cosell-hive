import { createRoot } from '@wordpress/element';

const el = document.getElementById( 'cosell-hive-root' );
if ( el && el.dataset.page === 'cosell-hive-setup' ) {
	const root = createRoot( el );
	root.render( <p>Onboarding — Phase 5.</p> );
}
