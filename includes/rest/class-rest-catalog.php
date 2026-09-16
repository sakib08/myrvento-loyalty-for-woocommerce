<?php
/**
 * Catalog search for product/category point rules.
 *
 * @package GrowthPilot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Catalog routes.
 */
class GrowthPilot_REST_Catalog {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register() {
		$ns = GrowthPilot_REST::NAMESPACE;

		register_rest_route(
			$ns,
			'/catalog/products',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'products' ),
				'permission_callback' => array( 'GrowthPilot_REST', 'can_manage' ),
			)
		);

		register_rest_route(
			$ns,
			'/catalog/categories',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'categories' ),
				'permission_callback' => array( 'GrowthPilot_REST', 'can_manage' ),
			)
		);
	}

	/**
	 * Search products.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function products( $request ) {
		$search = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$args   = array(
			'status' => 'publish',
			'limit'  => 20,
			'return' => 'objects',
		);

		if ( $search ) {
			$args['s'] = $search;
		}

		$products = wc_get_products( $args );
		$out      = array();

		foreach ( $products as $product ) {
			$out[] = array(
				'id'   => $product->get_id(),
				'name' => $product->get_name(),
			);
		}

		return rest_ensure_response( $out );
	}

	/**
	 * Search categories.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function categories( $request ) {
		$search = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$terms  = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => 20,
				'search'     => $search,
			)
		);

		$out = array();
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$out[] = array(
					'id'   => (int) $term->term_id,
					'name' => $term->name,
				);
			}
		}

		return rest_ensure_response( $out );
	}
}
