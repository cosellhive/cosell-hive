import { createRoot } from '@wordpress/element';

const el = document.getElementById( 'cosell-hive-root' );
if ( el && el.dataset.page === 'cosell-hive-feed' ) {
	const root = createRoot( el );
	root.render( <p>Marketplace feed — Phase 3.</p> );
}
