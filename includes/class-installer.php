<?php
/**
 * Activation, schema, and seed data.
 *
 * @package Ciwp
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables have no core API.

/**
 * Installer.
 */
class Ciwp_Installer {

	/**
	 * Activate plugin.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_tables();
		self::seed();
		update_option( 'ciwp_db_version', CIWP_DB_VERSION, false );

		if ( false === get_option( Ciwp_Settings::OPTION, false ) ) {
			update_option( Ciwp_Settings::OPTION, Ciwp_Settings::defaults(), false );
		}

		self::register_endpoints();
		flush_rewrite_rules();

		if ( ! wp_next_scheduled( 'ciwp_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'ciwp_daily' );
		}
	}

	/**
	 * Deactivate plugin.
	 *
	 * @return void
	 */
	public static function deactivate() {
		$timestamp = wp_next_scheduled( 'ciwp_daily' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'ciwp_daily' );
		}

		flush_rewrite_rules();
	}

	/**
	 * Upgrade schema when the DB version changes.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$installed = get_option( 'ciwp_db_version', '' );

		if ( (string) $installed === (string) CIWP_DB_VERSION ) {
			return;
		}

		self::create_tables();
		self::seed();
		update_option( 'ciwp_db_version', CIWP_DB_VERSION, false );
	}

	/**
	 * Register My Account rewrite endpoints (also called on init).
	 *
	 * @return void
	 */
	public static function register_endpoints() {
		add_rewrite_endpoint( 'loyalty', EP_ROOT | EP_PAGES );
		add_rewrite_endpoint( 'referrals', EP_ROOT | EP_PAGES );
	}

	/**
	 * Create custom tables.
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$prefix  = $wpdb->prefix;

		$sql = array();

		$sql[] = "CREATE TABLE {$prefix}gp_points_ledger (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			customer_id BIGINT UNSIGNED NOT NULL,
			amount INT NOT NULL,
			remaining INT NOT NULL DEFAULT 0,
			balance_after INT NOT NULL DEFAULT 0,
			type VARCHAR(20) NOT NULL,
			source VARCHAR(40) NOT NULL,
			source_id BIGINT UNSIGNED NULL,
			order_id BIGINT UNSIGNED NULL,
			description TEXT NULL,
			expires_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			created_by BIGINT UNSIGNED NULL,
			meta LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY customer_id (customer_id),
			KEY type_source (type, source),
			KEY order_id (order_id),
			KEY expires_at (expires_at),
			KEY created_at (created_at)
		) $charset;";

		$sql[] = "CREATE TABLE {$prefix}gp_points_balances (
			customer_id BIGINT UNSIGNED NOT NULL,
			available INT NOT NULL DEFAULT 0,
			pending INT NOT NULL DEFAULT 0,
			lifetime_earned INT NOT NULL DEFAULT 0,
			lifetime_redeemed INT NOT NULL DEFAULT 0,
			lifetime_expired INT NOT NULL DEFAULT 0,
			tier_id BIGINT UNSIGNED NULL,
			tier_evaluated_at DATETIME NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (customer_id),
			KEY tier_id (tier_id)
		) $charset;";

		$sql[] = "CREATE TABLE {$prefix}gp_point_rules (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			source VARCHAR(40) NOT NULL,
			enabled TINYINT(1) NOT NULL DEFAULT 1,
			points INT NOT NULL DEFAULT 0,
			rate DECIMAL(12,4) NOT NULL DEFAULT 0,
			object_id BIGINT UNSIGNED NULL,
			object_type VARCHAR(20) NULL,
			config LONGTEXT NULL,
			sort_order INT NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY source (source),
			KEY object_lookup (object_type, object_id)
		) $charset;";

		$sql[] = "CREATE TABLE {$prefix}gp_vip_tiers (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			slug VARCHAR(50) NOT NULL,
			name VARCHAR(191) NOT NULL,
			color VARCHAR(20) NOT NULL DEFAULT '#64748b',
			qualifier_type VARCHAR(20) NOT NULL DEFAULT 'spending',
			qualifier_value DECIMAL(18,4) NOT NULL DEFAULT 0,
			sort_order INT NOT NULL DEFAULT 0,
			benefits LONGTEXT NULL,
			is_default TINYINT(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) $charset;";

		$sql[] = "CREATE TABLE {$prefix}gp_customer_tiers (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			customer_id BIGINT UNSIGNED NOT NULL,
			tier_id BIGINT UNSIGNED NOT NULL,
			previous_tier_id BIGINT UNSIGNED NULL,
			reason VARCHAR(20) NOT NULL DEFAULT 'auto',
			assigned_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY customer_id (customer_id),
			KEY tier_id (tier_id)
		) $charset;";

		$sql[] = "CREATE TABLE {$prefix}gp_rewards (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			type VARCHAR(40) NOT NULL,
			points_cost INT NOT NULL DEFAULT 0,
			enabled TINYINT(1) NOT NULL DEFAULT 1,
			tier_id BIGINT UNSIGNED NULL,
			stock INT NULL,
			redeemed_count INT NOT NULL DEFAULT 0,
			config LONGTEXT NULL,
			PRIMARY KEY  (id)
		) $charset;";

		$sql[] = "CREATE TABLE {$prefix}gp_redemptions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			customer_id BIGINT UNSIGNED NOT NULL,
			reward_id BIGINT UNSIGNED NOT NULL,
			points_spent INT NOT NULL,
			coupon_id BIGINT UNSIGNED NULL,
			coupon_code VARCHAR(64) NULL,
			ledger_id BIGINT UNSIGNED NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'issued',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY customer_id (customer_id),
			KEY reward_id (reward_id)
		) $charset;";

		$sql[] = "CREATE TABLE {$prefix}gp_badges (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			slug VARCHAR(50) NOT NULL,
			name VARCHAR(191) NOT NULL,
			description TEXT NULL,
			icon VARCHAR(50) NULL,
			milestone_type VARCHAR(40) NOT NULL,
			milestone_value INT NOT NULL DEFAULT 1,
			points_bonus INT NOT NULL DEFAULT 0,
			enabled TINYINT(1) NOT NULL DEFAULT 1,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) $charset;";

		$sql[] = "CREATE TABLE {$prefix}gp_customer_badges (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			customer_id BIGINT UNSIGNED NOT NULL,
			badge_id BIGINT UNSIGNED NOT NULL,
			earned_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY customer_badge (customer_id, badge_id)
		) $charset;";

		$sql[] = "CREATE TABLE {$prefix}gp_challenges (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			description TEXT NULL,
			type VARCHAR(40) NOT NULL,
			target_value INT NOT NULL DEFAULT 1,
			points_reward INT NOT NULL DEFAULT 0,
			starts_at DATETIME NULL,
			ends_at DATETIME NULL,
			enabled TINYINT(1) NOT NULL DEFAULT 1,
			PRIMARY KEY  (id)
		) $charset;";

		$sql[] = "CREATE TABLE {$prefix}gp_challenge_progress (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			challenge_id BIGINT UNSIGNED NOT NULL,
			customer_id BIGINT UNSIGNED NOT NULL,
			progress INT NOT NULL DEFAULT 0,
			completed_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY challenge_customer (challenge_id, customer_id)
		) $charset;";

		$sql[] = "CREATE TABLE {$prefix}gp_referral_campaigns (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			enabled TINYINT(1) NOT NULL DEFAULT 1,
			first_order_points INT NOT NULL DEFAULT 0,
			referee_signup_points INT NOT NULL DEFAULT 0,
			recurring_points INT NOT NULL DEFAULT 0,
			cookie_days INT NOT NULL DEFAULT 30,
			landing_page VARCHAR(191) NULL,
			starts_at DATETIME NULL,
			ends_at DATETIME NULL,
			PRIMARY KEY  (id)
		) $charset;";

		$sql[] = "CREATE TABLE {$prefix}gp_referrals (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			referrer_id BIGINT UNSIGNED NOT NULL,
			referee_id BIGINT UNSIGNED NULL,
			code VARCHAR(40) NOT NULL,
			campaign_id BIGINT UNSIGNED NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			attributed_order_id BIGINT UNSIGNED NULL,
			first_order_rewarded TINYINT(1) NOT NULL DEFAULT 0,
			recurring_orders_count INT NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			converted_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY code (code),
			KEY referrer_id (referrer_id),
			KEY referee_id (referee_id),
			KEY status (status)
		) $charset;";

		$sql[] = "CREATE TABLE {$prefix}gp_referral_clicks (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			code VARCHAR(40) NOT NULL,
			campaign_id BIGINT UNSIGNED NULL,
			visitor_hash VARCHAR(64) NULL,
			landing_url TEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY code (code),
			KEY created_at (created_at)
		) $charset;";

		$sql[] = "CREATE TABLE {$prefix}gp_analytics_events (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id VARCHAR(64) NOT NULL,
			customer_id BIGINT UNSIGNED NULL,
			event_type VARCHAR(40) NOT NULL,
			product_id BIGINT UNSIGNED NULL,
			order_id BIGINT UNSIGNED NULL,
			channel VARCHAR(40) NULL,
			utm_source VARCHAR(191) NULL,
			utm_medium VARCHAR(191) NULL,
			utm_campaign VARCHAR(191) NULL,
			touch VARCHAR(20) NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY session_id (session_id),
			KEY event_type (event_type),
			KEY created_at (created_at),
			KEY customer_id (customer_id),
			KEY order_id (order_id)
		) $charset;";

		$sql[] = "CREATE TABLE {$prefix}gp_email_stats (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			email_key VARCHAR(100) NOT NULL,
			email_title VARCHAR(191) NULL,
			stat_date DATE NOT NULL,
			sent INT NOT NULL DEFAULT 0,
			opened INT NOT NULL DEFAULT 0,
			clicked INT NOT NULL DEFAULT 0,
			converted INT NOT NULL DEFAULT 0,
			revenue DOUBLE NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY email_day (email_key, stat_date)
		) $charset;";

		$sql[] = "CREATE TABLE {$prefix}gp_ai_predictions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			subject_type VARCHAR(20) NOT NULL,
			subject_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			kind VARCHAR(40) NOT NULL,
			score DECIMAL(12,4) NOT NULL DEFAULT 0,
			confidence DECIMAL(5,2) NOT NULL DEFAULT 0,
			payload LONGTEXT NULL,
			generated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY subject_kind (subject_type, subject_id, kind),
			KEY kind (kind)
		) $charset;";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	/**
	 * Seed default rules, tiers, badges, and a referral campaign.
	 *
	 * @return void
	 */
	public static function seed() {
		global $wpdb;

		$rules_table = esc_sql( Ciwp::table( 'point_rules' ) );
		$count       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$rules_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( 0 === $count ) {
			$defaults = array(
				array( 'Purchase points', 'purchase', 0, 1, 0 ),
				array( 'Product review', 'review', 50, 0, 10 ),
				array( 'Welcome / signup', 'signup', 100, 0, 20 ),
				array( 'First purchase bonus', 'first_purchase', 200, 0, 30 ),
				array( 'Birthday reward', 'birthday', 100, 0, 40 ),
				array( 'Social sharing', 'social', 25, 0, 50 ),
			);

			foreach ( $defaults as $row ) {
				$wpdb->insert(
					$rules_table,
					array(
						'name'       => $row[0],
						'source'     => $row[1],
						'enabled'    => 1,
						'points'     => $row[2],
						'rate'       => $row[3],
						'sort_order' => $row[4],
					),
					array( '%s', '%s', '%d', '%d', '%f', '%d' )
				);
			}
		}

		$tiers_table = esc_sql( Ciwp::table( 'vip_tiers' ) );
		$tier_count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tiers_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( 0 === $tier_count ) {
			$tiers = array(
				array( 'bronze', 'Bronze', '#b45309', 'spending', 0, 10, 1, array( 'Standard loyalty earning' ) ),
				array( 'silver', 'Silver', '#64748b', 'spending', 250, 20, 0, array( 'Priority support', 'Birthday bonus' ) ),
				array( 'gold', 'Gold', '#ca8a04', 'spending', 1000, 30, 0, array( 'Exclusive rewards', 'Early product access' ) ),
				array( 'platinum', 'Platinum', '#0f766e', 'spending', 5000, 40, 0, array( 'VIP-only offers', 'Highest earn rate' ) ),
			);

			foreach ( $tiers as $tier ) {
				$wpdb->insert(
					$tiers_table,
					array(
						'slug'            => $tier[0],
						'name'            => $tier[1],
						'color'           => $tier[2],
						'qualifier_type'  => $tier[3],
						'qualifier_value' => $tier[4],
						'sort_order'      => $tier[5],
						'is_default'      => $tier[6],
						'benefits'        => wp_json_encode( $tier[7] ),
					)
				);
			}
		}

		$badges_table = esc_sql( Ciwp::table( 'badges' ) );
		$badge_count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$badges_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( 0 === $badge_count ) {
			$badges = array(
				array( 'first-order', 'First Order', 'Placed your first order', 'orders', 1, 25 ),
				array( 'five-orders', 'Regular', 'Placed 5 orders', 'orders', 5, 50 ),
				array( 'big-spender', 'Big Spender', 'Spent 500 in store currency', 'spend', 500, 75 ),
				array( 'reviewer', 'Reviewer', 'Left 5 product reviews', 'reviews', 5, 50 ),
				array( 'advocate', 'Advocate', 'Referred a customer who purchased', 'referrals', 1, 100 ),
				array( 'streak-3', 'On a Roll', '3-order purchase streak', 'streak', 3, 40 ),
			);

			foreach ( $badges as $badge ) {
				$wpdb->insert(
					$badges_table,
					array(
						'slug'            => $badge[0],
						'name'            => $badge[1],
						'description'     => $badge[2],
						'milestone_type'  => $badge[3],
						'milestone_value' => $badge[4],
						'points_bonus'    => $badge[5],
						'enabled'         => 1,
					)
				);
			}
		}

		$campaigns = esc_sql( Ciwp::table( 'referral_campaigns' ) );
		$camp_n    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$campaigns}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( 0 === $camp_n ) {
			$wpdb->insert(
				$campaigns,
				array(
					'name'                  => __( 'Refer a friend', 'myrvento-loyalty-for-woocommerce' ),
					'enabled'               => 1,
					'first_order_points'    => 200,
					'referee_signup_points' => 50,
					'recurring_points'      => 50,
					'cookie_days'           => 30,
				)
			);
		}

		$rewards = esc_sql( Ciwp::table( 'rewards' ) );
		$rew_n   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$rewards}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( 0 === $rew_n ) {
			$seed_rewards = array(
				array( '10% off', 'coupon_percent', 200, array( 'amount' => 10 ) ),
				array( '5 off order', 'coupon_fixed', 150, array( 'amount' => 5 ) ),
				array( 'Free shipping', 'free_shipping', 100, array() ),
			);

			foreach ( $seed_rewards as $reward ) {
				$wpdb->insert(
					$rewards,
					array(
						'name'        => $reward[0],
						'type'        => $reward[1],
						'points_cost' => $reward[2],
						'enabled'     => 1,
						'config'      => wp_json_encode( $reward[3] ),
					)
				);
			}
		}
	}
}
