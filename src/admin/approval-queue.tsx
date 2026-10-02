import { createRoot } from '@wordpress/element';

const el = document.getElementById( 'cosell-hive-root' );
if ( el && el.dataset.page === 'cosell-hive-review' ) {
	const root = createRoot( el );
	root.render( <p>Approval queue — Phase 2.</p> );
}
