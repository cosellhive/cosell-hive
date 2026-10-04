import { createRoot, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import type { CSSProperties } from 'react';

type FeedItem = {
	id: number;
	product_id: number;
	ref: string;
	title: string;
	store: string;
	commission: { type: string; value: string; label: string };
};

type LinkResponse = {
	token: string;
	url: string;
	ref: string;
	affiliate_id: number;
};

const STYLES = [ 'card', 'text', 'banner' ] as const;

function copyText( text: string, done: () => void ) {
	if ( navigator.clipboard ) {
		navigator.clipboard.writeText( text ).then( done ).catch( () => {} );
	}
}

function MyLinks() {
	const [ items, setItems ] = useState< FeedItem[] >( [] );
	const [ selected, setSelected ] = useState< number | null >( null );
	const [ style, setStyle ] = useState< string >( 'card' );
	const [ link, setLink ] = useState< LinkResponse | null >( null );
	const [ copy, setCopy ] = useState< { blurb: string; caption: string } | null >( null );
	const [ copied, setCopied ] = useState< string | null >( null );
	const [ error, setError ] = useState< string | null >( null );

	useEffect( () => {
		const params = new URLSearchParams( window.location.search );
		const preselect = params.get( 'listing' );
		apiFetch< { items: FeedItem[] } >( { path: '/cosell-hive/v1/feed' } )
			.then( ( res ) => {
				setItems( res.items );
				if ( preselect ) {
					const id = parseInt( preselect, 10 );
					if ( res.items.some( ( i ) => i.id === id ) ) {
						setSelected( id );
					}
				} else if ( res.items.length > 0 && res.items[ 0 ] ) {
					setSelected( res.items[ 0 ].id );
				}
			} )
			.catch( () => setError( __( 'Could not load listings.', 'cosell-hive' ) ) );
	}, [] );

	useEffect( () => {
		if ( selected === null ) {
			return;
		}
		setLink( null );
		setCopy( null );
		apiFetch< LinkResponse >( {
			path: '/cosell-hive/v1/links',
			method: 'POST',
			data: { listing_id: selected },
		} )
			.then( setLink )
			.catch( () => setError( __( 'Could not mint a tracked link.', 'cosell-hive' ) ) );
	}, [ selected ] );

	const current = items.find( ( i ) => i.id === selected ) ?? null;
	const shortcode =
		current && link
			? `[cosell_product id="${ link.ref }" affiliate="${ link.affiliate_id }" style="${ style }"]`
			: '';

	const tabStyle = ( active: boolean ): CSSProperties => ( {
		flex: 1,
		textAlign: 'center',
		border: `1px solid ${ active ? '#b5d4f4' : '#cfcdc4' }`,
		background: active ? '#E6F1FB' : '#fff',
		color: active ? '#185FA5' : '#1a1a18',
		borderRadius: '8px',
		padding: '8px 14px',
		fontSize: '13px',
		cursor: 'pointer',
	} );

	if ( error ) {
		return <p>{ error }</p>;
	}

	if ( items.length === 0 ) {
		return <p>{ __( 'No live listings to promote yet.', 'cosell-hive' ) }</p>;
	}

	return (
		<div>
			<select
				value={ selected ?? '' }
				onChange={ ( e ) =>
					setSelected( parseInt( e.target.value, 10 ) )
				}
				aria-label={ __( 'Choose a product', 'cosell-hive' ) }
				style={ { marginBottom: '20px', minWidth: '280px' } }
			>
				{ items.map( ( item ) => (
					<option key={ item.id } value={ item.id }>
						{ item.title } · { item.commission.label }
					</option>
				) ) }
			</select>

			{ current && (
				<div
					style={ {
						background: '#f5f5f2',
						borderRadius: '8px',
						padding: '12px',
						marginBottom: '20px',
					} }
				>
					<p
						style={ {
							fontSize: '14px',
							fontWeight: 600,
							margin: '0 0 2px',
						} }
					>
						{ current.title }
					</p>
					<p
						style={ {
							fontSize: '12px',
							color: '#5f5e5a',
							margin: 0,
						} }
					>
						{ current.store } · { current.commission.label }
					</p>
				</div>
			) }

			<p
				style={ { fontSize: '13px', color: '#5f5e5a', margin: '0 0 6px' } }
			>
				{ __( 'Tracked link', 'cosell-hive' ) }
			</p>
			<div style={ { display: 'flex', gap: '8px', marginBottom: '16px' } }>
				<input
					className="regular-text"
					style={ { flex: 1 } }
					readOnly
					value={ link ? link.url : __( 'Minting…', 'cosell-hive' ) }
					aria-label={ __( 'Tracked link', 'cosell-hive' ) }
				/>
				<button
					className="button"
					disabled={ ! link }
					onClick={ () => {
						if ( link ) {
							copyText( link.url, () => setCopied( 'link' ) );
						}
					} }
				>
					{ copied === 'link' ? __( 'Copied', 'cosell-hive' ) : __( 'Copy', 'cosell-hive' ) }
				</button>
			</div>

			<p
				style={ { fontSize: '13px', color: '#5f5e5a', margin: '0 0 6px' } }
			>
				{ __( 'Embed on my site', 'cosell-hive' ) }
			</p>
			<div style={ { display: 'flex', gap: '8px', marginBottom: '8px' } }>
				{ STYLES.map( ( s ) => (
					<span
						key={ s }
						style={ tabStyle( style === s ) }
						onClick={ () => setStyle( s ) }
						onKeyDown={ ( e ) => {
							if ( e.key === 'Enter' ) {
								setStyle( s );
							}
						} }
						role="button"
						tabIndex={ 0 }
					>
						{ s === 'card'
							? __( 'Product card', 'cosell-hive' )
							: s === 'text'
								? __( 'Text link', 'cosell-hive' )
								: __( 'Banner', 'cosell-hive' ) }
					</span>
				) ) }
			</div>
			<div
				style={ {
					background: '#f5f5f2',
					borderRadius: '8px',
					padding: '16px',
					fontFamily: 'monospace',
					fontSize: '12px',
					color: '#5f5e5a',
					marginBottom: '8px',
					display: 'flex',
					justifyContent: 'space-between',
					alignItems: 'center',
				} }
			>
				<span>{ shortcode }</span>
				<button
					className="button"
					disabled={ ! shortcode }
					onClick={ () =>
						copyText( shortcode, () => setCopied( 'embed' ) )
					}
				>
					{ copied === 'embed' ? __( 'Copied', 'cosell-hive' ) : __( 'Copy', 'cosell-hive' ) }
				</button>
			</div>
			<p style={ { fontSize: '12px', color: '#888780' } }>
				{ __( 'Paste into any post or page — or use the CoSellHive Product block with the same listing ID. Your affiliate ID is baked in, so cached pages still attribute correctly.', 'cosell-hive' ) }
			</p>

			<div
				style={ {
					display: 'flex',
					justifyContent: 'space-between',
					alignItems: 'center',
					background: '#f5f5f2',
					borderRadius: '8px',
					padding: '16px',
					marginTop: '20px',
				} }
			>
				<div>
					<p style={ { fontSize: '13px', margin: 0 } }>
						{ __( 'Generate marketing copy', 'cosell-hive' ) }
					</p>
					<p style={ { fontSize: '12px', color: '#888780', margin: 0 } }>
						{ __( 'AI-written blurb and caption in your site’s tone', 'cosell-hive' ) }
					</p>
				</div>
				<button
					className="button"
					disabled={ selected === null }
					onClick={ () => {
						if ( selected === null ) {
							return;
						}
						apiFetch< { blurb: string; caption: string } >( {
							path: '/cosell-hive/v1/copy',
							method: 'POST',
							data: { listing_id: selected },
						} )
							.then( setCopy )
							.catch( () => setError( __( 'Copy generation failed.', 'cosell-hive' ) ) );
					} }
				>
					{ __( 'Generate', 'cosell-hive' ) }
				</button>
			</div>
			{ copy && (
				<div style={ { marginTop: '12px' } }>
					<div
						style={ {
							background: '#fff',
							border: '1px solid #e4e2da',
							borderRadius: '8px',
							padding: '12px',
							marginBottom: '8px',
						} }
					>
						<p style={ { fontSize: '12px', color: '#5f5e5a', margin: '0 0 4px' } }>
							{ __( 'Blurb', 'cosell-hive' ) }
						</p>
						<p style={ { fontSize: '13px', margin: '0 0 8px' } }>{ copy.blurb }</p>
						<button className="button" onClick={ () => copyText( copy.blurb, () => setCopied( 'blurb' ) ) }>
							{ copied === 'blurb' ? __( 'Copied', 'cosell-hive' ) : __( 'Copy', 'cosell-hive' ) }
						</button>
					</div>
					<div
						style={ {
							background: '#fff',
							border: '1px solid #e4e2da',
							borderRadius: '8px',
							padding: '12px',
						} }
					>
						<p style={ { fontSize: '12px', color: '#5f5e5a', margin: '0 0 4px' } }>
							{ __( 'Social caption', 'cosell-hive' ) }
						</p>
						<p style={ { fontSize: '13px', margin: '0 0 8px' } }>{ copy.caption }</p>
						<button className="button" onClick={ () => copyText( copy.caption, () => setCopied( 'caption' ) ) }>
							{ copied === 'caption' ? __( 'Copied', 'cosell-hive' ) : __( 'Copy', 'cosell-hive' ) }
						</button>
					</div>
				</div>
			)}
		</div>
	);
}

const el = document.getElementById( 'cosell-hive-root' );
if ( el && el.dataset.page === 'cosell-hive-links' ) {
	createRoot( el ).render( <MyLinks /> );
}
