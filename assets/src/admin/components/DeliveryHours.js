import { CheckboxControl, TextControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

const DAYS = [
	[ 'mon', __( 'Monday', 'fleetbase-for-woocommerce' ) ],
	[ 'tue', __( 'Tuesday', 'fleetbase-for-woocommerce' ) ],
	[ 'wed', __( 'Wednesday', 'fleetbase-for-woocommerce' ) ],
	[ 'thu', __( 'Thursday', 'fleetbase-for-woocommerce' ) ],
	[ 'fri', __( 'Friday', 'fleetbase-for-woocommerce' ) ],
	[ 'sat', __( 'Saturday', 'fleetbase-for-woocommerce' ) ],
	[ 'sun', __( 'Sunday', 'fleetbase-for-woocommerce' ) ],
];

/**
 * Weekly delivery hours editor.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.hours    Hours keyed by day: { enabled, open, close }.
 * @param {Function} props.onChange Called with the updated hours object.
 */
export default function DeliveryHours( { hours, onChange } ) {
	const update = ( day, patch ) =>
		onChange( {
			...hours,
			[ day ]: { ...( hours[ day ] || {} ), ...patch },
		} );

	return (
		<table className="fbwc-hours">
			<thead>
				<tr>
					<th>{ __( 'Day', 'fleetbase-for-woocommerce' ) }</th>
					<th>{ __( 'Deliveries', 'fleetbase-for-woocommerce' ) }</th>
					<th>{ __( 'From', 'fleetbase-for-woocommerce' ) }</th>
					<th>{ __( 'Until', 'fleetbase-for-woocommerce' ) }</th>
				</tr>
			</thead>
			<tbody>
				{ DAYS.map( ( [ key, label ] ) => {
					const day = hours[ key ] || {
						enabled: false,
						open: '09:00',
						close: '18:00',
					};
					return (
						<tr
							key={ key }
							className={ day.enabled ? '' : 'is-closed' }
						>
							<td>{ label }</td>
							<td>
								<CheckboxControl
									label={ sprintf(
										/* translators: %s: day name */
										__(
											'Deliveries on %s',
											'fleetbase-for-woocommerce'
										),
										label
									) }
									hideLabelFromVision
									checked={ !! day.enabled }
									onChange={ ( enabled ) =>
										update( key, { enabled } )
									}
									__nextHasNoMarginBottom
								/>
							</td>
							<td>
								<TextControl
									type="time"
									label={ sprintf(
										/* translators: %s: day name */
										__(
											'%s opening time',
											'fleetbase-for-woocommerce'
										),
										label
									) }
									hideLabelFromVision
									value={ day.open }
									disabled={ ! day.enabled }
									onChange={ ( open ) =>
										update( key, { open } )
									}
									__nextHasNoMarginBottom
								/>
							</td>
							<td>
								<TextControl
									type="time"
									label={ sprintf(
										/* translators: %s: day name */
										__(
											'%s closing time',
											'fleetbase-for-woocommerce'
										),
										label
									) }
									hideLabelFromVision
									value={ day.close }
									disabled={ ! day.enabled }
									onChange={ ( close ) =>
										update( key, { close } )
									}
									__nextHasNoMarginBottom
								/>
							</td>
						</tr>
					);
				} ) }
			</tbody>
		</table>
	);
}
