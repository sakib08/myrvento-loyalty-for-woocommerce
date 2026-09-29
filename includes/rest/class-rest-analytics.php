<?php
/**
 * Analytics REST.
 *
 * @package Ciwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Analytics routes.
 */
class Ciwp_REST_Analytics {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register() {
		$ns = Ciwp_REST::NAMESPACE;

		$range_args = array(
			'from' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'to'   => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);

		foreach ( array( 'overview', 'customers', 'products', 'marketing' ) as $report ) {
			register_rest_route(
				$ns,
				'/analytics/' . $report,
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_' . $report ),
					'permission_callback' => array( 'Ciwp_REST', 'can_manage' ),
					'args'                => $range_args,
				)
			);
		}

		// Public on purpose: storefront and email beacons. They record anonymous events and return no private data.
		register_rest_route(
			$ns,
			'/track',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'track' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$ns,
			'/track/pixel',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'pixel' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Overview / revenue.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_overview( $request ) {
		$range = Ciwp_Analytics_Query::range( $request->get_param( 'from' ), $request->get_param( 'to' ) );
		return rest_ensure_response( Ciwp_Analytics_Revenue::overview( $range ) );
	}

	/**
	 * Customers.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_customers( $request ) {
		$range = Ciwp_Analytics_Query::range( $request->get_param( 'from' ), $request->get_param( 'to' ) );
		return rest_ensure_response( Ciwp_Analytics_Customers::report( $range ) );
	}

	/**
	 * Products.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_products( $request ) {
		$range = Ciwp_Analytics_Query::range( $request->get_param( 'from' ), $request->get_param( 'to' ) );
		return rest_ensure_response( Ciwp_Analytics_Products::report( $range ) );
	}

	/**
	 * Marketing.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_marketing( $request ) {
		$range = Ciwp_Analytics_Query::range( $request->get_param( 'from' ), $request->get_param( 'to' ) );
		return rest_ensure_response( Ciwp_Analytics_Marketing::report( $range ) );
	}

	/**
	 * Public storefront tracker.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function track( $request ) {
		$type = sanitize_key( (string) $request->get_param( 'type' ) );
		if ( ! in_array( $type, array( 'visit', 'product_view', 'email_open', 'email_click' ), true ) ) {
			return new WP_REST_Response( array( 'ok' => false ), 400 );
		}

		Ciwp_Analytics_Tracker::record(
			$type,
			array(
				'product_id' => (int) $request->get_param( 'product_id' ),
				'channel'    => sanitize_key( (string) $request->get_param( 'channel' ) ),
			)
		);

		if ( 'email_open' === $type ) {
			Ciwp_Analytics_Tracker::bump_email_stat(
				sanitize_key( (string) $request->get_param( 'email_key' ) ),
				'',
				'opened'
			);
		}

		if ( 'email_click' === $type ) {
			Ciwp_Analytics_Tracker::bump_email_stat(
				sanitize_key( (string) $request->get_param( 'email_key' ) ),
				'',
				'clicked'
			);
		}

		return rest_ensure_response( array( 'ok' => true ) );
	}

	/**
	 * 1×1 GIF for email opens.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return void
	 */
	public static function pixel( $request ) {
		$key = sanitize_key( (string) $request->get_param( 'email_key' ) );
		if ( $key ) {
			Ciwp_Analytics_Tracker::bump_email_stat( $key, '', 'opened' );
			Ciwp_Analytics_Tracker::record( 'email_open', array( 'channel' => 'email' ) );
		}

		$gif = base64_decode( 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		nocache_headers();
		header( 'Content-Type: image/gif' );
		header( 'Content-Length: ' . strlen( $gif ) );
		echo $gif; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}
}
