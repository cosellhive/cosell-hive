import { createRoot, useCallback, useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import type { CSSProperties, FormEvent } from 'react';

type Wallet = {
	balances: { pending: number; payable: number; available: number; paid: number };
	methods: string[];
	minimum: number;
};

type Payout = {
	id: number;
	affiliate?: string;
	amount: number;
	method: string;
	details_mask: string;
	status: string;
	note: string;
	requested_at: string;
};

const STATUS_STYLE: Record< string, CSSProperties > = {
	paid: { background: '#EAF3DE', color: '#3b6d11' },
	approved: { background: '#E6F1FB', color: '#185FA5' },
	requested: { background: '#FAEEDA', color: '#854F0B' },
};

function money( n: number ): string {
	return `$${ n.toFixed( 2 ) }`;
}

function AffiliateWallet( { wallet }: { wallet: Wallet } ) {
	const [ items, setItems ] = useState< Payout[] >( [] );
	const [ amount, setAmount ] = useState< string >( '' );
	const [ method, setMethod ] = useState< string >( wallet.methods[ 0 ] ?? '' );
	const [ details, setDetails ] = useState< string >( '' );
	const [ error, setError ] = useState< string | null >( null );
	const [ done, setDone ] = useState< string | null >( null );

	const load = useCallback( () => {
		apiFetch< { items: Payout[] } >( {
			path: '/cosell-hive/v1/payouts?scope=mine',
		} )
			.then( ( res ) => setItems( res.items ) )
			.catch( () => setError( 'Could not load payout history.' ) );
	}, [] );

	useEffect( () => {
		load();
	}, [ load ] );

	const submit = ( e: FormEvent ) => {
		e.preventDefault();
		setError( null );
		setDone( null );
		apiFetch( {
			path: '/cosell-hive/v1/payouts',
			method: 'POST',
			data: {
				amount_minor: Math.round( parseFloat( amount || '0' ) * 100 ),
				method,
				details,
			},
		} )
			.then( () => {
				setDone( 'Payout requested. An admin will review it.' );
				setAmount( '' );
				setDetails( '' );
				load();
			} )
			.catch( () => setError( 'Request failed. Check the amount and details.' ) );
	};

	const tiles: Array< [ string, number, boolean ] > = [
		[ 'Pending', wallet.balances.pending, false ],
		[ 'Payable', wallet.balances.payable, false ],
		[ 'Available balance', wallet.balances.available, true ],
	];

	return (
		<div>
			<div style={ { display: 'flex', gap: '12px', marginBottom: '18px' } }>
				{ tiles.map( ( [ label, value, highlight ] ) => (
					<div
						key={ label }
						style={ {
							flex: 1,
							background: highlight ? '#E6F1FB' : '#f5f5f2',
							borderRadius: '8px',
							padding: '16px',
						} }
					>
						<p
							style={ {
								fontSize: '13px',
								color: highlight ? '#185FA5' : '#5f5e5a',
								margin: '0 0 6px',
							} }
						>
							{ label }
						</p>
						<p
							style={ {
								fontSize: '24px',
								fontWeight: 600,
								margin: 0,
								color: highlight ? '#185FA5' : undefined,
							} }
						>
							{ money( value ) }
						</p>
					</div>
				) ) }
			</div>

			<form onSubmit={ submit } style={ { marginBottom: '18px' } }>
				<p style={ { fontSize: '13px', color: '#5f5e5a' } }>
					Request payout (minimum { money( wallet.minimum ) })
				</p>
				<div style={ { display: 'flex', gap: '8px', flexWrap: 'wrap' } }>
					<input
						type="number"
						step="0.01"
						min="0"
						placeholder="Amount"
						value={ amount }
						onChange={ ( e ) => setAmount( e.target.value ) }
						aria-label="Amount"
						required
					/>
					<select
						value={ method }
						onChange={ ( e ) => setMethod( e.target.value ) }
						aria-label="Method"
					>
						{ wallet.methods.map( ( m ) => (
							<option key={ m } value={ m }>
								{ m }
							</option>
						) ) }
					</select>
					<input
						type="text"
						placeholder="Account details"
						value={ details }
						onChange={ ( e ) => setDetails( e.target.value ) }
						aria-label="Account details"
						style={ { flex: 1, minWidth: '200px' } }
						required
					/>
					<button className="button button-primary" type="submit">
						Request payout
					</button>
				</div>
				{ error && <p style={ { color: '#A32D2D' } }>{ error }</p> }
				{ done && <p style={ { color: '#3b6d11' } }>{ done }</p> }
			</form>

			<p style={ { fontSize: '12px', color: '#5f5e5a' } }>
				Payout history
			</p>
			<HistoryTable items={ items } />
		</div>
	);
}

function HistoryTable( { items }: { items: Payout[] } ) {
	return (
		<table className="widefat striped">
			<thead>
				<tr>
					<th>Date</th>
					<th>Method</th>
					<th>Amount</th>
					<th>Status</th>
				</tr>
			</thead>
			<tbody>
				{ items.length === 0 && (
					<tr>
						<td colSpan={ 4 }>No payouts yet.</td>
					</tr>
				) }
				{ items.map( ( item ) => (
					<tr key={ item.id }>
						<td>{ item.requested_at }</td>
						<td>
							{ item.method } · { item.details_mask }
						</td>
						<td>{ money( item.amount ) }</td>
						<td>
							<span
								style={ {
									fontSize: '12px',
									padding: '3px 9px',
									borderRadius: '8px',
									display: 'inline-block',
									background: '#f5f5f2',
									...( STATUS_STYLE[ item.status ] ?? {} ),
								} }
							>
								{ item.status }
							</span>
						</td>
					</tr>
				) ) }
			</tbody>
		</table>
	);
}

function AdminQueue() {
	const [ items, setItems ] = useState< Payout[] >( [] );
	const [ error, setError ] = useState< string | null >( null );

	const load = useCallback( () => {
		apiFetch< { items: Payout[] } >( {
			path: '/cosell-hive/v1/payouts?status=requested',
		} )
			.then( ( res ) => setItems( res.items ) )
			.catch( () => setError( 'Could not load payout requests.' ) );
	}, [] );

	useEffect( () => {
		load();
	}, [ load ] );

	const act = ( id: number, action: string ) => {
		apiFetch( {
			path: `/cosell-hive/v1/payouts/${ id }/${ action }`,
			method: 'POST',
		} )
			.then( load )
			.catch( () => setError( 'Action failed.' ) );
	};

	if ( error ) {
		return <p>{ error }</p>;
	}

	return (
		<div>
			<p style={ { fontSize: '13px', color: '#5f5e5a' } }>
				Payout requests — transfer manually, then mark paid.
			</p>
			{ items.length === 0 && <p>No pending requests.</p> }
			{ items.map( ( item ) => (
				<div
					key={ item.id }
					style={ {
						background: '#fff',
						border: '1px solid #e4e2da',
						borderRadius: '12px',
						padding: '16px',
						marginBottom: '10px',
						display: 'flex',
						gap: '12px',
						alignItems: 'center',
					} }
				>
					<div style={ { flex: 1 } }>
						<p style={ { fontWeight: 600, margin: '0 0 2px' } }>
							{ item.affiliate } · { money( item.amount ) }
						</p>
						<p
							style={ {
								fontSize: '12px',
								color: '#5f5e5a',
								margin: 0,
							} }
						>
							{ item.method } · { item.details_mask } ·{' '}
							{ item.requested_at }
						</p>
					</div>
					<button
						className="button"
						onClick={ () => act( item.id, 'reject' ) }
					>
						Reject
					</button>
					<button
						className="button button-primary"
						onClick={ () => act( item.id, 'approve' ) }
					>
						Approve
					</button>
					<button
						className="button"
						onClick={ () => act( item.id, 'mark-paid' ) }
					>
						Mark paid
					</button>
				</div>
			) ) }
		</div>
	);
}

function WalletRoot() {
	const [ wallet, setWallet ] = useState< Wallet | null >( null );
	const [ isAdmin, setIsAdmin ] = useState< boolean >( false );
	const [ error, setError ] = useState< string | null >( null );

	useEffect( () => {
		apiFetch< Wallet >( { path: '/cosell-hive/v1/wallet' } )
			.then( setWallet )
			.catch( () => setError( 'Could not load wallet.' ) );
		apiFetch< { items: Payout[] } >( { path: '/cosell-hive/v1/payouts' } )
			.then( ( res ) => {
				const first: Payout | undefined = res.items[ 0 ];
				setIsAdmin( !! first && 'affiliate' in first );
			} )
			.catch( () => {} );
	}, [] );

	if ( error ) {
		return <p>{ error }</p>;
	}

	if ( ! wallet ) {
		return <p>Loading CoSellHive…</p>;
	}

	return (
		<div>
			{ isAdmin && <AdminQueue /> }
			{ isAdmin && (
				<hr style={ { margin: '24px 0' } } />
			) }
			<AffiliateWallet wallet={ wallet } />
		</div>
	);
}

const el = document.getElementById( 'cosell-hive-root' );
if ( el && el.dataset.page === 'cosell-hive-wallet' ) {
	createRoot( el ).render( <WalletRoot /> );
}
