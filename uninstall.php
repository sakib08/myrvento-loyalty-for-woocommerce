<?php
/**
 * Uninstall Commerce Insights for WooCommerce by Ppros — drop custom tables and options.
 *
 * @package Ciwp
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall drops this plugin's tables.

global $wpdb;

$ciwp_tables = array(
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

foreach ( $ciwp_tables as $ciwp_table ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$ciwp_table}" );
}

delete_option( 'ciwp_settings' );
delete_option( 'ciwp_db_version' );
delete_option( 'ciwp_ai_last_run' );
delete_option( 'growthpilot_settings' );
delete_option( 'growthpilot_db_version' );
delete_option( 'growthpilot_ai_last_run' );
delete_option( 'growthpilot_demo_seed' );
delete_transient( 'ciwp_ai_predict' );
delete_transient( 'ciwp_ai_pricing' );
delete_transient( 'ciwp_ai_forecast' );
delete_transient( 'ciwp_ai_brain' );
delete_transient( 'ciwp_ai_brain_llm' );

$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'gp\\_%'" );
