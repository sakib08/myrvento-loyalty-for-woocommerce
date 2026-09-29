<?php
/**
 * Daily cron — expiration, birthdays, VIP re-evaluation.
 *
 * @package Myrvento
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cron runner.
 */
class Myrvento_Cron {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'myrvento_daily', array( $this, 'run_daily' ) );

		if ( ! wp_next_scheduled( 'myrvento_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'myrvento_daily' );
		}
	}

	/**
	 * Daily jobs.
	 *
	 * @return void
	 */
	public function run_daily() {
		Myrvento_Points_Ledger::expire_due_points();
		Myrvento_Points_Earner::award_birthdays();
		Myrvento_VIP_Tiers::evaluate_all();
		if ( class_exists( 'Myrvento_AI_Engine' ) && Myrvento_AI_Engine::enabled() ) {
			Myrvento_AI_Engine::refresh_all();
		}
	}
}
