import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * A plain table for admin lists.
 *
 * @param {Object}   props         Component props.
 * @param {Array}    props.columns [{ key, label, render?, width? }]
 * @param {Array}    props.rows    Row objects.
 * @param {boolean}  props.loading Show a spinner row.
 * @param {string}   props.empty   Message when there are no rows.
 * @param {Function} props.rowKey  Row => key.
 */
export default function DataTable( { columns, rows, loading, empty, rowKey } ) {
	return (
		<table className="fbwc-data-table widefat striped">
			<thead>
				<tr>
					{ columns.map( ( c ) => (
						<th
							key={ c.key }
							style={ c.width ? { width: c.width } : undefined }
						>
							{ c.label }
						</th>
					) ) }
				</tr>
			</thead>
			<tbody>
				{ loading && (
					<tr>
						<td
							colSpan={ columns.length }
							className="fbwc-data-table__state"
						>
							<Spinner />{ ' ' }
							{ __( 'Loading…', 'fleetbase-for-woocommerce' ) }
						</td>
					</tr>
				) }
				{ ! loading && rows.length === 0 && (
					<tr>
						<td
							colSpan={ columns.length }
							className="fbwc-data-table__state"
						>
							{ empty }
						</td>
					</tr>
				) }
				{ ! loading &&
					rows.map( ( row ) => (
						<tr key={ rowKey ? rowKey( row ) : row.id }>
							{ columns.map( ( c ) => (
								<td key={ c.key }>
									{ c.render
										? c.render( row )
										: row[ c.key ] }
								</td>
							) ) }
						</tr>
					) ) }
			</tbody>
		</table>
	);
}
