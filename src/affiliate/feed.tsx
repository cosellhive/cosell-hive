import { createRoot, useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import type { CSSProperties } from 'react';

type FeedItem = {
	id: number;
	product_id: number;
	ref: string;
	title: string;
	image: string;
	store: string;
	price_display: string;
	commission: { type: string; value: string; label: string };
};

function MarketplaceFeed() {
	const [ items, setItems ] = useState< FeedItem[] >( [] );
	const [ search, setSearch ] = useState< string >( '' );
	const [ min, setMin ] = useState< string >( '' );
	const [ recommended, setRecommended ] = useState< boolean >( false );
	const [ niche, setNiche ] = useState< string >(
		() => window.localStorage.getItem( 'cosell_hive_niche' ) ?? ''
	);
	const [ source, setSource ] = useState< string >( '' );
	const [ error, setError ] = useState< string | null >( null );
	const [ loaded, setLoaded ] = useState< boolean >( false );

	useEffect( () => {
		let path: string;
		let options: object | undefined;
		if ( recommended && niche ) {
			path = '/cosell-hive/v1/recommendations';
			options = { method: 'POST', data: { niche } };
		} else if ( search ) {
			const params = new URLSearchParams( { q: search } );
			path = `/cosell-hive/v1/nl-search?${ params.toString() }`;
		} else {
			const params = new URLSearchParams();
			if ( min ) {
				params.set( 'min_commission', min );
			}
			const query = params.toString();
			path = `/cosell-hive/v1/feed${ query ? `?${ query }` : '' }`;
		}
		apiFetch< { items: FeedItem[]; source?: string } >( { path, ...options } )
			.then( ( res ) => {
				let list = res.items;
				if ( search && min ) {
					list = list.filter(
						( i ) =>
							i.commission.value !== '' &&
							parseFloat( i.commission.value ) >= parseFloat( min )
					);
				}
				setItems( list );
				setSource( res.source ?? '' );
				setLoaded( true );
			} )
			.catch( () => setError( 'Could not load the marketplace.' ) );
	}, [ search, min, recommended, niche ] );

	const saveNiche = ( value: string ) => {
		setNiche( value );
		window.localStorage.setItem( 'cosell_hive_niche', value );
	};

	const addToLinks = ( id: number ) => {
		window.location.href = `admin.php?page=cosell-hive-links&listing=${ id }`;
	};

	const cardStyle: CSSProperties = {
		background: '#fff',
		borderRadius: '12px',
		border: '1px solid #e4e2da',
		padding: '16px',
		minWidth: '45%',
		flex: 1,
	};

	if ( error ) {
		return <p>{ error }</p>;
	}

	return (
		<div>
			<div style={ { display: 'flex', gap: '8px', marginBottom: '12px' } }>
				<input
					type="search"
					placeholder="Search products or describe intent"
					value={ search }
					onChange={ ( e ) => setSearch( e.target.value ) }
					style={ { flex: 1 } }
					className="regular-text"
				/>
				<select
					value={ min }
					onChange={ ( e ) => setMin( e.target.value ) }
					aria-label="Minimum commission"
				>
					<option value="">Any commission</option>
					<option value="10">10%+ / $10+</option>
					<option value="15">15%+ / $15+</option>
					<option value="20">20%+ / $20+</option>
				</select>
			</div>
			<div
				style={ {
					display: 'flex',
					gap: '8px',
					alignItems: 'center',
					marginBottom: '16px',
				} }
			>
				<label style={ { fontSize: '13px' } }>
					<input
						type="checkbox"
						checked={ recommended }
						onChange={ ( e ) => setRecommended( e.target.checked ) }
					/>{ ' ' }
					Recommended for you
				</label>
				{ recommended && (
					<input
						type="text"
						placeholder="Describe your audience niche"
						value={ niche }
						onChange={ ( e ) => saveNiche( e.target.value ) }
						style={ { flex: 1 } }
						className="regular-text"
						aria-label="Audience niche"
					/>
				) }
				{ source === 'hub' && (
					<span style={ { fontSize: '12px', color: '#185FA5' } }>
						AI-ranked
					</span>
				) }
			</div>
			{ loaded && items.length === 0 && (
				<p>
					{ recommended && ! niche
						? 'Describe your niche to get recommendations.'
						: 'No live listings match. Approved products appear here for promotion.' }
				</p>
			) }
			<div style={ { display: 'flex', gap: '12px', flexWrap: 'wrap' } }>
				{ items.map( ( item ) => (
					<div key={ item.id } style={ cardStyle }>
						{ item.image ? (
							<img
								src={ item.image }
								alt=""
								style={ {
									width: '100%',
									height: '90px',
									objectFit: 'cover',
									borderRadius: '8px',
									marginBottom: '10px',
								} }
							/>
						) : (
							<div
								style={ {
									width: '100%',
									height: '90px',
									borderRadius: '8px',
									background: '#f5f5f2',
									display: 'flex',
									alignItems: 'center',
									justifyContent: 'center',
									color: '#888780',
									fontSize: '12px',
									marginBottom: '10px',
								} }
							>
								IMG
							</div>
						) }
						<p style={ { fontSize: '14px', fontWeight: 600, margin: '0 0 2px' } }>
							{ item.title }
						</p>
						<p style={ { fontSize: '12px', color: '#5f5e5a', margin: '0 0 10px' } }>
							{ item.store } · { item.price_display }
						</p>
						<div style={ { display: 'flex', justifyContent: 'space-between', alignItems: 'center' } }>
							<span
								style={ {
									fontSize: '12px',
									padding: '3px 9px',
									borderRadius: '8px',
									background: '#EAF3DE',
									color: '#3b6d11',
								} }
							>
								{ item.commission.label }
							</span>
							<button className="button" onClick={ () => addToLinks( item.id ) } aria-label={ `Promote ${ item.title }` }>
								+
							</button>
						</div>
					</div>
				) ) }
			</div>
		</div>
	);
}

const el = document.getElementById( 'cosell-hive-root' );
if ( el && el.dataset.page === 'cosell-hive-feed' ) {
	createRoot( el ).render( <MarketplaceFeed /> );
}
