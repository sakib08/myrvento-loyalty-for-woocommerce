<?php
/**
 * Orders, subscriptions, coupons, and customer activity.
 *
 * @package Ciwp
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables have no core API.

/**
 * WooCommerce operations.
 */
class Ciwp_Operations {

	/**
	 * Operations report.
	 *
	 * @return array<string, mixed>
	 */
	public static function report() {
		return array(
			'orders'        => self::orders(),
			'subscriptions' => self::subscriptions(),
			'coupons'       => self::coupons(),
			'activity'      => self::activity(),
		);
	}

	/**
	 * Status counts and the latest orders.
	 *
	 * @return array<string, mixed>
	 */
	public static function orders() {
		global $wpdb;

		$table  = esc_sql( $wpdb->prefix . 'wc_orders' );
		$counts = $wpdb->get_results(
			"SELECT status, COUNT(*) AS total FROM {$table} WHERE type = 'shop_order' GROUP BY status ORDER BY total DESC" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		$summary = array();
		foreach ( $counts ? $counts : array() as $row ) {
			$summary[] = array(
				'status' => $row->status,
				'label'  => wc_get_order_status_name( $row->status ),
				'count'  => (int) $row->total,
			);
		}

		$recent = array();
		foreach ( wc_get_orders( array( 'limit' => 12, 'orderby' => 'date', 'order' => 'DESC', 'type' => 'shop_order' ) ) as $order ) {
			$recent[] = array(
				'id'       => $order->get_id(),
				'number'   => $order->get_order_number(),
				'status'   => wc_get_order_status_name( $order->get_status() ),
				'total'    => (float) $order->get_total(),
				'customer' => $order->get_formatted_billing_full_name() ? $order->get_formatted_billing_full_name() : __( 'Guest', 'commerce-insights-woocommerce-by-ppros' ),
				'date'     => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i' ) : '',
			);
		}

		return array(
			'summary' => $summary,
			'recent'  => $recent,
		);
	}

	/**
	 * WooCommerce Subscriptions, when that plugin is active.
	 *
	 * @return array<string, mixed>
	 */
	public static function subscriptions() {
		if ( ! function_exists( 'wcs_get_subscriptions' ) ) {
			return array(
				'available' => false,
				'note'      => __( 'WooCommerce Subscriptions is not active. Recurring orders will show here when it is.', 'commerce-insights-woocommerce-by-ppros' ),
				'items'     => array(),
			);
		}

		$items = array();
		$subs  = wcs_get_subscriptions(
			array(
				'subscriptions_per_page' => 15,
				'orderby'                => 'start_date',
				'order'                  => 'DESC',
			)
		);

		foreach ( $subs as $subscription ) {
			$items[] = array(
				'id'       => $subscription->get_id(),
				'status'   => wc_get_order_status_name( $subscription->get_status() ),
				'total'    => (float) $subscription->get_total(),
				'customer' => $subscription->get_formatted_billing_full_name(),
				'next'     => $subscription->get_date( 'next_payment' ) ? $subscription->get_date( 'next_payment' ) : '',
			);
		}

		return array(
			'available' => true,
			'note'      => '',
			'items'     => $items,
		);
	}

	/**
	 * Store coupons and how often they were used.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function coupons() {
		$posts = get_posts(
			array(
				'post_type'      => 'shop_coupon',
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$out = array();
		foreach ( $posts as $post ) {
			$coupon = new WC_Coupon( $post->ID );
			$out[]  = array(
				'code'   => $coupon->get_code(),
				'type'   => $coupon->get_discount_type(),
				'amount' => (float) $coupon->get_amount(),
				'used'   => (int) $coupon->get_usage_count(),
				'expiry' => $coupon->get_date_expires() ? $coupon->get_date_expires()->date( 'Y-m-d' ) : '',
			);
		}

		return $out;
	}

	/**
	 * Recent storefront events and points movements.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function activity() {
		global $wpdb;

		$events = $wpdb->get_results(
			'SELECT event_type, customer_id, product_id, channel, created_at FROM ' . esc_sql( Ciwp::table( 'analytics_events' ) ) . ' ORDER BY id DESC LIMIT 20' // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		$items = array();
		foreach ( $events ? $events : array() as $event ) {
			$user = (int) $event->customer_id ? get_userdata( (int) $event->customer_id ) : null;
			$items[] = array(
				'at'    => $event->created_at,
				'kind'  => 'event',
				'label' => ucwords( str_replace( '_', ' ', $event->event_type ) ),
				'who'   => $user ? $user->display_name : __( 'Guest', 'commerce-insights-woocommerce-by-ppros' ),
				'extra' => $event->channel ? $event->channel : '',
			);
		}

		$ledger = $wpdb->get_results(
			'SELECT customer_id, amount, source, description, created_at FROM ' . esc_sql( Ciwp::table( 'points_ledger' ) ) . ' ORDER BY id DESC LIMIT 15' // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		foreach ( $ledger ? $ledger : array() as $row ) {
			$user = get_userdata( (int) $row->customer_id );
			$items[] = array(
				'at'    => $row->created_at,
				'kind'  => 'points',
				'label' => $row->description ? $row->description : $row->source,
				'who'   => $user ? $user->display_name : ( '#' . $row->customer_id ),
				'extra' => (string) (int) $row->amount,
			);
		}

		usort(
			$items,
			static function ( $a, $b ) {
				return strcmp( $b['at'], $a['at'] );
			}
		);

		return array_slice( $items, 0, 25 );
	}
}
