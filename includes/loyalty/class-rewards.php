<?php
/**
 * Reward catalog and redemption via WooCommerce coupons.
 *
 * @package Myrvento
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables have no core API.

/**
 * Rewards.
 */
class Myrvento_Rewards {

	/**
	 * List rewards.
	 *
	 * @param bool $enabled_only Only enabled.
	 * @return array<int, object>
	 */
	public static function all( $enabled_only = false ) {
		global $wpdb;

		$table = esc_sql( Myrvento::table( 'rewards' ) );
		$sql   = $enabled_only
			? "SELECT * FROM {$table} WHERE enabled = 1 ORDER BY points_cost ASC, id ASC"
			: "SELECT * FROM {$table} ORDER BY points_cost ASC, id ASC";

		$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $rows ? $rows : array();
	}

	/**
	 * Get one reward.
	 *
	 * @param int $id Reward ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$table = esc_sql( Myrvento::table( 'rewards' ) );
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $id
			)
		);
	}

	/**
	 * Save reward.
	 *
	 * @param array<string, mixed> $data Data.
	 * @param int                  $id   Optional ID.
	 * @return int|WP_Error
	 */
	public static function save( $data, $id = 0 ) {
		global $wpdb;

		$table = esc_sql( Myrvento::table( 'rewards' ) );
		$name  = sanitize_text_field( $data['name'] ?? '' );

		if ( '' === $name ) {
			return new WP_Error( 'myrvento_reward_name', __( 'Reward name is required.', 'myrvento-loyalty-for-woocommerce' ) );
		}

		$row = array(
			'name'        => $name,
			'type'        => sanitize_key( $data['type'] ?? 'coupon_percent' ),
			'points_cost' => (int) ( $data['points_cost'] ?? 0 ),
			'enabled'     => ! empty( $data['enabled'] ) ? 1 : 0,
			'tier_id'     => ! empty( $data['tier_id'] ) ? (int) $data['tier_id'] : null,
			'stock'       => isset( $data['stock'] ) && '' !== $data['stock'] && null !== $data['stock'] ? (int) $data['stock'] : null,
			'config'      => wp_json_encode( is_array( $data['config'] ?? null ) ? $data['config'] : array() ),
		);

		if ( $id ) {
			$wpdb->update( $table, $row, array( 'id' => (int) $id ) );
			return (int) $id;
		}

		$row['redeemed_count'] = 0;
		$wpdb->insert( $table, $row );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete reward.
	 *
	 * @param int $id Reward ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( esc_sql( Myrvento::table( 'rewards' ) ), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Redeem a reward for a customer.
	 *
	 * @param int $customer_id Customer ID.
	 * @param int $reward_id   Reward ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function redeem( $customer_id, $reward_id ) {
		global $wpdb;

		$reward = self::get( $reward_id );
		if ( ! $reward || ! $reward->enabled ) {
			return new WP_Error( 'myrvento_reward_missing', __( 'Reward is not available.', 'myrvento-loyalty-for-woocommerce' ) );
		}

		if ( null !== $reward->stock && (int) $reward->redeemed_count >= (int) $reward->stock ) {
			return new WP_Error( 'myrvento_reward_stock', __( 'This reward is out of stock.', 'myrvento-loyalty-for-woocommerce' ) );
		}

		$balance = Myrvento_Points_Ledger::get_balance( $customer_id );
		if ( (int) $reward->tier_id ) {
			$required = Myrvento_VIP_Tiers::get( (int) $reward->tier_id );
			$current  = $balance->tier_id ? Myrvento_VIP_Tiers::get( (int) $balance->tier_id ) : null;
			if ( $required && ( ! $current || (int) $current->sort_order < (int) $required->sort_order ) ) {
				return new WP_Error( 'myrvento_reward_tier', __( 'Your VIP tier cannot redeem this reward.', 'myrvento-loyalty-for-woocommerce' ) );
			}
		}

		$cost = (int) $reward->points_cost;
		if ( $cost > 0 ) {
			$ledger_id = Myrvento_Points_Ledger::debit(
				$customer_id,
				$cost,
				'redeem',
				array(
					'type'        => 'redeem',
					'source_id'   => (int) $reward->id,
					'description' => sprintf(
						/* translators: %s reward name */
						__( 'Redeemed: %s', 'myrvento-loyalty-for-woocommerce' ),
						$reward->name
					),
				)
			);

			if ( is_wp_error( $ledger_id ) ) {
				return $ledger_id;
			}
		} else {
			$ledger_id = 0;
		}

		$user   = get_userdata( $customer_id );
		$config = Myrvento::decode( $reward->config );
		$coupon = self::create_coupon( $reward, $config, $user );

		$wpdb->insert(
			esc_sql( Myrvento::table( 'redemptions' ) ),
			array(
				'customer_id'  => $customer_id,
				'reward_id'    => (int) $reward->id,
				'points_spent' => $cost,
				'coupon_id'    => $coupon ? (int) $coupon['id'] : null,
				'coupon_code'  => $coupon ? $coupon['code'] : null,
				'ledger_id'    => $ledger_id ? (int) $ledger_id : null,
				'status'       => 'issued',
				'created_at'   => current_time( 'mysql' ),
			)
		);

		$redemption_id = (int) $wpdb->insert_id;

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE " . esc_sql( Myrvento::table( 'rewards' ) ) . " SET redeemed_count = redeemed_count + 1 WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				(int) $reward->id
			)
		);

		self::grant_entitlements( $customer_id, $reward, $config );

		return array(
			'id'          => $redemption_id,
			'reward_id'   => (int) $reward->id,
			'coupon_code' => $coupon ? $coupon['code'] : '',
			'coupon_id'   => $coupon ? (int) $coupon['id'] : 0,
			'points_spent'=> $cost,
		);
	}

