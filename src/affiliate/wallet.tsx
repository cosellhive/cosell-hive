import { createRoot } from '@wordpress/element';

const el = document.getElementById( 'cosell-hive-root' );
if ( el && el.dataset.page === 'cosell-hive-wallet' ) {
	const root = createRoot( el );
	root.render( <p>Wallet — Phase 5.</p> );
}
