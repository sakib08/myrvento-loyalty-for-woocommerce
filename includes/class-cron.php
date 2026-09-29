<?php
/**
 * Daily cron — expiration, birthdays, VIP re-evaluation.
 *
 * @package Ciwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cron runner.
 */
class Ciwp_Cron {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'ciwp_daily', array( $this, 'run_daily' ) );

		if ( ! wp_next_scheduled( 'ciwp_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'ciwp_daily' );
		}
	}

	/**
	 * Daily jobs.
	 *
	 * @return void
	 */
	public function run_daily() {
		Ciwp_Points_Ledger::expire_due_points();
		Ciwp_Points_Earner::award_birthdays();
		Ciwp_VIP_Tiers::evaluate_all();
		if ( class_exists( 'Ciwp_AI_Engine' ) && Ciwp_AI_Engine::enabled() ) {
			Ciwp_AI_Engine::refresh_all();
		}
	}
}
