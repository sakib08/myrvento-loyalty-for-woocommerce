<?php
/**
 * Daily cron — expiration, birthdays, VIP re-evaluation.
 *
 * @package GrowthPilot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cron runner.
 */
class GrowthPilot_Cron {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'growthpilot_daily', array( $this, 'run_daily' ) );

		if ( ! wp_next_scheduled( 'growthpilot_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'growthpilot_daily' );
		}
	}

	/**
	 * Daily jobs.
	 *
	 * @return void
	 */
	public function run_daily() {
		GrowthPilot_Points_Ledger::expire_due_points();
		GrowthPilot_Points_Earner::award_birthdays();
		GrowthPilot_VIP_Tiers::evaluate_all();
		if ( class_exists( 'GrowthPilot_AI_Engine' ) && GrowthPilot_AI_Engine::enabled() ) {
			GrowthPilot_AI_Engine::refresh_all();
		}
	}
}
