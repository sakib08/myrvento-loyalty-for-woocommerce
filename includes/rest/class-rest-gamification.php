<?php
/**
 * Gamification REST.
 *
 * @package Ciwp
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables have no core API.

/**
 * Gamification routes.
 */
class Ciwp_REST_Gamification {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register() {
		$ns = Ciwp_REST::NAMESPACE;

		register_rest_route(
			$ns,
			'/badges',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'list_badges' ),
					'permission_callback' => array( 'Ciwp_REST', 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_badge' ),
					'permission_callback' => array( 'Ciwp_REST', 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/badges/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'update_badge' ),
					'permission_callback' => array( 'Ciwp_REST', 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete_badge' ),
					'permission_callback' => array( 'Ciwp_REST', 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/challenges',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'list_challenges' ),
					'permission_callback' => array( 'Ciwp_REST', 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_challenge' ),
					'permission_callback' => array( 'Ciwp_REST', 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/challenges/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'update_challenge' ),
					'permission_callback' => array( 'Ciwp_REST', 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete_challenge' ),
					'permission_callback' => array( 'Ciwp_REST', 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/leaderboard',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'leaderboard' ),
				'permission_callback' => array( 'Ciwp_REST', 'can_manage' ),
			)
		);
	}

	/**
	 * List badges.
	 *
	 * @return WP_REST_Response
	 */
	public static function list_badges() {
		$out = array();
		foreach ( Ciwp_Gamification::badges() as $badge ) {
			$out[] = self::format_badge( $badge );
		}
		return rest_ensure_response( $out );
	}

	/**
	 * Create badge.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function create_badge( $request ) {
		$id = Ciwp_Gamification::save_badge( self::payload( $request ) );
		if ( is_wp_error( $id ) ) {
			return Ciwp_REST::error( $id );
		}
		return rest_ensure_response( self::format_badge( self::get_badge( $id ) ) );
	}

	/**
	 * Update badge.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function update_badge( $request ) {
		$id = Ciwp_Gamification::save_badge( self::payload( $request ), (int) $request['id'] );
		if ( is_wp_error( $id ) ) {
			return Ciwp_REST::error( $id );
		}
		return rest_ensure_response( self::format_badge( self::get_badge( $id ) ) );
	}

	/**
	 * Delete badge.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function delete_badge( $request ) {
		Ciwp_Gamification::delete_badge( (int) $request['id'] );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	/**
	 * List challenges.
	 *
	 * @return WP_REST_Response
	 */
	public static function list_challenges() {
		$out = array();
		foreach ( Ciwp_Gamification::challenges() as $row ) {
			$out[] = self::format_challenge( $row );
		}
		return rest_ensure_response( $out );
	}

	/**
	 * Create challenge.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function create_challenge( $request ) {
		$id = Ciwp_Gamification::save_challenge( self::payload( $request ) );
		if ( is_wp_error( $id ) ) {
			return Ciwp_REST::error( $id );
		}
		return rest_ensure_response( self::format_challenge( self::get_challenge( $id ) ) );
	}

	/**
	 * Update challenge.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function update_challenge( $request ) {
		$id = Ciwp_Gamification::save_challenge( self::payload( $request ), (int) $request['id'] );
		if ( is_wp_error( $id ) ) {
			return Ciwp_REST::error( $id );
		}
		return rest_ensure_response( self::format_challenge( self::get_challenge( $id ) ) );
	}

	/**
	 * Delete challenge.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function delete_challenge( $request ) {
		Ciwp_Gamification::delete_challenge( (int) $request['id'] );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	/**
	 * Leaderboard.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function leaderboard( $request ) {
		$limit = (int) $request->get_param( 'limit' );
		return rest_ensure_response( Ciwp_Gamification::leaderboard( $limit ? $limit : 20 ) );
	}

	/**
	 * Payload.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	private static function payload( $request ) {
		$params = $request->get_json_params();
		return is_array( $params ) ? $params : $request->get_params();
	}

	/**
	 * Get badge row.
	 *
	 * @param int $id ID.
	 * @return object|null
	 */
	private static function get_badge( $id ) {
		global $wpdb;
		$table = esc_sql( Ciwp::table( 'badges' ) );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Get challenge row.
	 *
	 * @param int $id ID.
	 * @return object|null
	 */
	private static function get_challenge( $id ) {
		global $wpdb;
		$table = esc_sql( Ciwp::table( 'challenges' ) );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Format badge.
	 *
	 * @param object|null $badge Badge.
	 * @return array<string, mixed>
	 */
	private static function format_badge( $badge ) {
		if ( ! $badge ) {
			return array();
		}

		return array(
			'id'               => (int) $badge->id,
			'slug'             => $badge->slug,
			'name'             => $badge->name,
			'description'      => $badge->description,
			'icon'             => $badge->icon,
			'milestone_type'   => $badge->milestone_type,
			'milestone_value'  => (int) $badge->milestone_value,
			'points_bonus'     => (int) $badge->points_bonus,
			'enabled'          => (bool) $badge->enabled,
		);
	}

	/**
	 * Format challenge.
	 *
	 * @param object|null $row Challenge.
	 * @return array<string, mixed>
	 */
	private static function format_challenge( $row ) {
		if ( ! $row ) {
			return array();
		}

		return array(
			'id'            => (int) $row->id,
			'name'          => $row->name,
			'description'   => $row->description,
			'type'          => $row->type,
			'target_value'  => (int) $row->target_value,
			'points_reward' => (int) $row->points_reward,
			'starts_at'     => $row->starts_at,
			'ends_at'       => $row->ends_at,
			'enabled'       => (bool) $row->enabled,
		);
	}
}
