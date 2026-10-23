import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

function Edit() {
	const blockProps = useBlockProps( {
		className: 'fleetbase-wc-delivery-time',
	} );
	return (
		<div { ...blockProps }>
			<strong>
				{ __( 'Delivery time', 'fleetbase-for-woocommerce' ) }
			</strong>
			<p style={ { margin: '4px 0 0', opacity: 0.7 } }>
				{ __(
					'Customers choose "as soon as possible" or a day and time window here when Fleetbase Delivery is selected.',
					'fleetbase-for-woocommerce'
				) }
			</p>
		</div>
	);
}

function Save() {
	const blockProps = useBlockProps.save();
	return <div { ...blockProps } />;
}

registerBlockType( metadata, { edit: Edit, save: Save } );
