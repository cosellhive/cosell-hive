import { createRoot, useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import type { CSSProperties } from 'react';

type Flag = {
	type: string;
	message: string;
};

type QueueItem = {
	id: number;
	product_id: number;
	title: string;
	image: string;
	store: string;
	commission: { type: string; value: string; label: string };
	status: string;
	submitted: string;
	flags: Flag[];
};

type QueueResponse = {
	counts: { pending: number; live: number; rejected: number; paused: number };
	items: QueueItem[];
};

const TABS = [
	{ key: 'pending', label: __( 'Pending review', 'cosell-hive' ) },
	{ key: 'live', label: __( 'Approved', 'cosell-hive' ) },
	{ key: 'rejected', label: __( 'Rejected', 'cosell-hive' ) },
];

function timeAgo( iso: string ): string {
	const then = new Date( iso.replace( ' ', 'T' ) ).getTime();
	const diff = Date.now() - then;
	const hours = Math.floor( diff / 3600000 );
	if ( hours < 1 ) {
		return __( 'just now', 'cosell-hive' );
	}
	if ( hours < 24 ) {
		return sprintf(
			/* translators: %s: number of hours */
			__( '%sh ago', 'cosell-hive' ),
			hours
		);
	}
	return sprintf(
		/* translators: %s: number of days */
		__( '%sd ago', 'cosell-hive' ),
		Math.floor( hours / 24 )
	);
}

function ApprovalQueue() {
	const [ tab, setTab ] = useState< string >( 'pending' );
	const [ data, setData ] = useState< QueueResponse | null >( null );
	const [ error, setError ] = useState< string | null >( null );
	const [ busy, setBusy ] = useState< number | null >( null );

	const load = useCallback( () => {
		apiFetch< QueueResponse >( {
			path: `/cosell-hive/v1/listings?status=${ tab }`,
		} )
			.then( setData )
			.catch( () => setError( __( 'Could not load the queue.', 'cosell-hive' ) ) );
	}, [ tab ] );

	useEffect( () => {
		load();
	}, [ load ] );

	const decide = ( id: number, action: 'approve' | 'reject' ) => {
		setBusy( id );
		apiFetch( {
			path: `/cosell-hive/v1/listings/${ id }/${ action }`,
			method: 'POST',
		} )
			.then( load )
			.catch( () => setError( __( 'Decision failed. Try again.', 'cosell-hive' ) ) )
			.finally( () => setBusy( null ) );
	};

	if ( error ) {
		return <p>{ error }</p>;
	}

	if ( ! data ) {
		return <p>{ __( 'Loading CoSellHive…', 'cosell-hive' ) }</p>;
	}

	const tabStyle = ( active: boolean ): CSSProperties => ( {
		fontSize: '12px',
		padding: '3px 9px',
		borderRadius: '8px',
		display: 'inline-block',
		cursor: 'pointer',
		background: active ? '#E6F1FB' : '#f5f5f2',
		color: active ? '#185FA5' : '#5f5e5a',
		marginRight: '8px',
	} );

	return (
		<div>
			<div style={ { marginBottom: '16px' } }>
				{ TABS.map( ( t ) => (
					<span
						key={ t.key }
						style={ tabStyle( tab === t.key ) }
						onClick={ () => setTab( t.key ) }
						onKeyDown={ ( e ) => {
							if ( e.key === 'Enter' ) {
								setTab( t.key );
							}
						} }
						role="button"
						tabIndex={ 0 }
					>
						{ t.key === 'pending'
							? sprintf(
									/* translators: %s: number of pending items */
									__( 'Pending review (%s)', 'cosell-hive' ),
									data.counts.pending
								)
							: t.label }
					</span>
				) ) }
			</div>
			<div
				style={ { display: 'flex', flexDirection: 'column', gap: '10px' } }
			>
				{ data.items.length === 0 && (
					<p>{ __( 'Nothing here. New submissions will appear for review.', 'cosell-hive' ) }</p>
				) }
				{ data.items.map( ( item ) => (
					<div
						key={ item.id }
						style={ {
							background: '#fff',
							borderRadius: '12px',
							border: `1px solid ${
								item.flags.length > 0 ? '#F09595' : '#e4e2da'
							}`,
							padding: '16px',
						} }
					>
						<div
							style={ {
								display: 'flex',
								gap: '12px',
								alignItems: 'center',
							} }
						>
							{ item.image ? (
								<img
									src={ item.image }
									alt=""
									width={ 44 }
									height={ 44 }
									style={ { borderRadius: '8px' } }
								/>
							) : (
								<div
									style={ {
										width: '44px',
										height: '44px',
										borderRadius: '8px',
										background: '#f5f5f2',
										display: 'flex',
										alignItems: 'center',
										justifyContent: 'center',
										color: '#888780',
										fontSize: '11px',
										flexShrink: 0,
									} }
								>
									IMG
								</div>
							) }
							<div style={ { flex: 1 } }>
								<p
									style={ {
										fontSize: '14px',
										fontWeight: 600,
										margin: '0 0 2px',
									} }
								>
									{ item.title }
								</p>
								<p
									style={ {
										fontSize: '12px',
										color: '#5f5e5a',
										margin: 0,
									} }
								>
									{ item.store } · { item.commission.label } ·{ ' ' }
									{ sprintf(
										/* translators: %s: relative time */
										__( 'Submitted %s', 'cosell-hive' ),
										timeAgo( item.submitted )
									) }
								</p>
							</div>
							{ tab === 'pending' && (
								<>
									<button
										className="button"
										disabled={ busy === item.id }
										onClick={ () =>
											decide( item.id, 'reject' )
										}
									>
										{ __( 'Reject', 'cosell-hive' ) }
									</button>
									<button
										className="button button-primary"
										disabled={ busy === item.id }
										onClick={ () =>
											decide( item.id, 'approve' )
										}
									>
										{ __( 'Approve', 'cosell-hive' ) }
									</button>
								</>
							) }
						</div>
						{ item.flags.map( ( flag ) => (
							<div
								key={ flag.type }
								style={ {
									background: '#FCEBEB',
									borderRadius: '8px',
									padding: '8px 10px',
									marginTop: '10px',
								} }
							>
								<p
									style={ {
										fontSize: '12px',
										color: '#A32D2D',
										margin: 0,
									} }
								>
									{ flag.message }
								</p>
							</div>
						) ) }
					</div>
				) ) }
			</div>
		</div>
	);
}

const el = document.getElementById( 'cosell-hive-root' );
if ( el && el.dataset.page === 'cosell-hive-review' ) {
	createRoot( el ).render( <ApprovalQueue /> );
}
