import { useState, useId } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function CopyField( { value, label } ) {
	const [ copied, setCopied ] = useState( false );
	const inputId = useId();
	const copy = async () => {
		try {
			await window.navigator.clipboard.writeText( value );
			setCopied( true );
			setTimeout( () => setCopied( false ), 2000 );
		} catch ( e ) {
			// Clipboard blocked: the input is selectable.
		}
	};
	return (
		<div className="fbwc-copy">
			{ label && (
				<label className="fbwc-copy__label" htmlFor={ inputId }>
					{ label }
				</label>
			) }
			<div className="fbwc-copy__row">
				<input
					id={ inputId }
					type="text"
					readOnly
					aria-label={ label || 'Value' }
					value={ value }
					onFocus={ ( e ) => e.target.select() }
					className="fbwc-copy__input"
				/>
				<Button variant="secondary" onClick={ copy }>
					{ copied
						? __( 'Copied', 'fleetbase-for-woocommerce' )
						: __( 'Copy', 'fleetbase-for-woocommerce' ) }
				</Button>
			</div>
		</div>
	);
}
