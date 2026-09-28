<?php
/**
 * Customer balances REST.
 *
 * @package GrowthPilot
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables have no core API.

/**
 * Customers routes.
 */
class GrowthPilot_REST_Customers {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register() {
		$ns = GrowthPilot_REST::NAMESPACE;

		register_rest_route(
			$ns,
			'/customers',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'list_customers' ),
				'permission_callback' => array( 'GrowthPilot_REST', 'can_manage' ),
			)
		);

		register_rest_route(
			$ns,
			'/customers/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_customer' ),
				'permission_callback' => array( 'GrowthPilot_REST', 'can_manage' ),
			)
		);
	}

	/**
	 * Paginated customer list.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function list_customers( $request ) {
		global $wpdb;

		$page     = max( 1, (int) $request->get_param( 'page' ) ?: 1 );
		$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ?: 20 ) );
		$search   = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$offset   = ( $page - 1 ) * $per_page;
		$balances = esc_sql( GrowthPilot::table( 'points_balances' ) );
		$tiers    = esc_sql( GrowthPilot::table( 'vip_tiers' ) );
		$users    = $wpdb->users;

		$where  = '1=1';
		$params = array();

		if ( $search ) {
			$like    = '%' . $wpdb->esc_like( $search ) . '%';
			$where  .= " AND (u.user_login LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s)";
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$count_sql = "SELECT COUNT(*) FROM {$balances} b INNER JOIN {$users} u ON u.ID = b.customer_id WHERE {$where}";
		$list_sql  = "SELECT b.*, u.display_name, u.user_email, t.name AS tier_name, t.color AS tier_color
			FROM {$balances} b
			INNER JOIN {$users} u ON u.ID = b.customer_id
			LEFT JOIN {$tiers} t ON t.id = b.tier_id
			WHERE {$where}
			ORDER BY b.available DESC, b.lifetime_earned DESC
			LIMIT %d OFFSET %d";

		if ( $params ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$items = $wpdb->get_results( $wpdb->prepare( $list_sql, array_merge( $params, array( $per_page, $offset ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} else {
			$total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$items = $wpdb->get_results( $wpdb->prepare( $list_sql, $per_page, $offset ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$out = array();
		foreach ( $items ? $items : array() as $row ) {
			$out[] = array(
				'id'              => (int) $row->customer_id,
				'name'            => $row->display_name,
				'email'           => $row->user_email,
				'available'       => (int) $row->available,
				'lifetime_earned' => (int) $row->lifetime_earned,
				'tier_id'         => $row->tier_id ? (int) $row->tier_id : null,
				'tier_name'       => $row->tier_name,
				'tier_color'      => $row->tier_color,
			);
		}

		return rest_ensure_response(
			array(
				'items' => $out,
				'total' => $total,
				'page'  => $page,
			)
		);
	}

	/**
	 * Customer detail.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_customer( $request ) {
		$id   = (int) $request['id'];
		$user = get_userdata( $id );

		if ( ! $user ) {
			return new WP_REST_Response( array( 'message' => __( 'Customer not found.', 'gp-ppros' ) ), 404 );
		}

		$balance  = GrowthPilot_Points_Ledger::get_balance( $id );
		$history  = GrowthPilot_Points_Ledger::get_history( $id, array( 'page' => 1, 'per_page' => 50 ) );
		$tier     = $balance->tier_id ? GrowthPilot_VIP_Tiers::get( (int) $balance->tier_id ) : null;
		$stats    = GrowthPilot_Referral_Program::stats_for( $id );

		return rest_ensure_response(
			array(
				'id'              => $id,
				'name'            => $user->display_name,
				'email'           => $user->user_email,
				'available'       => (int) $balance->available,
				'pending'         => (int) $balance->pending,
				'lifetime_earned' => (int) $balance->lifetime_earned,
				'lifetime_redeemed' => (int) $balance->lifetime_redeemed,
				'lifetime_expired'  => (int) $balance->lifetime_expired,
				'tier'            => GrowthPilot_VIP_Tiers::to_array( $tier ),
				'history'         => $history['items'],
				'referral'        => array(
					'code'  => GrowthPilot_Referral_Program::get_or_create_code( $id ),
					'stats' => $stats,
				),
				'birthday'        => get_user_meta( $id, 'gp_birthday', true ),
				'streak'          => (int) get_user_meta( $id, 'gp_streak_count', true ),
			)
		);
	}
}
