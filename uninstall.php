<?php
/**
 * Uninstall GrowthPilot — drop custom tables and options.
 *
 * @package GrowthPilot
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$tables = array(
	'gp_points_ledger',
	'gp_points_balances',
	'gp_point_rules',
	'gp_vip_tiers',
	'gp_customer_tiers',
	'gp_rewards',
	'gp_redemptions',
	'gp_badges',
	'gp_customer_badges',
	'gp_challenges',
	'gp_challenge_progress',
	'gp_referral_campaigns',
	'gp_referrals',
	'gp_referral_clicks',
	'gp_analytics_events',
	'gp_email_stats',
	'gp_ai_predictions',
);

foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" );
}

delete_option( 'growthpilot_settings' );
delete_option( 'growthpilot_db_version' );
delete_option( 'growthpilot_ai_last_run' );
delete_transient( 'growthpilot_ai_predict' );
delete_transient( 'growthpilot_ai_pricing' );
delete_transient( 'growthpilot_ai_forecast' );
delete_transient( 'growthpilot_ai_brain' );
delete_transient( 'growthpilot_ai_brain_llm' );

$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'gp\\_%'" );
