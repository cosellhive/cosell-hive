import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import type { FormEvent } from 'react';

type Attributes = {
	listingId: string;
	affiliateId: number;
	style: string;
};

registerBlockType< Attributes >( 'cosell-hive/product-card', {
	title: __( 'CoSellHive Product', 'cosell-hive' ),
	category: 'widgets',
	description: __( 'Embed a marketplace product with your tracked affiliate link.', 'cosell-hive' ),
	attributes: {
		listingId: { type: 'string', default: '' },
		affiliateId: { type: 'number', default: 0 },
		style: { type: 'string', default: 'card' },
	},
	edit( { attributes, setAttributes } ) {
		const blockProps = useBlockProps();
		const onChange = (
			key: 'listingId' | 'affiliateId' | 'style',
			e: FormEvent< HTMLInputElement | HTMLSelectElement >
		) => {
			const value = e.currentTarget.value;
			setAttributes( {
				[key]:
					key === 'affiliateId' ? parseInt( value, 10 ) || 0 : value,
			} );
		};

		return (
			<div { ...blockProps }>
				<p>
					<strong>{ __( 'CoSellHive Product', 'cosell-hive' ) }</strong>
				</p>
				<label>
					{ __( 'Listing ID (copy it from My Links)', 'cosell-hive' ) }
					<input
						type="text"
						value={ attributes.listingId }
						onChange={ ( e ) => onChange( 'listingId', e ) }
						placeholder="mock_42 or p-123"
					/>
				</label>
				<label>
					{ __( 'Affiliate user ID', 'cosell-hive' ) }
					<input
						type="number"
						min={ 0 }
						value={ attributes.affiliateId }
						onChange={ ( e ) => onChange( 'affiliateId', e ) }
					/>
				</label>
				<label>
					{ __( 'Style', 'cosell-hive' ) }
					<select
						value={ attributes.style }
						onChange={ ( e ) => onChange( 'style', e ) }
					>
						<option value="card">{ __( 'Product card', 'cosell-hive' ) }</option>
						<option value="text">{ __( 'Text link', 'cosell-hive' ) }</option>
						<option value="banner">{ __( 'Banner', 'cosell-hive' ) }</option>
					</select>
				</label>
			</div>
		);
	},
	save() {
		return null;
	},
} );
