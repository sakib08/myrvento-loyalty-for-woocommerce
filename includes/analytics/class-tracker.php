<?php
/**
 * Storefront event + UTM tracker for funnel and attribution.
 *
 * @package GrowthPilot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Analytics tracker.
 */
class GrowthPilot_Analytics_Tracker {

	/**
	 * Hooks.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'capture_utm' ), 6 );
		add_action( 'woocommerce_add_to_cart', array( $this, 'on_add_to_cart' ), 20, 6 );
		add_action( 'template_redirect', array( $this, 'on_template' ), 20 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'on_order' ), 30, 3 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'on_store_api_order' ), 30, 1 );
		add_action( 'woocommerce_email_sent', array( $this, 'on_email_sent' ), 10, 3 );
		add_action( 'woocommerce_email_header', array( $this, 'on_email_header' ), 10, 2 );
		add_action( 'woocommerce_email_footer', array( $this, 'on_email_footer' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_tracker' ) );
	}

	/**
	 * Current WooCommerce email id while rendering.
	 *
	 * @var string
	 */
	private $current_email_key = '';

	/**
	 * Persist UTM + session cookies.
	 *
	 * @return void
	 */
	public function capture_utm() {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		self::session_id();

		$source   = isset( $_GET['utm_source'] ) ? sanitize_text_field( wp_unslash( $_GET['utm_source'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$medium   = isset( $_GET['utm_medium'] ) ? sanitize_text_field( wp_unslash( $_GET['utm_medium'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$campaign = isset( $_GET['utm_campaign'] ) ? sanitize_text_field( wp_unslash( $_GET['utm_campaign'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$ref      = isset( $_GET['gp_ref'] ) ? sanitize_text_field( wp_unslash( $_GET['gp_ref'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( '' === $source && '' !== $ref ) {
			$source   = 'referral';
			$medium   = 'referral';
			$campaign = $ref;
		}

		if ( '' === $source ) {
			return;
		}

		$existing = self::utm();
		$touch    = array(
			'source'   => $source,
			'medium'   => $medium,
			'campaign' => $campaign,
			'at'       => time(),
		);

		if ( empty( $existing['first'] ) ) {
			$existing['first'] = $touch;
		}
		$existing['last'] = $touch;
		self::set_cookie( 'gp_utm', wp_json_encode( $existing ) );
	}

	/**
	 * Session id cookie.
	 *
	 * @return string
	 */
	public static function session_id() {
		if ( ! empty( $_COOKIE['gp_sid'] ) ) {
			return sanitize_text_field( wp_unslash( $_COOKIE['gp_sid'] ) );
		}

		$sid = wp_generate_password( 16, false, false );
		self::set_cookie( 'gp_sid', $sid );
		$_COOKIE['gp_sid'] = $sid;
		return $sid;
	}

	/**
	 * Decoded UTM cookie.
	 *
	 * @return array<string, mixed>
	 */
	public static function utm() {
		if ( empty( $_COOKIE['gp_utm'] ) ) {
			return array();
		}

		$raw = json_decode( wp_unslash( $_COOKIE['gp_utm'] ), true );
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Record an analytics event.
	 *
	 * @param string               $type Event type.
	 * @param array<string, mixed> $args Extra.
	 * @return void
	 */
	public static function record( $type, $args = array() ) {
		global $wpdb;

		$allowed = array( 'visit', 'product_view', 'add_to_cart', 'checkout', 'purchase', 'email_open', 'email_click' );
		$type    = sanitize_key( $type );
		if ( ! in_array( $type, $allowed, true ) ) {
			return;
		}

		$session = isset( $args['session_id'] ) ? sanitize_text_field( $args['session_id'] ) : self::session_id();
		$utm     = self::utm();
		$last    = isset( $utm['last'] ) && is_array( $utm['last'] ) ? $utm['last'] : array();
		$first   = isset( $utm['first'] ) && is_array( $utm['first'] ) ? $utm['first'] : array();
		$touch   = ! empty( $args['touch'] ) ? $args['touch'] : 'last';
		$use     = ( 'first' === $touch ) ? $first : $last;

		$wpdb->insert(
			GrowthPilot::table( 'analytics_events' ),
			array(
				'session_id'   => $session,
				'customer_id'  => isset( $args['customer_id'] ) ? (int) $args['customer_id'] : ( is_user_logged_in() ? get_current_user_id() : null ),
				'event_type'   => $type,
				'product_id'   => isset( $args['product_id'] ) ? (int) $args['product_id'] : null,
				'order_id'     => isset( $args['order_id'] ) ? (int) $args['order_id'] : null,
				'channel'      => isset( $args['channel'] ) ? sanitize_key( $args['channel'] ) : sanitize_key( $use['source'] ?? '' ),
				'utm_source'   => sanitize_text_field( $use['source'] ?? '' ),
				'utm_medium'   => sanitize_text_field( $use['medium'] ?? '' ),
				'utm_campaign' => sanitize_text_field( $use['campaign'] ?? '' ),
				'touch'        => sanitize_key( $touch ),
				'created_at'   => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Add-to-cart from WooCommerce.
	 *
	 * @param string $cart_id      Cart item key.
	 * @param int    $product_id   Product ID.
	 * @param int    $quantity     Qty.
	 * @param int    $variation_id Variation ID.
	 * @param array  $variation    Variation.
	 * @param array  $cart_item    Cart item.
	 * @return void
	 */
	public function on_add_to_cart( $cart_id, $product_id, $quantity, $variation_id, $variation, $cart_item ) {
		unset( $cart_id, $quantity, $variation_id, $variation, $cart_item );
		self::record( 'add_to_cart', array( 'product_id' => (int) $product_id, 'channel' => 'store' ) );
	}

	/**
	 * Checkout + visit flags.
	 *
	 * @return void
	 */
	public function on_template() {
		if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page() ) {
			if ( empty( WC()->session ) || ! WC()->session->get( 'gp_checkout_tracked' ) ) {
				self::record( 'checkout', array( 'channel' => 'store' ) );
				if ( WC()->session ) {
					WC()->session->set( 'gp_checkout_tracked', 1 );
				}
			}
		}
	}

	/**
	 * Classic checkout order.
	 *
	 * @param int      $order_id Order ID.
	 * @param array    $posted   Posted data.
	 * @param WC_Order $order    Order.
	 * @return void
	 */
	public function on_order( $order_id, $posted, $order ) {
		unset( $posted );
		self::stamp_and_purchase( $order ? $order : wc_get_order( $order_id ) );
	}

	/**
	 * Store API checkout.
	 *
	 * @param WC_Order $order Order.
	 * @return void
	 */
	public function on_store_api_order( $order ) {
		self::stamp_and_purchase( $order );
	}

	/**
	 * Stamp UTM on the order and record purchase.
	 *
	 * @param WC_Order|false $order Order.
	 * @return void
	 */
	public static function stamp_and_purchase( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( $order->get_meta( '_gp_purchase_tracked' ) ) {
			return;
		}

		$utm   = self::utm();
		$first = isset( $utm['first'] ) && is_array( $utm['first'] ) ? $utm['first'] : array();
		$last  = isset( $utm['last'] ) && is_array( $utm['last'] ) ? $utm['last'] : array();

		if ( $first ) {
			$order->update_meta_data( '_gp_first_source', sanitize_text_field( $first['source'] ?? '' ) );
			$order->update_meta_data( '_gp_first_medium', sanitize_text_field( $first['medium'] ?? '' ) );
			$order->update_meta_data( '_gp_first_campaign', sanitize_text_field( $first['campaign'] ?? '' ) );
		}
		if ( $last ) {
			$order->update_meta_data( '_gp_last_source', sanitize_text_field( $last['source'] ?? '' ) );
			$order->update_meta_data( '_gp_last_medium', sanitize_text_field( $last['medium'] ?? '' ) );
			$order->update_meta_data( '_gp_last_campaign', sanitize_text_field( $last['campaign'] ?? '' ) );
		}

		$order->update_meta_data( '_gp_session_id', self::session_id() );
		$order->update_meta_data( '_gp_purchase_tracked', 1 );
		$order->save();

		self::record(
			'purchase',
			array(
				'order_id'    => $order->get_id(),
				'customer_id' => (int) $order->get_customer_id(),
				'touch'       => 'last',
				'channel'     => sanitize_key( $last['source'] ?? 'direct' ),
			)
		);

		if ( $first ) {
			self::record(
				'purchase',
				array(
					'order_id'    => $order->get_id(),
					'customer_id' => (int) $order->get_customer_id(),
					'touch'       => 'first',
					'channel'     => sanitize_key( $first['source'] ?? 'direct' ),
				)
			);
		}
	}

	/**
	 * Count WooCommerce transactional emails sent.
	 *
	 * @param bool     $return Whether send succeeded (WC varies).
	 * @param string   $id     Email ID.
	 * @param WC_Email $email  Email object.
	 * @return void
	 */
	public function on_email_sent( $return, $id = '', $email = null ) {
		if ( is_object( $return ) && $return instanceof WC_Email ) {
			$email = $return;
			$id    = $email->id;
		}

		$key   = $id ? sanitize_key( $id ) : ( $email && isset( $email->id ) ? sanitize_key( $email->id ) : 'unknown' );
		$title = ( $email && isset( $email->title ) ) ? $email->title : $key;

		self::bump_email_stat( $key, $title, 'sent' );
	}

	/**
	 * Remember the email being rendered so the footer pixel can tag it.
	 *
	 * @param string        $heading Heading.
	 * @param WC_Email|null $email   Email.
	 * @return void
	 */
	public function on_email_header( $heading, $email = null ) {
		unset( $heading );
		if ( is_object( $email ) && ! empty( $email->id ) ) {
			$this->current_email_key = sanitize_key( $email->id );
		}
	}

	/**
	 * 1×1 open-tracking pixel in WooCommerce emails.
	 *
	 * @return void
	 */
	public function on_email_footer() {
		if ( '' === $this->current_email_key ) {
			return;
		}

		$url = add_query_arg(
			'email_key',
			rawurlencode( $this->current_email_key ),
			rest_url( 'growthpilot/v1/track/pixel' )
		);

		echo '<img src="' . esc_url( $url ) . '" width="1" height="1" alt="" style="display:block;height:1px;width:1px;border:0;" />';
	}

	/**
	 * Lightweight storefront tracker (visit + product view). Cart/checkout/purchase stay on PHP hooks.
	 *
	 * @return void
	 */
	public function enqueue_tracker() {
		if ( is_admin() ) {
			return;
		}

		wp_register_script( 'growthpilot-track', false, array(), GROWTHPILOT_VERSION, true );
		wp_enqueue_script( 'growthpilot-track' );
		wp_localize_script(
			'growthpilot-track',
			'growthPilotTrack',
			array(
				'apiUrl'    => rest_url( 'growthpilot/v1/' ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'productId' => ( function_exists( 'is_product' ) && is_product() ) ? (int) get_queried_object_id() : 0,
			)
		);

		wp_add_inline_script(
			'growthpilot-track',
			'(function(){var c=window.growthPilotTrack||{};function send(t,e){e=e||{};e.type=t;try{var k="gp_"+t+"_"+(e.product_id||0);if(sessionStorage.getItem(k))return;sessionStorage.setItem(k,"1");}catch(err){}if(!c.apiUrl)return;fetch(c.apiUrl+"track",{method:"POST",headers:{"Content-Type":"application/json","X-WP-Nonce":c.nonce||""},credentials:"same-origin",body:JSON.stringify(e)}); }send("visit",{channel:"store"});if(c.productId)send("product_view",{product_id:c.productId,channel:"store"});})();'
		);
	}

	/**
	 * Increment a daily email counter.
	 *
	 * @param string $key   Email key.
	 * @param string $title Title.
	 * @param string $col   sent|opened|clicked|converted.
	 * @param float  $revenue Revenue bump.
	 * @return void
	 */
	public static function bump_email_stat( $key, $title, $col, $revenue = 0 ) {
		global $wpdb;

		$table = GrowthPilot::table( 'email_stats' );
		$day   = current_time( 'Y-m-d' );
		$cols  = array( 'sent', 'opened', 'clicked', 'converted' );
		if ( ! in_array( $col, $cols, true ) ) {
			return;
		}

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE email_key = %s AND stat_date = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$key,
				$day
			)
		);

		if ( $exists ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET {$col} = {$col} + 1, revenue = revenue + %f WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					(float) $revenue,
					(int) $exists
				)
			);
			return;
		}

		$row = array(
			'email_key'   => $key,
			'email_title' => sanitize_text_field( $title ),
			'stat_date'   => $day,
			'sent'        => 0,
			'opened'      => 0,
			'clicked'     => 0,
			'converted'   => 0,
			'revenue'     => (float) $revenue,
		);
		$row[ $col ] = 1;
		$wpdb->insert( $table, $row );
	}

	/**
	 * Set a cookie.
	 *
	 * @param string $name  Name.
	 * @param string $value Value.
	 * @return void
	 */
	private static function set_cookie( $name, $value ) {
		$days = (int) GrowthPilot_Settings::get_value( 'cookie_days', 30 );
		if ( headers_sent() ) {
			return;
		}
		setcookie( $name, $value, time() + ( max( 1, $days ) * DAY_IN_SECONDS ), COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), false );
		$_COOKIE[ $name ] = $value;
	}
}
