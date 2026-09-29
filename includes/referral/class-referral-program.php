<?php
/**
 * Referral codes and customer referral records.
 *
 * @package Ciwp
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables have no core API.

/**
 * Referral program.
 */
class Ciwp_Referral_Program {

	/**
	 * Get or create a unique referral code for a customer.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public static function get_or_create_code( $user_id ) {
		$code = get_user_meta( $user_id, 'gp_referral_code', true );
		if ( is_string( $code ) && '' !== $code ) {
			return $code;
		}

		$secret = Ciwp::hash_secret();
		$base   = strtoupper( substr( hash( 'sha256', $user_id . '|' . $secret ), 0, 8 ) );
		$code   = $base;
		$i      = 0;

		while ( self::find_referrer_by_code( $code ) && $i < 10 ) {
			++$i;
			$code = strtoupper( substr( hash( 'sha256', $user_id . '|' . $i . '|' . $secret ), 0, 8 ) );
		}

		update_user_meta( $user_id, 'gp_referral_code', $code );
		return $code;
	}

	/**
	 * Find the user who owns a code.
	 *
	 * @param string $code Code.
	 * @return int
	 */
	public static function find_referrer_by_code( $code ) {
		$code = strtoupper( sanitize_text_field( $code ) );
		if ( '' === $code ) {
			return 0;
		}

		$users = get_users(
			array(
				'meta_key'   => 'gp_referral_code', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $code, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'number'     => 1,
				'fields'     => 'ID',
			)
		);

		return $users ? (int) $users[0] : 0;
	}

