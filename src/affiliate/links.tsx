import { createRoot } from '@wordpress/element';

const el = document.getElementById( 'cosell-hive-root' );
if ( el && el.dataset.page === 'cosell-hive-links' ) {
	const root = createRoot( el );
	root.render( <p>Tracked links — Phase 3.</p> );
}
