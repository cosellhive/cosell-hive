import { createRoot, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import type { CSSProperties, FormEvent } from 'react';

type Status = {
	connected: boolean;
	site_role: string;
	hub: boolean;
	entitlements: {
		tier: string;
		listed_product_limit: number;
		ai_copy_credits: number;
		analytics_depth: string;
	};
};

function Onboarding() {
	const [ status, setStatus ] = useState< Status | null >( null );
	const [ key, setKey ] = useState< string >( '' );
	const [ role, setRole ] = useState< string >( 'store' );
	const [ error, setError ] = useState< string | null >( null );
	const [ done, setDone ] = useState< boolean >( false );

	useEffect( () => {
		apiFetch< Status >( { path: '/cosell-hive/v1/onboarding' } )
			.then( ( res ) => {
				setStatus( res );
				if ( res.site_role ) {
					setRole( res.site_role );
				}
			} )
			.catch( () => setError( __( 'Could not load onboarding status.', 'cosell-hive' ) ) );
	}, [] );

	const submit = ( e: FormEvent ) => {
		e.preventDefault();
		setError( null );
		apiFetch< Status >( {
			path: '/cosell-hive/v1/onboarding/activate',
			method: 'POST',
			data: { key, role },
		} )
			.then( ( res ) => {
				setStatus( res );
				setDone( true );
			} )
			.catch( () => setError( __( 'Activation failed. Check the key.', 'cosell-hive' ) ) );
	};

	const roleCard = ( value: string, label: string ): CSSProperties => ( {
		flex: 1,
		border: `1px solid ${ role === value ? '#b5d4f4' : '#cfcdc4' }`,
		background: role === value ? '#E6F1FB' : '#fff',
		borderRadius: '8px',
		padding: '12px',
		textAlign: 'center',
		cursor: 'pointer',
	} );

	if ( error && ! status ) {
		return <p>{ error }</p>;
	}

	return (
		<div style={ { maxWidth: '420px', margin: '0 auto', padding: '24px' } }>
			<p style={ { fontSize: '16px', fontWeight: 600, margin: '0 0 4px' } }>
				{ __( 'Connect to the marketplace', 'cosell-hive' ) }
			</p>
			<p style={ { fontSize: '12px', color: '#5f5e5a', marginBottom: '20px' } }>
				{ __( 'Activate your license to start publishing or browsing products.', 'cosell-hive' ) }
			</p>
			{ status?.connected && (
				<p
					style={ {
						background: '#EAF3DE',
						color: '#3b6d11',
						borderRadius: '8px',
						padding: '8px 12px',
						fontSize: '13px',
					} }
				>
					{ sprintf(
						/* translators: 1: tier name, 2: listing limit, 3: hub or local mock */
						__( 'Connected · %1$s tier · up to %2$s listings · %3$s', 'cosell-hive' ),
						status.entitlements.tier,
						status.entitlements.listed_product_limit,
						status.hub ? 'hub' : 'local mock'
					) }
				</p>
			) }
			<form onSubmit={ submit }>
				<p style={ { fontSize: '13px', color: '#5f5e5a' } }>
					{ __( 'License key', 'cosell-hive' ) }
				</p>
				<input
					type="text"
					value={ key }
					onChange={ ( e ) => setKey( e.target.value ) }
					placeholder="XXXX-XXXX-XXXX-XXXX"
					style={ { width: '100%', marginBottom: '16px' } }
					aria-label={ __( 'License key', 'cosell-hive' ) }
					required
				/>
				<p style={ { fontSize: '13px', color: '#5f5e5a' } }>
					{ __( 'This site will act as a', 'cosell-hive' ) }
				</p>
				<div style={ { display: 'flex', gap: '8px', marginBottom: '20px' } }>
					{ [ 'store', 'affiliate' ].map( ( r ) => (
						<div
							key={ r }
							style={ roleCard( r, r ) }
							onClick={ () => setRole( r ) }
							onKeyDown={ ( e ) => {
								if ( e.key === 'Enter' ) {
									setRole( r );
								}
							} }
							role="button"
							tabIndex={ 0 }
						>
							<p
								style={ {
									fontSize: '13px',
									fontWeight: 600,
									margin: 0,
									color:
										role === r ? '#185FA5' : '#5f5e5a',
								} }
							>
								{ r === 'store' ? __( 'Store', 'cosell-hive' ) : __( 'Affiliate', 'cosell-hive' ) }
							</p>
						</div>
					) ) }
				</div>
				<button
					className="button button-primary button-large"
					style={ { width: '100%', textAlign: 'center' } }
					type="submit"
				>
					{ __( 'Activate and connect', 'cosell-hive' ) }
				</button>
			</form>
			{ error && <p style={ { color: '#A32D2D' } }>{ error }</p> }
			{ done && (
				<p style={ { color: '#3b6d11' } }>
					{ __( 'Activated. Publish products from WooCommerce or browse the marketplace.', 'cosell-hive' ) }
				</p>
			) }
			<p style={ { fontSize: '12px', textAlign: 'center' } }>
				{ __( 'Don’t have a key? Start a free trial from your CoSellHive account.', 'cosell-hive' ) }
			</p>
		</div>
	);
}

const el = document.getElementById( 'cosell-hive-root' );
if ( el && el.dataset.page === 'cosell-hive-setup' ) {
	createRoot( el ).render( <Onboarding /> );
}
