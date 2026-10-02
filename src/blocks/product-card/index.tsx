import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import type { FormEvent } from 'react';

type Attributes = {
	listingId: string;
	affiliateId: number;
	style: string;
};

registerBlockType< Attributes >( 'cosell-hive/product-card', {
	title: 'CoSellHive Product',
	category: 'widgets',
	description: 'Embed a marketplace product with your tracked affiliate link.',
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
					<strong>CoSellHive Product</strong>
				</p>
				<label>
					Listing ID (copy it from My Links)
					<input
						type="text"
						value={ attributes.listingId }
						onChange={ ( e ) => onChange( 'listingId', e ) }
						placeholder="mock_42 or p-123"
					/>
				</label>
				<label>
					Affiliate user ID
					<input
						type="number"
						min={ 0 }
						value={ attributes.affiliateId }
						onChange={ ( e ) => onChange( 'affiliateId', e ) }
					/>
				</label>
				<label>
					Style
					<select
						value={ attributes.style }
						onChange={ ( e ) => onChange( 'style', e ) }
					>
						<option value="card">Product card</option>
						<option value="text">Text link</option>
						<option value="banner">Banner</option>
					</select>
				</label>
			</div>
		);
	},
	save() {
		return null;
	},
} );
