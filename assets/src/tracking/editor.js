import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

function Edit() {
	const blockProps = useBlockProps();
	return (
		<div { ...blockProps }>
			<div
				style={ {
					border: '1px dashed #c3c4c7',
					borderRadius: 4,
					padding: 16,
				} }
			>
				<strong>
					{ __( 'Delivery tracking', 'fleetbase-for-woocommerce' ) }
				</strong>
				<p style={ { margin: '4px 0 0', opacity: 0.7 } }>
					{ __(
						'Visitors enter a tracking number here and see the status of their Fleetbase delivery. Rendered on the live site.',
						'fleetbase-for-woocommerce'
					) }
				</p>
			</div>
		</div>
	);
}

registerBlockType( metadata, { edit: Edit, save: () => null } );
