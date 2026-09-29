<?php
/**
 * Customer balances REST.
 *
 * @package Myrvento
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables have no core API.

/**
 * Customers routes.
 */
class Myrvento_REST_Customers {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register() {
		$ns = Myrvento_REST::NAMESPACE;

		register_rest_route(
			$ns,
			'/customers',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'list_customers' ),
				'permission_callback' => array( 'Myrvento_REST', 'can_manage' ),
			)
		);

		register_rest_route(
			$ns,
			'/customers/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_customer' ),
				'permission_callback' => array( 'Myrvento_REST', 'can_manage' ),
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
		$balances = esc_sql( Myrvento::table( 'points_balances' ) );
		$tiers    = esc_sql( Myrvento::table( 'vip_tiers' ) );
		$users    = $wpdb->users;

		$like = $search ? '%' . $wpdb->esc_like( $search ) . '%' : '';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are escaped.
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$balances} b INNER JOIN {$users} u ON u.ID = b.customer_id WHERE ( %s = '' OR u.user_login LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s )",
				$like,
				$like,
				$like,
				$like
			)
		);
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.*, u.display_name, u.user_email, t.name AS tier_name, t.color AS tier_color
				FROM {$balances} b
				INNER JOIN {$users} u ON u.ID = b.customer_id
				LEFT JOIN {$tiers} t ON t.id = b.tier_id
				WHERE ( %s = '' OR u.user_login LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s )
				ORDER BY b.available DESC, b.lifetime_earned DESC
				LIMIT %d OFFSET %d",
				$like,
				$like,
				$like,
				$like,
				$per_page,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

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
			return new WP_REST_Response( array( 'message' => __( 'Customer not found.', 'myrvento-loyalty-for-woocommerce' ) ), 404 );
		}

		$balance  = Myrvento_Points_Ledger::get_balance( $id );
		$history  = Myrvento_Points_Ledger::get_history( $id, array( 'page' => 1, 'per_page' => 50 ) );
		$tier     = $balance->tier_id ? Myrvento_VIP_Tiers::get( (int) $balance->tier_id ) : null;
		$stats    = Myrvento_Referral_Program::stats_for( $id );

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
				'tier'            => Myrvento_VIP_Tiers::to_array( $tier ),
				'history'         => $history['items'],
				'referral'        => array(
					'code'  => Myrvento_Referral_Program::get_or_create_code( $id ),
					'stats' => $stats,
				),
				'birthday'        => get_user_meta( $id, 'myrvento_birthday', true ),
				'streak'          => (int) get_user_meta( $id, 'myrvento_streak_count', true ),
			)
		);
	}
}
