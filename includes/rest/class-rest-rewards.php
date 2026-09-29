<?php
/**
 * Rewards REST.
 *
 * @package Myrvento
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rewards routes.
 */
class Myrvento_REST_Rewards {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register() {
		$ns = Myrvento_REST::NAMESPACE;

		register_rest_route(
			$ns,
			'/rewards',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'list_rewards' ),
					'permission_callback' => array( 'Myrvento_REST', 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create' ),
					'permission_callback' => array( 'Myrvento_REST', 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/rewards/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'update' ),
					'permission_callback' => array( 'Myrvento_REST', 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete' ),
					'permission_callback' => array( 'Myrvento_REST', 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/redemptions',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'redemptions' ),
				'permission_callback' => array( 'Myrvento_REST', 'can_manage' ),
			)
		);
	}

	/**
	 * List rewards.
	 *
	 * @return WP_REST_Response
	 */
	public static function list_rewards() {
		$out = array();
		foreach ( Myrvento_Rewards::all() as $reward ) {
			$out[] = Myrvento_Rewards::to_array( $reward );
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
		$id = Myrvento_Rewards::save( self::payload( $request ) );
		if ( is_wp_error( $id ) ) {
			return Myrvento_REST::error( $id );
		}
		return rest_ensure_response( Myrvento_Rewards::to_array( Myrvento_Rewards::get( $id ) ) );
	}

	/**
	 * Update.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function update( $request ) {
		$id = Myrvento_Rewards::save( self::payload( $request ), (int) $request['id'] );
		if ( is_wp_error( $id ) ) {
			return Myrvento_REST::error( $id );
		}
		return rest_ensure_response( Myrvento_Rewards::to_array( Myrvento_Rewards::get( $id ) ) );
	}

	/**
	 * Delete.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function delete( $request ) {
		Myrvento_Rewards::delete( (int) $request['id'] );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	/**
	 * Redemption history.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function redemptions( $request ) {
		$data  = Myrvento_Rewards::redemptions(
			array(
				'page'     => $request->get_param( 'page' ),
				'per_page' => $request->get_param( 'per_page' ),
			)
		);
		$items = array();

		foreach ( $data['items'] as $row ) {
			$user     = get_userdata( (int) $row->customer_id );
			$reward   = Myrvento_Rewards::get( (int) $row->reward_id );
			$items[]  = array(
				'id'           => (int) $row->id,
				'customer_id'  => (int) $row->customer_id,
				'customer'     => $user ? $user->display_name : '',
				'email'        => $user ? $user->user_email : '',
				'reward_id'    => (int) $row->reward_id,
				'reward'       => $reward ? $reward->name : '',
				'points_spent' => (int) $row->points_spent,
				'coupon_code'  => $row->coupon_code,
				'status'       => $row->status,
				'created_at'   => $row->created_at,
			);
		}

		return rest_ensure_response(
			array(
				'items' => $items,
				'total' => $data['total'],
			)
		);
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