	/**
	 * Create a WooCommerce coupon for the redemption.
	 *
	 * @param object      $reward Reward row.
	 * @param array       $config Config JSON.
	 * @param WP_User|false $user User.
	 * @return array{id: int, code: string}|null
	 */
	private static function create_coupon( $reward, $config, $user ) {
		if ( ! class_exists( 'WC_Coupon' ) ) {
			return null;
		}

		$type = $reward->type;
		$code = strtoupper( 'GP-' . wp_generate_password( 8, false, false ) );

		$coupon = new WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_description( $reward->name );
		$coupon->set_individual_use( true );
		$coupon->set_usage_limit( 1 );
		$coupon->set_usage_limit_per_user( 1 );

		if ( $user && is_email( $user->user_email ) ) {
			$coupon->set_email_restrictions( array( $user->user_email ) );
		}

		switch ( $type ) {
			case 'coupon_percent':
				$coupon->set_discount_type( 'percent' );
				$coupon->set_amount( (float) ( $config['amount'] ?? 10 ) );
				break;
			case 'coupon_fixed':
				$coupon->set_discount_type( 'fixed_cart' );
				$coupon->set_amount( (float) ( $config['amount'] ?? 5 ) );
				break;
			case 'free_shipping':
				$coupon->set_discount_type( 'fixed_cart' );
				$coupon->set_amount( 0 );
				$coupon->set_free_shipping( true );
				break;
			case 'free_product':
				$product_id = (int) ( $config['product_id'] ?? 0 );
				$coupon->set_discount_type( 'percent' );
				$coupon->set_amount( 100 );
				if ( $product_id ) {
					$coupon->set_product_ids( array( $product_id ) );
				}
				break;
			case 'special_pricing':
				$coupon->set_discount_type( 'percent' );
				$coupon->set_amount( (float) ( $config['amount'] ?? 15 ) );
				if ( ! empty( $config['product_id'] ) ) {
					$coupon->set_product_ids( array( (int) $config['product_id'] ) );
				}
				break;
			default:
				return null;
		}

		$coupon->update_meta_data( '_myrvento_reward_id', (int) $reward->id );
		$coupon->save();

		return array(
			'id'   => $coupon->get_id(),
			'code' => $coupon->get_code(),
		);
	}

	/**
	 * Grant non-coupon entitlements (exclusive, early access, VIP offers).
	 *
	 * @param int    $customer_id Customer ID.
	 * @param object $reward      Reward.
	 * @param array  $config      Config.
	 * @return void
	 */
	private static function grant_entitlements( $customer_id, $reward, $config ) {
		$flags = array( 'exclusive_product', 'early_access', 'vip_offer' );
		if ( ! in_array( $reward->type, $flags, true ) && empty( $config['exclusive'] ) && empty( $config['early_access'] ) && empty( $config['vip_only'] ) ) {
			return;
		}

		$ents   = get_user_meta( $customer_id, 'myrvento_entitlements', true );
		$ents   = is_array( $ents ) ? $ents : array();
		$ents[] = array(
			'reward_id'   => (int) $reward->id,
			'type'        => $reward->type,
			'product_id'  => (int) ( $config['product_id'] ?? 0 ),
			'granted_at'  => current_time( 'mysql' ),
		);
		update_user_meta( $customer_id, 'myrvento_entitlements', $ents );
	}

	/**
	 * Redemption history.
	 *
	 * @param array<string, mixed> $args Filters.
	 * @return array{items: array<int, object>, total: int}
	 */
	public static function redemptions( $args = array() ) {
		global $wpdb;

		$table    = esc_sql( Myrvento::table( 'redemptions' ) );
		$where    = array( '1=1' );
		$params   = array();
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		if ( ! empty( $args['customer_id'] ) ) {
			$where[]  = 'customer_id = %d';
			$params[] = (int) $args['customer_id'];
		}

		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$list_sql  = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d";

		if ( $params ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$items = $wpdb->get_results( $wpdb->prepare( $list_sql, array_merge( $params, array( $per_page, $offset ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} else {
			$total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$items = $wpdb->get_results( $wpdb->prepare( $list_sql, $per_page, $offset ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Format reward for API.
	 *
	 * @param object $reward Reward.
	 * @return array<string, mixed>
	 */
	public static function to_array( $reward ) {
		return array(
			'id'             => (int) $reward->id,
			'name'           => $reward->name,
			'type'           => $reward->type,
			'points_cost'    => (int) $reward->points_cost,
			'enabled'        => (bool) $reward->enabled,
			'tier_id'        => $reward->tier_id ? (int) $reward->tier_id : null,
			'stock'          => null === $reward->stock ? null : (int) $reward->stock,
			'redeemed_count' => (int) $reward->redeemed_count,
			'config'         => Myrvento::decode( $reward->config ),
		);
	}
}
