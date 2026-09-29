<?php
/**
 * VIP tiers REST.
 *
 * @package Ciwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tiers routes.
 */
class Ciwp_REST_Tiers {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register() {
		$ns = Ciwp_REST::NAMESPACE;

		register_rest_route(
			$ns,
			'/tiers',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'list_tiers' ),
					'permission_callback' => array( 'Ciwp_REST', 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create' ),
					'permission_callback' => array( 'Ciwp_REST', 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/tiers/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'update' ),
					'permission_callback' => array( 'Ciwp_REST', 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete' ),
					'permission_callback' => array( 'Ciwp_REST', 'can_manage' ),
				),
			)
		);
	}

	/**
	 * List tiers.
	 *
	 * @return WP_REST_Response
	 */
	public static function list_tiers() {
		$out = array();
		foreach ( Ciwp_VIP_Tiers::all() as $tier ) {
			$out[] = Ciwp_VIP_Tiers::to_array( $tier );
		}
		return rest_ensure_response( $out );
	}

	/**
	 * Create.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function create( $request ) {
		$id = Ciwp_VIP_Tiers::save( self::payload( $request ) );
		if ( is_wp_error( $id ) ) {
			return Ciwp_REST::error( $id );
		}
		return rest_ensure_response( Ciwp_VIP_Tiers::to_array( Ciwp_VIP_Tiers::get( $id ) ) );
	}

	/**
	 * Update.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function update( $request ) {
		$id = Ciwp_VIP_Tiers::save( self::payload( $request ), (int) $request['id'] );
		if ( is_wp_error( $id ) ) {
			return Ciwp_REST::error( $id );
		}
		return rest_ensure_response( Ciwp_VIP_Tiers::to_array( Ciwp_VIP_Tiers::get( $id ) ) );
	}

	/**
	 * Delete.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function delete( $request ) {
		Ciwp_VIP_Tiers::delete( (int) $request['id'] );
		return rest_ensure_response( array( 'deleted' => true ) );
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
}
