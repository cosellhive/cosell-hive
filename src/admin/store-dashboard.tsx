import { createRoot, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import type { CSSProperties } from 'react';

type Commission = {
	type: 'flat' | 'percent';
	value: string;
	cap: string;
	label: string;
};

type Listing = {
	id: number;
	title: string;
	price: string | null;
	stock_quantity: number | null;
	commission: Commission;
	status: string;
	hub_id: string;
	affiliates: number;
	sales_30d: number;
};

type Dashboard = {
	stats: {
		active_listings: number;
		active_affiliates: number;
		gmv_this_month: number;
		commission_paid: number;
	};
	listings: Listing[];
};

const STATUS_STYLES: Record< string, CSSProperties > = {
	live: {
		background: '#EAF3DE',
		color: '#3b6d11',
	},
	pending: {
		background: '#FAEEDA',
		color: '#854F0B',
	},
};

const DEFAULT_STATUS_STYLE: CSSProperties = {
	background: '#f5f5f2',
	color: '#5f5e5a',
};

function StoreDashboard() {
	const [ data, setData ] = useState< Dashboard | null >( null );
	const [ error, setError ] = useState< string | null >( null );

	useEffect( () => {
		apiFetch< Dashboard >( { path: '/cosell-hive/v1/dashboard' } )
			.then( setData )
			.catch( () => setError( __( 'Could not load dashboard.', 'cosell-hive' ) ) );
	}, [] );

	if ( error ) {
		return <p>{ error }</p>;
	}

	if ( ! data ) {
		return <p>{ __( 'Loading CoSellHive…', 'cosell-hive' ) }</p>;
	}

	const { stats, listings } = data;
	const tiles: Array< [ string, number ] > = [
		[ __( 'Active listings', 'cosell-hive' ), stats.active_listings ],
		[ __( 'Active affiliates', 'cosell-hive' ), stats.active_affiliates ],
		[ __( 'GMV this month', 'cosell-hive' ), stats.gmv_this_month ],
		[ __( 'Commission paid', 'cosell-hive' ), stats.commission_paid ],
	];

	return (
		<div>
			<div style={ { display: 'flex', gap: '10px', marginBottom: '16px' } }>
				{ tiles.map( ( [ label, value ] ) => (
					<div
						key={ label }
						style={ {
							flex: 1,
							background: '#f5f5f2',
							borderRadius: '8px',
							padding: '16px',
						} }
					>
						<p
							style={ {
								fontSize: '13px',
								color: '#5f5e5a',
								margin: '0 0 6px',
							} }
						>
							{ label }
						</p>
						<p style={ { fontSize: '24px', fontWeight: 600, margin: 0 } }>
							{ value }
						</p>
					</div>
				) ) }
			</div>
			<table className="widefat striped">
				<thead>
					<tr>
						<th>{ __( 'Product', 'cosell-hive' ) }</th>
						<th>{ __( 'Commission', 'cosell-hive' ) }</th>
						<th>{ __( 'Affiliates', 'cosell-hive' ) }</th>
						<th>{ __( 'Sales (30d)', 'cosell-hive' ) }</th>
						<th>{ __( 'Status', 'cosell-hive' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ listings.length === 0 && (
						<tr>
							<td colSpan={ 5 }>
								{ __( 'No marketplace listings yet. Enable CoSellHive on a product to publish it for review.', 'cosell-hive' ) }
							</td>
						</tr>
					) }
					{ listings.map( ( listing ) => (
						<tr key={ listing.id }>
							<td>{ listing.title }</td>
							<td>{ listing.commission.label }</td>
							<td>{ listing.affiliates }</td>
							<td>{ listing.sales_30d }</td>
							<td>
								<span
									style={ {
										fontSize: '12px',
										padding: '3px 9px',
										borderRadius: '8px',
										display: 'inline-block',
										...( STATUS_STYLES[ listing.status ] ??
											DEFAULT_STATUS_STYLE ),
									} }
								>
									{ listing.status || 'draft' }
								</span>
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
		</div>
	);
}

const el = document.getElementById( 'cosell-hive-root' );
if ( el && el.dataset.page === 'cosell-hive' ) {
	createRoot( el ).render( <StoreDashboard /> );
}
