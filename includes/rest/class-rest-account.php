<?php
/**
 * Customer-facing REST (My Account).
 *
 * @package Ciwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Account routes.
 */
class Ciwp_REST_Account {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register() {
		$ns = Ciwp_REST::NAMESPACE;

		register_rest_route(
			$ns,
			'/account/loyalty',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'loyalty' ),
				'permission_callback' => array( 'Ciwp_REST', 'can_account' ),
			)
		);

		register_rest_route(
			$ns,
			'/account/redeem',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'redeem' ),
				'permission_callback' => array( 'Ciwp_REST', 'can_account' ),
			)
		);

		register_rest_route(
			$ns,
			'/account/referrals',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'referrals' ),
				'permission_callback' => array( 'Ciwp_REST', 'can_account' ),
			)
		);

		register_rest_route(
			$ns,
			'/account/share',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'share' ),
				'permission_callback' => array( 'Ciwp_REST', 'can_account' ),
			)
		);

		register_rest_route(
			$ns,
			'/account/birthday',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'birthday' ),
				'permission_callback' => array( 'Ciwp_REST', 'can_account' ),
			)
		);
	}

	/**
	 * Loyalty payload.
	 *
	 * @return WP_REST_Response
	 */
	public static function loyalty() {
		$user_id  = get_current_user_id();
		$balance  = Ciwp_Points_Ledger::get_balance( $user_id );
		$history  = Ciwp_Points_Ledger::get_history( $user_id, array( 'per_page' => 20 ) );
		$tier     = $balance->tier_id ? Ciwp_VIP_Tiers::get( (int) $balance->tier_id ) : Ciwp_VIP_Tiers::get_default();
		$rewards  = array();

		foreach ( Ciwp_Rewards::all( true ) as $reward ) {
			$rewards[] = Ciwp_Rewards::to_array( $reward );
		}

		$redemptions = Ciwp_Rewards::redemptions(
			array(
				'customer_id' => $user_id,
				'per_page'    => 20,
			)
		);

		return rest_ensure_response(
			array(
				'available'         => (int) $balance->available,
				'lifetime_earned'   => (int) $balance->lifetime_earned,
				'lifetime_redeemed' => (int) $balance->lifetime_redeemed,
				'points_name'       => Ciwp_Settings::get_value( 'points_name', 'Points' ),
				'tier'              => Ciwp_VIP_Tiers::to_array( $tier ),
				'history'           => $history['items'],
				'rewards'           => $rewards,
				'redemptions'       => $redemptions['items'],
				'badges'            => Ciwp_Gamification::customer_badges( $user_id ),
				'challenges'        => Ciwp_Gamification::customer_challenges( $user_id ),
				'streak'            => (int) get_user_meta( $user_id, 'gp_streak_count', true ),
				'birthday'          => get_user_meta( $user_id, 'gp_birthday', true ),
			)
		);
	}

	/**
	 * Redeem a reward.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function redeem( $request ) {
		$reward_id = (int) $request->get_param( 'reward_id' );
		$result    = Ciwp_Rewards::redeem( get_current_user_id(), $reward_id );

		if ( is_wp_error( $result ) ) {
			return Ciwp_REST::error( $result );
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Referral payload.
	 *
	 * @return WP_REST_Response
	 */
	public static function referrals() {
		$user_id  = get_current_user_id();
		$campaign = Ciwp_Referral_Program::active_campaign();
		$list     = Ciwp_Referral_Program::list(
			array(
				'referrer_id' => $user_id,
				'per_page'    => 50,
			)
		);

		return rest_ensure_response(
			array(
				'share'    => Ciwp_Share::payload( $user_id ),
				'stats'    => Ciwp_Referral_Program::stats_for( $user_id ),
				'campaign' => $campaign ? array(
					'name'                  => $campaign->name,
					'first_order_points'    => (int) $campaign->first_order_points,
					'referee_signup_points' => (int) $campaign->referee_signup_points,
					'recurring_points'      => (int) $campaign->recurring_points,
				) : null,
				'history'  => $list['items'],
			)
		);
	}

	/**
	 * Social share award.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function share( $request ) {
		$channel = sanitize_key( (string) $request->get_param( 'channel' ) );
		$result  = Ciwp_Points_Earner::award_social( get_current_user_id(), $channel ? $channel : 'copy' );

		if ( is_wp_error( $result ) ) {
			return rest_ensure_response(
				array(
					'awarded' => false,
					'message' => $result->get_error_message(),
				)
			);
		}

		return rest_ensure_response(
			array(
				'awarded' => true,
				'ledger_id' => $result,
			)
		);
	}

	/**
	 * Save birthday as mm-dd.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function birthday( $request ) {
		$date = sanitize_text_field( (string) $request->get_param( 'date' ) );
		$mmdd = '';

		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			$mmdd = substr( $date, 5 );
		} elseif ( preg_match( '/^\d{2}-\d{2}$/', $date ) ) {
			$mmdd = $date;
		} else {
			return Ciwp_REST::error( new WP_Error( 'gp_birthday', __( 'Use YYYY-MM-DD or MM-DD.', 'myrvento-loyalty-for-woocommerce' ) ) );
		}

		update_user_meta( get_current_user_id(), 'gp_birthday', $mmdd );

		return rest_ensure_response( array( 'birthday' => $mmdd ) );
	}
}
