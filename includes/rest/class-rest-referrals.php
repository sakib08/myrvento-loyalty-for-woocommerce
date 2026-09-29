<?php
/**
 * Referrals REST.
 *
 * @package Myrvento
 */

defined( 'ABSPATH' ) || exit;

/**
 * Referral routes.
 */
class Myrvento_REST_Referrals {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register() {
		$ns = Myrvento_REST::NAMESPACE;

		register_rest_route(
			$ns,
			'/referral-campaigns',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'list_campaigns' ),
					'permission_callback' => array( 'Myrvento_REST', 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_campaign' ),
					'permission_callback' => array( 'Myrvento_REST', 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/referral-campaigns/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'update_campaign' ),
					'permission_callback' => array( 'Myrvento_REST', 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete_campaign' ),
					'permission_callback' => array( 'Myrvento_REST', 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/referrals',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'list_referrals' ),
				'permission_callback' => array( 'Myrvento_REST', 'can_manage' ),
			)
		);

		register_rest_route(
			$ns,
			'/referral-clicks',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'clicks' ),
				'permission_callback' => array( 'Myrvento_REST', 'can_manage' ),
			)
		);
	}

	/**
	 * List campaigns.
	 *
	 * @return WP_REST_Response
	 */
	public static function list_campaigns() {
		$out = array();
		foreach ( Myrvento_Referral_Program::campaigns() as $row ) {
			$out[] = self::format_campaign( $row );
		}
		return rest_ensure_response( $out );
	}

	/**
	 * Create campaign.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function create_campaign( $request ) {
		$id = Myrvento_Referral_Program::save_campaign( self::payload( $request ) );
		if ( is_wp_error( $id ) ) {
			return Myrvento_REST::error( $id );
		}
		return rest_ensure_response( self::format_campaign( Myrvento_Referral_Program::get_campaign( $id ) ) );
	}

	/**
	 * Update campaign.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function update_campaign( $request ) {
		$id = Myrvento_Referral_Program::save_campaign( self::payload( $request ), (int) $request['id'] );
		if ( is_wp_error( $id ) ) {
			return Myrvento_REST::error( $id );
		}
		return rest_ensure_response( self::format_campaign( Myrvento_Referral_Program::get_campaign( $id ) ) );
	}

	/**
	 * Delete campaign.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function delete_campaign( $request ) {
		Myrvento_Referral_Program::delete_campaign( (int) $request['id'] );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	/**
	 * List referrals.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function list_referrals( $request ) {
		$data  = Myrvento_Referral_Program::list(
			array(
				'page'        => $request->get_param( 'page' ),
				'per_page'    => $request->get_param( 'per_page' ),
				'status'      => $request->get_param( 'status' ),
				'referrer_id' => $request->get_param( 'referrer_id' ),
			)
		);
		$items = array();

		foreach ( $data['items'] as $row ) {
			$referrer = get_userdata( (int) $row->referrer_id );
			$referee  = $row->referee_id ? get_userdata( (int) $row->referee_id ) : null;
			$items[]  = array(
				'id'                     => (int) $row->id,
				'code'                   => $row->code,
				'status'                 => $row->status,
				'referrer_id'            => (int) $row->referrer_id,
				'referrer'               => $referrer ? $referrer->display_name : '',
				'referrer_email'         => $referrer ? $referrer->user_email : '',
				'referee_id'             => $row->referee_id ? (int) $row->referee_id : null,
				'referee'                => $referee ? $referee->display_name : '',
				'attributed_order_id'    => $row->attributed_order_id ? (int) $row->attributed_order_id : null,
				'first_order_rewarded'   => (bool) $row->first_order_rewarded,
				'recurring_orders_count' => (int) $row->recurring_orders_count,
				'created_at'             => $row->created_at,
				'converted_at'           => $row->converted_at,
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
	 * Clicks.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function clicks( $request ) {
		return rest_ensure_response(
			Myrvento_Referral_Program::clicks(
				array(
					'page'     => $request->get_param( 'page' ),
					'per_page' => $request->get_param( 'per_page' ),
				)
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

	/**
	 * Format campaign.
	 *
	 * @param object|null $row Campaign.
	 * @return array<string, mixed>
	 */
	private static function format_campaign( $row ) {
		if ( ! $row ) {
			return array();
		}

		return array(
			'id'                    => (int) $row->id,
			'name'                  => $row->name,
			'enabled'               => (bool) $row->enabled,
			'first_order_points'    => (int) $row->first_order_points,
			'referee_signup_points' => (int) $row->referee_signup_points,
			'recurring_points'      => (int) $row->recurring_points,
			'cookie_days'           => (int) $row->cookie_days,
			'landing_page'          => $row->landing_page,
			'starts_at'             => $row->starts_at,
			'ends_at'               => $row->ends_at,
		);
	}
}
