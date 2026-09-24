<?php
/**
 * Point rules and manual adjustments REST.
 *
 * @package GrowthPilot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Points routes.
 */
class GrowthPilot_REST_Points {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register() {
		$ns = GrowthPilot_REST::NAMESPACE;

		register_rest_route(
			$ns,
			'/point-rules',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'list_rules' ),
					'permission_callback' => array( 'GrowthPilot_REST', 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_rule' ),
					'permission_callback' => array( 'GrowthPilot_REST', 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/point-rules/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'update_rule' ),
					'permission_callback' => array( 'GrowthPilot_REST', 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete_rule' ),
					'permission_callback' => array( 'GrowthPilot_REST', 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/points/adjust',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'adjust' ),
				'permission_callback' => array( 'GrowthPilot_REST', 'can_manage' ),
			)
		);
	}

	/**
	 * List rules with object labels.
	 *
	 * @return WP_REST_Response
	 */
	public static function list_rules() {
		$rules = GrowthPilot_Points_Rules::all();
		$out   = array();

		foreach ( $rules as $rule ) {
			$out[] = self::format_rule( $rule );
		}

		return rest_ensure_response( $out );
	}

	/**
	 * Create rule.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function create_rule( $request ) {
		$id = GrowthPilot_Points_Rules::save( self::payload( $request ) );
		if ( is_wp_error( $id ) ) {
			return GrowthPilot_REST::error( $id );
		}

		return rest_ensure_response( self::format_rule( GrowthPilot_Points_Rules::get( $id ) ) );
	}

	/**
	 * Update rule.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function update_rule( $request ) {
		$id = GrowthPilot_Points_Rules::save( self::payload( $request ), (int) $request['id'] );
		if ( is_wp_error( $id ) ) {
			return GrowthPilot_REST::error( $id );
		}

		return rest_ensure_response( self::format_rule( GrowthPilot_Points_Rules::get( $id ) ) );
	}

	/**
	 * Delete rule.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function delete_rule( $request ) {
		GrowthPilot_Points_Rules::delete( (int) $request['id'] );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	/**
	 * Manual adjust.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function adjust( $request ) {
		$customer_id = (int) $request->get_param( 'customer_id' );
		$amount      = (int) $request->get_param( 'amount' );
		$description = sanitize_text_field( (string) $request->get_param( 'description' ) );

		if ( $customer_id <= 0 || 0 === $amount ) {
			return GrowthPilot_REST::error( new WP_Error( 'gp_adjust', __( 'Customer and non-zero amount are required.', 'gp_ppros' ) ) );
		}

		$args = array(
			'type'        => 'adjust',
			'description' => $description ? $description : __( 'Manual adjustment', 'gp_ppros' ),
			'created_by'  => get_current_user_id(),
		);

		if ( $amount > 0 ) {
			$result = GrowthPilot_Points_Ledger::credit( $customer_id, $amount, 'manual', $args );
		} else {
			$result = GrowthPilot_Points_Ledger::debit( $customer_id, abs( $amount ), 'manual', $args );
		}

		if ( is_wp_error( $result ) ) {
			return GrowthPilot_REST::error( $result );
		}

		return rest_ensure_response(
			array(
				'ledger_id' => $result,
				'balance'   => GrowthPilot_Points_Ledger::get_balance( $customer_id ),
			)
		);
	}

	/**
	 * Request payload.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	private static function payload( $request ) {
		$params = $request->get_json_params();
		return is_array( $params ) ? $params : $request->get_params();
	}

	/**
	 * Format a rule.
	 *
	 * @param object|null $rule Rule.
	 * @return array<string, mixed>
	 */
	private static function format_rule( $rule ) {
		if ( ! $rule ) {
			return array();
		}

		$label = '';
		if ( 'product' === $rule->object_type && $rule->object_id ) {
			$product = wc_get_product( $rule->object_id );
			$label   = $product ? $product->get_name() : (string) $rule->object_id;
		} elseif ( 'category' === $rule->object_type && $rule->object_id ) {
			$term  = get_term( (int) $rule->object_id, 'product_cat' );
			$label = ( $term && ! is_wp_error( $term ) ) ? $term->name : (string) $rule->object_id;
		}

		return array(
			'id'          => (int) $rule->id,
			'name'        => $rule->name,
			'source'      => $rule->source,
			'enabled'     => (bool) $rule->enabled,
			'points'      => (int) $rule->points,
			'rate'        => (float) $rule->rate,
			'object_id'   => $rule->object_id ? (int) $rule->object_id : null,
			'object_type' => $rule->object_type,
			'object_label'=> $label,
			'config'      => GrowthPilot::decode( $rule->config ),
			'sort_order'  => (int) $rule->sort_order,
		);
	}
}