	/**
	 * Share URL for a customer.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public static function share_url( $user_id ) {
		$code     = self::get_or_create_code( $user_id );
		$param    = Ciwp_Settings::get_value( 'referral_param', 'gp_ref' );
		$campaign = self::active_campaign();
		$base     = home_url( '/' );

		if ( $campaign && ! empty( $campaign->landing_page ) ) {
			$page = get_page_by_path( $campaign->landing_page );
			if ( $page ) {
				$base = get_permalink( $page );
			} else {
				$base = home_url( '/' . ltrim( $campaign->landing_page, '/' ) );
			}
		}

		return add_query_arg( $param, $code, $base );
	}

	/**
	 * Currently active campaign (first enabled in-window).
	 *
	 * @return object|null
	 */
	public static function active_campaign() {
		global $wpdb;

		$table = esc_sql( Ciwp::table( 'referral_campaigns' ) );
		$now   = current_time( 'mysql' );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE enabled = 1 AND (starts_at IS NULL OR starts_at <= %s) AND (ends_at IS NULL OR ends_at >= %s) ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$now,
				$now
			)
		);

		return $row;
	}

	/**
	 * All campaigns.
	 *
	 * @return array<int, object>
	 */
	public static function campaigns() {
		global $wpdb;

		$table = esc_sql( Ciwp::table( 'referral_campaigns' ) );
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $rows ? $rows : array();
	}

	/**
	 * Get campaign.
	 *
	 * @param int $id ID.
	 * @return object|null
	 */
	public static function get_campaign( $id ) {
		global $wpdb;

		$table = esc_sql( Ciwp::table( 'referral_campaigns' ) );
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $id
			)
		);
	}

	/**
	 * Save campaign.
	 *
	 * @param array<string, mixed> $data Data.
	 * @param int                  $id   ID.
	 * @return int|WP_Error
	 */
	public static function save_campaign( $data, $id = 0 ) {
		global $wpdb;

		$table = esc_sql( Ciwp::table( 'referral_campaigns' ) );
		$name  = sanitize_text_field( $data['name'] ?? '' );
		if ( '' === $name ) {
			return new WP_Error( 'gp_campaign_name', __( 'Campaign name is required.', 'myrvento-loyalty-for-woocommerce' ) );
		}

		$row = array(
			'name'                  => $name,
			'enabled'               => ! empty( $data['enabled'] ) ? 1 : 0,
			'first_order_points'    => (int) ( $data['first_order_points'] ?? 0 ),
			'referee_signup_points' => (int) ( $data['referee_signup_points'] ?? 0 ),
			'recurring_points'      => (int) ( $data['recurring_points'] ?? 0 ),
			'cookie_days'           => (int) ( $data['cookie_days'] ?? 30 ),
			'landing_page'          => sanitize_title( $data['landing_page'] ?? '' ),
			'starts_at'             => ! empty( $data['starts_at'] ) ? sanitize_text_field( $data['starts_at'] ) : null,
			'ends_at'               => ! empty( $data['ends_at'] ) ? sanitize_text_field( $data['ends_at'] ) : null,
		);

		if ( $id ) {
			$wpdb->update( $table, $row, array( 'id' => (int) $id ) );
			return (int) $id;
		}

		$wpdb->insert( $table, $row );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete campaign.
	 *
	 * @param int $id ID.
	 * @return bool
	 */
	public static function delete_campaign( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( esc_sql( Ciwp::table( 'referral_campaigns' ) ), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Find an open referral row for a referee.
	 *
	 * @param int $referee_id Referee user ID.
	 * @return object|null
	 */
	public static function get_for_referee( $referee_id ) {
		global $wpdb;

		$table = esc_sql( Ciwp::table( 'referrals' ) );
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE referee_id = %d ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $referee_id
			)
		);
	}

	/**
	 * Create or update a referral attribution row.
	 *
	 * @param int    $referrer_id Referrer.
	 * @param string $code        Code.
	 * @param int    $referee_id  Referee (0 if unknown).
	 * @param string $status      Status.
	 * @return int Referral row ID.
	 */
	public static function upsert( $referrer_id, $code, $referee_id = 0, $status = 'clicked' ) {
		global $wpdb;

		$table     = esc_sql( Ciwp::table( 'referrals' ) );
		$campaign  = self::active_campaign();
		$code      = strtoupper( sanitize_text_field( $code ) );
		$existing  = null;

		if ( $referee_id ) {
			$existing = self::get_for_referee( $referee_id );
		}

		if ( $existing ) {
			$wpdb->update(
				$table,
				array(
					'status' => $status,
				),
				array( 'id' => (int) $existing->id )
			);
			return (int) $existing->id;
		}

		$wpdb->insert(
			$table,
			array(
				'referrer_id' => (int) $referrer_id,
				'referee_id'  => $referee_id ? (int) $referee_id : null,
				'code'        => $code,
				'campaign_id' => $campaign ? (int) $campaign->id : null,
				'status'      => $status,
				'created_at'  => current_time( 'mysql' ),
			)
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * List referrals.
	 *
	 * @param array<string, mixed> $args Filters.
	 * @return array{items: array<int, object>, total: int}
	 */
	public static function list( $args = array() ) {
		global $wpdb;

		$table    = esc_sql( Ciwp::table( 'referrals' ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;
		$status   = ! empty( $args['status'] ) ? sanitize_key( $args['status'] ) : '';
		$referrer = ! empty( $args['referrer_id'] ) ? (int) $args['referrer_id'] : 0;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is escaped.
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE ( %s = '' OR status = %s ) AND ( %d = 0 OR referrer_id = %d )",
				$status,
				$status,
				$referrer,
				$referrer
			)
		);
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE ( %s = '' OR status = %s ) AND ( %d = 0 OR referrer_id = %d ) ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
				$status,
				$status,
				$referrer,
				$referrer,
				$per_page,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Click log.
	 *
	 * @param array<string, mixed> $args Filters.
	 * @return array{items: array<int, object>, total: int}
	 */
	public static function clicks( $args = array() ) {
		global $wpdb;

		$table    = esc_sql( Ciwp::table( 'referral_clicks' ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $table ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$items = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . $table . ' ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$per_page,
				$offset
			)
		);

		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Stats for a referrer.
	 *
	 * @param int $user_id User ID.
	 * @return array<string, int>
	 */
	public static function stats_for( $user_id ) {
		global $wpdb;

		$table = esc_sql( Ciwp::table( 'referrals' ) );
		$code  = self::get_or_create_code( $user_id );

		$clicks = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . esc_sql( Ciwp::table( 'referral_clicks' ) ) . ' WHERE code = %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$code
			)
		);

		$signed = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . $table . ' WHERE referrer_id = %d AND referee_id IS NOT NULL', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$user_id
			)
		);

		$converted = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . $table . " WHERE referrer_id = %d AND status IN ('converted','rewarded')", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$user_id
			)
		);

		return array(
			'clicks'     => $clicks,
			'signups'    => $signed,
			'converted'  => $converted,
		);
	}
}
