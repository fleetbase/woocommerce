<?php
/**
 * Fleetbase Usage Tracker
 *
 * Tracks and calculates resource unit consumption.
 *
 * @package FleetbaseWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fleetbase_Usage_Tracker
 */
class Fleetbase_Usage_Tracker extends Fleetbase_Singleton {
	/**
	 * Resource unit weights.
	 *
	 * @var array
	 */
	const UNIT_WEIGHTS = array(
		'orders'   => 2,
		'drivers'  => 1,
		'vehicles' => 1,
		'users'    => 5,
		'places'   => 1,
	);

	/**
	 * Get the current billing period key.
	 *
	 * @return string Period key (YYYY-MM).
	 */
	private function get_current_period() {
		return gmdate( 'Y-m' );
	}

	/**
	 * Get the transient key for the current period.
	 *
	 * @return string Transient key.
	 */
	private function get_transient_key() {
		return 'fleetbase_usage_' . $this->get_current_period();
	}

	/**
	 * Get usage data for the current period.
	 *
	 * @return array Usage data.
	 */
	public function get_usage() {
		$usage = get_transient( $this->get_transient_key() );

		if ( false === $usage ) {
			$usage = array(
				'orders'   => 0,
				'drivers'  => 0,
				'vehicles' => 0,
				'users'    => 0,
				'places'   => 0,
			);
			set_transient( $this->get_transient_key(), $usage, MONTH_IN_SECONDS );
		}

		return $usage;
	}

	/**
	 * Increment a resource counter.
	 *
	 * @param string $resource Resource type.
	 * @param int    $count    Count to increment by.
	 */
	public function increment( $resource, $count = 1 ) {
		$usage = $this->get_usage();

		if ( ! isset( $usage[ $resource ] ) ) {
			return;
		}

		$usage[ $resource ] += $count;
		set_transient( $this->get_transient_key(), $usage, MONTH_IN_SECONDS );

		// Also update the total in settings
		$settings    = Fleetbase_Settings::get_instance();
		$total_units = $this->calculate_total_units( $usage );
		$settings->update( 'trial_units_used', $total_units );
	}

	/**
	 * Calculate total resource units consumed.
	 *
	 * @param array $usage Usage data.
	 * @return int Total units.
	 */
	public function calculate_total_units( $usage = null ) {
		if ( null === $usage ) {
			$usage = $this->get_usage();
		}

		$total = 0;

		foreach ( $usage as $resource => $count ) {
			if ( isset( self::UNIT_WEIGHTS[ $resource ] ) ) {
				$total += $count * self::UNIT_WEIGHTS[ $resource ];
			}
		}

		return $total;
	}

	/**
	 * Get usage breakdown with units.
	 *
	 * @return array Usage breakdown.
	 */
	public function get_usage_breakdown() {
		$usage     = $this->get_usage();
		$breakdown = array();

		foreach ( $usage as $resource => $count ) {
			$breakdown[] = array(
				'resource' => ucfirst( $resource ),
				'count'    => $count,
				'units'    => $count * self::UNIT_WEIGHTS[ $resource ],
			);
		}

		return $breakdown;
	}

	/**
	 * Check if trial limit is exceeded.
	 *
	 * @return bool True if exceeded.
	 */
	public function is_trial_exceeded() {
		$settings    = Fleetbase_Settings::get_instance();
		$used        = $this->calculate_total_units();
		$total       = $settings->get( 'trial_units_total', 200 );

		return $used >= $total;
	}

	/**
	 * Get trial usage percentage.
	 *
	 * @return float Percentage (0-100).
	 */
	public function get_trial_percentage() {
		$settings = Fleetbase_Settings::get_instance();
		$used     = $this->calculate_total_units();
		$total    = $settings->get( 'trial_units_total', 200 );

		if ( $total <= 0 ) {
			return 0;
		}

		return min( 100, ( $used / $total ) * 100 );
	}
}
