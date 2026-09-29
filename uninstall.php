<?php
/**
 * Uninstall Myrvento Loyalty for WooCommerce. Drops this plugin's tables and options.
 *
 * @package Myrvento
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall drops this plugin's tables.

global $wpdb;

$myrvento_tables = array(
	'myrvento_points_ledger',
	'myrvento_points_balances',
	'myrvento_point_rules',
	'myrvento_vip_tiers',
	'myrvento_customer_tiers',
	'myrvento_rewards',
	'myrvento_redemptions',
	'myrvento_badges',
	'myrvento_customer_badges',
	'myrvento_challenges',
	'myrvento_challenge_progress',
	'myrvento_referral_campaigns',
	'myrvento_referrals',
	'myrvento_referral_clicks',
	'myrvento_analytics_events',
	'myrvento_email_stats',
	'myrvento_ai_predictions',
);

foreach ( $myrvento_tables as $myrvento_table ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$myrvento_table}" );
}

delete_option( 'myrvento_settings' );
delete_option( 'myrvento_db_version' );
delete_option( 'myrvento_ai_last_run' );
delete_option( 'myrvento_hash_secret' );
delete_option( 'myrvento_demo_seed' );
delete_transient( 'myrvento_ai_predict' );
delete_transient( 'myrvento_ai_pricing' );
delete_transient( 'myrvento_ai_forecast' );
delete_transient( 'myrvento_ai_brain' );
delete_transient( 'myrvento_ai_brain_llm' );

$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'myrvento\\_%' OR meta_key LIKE '\\_myrvento\\_%'" );
