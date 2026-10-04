// Dev-only: extract __() strings from src/**/*.tsx into the POT file.
// wp-cli's make-pot does not parse TypeScript/TSX, so this fills the gap.
// Usage: node bin/extract-ts-strings.mjs >> languages/cosell-hive.pot
// (run after `wp i18n make-pot`; the file is excluded from the release zip)
import { readFileSync } from 'node:fs';
import { execSync } from 'node:child_process';

const files = execSync( 'git ls-files "src/**/*.tsx" "src/**/*.ts"', {
	cwd: new URL( '..', import.meta.url ),
	encoding: 'utf8',
} )
	.split( '\n' )
	.filter( Boolean );

// Skip strings already covered by the PHP/block.json extraction so the
// merged POT has no duplicate msgids (msgfmt treats those as fatal).
const potPath = new URL( '../languages/cosell-hive.pot', import.meta.url );
let existing = new Set();
try {
	const pot = readFileSync( potPath, 'utf8' );
	const re = /^msgid "((?:[^"\\]|\\.)*)"$/gm;
	let m;
	while ( ( m = re.exec( pot ) ) ) {
		existing.add( m[ 1 ] );
	}
} catch {}

const seen = new Set();

function potEscape( s ) {
	return s.replace( /\\/g, '\\\\' ).replace( /"/g, '\\"' ).replace( /\n/g, '\\n' );
}

for ( const file of files ) {
	const src = readFileSync( new URL( `../${ file }`, import.meta.url ), 'utf8' );
	const lines = src.split( '\n' );
	const re = /__\(\s*(['"])((?:[^\\]|\\.)*?)\1\s*,\s*['"]cosell-hive['"]\s*\)/g;
	let m;
	while ( ( m = re.exec( src ) ) ) {
		const raw = m[ 2 ].replace( /\\'/g, "'" ).replace( /\\"/g, '"' ).replace( /\\\\/g, '\\' );
		const id = potEscape( raw );
		if ( seen.has( id ) || existing.has( id ) ) {
			continue;
		}
		seen.add( id );
		const line = src.slice( 0, m.index ).split( '\n' ).length;
		console.log( `#:` + ` ${ file }:${ line }` );
		console.log( `msgid "${ id }"` );
		console.log( 'msgstr ""\n' );
	}
}
