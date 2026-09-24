<?php
// phpcs:ignoreFile -- CLI demo seeder. Not loaded on normal requests.
/**
 * Local demo data for GrowthPilot (customers, orders, loyalty, referrals, analytics, AI).
 *
 * Usage (from the WordPress root):
 *   wp eval-file wp-content/plugins/GrowthPilot/bin/seed-demo.php
 *   wp eval-file wp-content/plugins/GrowthPilot/bin/seed-demo.php rename
 *   wp eval-file wp-content/plugins/GrowthPilot/bin/seed-demo.php reset
 *
 * @package GrowthPilot
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Option that records everything the seeder created.
 *
 * @return string
 */
function growthpilot_demo_option() {
	return 'growthpilot_demo_seed';
}

/**
 * Random float in a range.
 *
 * @param float $min Min.
 * @param float $max Max.
 * @return float
 */
function growthpilot_demo_rand( $min, $max ) {
	return $min + ( mt_rand() / mt_getrandmax() ) * ( $max - $min );
}

/**
 * Pick a key from a weight map.
 *
 * @param array<int|string, float> $weights Weights.
 * @return int|string
 */
function growthpilot_demo_pick( $weights ) {
	$total = array_sum( $weights );
	$roll  = growthpilot_demo_rand( 0, $total );
	foreach ( $weights as $key => $weight ) {
		$roll -= $weight;
		if ( $roll <= 0 ) {
			return $key;
		}
	}
	return array_key_last( $weights );
}

/**
 * European demo identities. First × last is unique, so logins never collide.
 *
 * @return array<int, array<string, string>>
 */
function growthpilot_demo_identities() {
	$first  = array( 'Emma', 'Luca', 'Sophie', 'Matteo', 'Anna', 'Lukas', 'Elena', 'Hugo', 'Marie', 'Oliver', 'Chiara', 'Noah', 'Isla', 'Felix', 'Amelie', 'Jonas', 'Clara', 'Marco', 'Freya', 'Henrik', 'Sofia', 'Thomas', 'Ingrid', 'Pierre', 'Lena', 'Andreas', 'Giulia', 'Erik', 'Hannah', 'Jan', 'Camille', 'Nils', 'Rosa', 'David', 'Elise', 'Karl', 'Nina', 'Oscar', 'Laura', 'Martin' );
	$last   = array( 'Muller', 'Rossi', 'Dubois', 'Nielsen', 'Garcia', 'Kowalski', 'Novak', 'Andersson', 'Silva', 'Berg', 'Moreau', 'Costa', 'Keller', 'Bianchi', 'Hansen', 'Lindberg', 'Fischer', 'Romano', 'Bernard', 'Kovacs' );
	$places = array(
		array( 'Berlin', 'DE', 'Hauptstrasse', '10115', '+49 30 ' ),
		array( 'Munich', 'DE', 'Leopoldstrasse', '80331', '+49 89 ' ),
		array( 'Paris', 'FR', 'Rue de Rivoli', '75001', '+33 1 ' ),
		array( 'Lyon', 'FR', 'Rue Victor Hugo', '69002', '+33 4 ' ),
		array( 'Rome', 'IT', 'Via Roma', '00184', '+39 06 ' ),
		array( 'Milan', 'IT', 'Via Dante', '20121', '+39 02 ' ),
		array( 'Madrid', 'ES', 'Calle Mayor', '28013', '+34 91 ' ),
		array( 'Barcelona', 'ES', 'Carrer de Balmes', '08007', '+34 93 ' ),
		array( 'Amsterdam', 'NL', 'Keizersgracht', '1015', '+31 20 ' ),
		array( 'Vienna', 'AT', 'Mariahilfer Strasse', '1060', '+43 1 ' ),
		array( 'Stockholm', 'SE', 'Drottninggatan', '11151', '+46 8 ' ),
		array( 'Lisbon', 'PT', 'Rua Augusta', '1100', '+351 21 ' ),
		array( 'Warsaw', 'PL', 'Nowy Swiat', '00001', '+48 22 ' ),
		array( 'Prague', 'CZ', 'Narodni', '11000', '+420 2 ' ),
		array( 'Copenhagen', 'DK', 'Vesterbrogade', '1620', '+45 33 ' ),
		array( 'Brussels', 'BE', 'Rue Neuve', '1000', '+32 2 ' ),
		array( 'Dublin', 'IE', 'Grafton Street', 'D02', '+353 1 ' ),
		array( 'Helsinki', 'FI', 'Mannerheimintie', '00100', '+358 9 ' ),
		array( 'Athens', 'GR', 'Ermou', '10563', '+30 21 ' ),
		array( 'Oslo', 'NO', 'Karl Johans gate', '0154', '+47 22 ' ),
	);

	$out = array();
	$n   = 0;
	foreach ( $first as $given ) {
		foreach ( $last as $family ) {
			$place = $places[ $n % count( $places ) ];
			$out[] = array(
				'first'    => $given,
				'last'     => $family,
				'login'    => strtolower( $given . '.' . $family ),
				'city'     => $place[0],
				'country'  => $place[1],
				'street'   => $place[2],
				'postcode' => $place[3],
				'phone'    => $place[4] . str_pad( (string) ( 1000000 + $n ), 7, '0', STR_PAD_LEFT ),
			);
			++$n;
		}
	}

	$mixed = array();
	$total = count( $out );
	for ( $i = 0; $i < $total; $i++ ) {
		$mixed[] = $out[ ( $i * 41 ) % $total ];
	}

	return $mixed;
}

/**
 * Store demand multiplier for a month (Eid, winter sale, 11.11 / year-end peaks).
 *
 * @param int $ts Timestamp.
 * @return float
 */
function growthpilot_demo_season( $ts ) {
	$curve = array( 1 => 0.85, 0.8, 1.35, 1.1, 1.25, 1.15, 0.8, 0.85, 0.95, 1.05, 1.5, 1.65 );
	return $curve[ (int) wp_date( 'n', $ts ) ];
}

/**
 * Move every row the plugin just wrote "now" to the simulated event time.
 *
 * Seeder events run oldest-first and are always dated before the run started,
 * so only rows written during the current event match `>= since`.
 *
 * @param int $ts Event timestamp.
 * @return void
 */
function growthpilot_demo_sweep( $ts ) {
	global $wpdb;

	$when    = wp_date( 'Y-m-d H:i:s', $ts );
	$since   = $GLOBALS['growthpilot_demo_since'];
	$columns = array(
		'points_ledger'      => array( 'created_at' ),
		'points_balances'    => array( 'updated_at', 'tier_evaluated_at' ),
		'customer_tiers'     => array( 'assigned_at' ),
		'customer_badges'    => array( 'earned_at' ),
		'challenge_progress' => array( 'completed_at' ),
		'referrals'          => array( 'created_at', 'converted_at' ),
		'redemptions'        => array( 'created_at' ),
	);

	foreach ( $columns as $table => $cols ) {
		foreach ( $cols as $col ) {
			$wpdb->query(
				$wpdb->prepare(
					'UPDATE ' . GrowthPilot::table( $table ) . " SET {$col} = %s WHERE {$col} >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$when,
					$since
				)
			);
		}
	}
}

/**
 * Run a callback as if it happened at a past time.
 *
 * @param int      $ts       Timestamp.
 * @param callable $callback Callback.
 * @return mixed
 */
function growthpilot_demo_at( $ts, $callback ) {
	$result = $callback();
	growthpilot_demo_sweep( $ts );
	return $result;
}

/**
 * Multi-row insert with prepared values.
 *
 * @param string                           $table   Table (already prefixed).
 * @param array<string, string>            $formats Column => placeholder.
 * @param array<int, array<string, mixed>> $rows    Rows.
 * @return void
 */
function growthpilot_demo_bulk_insert( $table, $formats, $rows ) {
	global $wpdb;

	$columns = implode( ', ', array_keys( $formats ) );
	$tuple   = '(' . implode( ', ', array_values( $formats ) ) . ')';

	foreach ( array_chunk( $rows, 400 ) as $chunk ) {
		$values = array();
		foreach ( $chunk as $row ) {
			foreach ( array_keys( $formats ) as $col ) {
				$values[] = $row[ $col ];
			}
		}

		$sql = "INSERT INTO {$table} ({$columns}) VALUES " . implode( ', ', array_fill( 0, count( $chunk ), $tuple ) );
		$wpdb->query( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}

/**
 * Delete rows whose column is in a list of IDs.
 *
 * @param string          $table  Table (already prefixed).
 * @param string          $column Column.
 * @param array<int, int> $ids    IDs.
 * @return void
 */
function growthpilot_demo_delete_in( $table, $column, $ids ) {
	global $wpdb;

	$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
	foreach ( array_chunk( $ids, 500 ) as $chunk ) {
		$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE {$column} IN ({$placeholders})", $chunk ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}

/**
 * Keep mail, stock, and email counters quiet while seeding.
 *
 * @return void
 */
function growthpilot_demo_quiet() {
	add_filter( 'pre_wp_mail', '__return_false' );
	add_filter( 'woocommerce_can_reduce_order_stock', '__return_false' );
	remove_all_actions( 'woocommerce_email_sent' );
	wc_set_time_limit( 0 );
}

/**
 * Seed demo data.
 *
 * @return void
 */
function growthpilot_demo_seed() {
	global $wpdb;

	if ( get_option( growthpilot_demo_option() ) ) {
		WP_CLI::error( 'Demo data already exists. Run with "reset" first.' );
	}

	growthpilot_demo_quiet();
	mt_srand( 20260923 );

	$GLOBALS['growthpilot_demo_since'] = current_time( 'mysql' );

	$now  = time();
	$cap  = $now - ( 3 * HOUR_IN_SECONDS );
	$day  = DAY_IN_SECONDS;
	$seed = array(
		'users'       => array(),
		'guests'      => array(),
		'orders'      => array(),
		'coupons'     => array(),
		'challenges'  => array(),
		'comments'    => array(),
		'email_stats' => array(),
		'products'    => array(),
		'seeded_at'   => $GLOBALS['growthpilot_demo_since'],
	);

	// Catalog: popularity, seasonal lines, and unit cost.
	$catalog = array();
	foreach ( wc_get_products( array( 'type' => array( 'simple' ), 'status' => 'publish', 'limit' => -1 ) ) as $product ) {
		if ( (float) $product->get_price() > 0 ) {
			$catalog[ $product->get_id() ] = $product;
		}
	}

	if ( count( $catalog ) < 6 ) {
		WP_CLI::error( 'Need at least 6 published simple products with a price.' );
	}

	$ids = array_keys( $catalog );
	shuffle( $ids );

	$weights = array();
	$winter  = array_slice( $ids, 2, 3 );
	$summer  = array_slice( $ids, 5, 3 );
	foreach ( $ids as $rank => $pid ) {
		$weights[ $pid ] = 100 / pow( $rank + 1, 0.85 );

		$product                  = $catalog[ $pid ];
		$seed['products'][ $pid ] = array(
			'manage_stock' => $product->get_manage_stock(),
			'stock'        => $product->get_stock_quantity(),
			'stock_status' => $product->get_stock_status(),
			'cogs'         => get_post_meta( $pid, '_cogs', true ),
		);

		if ( mt_rand( 1, 100 ) <= 85 ) {
			update_post_meta( $pid, '_cogs', round( (float) $product->get_price() * growthpilot_demo_rand( 0.42, 0.72 ), 2 ) );
		}
	}

	$product_weight = static function ( $ts ) use ( $weights, $winter, $summer ) {
		$month = (int) wp_date( 'n', $ts );
		$out   = $weights;
		foreach ( $winter as $pid ) {
			$out[ $pid ] *= in_array( $month, array( 11, 12, 1 ), true ) ? 3.5 : 0.35;
		}
		foreach ( $summer as $pid ) {
			$out[ $pid ] *= in_array( $month, array( 4, 5, 6 ), true ) ? 3.5 : 0.35;
		}
		return $out;
	};

	// Coupons.
	$coupons = array(
		'WELCOME10' => array( 'percent', 10, 0 ),
		'EID25'     => array( 'percent', 25, 0 ),
		'FLASH15'   => array( 'percent', 15, 0 ),
		'SAVE50'    => array( 'fixed_cart', 50, 300 ),
	);
	foreach ( $coupons as $code => $conf ) {
		$existing = wc_get_coupon_id_by_code( $code );
		if ( $existing ) {
			continue;
		}
		$coupon = new WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( $conf[0] );
		$coupon->set_amount( $conf[1] );
		$coupon->set_minimum_amount( $conf[2] );
		$coupon->update_meta_data( '_gp_demo', 1 );
		$seed['coupons'][] = $coupon->save();
	}

	// Challenges tick as orders land.
	$challenges = array(
		array( 'Shop 3 times', 'Place 3 completed orders.', 'orders', 3, 150 ),
		array( 'Big basket', 'Spend 1,000 in total.', 'spend', 1000, 250 ),
		array( 'Bring 2 friends', 'Refer 2 friends who buy.', 'referrals', 2, 300 ),
	);
	foreach ( $challenges as $c ) {
		$seed['challenges'][] = GrowthPilot_Gamification::save_challenge(
			array(
				'name'          => $c[0],
				'description'   => $c[1],
				'type'          => $c[2],
				'target_value'  => $c[3],
				'points_reward' => $c[4],
				'enabled'       => 1,
			)
		);
	}

	// Customers.
	$people = growthpilot_demo_identities();

	$segments = array_merge(
		array_fill( 0, 6, 'vip' ),
		array_fill( 0, 16, 'loyal' ),
		array_fill( 0, 20, 'occasional' ),
		array_fill( 0, 14, 'one_time' ),
		array_fill( 0, 10, 'churned' ),
		array_fill( 0, 14, 'referred' )
	);

	$reg_window = array(
		'vip'        => array( 430, 480 ),
		'loyal'      => array( 300, 460 ),
		'occasional' => array( 60, 420 ),
		'one_time'   => array( 10, 400 ),
		'churned'    => array( 380, 470 ),
		'referred'   => array( 20, 300 ),
	);

	$specs = array();
	foreach ( $segments as $i => $segment ) {
		$person  = $people[ $i ];
		$specs[] = array(
			'segment'  => $segment,
			'first'    => $person['first'],
			'last'     => $person['last'],
			'login'    => $person['login'],
			'city'     => $person['city'],
			'country'  => $person['country'],
			'street'   => $person['street'],
			'postcode' => $person['postcode'],
			'phone'    => $person['phone'],
			'reg'      => $now - (int) ( growthpilot_demo_rand( $reg_window[ $segment ][0], $reg_window[ $segment ][1] ) * $day ),
		);
	}
	usort(
		$specs,
		static function ( $a, $b ) {
			return $a['reg'] <=> $b['reg'];
		}
	);

	$campaign  = GrowthPilot_Referral_Program::active_campaign();
	$customers = array();
	$referrers = array();
	$clicks    = array();

	WP_CLI::log( 'Creating customers…' );
	foreach ( $specs as $n => $spec ) {
		$user = get_user_by( 'login', $spec['login'] );
		if ( $user ) {
			WP_CLI::error( "User {$spec['login']} already exists. Remove it or run reset." );
		}

		$referrer = 0;
		if ( 'referred' === $spec['segment'] && $referrers ) {
			$referrer = $referrers[ array_rand( $referrers ) ];
			$code     = GrowthPilot_Referral_Program::get_or_create_code( $referrer );
			$clicks[] = array(
				'code'         => $code,
				'campaign_id'  => $campaign ? (int) $campaign->id : 0,
				'visitor_hash' => 'demo-' . md5( $spec['login'] ),
				'landing_url'  => add_query_arg( 'gp_ref', $code, home_url( '/' ) ),
				'created_at'   => wp_date( 'Y-m-d H:i:s', $spec['reg'] - mt_rand( HOUR_IN_SECONDS, 3 * $day ) ),
			);
		}

		$user_id = growthpilot_demo_at(
			$spec['reg'],
			static function () use ( $spec, $referrer ) {
				$user_id = wp_insert_user(
					array(
						'user_login'      => $spec['login'],
						'user_email'      => $spec['login'] . '@example.com',
						'user_pass'       => wp_generate_password( 24 ),
						'first_name'      => $spec['first'],
						'last_name'       => $spec['last'],
						'display_name'    => $spec['first'] . ' ' . $spec['last'],
						'role'            => 'customer',
						'user_registered' => gmdate( 'Y-m-d H:i:s', $spec['reg'] ),
					)
				);

				if ( is_wp_error( $user_id ) ) {
					return $user_id;
				}

				if ( $referrer ) {
					$code = GrowthPilot_Referral_Program::get_or_create_code( $referrer );
					update_user_meta( $user_id, 'gp_referred_code', $code );
					GrowthPilot_Referral_Program::upsert( $referrer, $code, $user_id, 'signed_up' );
					GrowthPilot_Referral_Rewards::on_signup( $user_id, $referrer );
				}

				return $user_id;
			}
		);

		if ( is_wp_error( $user_id ) ) {
			WP_CLI::error( $user_id->get_error_message() );
		}

		update_user_meta( $user_id, '_gp_demo', 1 );
		update_user_meta( $user_id, 'billing_first_name', $spec['first'] );
		update_user_meta( $user_id, 'billing_last_name', $spec['last'] );
		update_user_meta( $user_id, 'billing_email', $spec['login'] . '@example.com' );
		update_user_meta( $user_id, 'billing_phone', $spec['phone'] );
		update_user_meta( $user_id, 'billing_city', $spec['city'] );
		update_user_meta( $user_id, 'billing_country', $spec['country'] );
		update_user_meta( $user_id, 'billing_postcode', $spec['postcode'] );
		update_user_meta( $user_id, 'billing_address_1', ( ( $n % 80 ) + 1 ) . ' ' . $spec['street'] );
		update_user_meta( $user_id, 'gp_birthday', $n < 2 ? wp_date( 'm-d' ) : wp_date( 'm-d', mt_rand( 0, 364 ) * $day ) );

		$seed['users'][]       = $user_id;
		$customers[ $user_id ] = $spec + array( 'id' => $user_id, 'referrer' => $referrer );

		if ( in_array( $spec['segment'], array( 'vip', 'loyal' ), true ) ) {
			$referrers[] = $user_id;
		}
	}

	// Clicks on shared links that never converted.
	foreach ( $referrers as $referrer ) {
		$code = GrowthPilot_Referral_Program::get_or_create_code( $referrer );
		for ( $i = 0, $n = mt_rand( 3, 14 ); $i < $n; $i++ ) {
			$clicks[] = array(
				'code'         => $code,
				'campaign_id'  => $campaign ? (int) $campaign->id : 0,
				'visitor_hash' => 'demo-' . md5( $referrer . '-' . $i ),
				'landing_url'  => add_query_arg( 'gp_ref', $code, home_url( '/' ) ),
				'created_at'   => wp_date( 'Y-m-d H:i:s', $cap - mt_rand( 0, 360 ) * $day ),
			);
		}
	}
	growthpilot_demo_bulk_insert(
		GrowthPilot::table( 'referral_clicks' ),
		array(
			'code'         => '%s',
			'campaign_id'  => '%d',
			'visitor_hash' => '%s',
			'landing_url'  => '%s',
			'created_at'   => '%s',
		),
		$clicks
	);

	// Order timeline.
	$plans = array();
	foreach ( $customers as $customer ) {
		$reg     = $customer['reg'];
		$segment = $customer['segment'];
		$times   = array();

		if ( 'one_time' === $segment ) {
			$times[] = $reg + mt_rand( 0, 10 ) * $day;
		} elseif ( 'occasional' === $segment ) {
			for ( $i = 0, $n = mt_rand( 2, 4 ); $i < $n; $i++ ) {
				$times[] = mt_rand( $reg, $cap );
			}
		} else {
			$gaps = array(
				'vip'      => array( 8, 22 ),
				'loyal'    => array( 22, 48 ),
				'churned'  => array( 20, 40 ),
				'referred' => array( 30, 70 ),
			);
			$stop = 'churned' === $segment ? $now - mt_rand( 150, 280 ) * $day : $cap;
			$max  = array(
				'vip'      => 40,
				'loyal'    => 20,
				'churned'  => mt_rand( 3, 6 ),
				'referred' => mt_rand( 1, 4 ),
			);
			$ts   = $reg + mt_rand( 0, 7 ) * $day;
			while ( $ts < $stop && count( $times ) < $max[ $segment ] ) {
				$times[] = $ts;
				$ts     += (int) ( growthpilot_demo_rand( $gaps[ $segment ][0], $gaps[ $segment ][1] ) * $day / growthpilot_demo_season( $ts ) );
			}
		}

		sort( $times );
		foreach ( $times as $index => $ts ) {
			$plans[] = array(
				'ts'       => min( $ts + mt_rand( 8, 22 ) * HOUR_IN_SECONDS, $cap ),
				'customer' => $customer['id'],
				'segment'  => $segment,
				'first'    => 0 === $index,
				'referred' => $customer['referrer'] > 0 && 0 === $index,
			);
		}
	}

	// Guest checkouts, weighted by season and growth.
	$start = $now - 470 * $day;
	for ( $guests = 0; $guests < 70; ) {
		$ts     = mt_rand( $start, $cap );
		$growth = 0.65 + 0.55 * ( ( $ts - $start ) / ( $cap - $start ) );
		if ( growthpilot_demo_rand( 0, 2 ) > growthpilot_demo_season( $ts ) * $growth ) {
			continue;
		}
		$plans[] = array(
			'ts'       => $ts,
			'customer' => 0,
			'segment'  => 'guest',
			'first'    => true,
			'referred' => false,
		);
		++$guests;
	}

	usort(
		$plans,
		static function ( $a, $b ) {
			return $a['ts'] <=> $b['ts'];
		}
	);

	$channels = array(
		'google'    => array( 30, 'cpc', 'brand-search' ),
		'facebook'  => array( 22, 'paid_social', 'eid-sale' ),
		'direct'    => array( 20, '', '' ),
		'email'     => array( 12, 'email', 'newsletter' ),
		'instagram' => array( 8, 'social', 'reels' ),
		'tiktok'    => array( 5, 'paid_social', 'creator-collab' ),
	);
	$channel_weights = array_map(
		static function ( $c ) {
			return $c[0];
		},
		$channels
	);

	$guest_n = count( $segments );
	$events  = array();
	$refunds = array();
	$units   = array();
	$total   = count( $plans );

	WP_CLI::log( "Creating {$total} orders…" );
	$progress = \WP_CLI\Utils\make_progress_bar( 'Orders', $total );

	foreach ( $plans as $plan ) {
		$ts       = $plan['ts'];
		$customer = $plan['customer'] ? $customers[ $plan['customer'] ] : null;

		if ( $customer ) {
			$person = $customer;
			$email  = $customer['login'] . '@example.com';
		} else {
			$person           = $people[ $guest_n ];
			$email            = 'guest.' . $person['login'] . '@example.com';
			$seed['guests'][] = $email;
			++$guest_n;
		}

		$first    = $person['first'];
		$last     = $person['last'];
		$city     = $person['city'];
		$country  = $person['country'];
		$street   = $person['street'];
		$postcode = $person['postcode'];
		$phone    = $person['phone'];

		$source = $plan['referred'] ? 'referral' : growthpilot_demo_pick( $channel_weights );
		$first_source = mt_rand( 1, 100 ) <= 60 || $plan['referred'] ? $source : growthpilot_demo_pick( $channel_weights );
		$touch  = static function ( $key ) use ( $channels, $customer ) {
			if ( 'referral' === $key ) {
				return array( 'referral', 'referral', $customer ? GrowthPilot_Referral_Program::get_or_create_code( $customer['referrer'] ) : '' );
			}
			return array( $key, $channels[ $key ][1], $channels[ $key ][2] );
		};
		$last_touch  = $touch( $source );
		$first_touch = $touch( $first_source );

		$status = 'completed';
		if ( $ts > $now - 4 * $day ) {
			$status = mt_rand( 1, 100 ) <= 80 ? 'processing' : 'on-hold';
		} else {
			$roll = mt_rand( 1, 100 );
			if ( $roll <= 3 ) {
				$status = 'refunded';
			} elseif ( $roll <= 5 ) {
				$status = 'cancelled';
			}
		}

		$order = wc_create_order(
			array(
				'customer_id' => $plan['customer'],
				'created_via' => 'checkout',
			)
		);

		$lines    = (int) growthpilot_demo_pick( array( 1 => 55, 2 => 30, 3 => 15 ) );
		$lines   += 'vip' === $plan['segment'] && mt_rand( 1, 100 ) <= 40 ? 1 : 0;
		$picked   = array();
		$pweights = $product_weight( $ts );
		for ( $i = 0; $i < $lines; $i++ ) {
			$pid = (int) growthpilot_demo_pick( array_diff_key( $pweights, $picked ) );
			$qty = (int) growthpilot_demo_pick( array( 1 => 75, 2 => 20, 3 => 5 ) );
			if ( 'vip' === $plan['segment'] ) {
				$qty += mt_rand( 0, 2 );
			}
			$order->add_product( $catalog[ $pid ], $qty );
			$picked[ $pid ] = true;

			if ( in_array( $status, array( 'completed', 'processing', 'on-hold' ), true ) ) {
				$units[ $pid ][] = array( $ts, $qty );
			}
		}

		$address = array(
			'first_name' => $first,
			'last_name'  => $last,
			'email'      => $email,
			'phone'      => $phone,
			'address_1'  => mt_rand( 1, 80 ) . ' ' . $street,
			'city'       => $city,
			'postcode'   => $postcode,
			'country'    => $country,
		);
		$order->set_address( $address, 'billing' );
		unset( $address['email'], $address['phone'] );
		$order->set_address( $address, 'shipping' );

		$order->calculate_totals( false );
		if ( (float) $order->get_subtotal() < 150 ) {
			$shipping = new WC_Order_Item_Shipping();
			$shipping->set_method_title( 'Flat rate' );
			$shipping->set_method_id( 'flat_rate' );
			$shipping->set_total( 60 );
			$order->add_item( $shipping );
		}

		$month = (int) wp_date( 'n', $ts );
		$code  = '';
		if ( in_array( $month, array( 3, 6 ), true ) && mt_rand( 1, 100 ) <= 35 ) {
			$code = 'EID25';
		} elseif ( $plan['first'] && $plan['customer'] && mt_rand( 1, 100 ) <= 30 ) {
			$code = 'WELCOME10';
		} elseif ( mt_rand( 1, 100 ) <= 8 ) {
			$code = (float) $order->get_subtotal() >= 300 ? 'SAVE50' : 'FLASH15';
		}

		$payment = mt_rand( 1, 100 ) <= 55 ? array( 'cod', 'Cash on delivery' ) : array( 'bkash', 'bKash' );
		$order->set_payment_method( $payment[0] );
		$order->set_payment_method_title( $payment[1] );
		$order->calculate_totals( false );
		if ( $code ) {
			$order->apply_coupon( $code );
		}

		$order->set_date_created( $ts );
		$order->update_meta_data( '_gp_demo', 1 );
		$order->update_meta_data( '_gp_first_source', $first_touch[0] );
		$order->update_meta_data( '_gp_first_medium', $first_touch[1] );
		$order->update_meta_data( '_gp_first_campaign', $first_touch[2] );
		$order->update_meta_data( '_gp_last_source', $last_touch[0] );
		$order->update_meta_data( '_gp_last_medium', $last_touch[1] );
		$order->update_meta_data( '_gp_last_campaign', $last_touch[2] );
		$order->update_meta_data( '_gp_purchase_tracked', 1 );
		$order->save();

		growthpilot_demo_at(
			$ts,
			static function () use ( $order, $status, $ts, $cap, $day ) {
				if ( 'cancelled' !== $status ) {
					$order->set_date_paid( $ts + mt_rand( 60, 3600 ) );
				}
				if ( in_array( $status, array( 'completed', 'refunded' ), true ) ) {
					$order->set_date_completed( min( $ts + mt_rand( 1, 4 ) * $day, $cap ) );
					$order->set_status( 'completed' );
				} else {
					$order->set_status( $status );
				}
				$order->save();
			}
		);

		if ( 'refunded' === $status ) {
			$refunds[] = array( $order->get_id(), min( $ts + mt_rand( 3, 10 ) * $day, $cap ) );
		}

		\Automattic\WooCommerce\Internal\Admin\Schedulers\OrdersScheduler::import( $order->get_id() );

		$seed['orders'][] = $order->get_id();

		// Funnel + attribution events for this session.
		$sid    = 'demo-' . wp_generate_password( 12, false, false );
		$cid    = $plan['customer'] ? $plan['customer'] : null;
		$visit  = $ts - mt_rand( 8, 40 ) * MINUTE_IN_SECONDS;
		$base   = array(
			'session_id'   => $sid,
			'customer_id'  => $cid,
			'product_id'   => null,
			'order_id'     => null,
			'channel'      => $last_touch[0],
			'utm_source'   => 'direct' === $last_touch[0] ? '' : $last_touch[0],
			'utm_medium'   => $last_touch[1],
			'utm_campaign' => $last_touch[2],
			'touch'        => 'last',
		);
		$events[] = $base + array( 'event_type' => 'visit', 'created_at' => wp_date( 'Y-m-d H:i:s', $visit ) );
		foreach ( array_keys( $picked ) as $pid ) {
			$events[] = array_merge( $base, array( 'product_id' => $pid ) ) + array( 'event_type' => 'product_view', 'created_at' => wp_date( 'Y-m-d H:i:s', $visit + 120 ) );
			$events[] = array_merge( $base, array( 'product_id' => $pid ) ) + array( 'event_type' => 'add_to_cart', 'created_at' => wp_date( 'Y-m-d H:i:s', $visit + 300 ) );
		}
		$events[] = $base + array( 'event_type' => 'checkout', 'created_at' => wp_date( 'Y-m-d H:i:s', $ts - 180 ) );
		if ( 'cancelled' !== $status ) {
			$events[] = array_merge( $base, array( 'order_id' => $order->get_id() ) ) + array( 'event_type' => 'purchase', 'created_at' => wp_date( 'Y-m-d H:i:s', $ts ) );
			$events[] = array_merge(
				$base,
				array(
					'order_id'     => $order->get_id(),
					'channel'      => $first_touch[0],
					'utm_source'   => 'direct' === $first_touch[0] ? '' : $first_touch[0],
					'utm_medium'   => $first_touch[1],
					'utm_campaign' => $first_touch[2],
					'touch'        => 'first',
				)
			) + array( 'event_type' => 'purchase', 'created_at' => wp_date( 'Y-m-d H:i:s', $ts ) );
		}

		$progress->tick();
	}
	$progress->finish();

	foreach ( $refunds as $refund ) {
		list( $order_id, $ts ) = $refund;
		growthpilot_demo_at(
			$ts,
			static function () use ( $order_id, $ts ) {
				$order = wc_get_order( $order_id );
				if ( ! $order ) {
					return;
				}
				$order->update_status( 'refunded', 'Customer returned the items.' );
				foreach ( $order->get_refunds() as $refund ) {
					$refund->set_date_created( $ts );
					$refund->save();
				}
			}
		);
		\Automattic\WooCommerce\Internal\Admin\Schedulers\OrdersScheduler::import( $order_id );
	}

	// Sessions that bounced or abandoned the cart.
	WP_CLI::log( 'Writing funnel events…' );
	for ( $i = 0; $i < 4200; $i++ ) {
		$ts = mt_rand( $now - 400 * $day, $cap );
		if ( growthpilot_demo_rand( 0, 1.7 ) > growthpilot_demo_season( $ts ) ) {
			continue;
		}
		$key  = (string) growthpilot_demo_pick( $channel_weights );
		$base = array(
			'session_id'   => 'demo-' . wp_generate_password( 12, false, false ),
			'customer_id'  => null,
			'product_id'   => null,
			'order_id'     => null,
			'channel'      => $key,
			'utm_source'   => 'direct' === $key ? '' : $key,
			'utm_medium'   => $channels[ $key ][1],
			'utm_campaign' => $channels[ $key ][2],
			'touch'        => 'last',
		);

		$events[] = $base + array( 'event_type' => 'visit', 'created_at' => wp_date( 'Y-m-d H:i:s', $ts ) );
		if ( mt_rand( 1, 100 ) > 58 ) {
			continue;
		}
		$pid      = (int) growthpilot_demo_pick( $weights );
		$events[] = array_merge( $base, array( 'product_id' => $pid ) ) + array( 'event_type' => 'product_view', 'created_at' => wp_date( 'Y-m-d H:i:s', $ts + 90 ) );
		if ( mt_rand( 1, 100 ) > 24 ) {
			continue;
		}
		$events[] = array_merge( $base, array( 'product_id' => $pid ) ) + array( 'event_type' => 'add_to_cart', 'created_at' => wp_date( 'Y-m-d H:i:s', $ts + 240 ) );
		if ( mt_rand( 1, 100 ) > 40 ) {
			continue;
		}
		$events[] = $base + array( 'event_type' => 'checkout', 'created_at' => wp_date( 'Y-m-d H:i:s', $ts + 420 ) );
	}

	growthpilot_demo_bulk_insert(
		GrowthPilot::table( 'analytics_events' ),
		array(
			'session_id'   => '%s',
			'customer_id'  => '%d',
			'event_type'   => '%s',
			'product_id'   => '%d',
			'order_id'     => '%d',
			'channel'      => '%s',
			'utm_source'   => '%s',
			'utm_medium'   => '%s',
			'utm_campaign' => '%s',
			'touch'        => '%s',
			'created_at'   => '%s',
		),
		$events
	);
	$wpdb->query(
		$wpdb->prepare(
			'UPDATE ' . GrowthPilot::table( 'analytics_events' ) . ' SET customer_id = NULL WHERE customer_id = 0 AND session_id LIKE %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->esc_like( 'demo-' ) . '%'
		)
	);
	$wpdb->query(
		$wpdb->prepare(
			'UPDATE ' . GrowthPilot::table( 'analytics_events' ) . ' SET product_id = NULL WHERE product_id = 0 AND session_id LIKE %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->esc_like( 'demo-' ) . '%'
		)
	);
	$wpdb->query(
		$wpdb->prepare(
			'UPDATE ' . GrowthPilot::table( 'analytics_events' ) . ' SET order_id = NULL WHERE order_id = 0 AND session_id LIKE %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->esc_like( 'demo-' ) . '%'
		)
	);

	// Product reviews on completed purchases.
	WP_CLI::log( 'Adding reviews, shares, and redemptions…' );
	$review_texts = array(
		5 => array( 'Excellent quality, arrived quickly.', 'Exactly as described. Will buy again!', 'Great value for the price.', 'Love it — my second order already.' ),
		4 => array( 'Good product, packaging could be better.', 'Works well, delivery took a day longer.', 'Nice quality overall.' ),
		3 => array( 'Okay for the price.', 'Decent, but the colour was slightly different.' ),
	);
	$reviewed = array();
	foreach ( $seed['orders'] as $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->get_customer_id() || 'completed' !== $order->get_status() || mt_rand( 1, 100 ) > 32 ) {
			continue;
		}
		$items = $order->get_items();
		$item  = reset( $items );
		$pid   = $item ? (int) $item->get_product_id() : 0;
		$key   = $order->get_customer_id() . ':' . $pid;
		if ( ! $pid || isset( $reviewed[ $key ] ) ) {
			continue;
		}
		$reviewed[ $key ] = $pid;

		$ts     = min( $order->get_date_created()->getTimestamp() + mt_rand( 3, 12 ) * $day, $cap );
		$rating = (int) growthpilot_demo_pick( array( 5 => 60, 4 => 30, 3 => 10 ) );
		$user   = get_userdata( $order->get_customer_id() );

		$seed['comments'][] = growthpilot_demo_at(
			$ts,
			static function () use ( $pid, $user, $rating, $review_texts, $ts ) {
				$data = array(
					'comment_post_ID'      => $pid,
					'comment_author'       => $user->display_name,
					'comment_author_email' => $user->user_email,
					'comment_content'      => $review_texts[ $rating ][ array_rand( $review_texts[ $rating ] ) ],
					'comment_type'         => 'review',
					'comment_approved'     => 1,
					'user_id'              => $user->ID,
					'comment_date'         => wp_date( 'Y-m-d H:i:s', $ts ),
					'comment_date_gmt'     => gmdate( 'Y-m-d H:i:s', $ts ),
				);
				$comment_id = wp_insert_comment( $data );
				update_comment_meta( $comment_id, 'rating', $rating );
				update_comment_meta( $comment_id, 'verified', 1 );
				do_action( 'comment_post', $comment_id, 1, $data );
				return $comment_id;
			}
		);
	}
	foreach ( array_unique( $reviewed ) as $pid ) {
		WC_Comments::clear_transients( $pid );
	}

	// Social shares and admin goodwill adjustments.
	$ids = array_keys( $customers );
	shuffle( $ids );
	foreach ( array_slice( $ids, 0, 18 ) as $user_id ) {
		$ts = mt_rand( $customers[ $user_id ]['reg'], $cap );
		growthpilot_demo_at(
			$ts,
			static function () use ( $user_id ) {
				$channels = array( 'facebook', 'whatsapp', 'x', 'email' );
				GrowthPilot_Points_Earner::award_social( $user_id, $channels[ array_rand( $channels ) ] );
			}
		);
	}
	foreach ( array_slice( $ids, 18, 5 ) as $user_id ) {
		growthpilot_demo_at(
			mt_rand( max( $customers[ $user_id ]['reg'], $now - 120 * $day ), $cap ),
			static function () use ( $user_id ) {
				GrowthPilot_Points_Ledger::credit(
					$user_id,
					100,
					'manual',
					array(
						'type'        => 'adjust',
						'description' => 'Goodwill for a delayed delivery',
						'created_by'  => 1,
					)
				);
			}
		);
	}

	// Redemptions from customers with enough points.
	$rewards = GrowthPilot_Rewards::all( true );
	foreach ( $ids as $user_id ) {
		if ( mt_rand( 1, 100 ) > 45 ) {
			continue;
		}
		$available  = GrowthPilot_Points_Ledger::available( $user_id );
		$affordable = array_values(
			array_filter(
				$rewards,
				static function ( $reward ) use ( $available ) {
					return (int) $reward->points_cost <= $available;
				}
			)
		);
		if ( ! $affordable ) {
			continue;
		}
		$reward = $affordable[ array_rand( $affordable ) ];
		growthpilot_demo_at(
			mt_rand( max( $customers[ $user_id ]['reg'], $now - 90 * $day ), $cap ),
			static function () use ( $user_id, $reward ) {
				return GrowthPilot_Rewards::redeem( $user_id, (int) $reward->id );
			}
		);
	}

	// Email performance, last 180 days.
	$emails = array(
		'customer_processing_order' => array( 'Processing order', 0.62, 0.08, 0.0 ),
		'customer_completed_order'  => array( 'Completed order', 0.55, 0.06, 0.0 ),
		'customer_new_account'      => array( 'New account', 0.48, 0.12, 0.03 ),
		'gp_points_reminder'        => array( 'Points balance reminder', 0.41, 0.09, 0.04 ),
		'gp_winback'                => array( 'Win-back offer', 0.33, 0.07, 0.05 ),
	);
	$email_rows = array();
	for ( $d = 180; $d >= 1; $d-- ) {
		$ts   = $now - $d * $day;
		$date = wp_date( 'Y-m-d', $ts );
		$dow  = (int) wp_date( 'N', $ts );
		foreach ( $emails as $key => $conf ) {
			if ( in_array( $key, array( 'gp_points_reminder', 'gp_winback' ), true ) ) {
				$sent = ( 'gp_points_reminder' === $key ? 2 : 4 ) === $dow ? mt_rand( 40, 90 ) : 0;
			} else {
				$sent = (int) round( growthpilot_demo_rand( 0, 3 ) * growthpilot_demo_season( $ts ) );
			}
			if ( $sent <= 0 ) {
				continue;
			}
			$opened    = (int) round( $sent * growthpilot_demo_rand( $conf[1] * 0.8, $conf[1] * 1.2 ) );
			$clicked   = (int) round( $opened * growthpilot_demo_rand( $conf[2] * 2, $conf[2] * 4 ) );
			$converted = (int) round( $sent * $conf[3] * growthpilot_demo_rand( 0.5, 1.5 ) );
			$email_rows[] = array(
				'email_key'   => $key,
				'email_title' => $conf[0],
				'stat_date'   => $date,
				'sent'        => $sent,
				'opened'      => $opened,
				'clicked'     => min( $clicked, $opened ),
				'converted'   => $converted,
				'revenue'     => round( $converted * growthpilot_demo_rand( 60, 180 ), 2 ),
			);
		}
	}
	$email_table = GrowthPilot::table( 'email_stats' );
	$before      = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id), 0) FROM {$email_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	growthpilot_demo_bulk_insert(
		$email_table,
		array(
			'email_key'   => '%s',
			'email_title' => '%s',
			'stat_date'   => '%s',
			'sent'        => '%d',
			'opened'      => '%d',
			'clicked'     => '%d',
			'converted'   => '%d',
			'revenue'     => '%f',
		),
		$email_rows
	);
	$seed['email_stats'] = array_map(
		'intval',
		$wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$email_table} WHERE id > %d", $before ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	);

	// Stock: a few fast sellers running out, slow movers overstocked, rest healthy.
	$recent = array();
	foreach ( array_keys( $catalog ) as $pid ) {
		$recent[ $pid ] = 0;
		foreach ( $units[ $pid ] ?? array() as $sale ) {
			if ( $sale[0] >= $now - 30 * $day ) {
				$recent[ $pid ] += $sale[1];
			}
		}
	}
	arsort( $recent );
	$ranked = array_keys( $recent );
	foreach ( $ranked as $rank => $pid ) {
		$monthly = max( 1, $recent[ $pid ] );
		if ( $rank < 4 ) {
			$stock = max( 1, (int) round( $monthly * 0.4 ) );
		} elseif ( $rank < 6 ) {
			$stock = 0;
		} elseif ( $rank >= count( $ranked ) - 6 ) {
			$stock = mt_rand( 220, 400 );
		} else {
			$stock = (int) round( $monthly * growthpilot_demo_rand( 1.5, 3.5 ) ) + 5;
		}

		$product = wc_get_product( $pid );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( $stock );
		$product->save();
	}

	update_option( growthpilot_demo_option(), $seed, false );

	// Live actions last so the sweep never touches them.
	GrowthPilot_Points_Earner::award_birthdays();
	GrowthPilot_VIP_Tiers::evaluate_all();
	GrowthPilot_AI_Engine::refresh_all();

	WP_CLI::success(
		sprintf(
			'Seeded %d customers, %d orders, %d reviews, %d referral clicks, %d analytics events, %d email stat rows.',
			count( $seed['users'] ),
			count( $seed['orders'] ),
			count( $seed['comments'] ),
			count( $clicks ),
			count( $events ),
			count( $seed['email_stats'] )
		)
	);
}

/**
 * Remove everything the seeder created and restore product stock/cost.
 *
 * @return void
 */
function growthpilot_demo_reset() {
	global $wpdb;

	$seed = get_option( growthpilot_demo_option() );
	if ( ! $seed ) {
		WP_CLI::warning( 'No demo data recorded.' );
		return;
	}

	growthpilot_demo_quiet();
	require_once ABSPATH . 'wp-admin/includes/user.php';

	$users  = array_map( 'intval', $seed['users'] );
	$orders = array_map( 'intval', $seed['orders'] );

	$coupon_ids = array();
	if ( $users ) {
		$placeholders = implode( ',', array_fill( 0, count( $users ), '%d' ) );
		$coupon_ids   = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT coupon_id FROM ' . GrowthPilot::table( 'redemptions' ) . " WHERE coupon_id IS NOT NULL AND customer_id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$users
			)
		);
	}

	foreach ( $orders as $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			continue;
		}
		foreach ( $order->get_refunds() as $refund ) {
			$refund->delete( true );
		}
		$order->delete( true );
	}
	foreach ( array( 'wc_order_stats', 'wc_order_product_lookup', 'wc_order_coupon_lookup', 'wc_order_tax_lookup' ) as $lookup ) {
		growthpilot_demo_delete_in( $wpdb->prefix . $lookup, 'order_id', $orders );
	}
	growthpilot_demo_delete_in( $wpdb->prefix . 'wc_order_stats', 'parent_id', $orders );
	growthpilot_demo_delete_in( $wpdb->prefix . 'wc_customer_lookup', 'user_id', $users );
	foreach ( array_chunk( $seed['guests'], 300 ) as $chunk ) {
		$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wc_customer_lookup WHERE user_id IS NULL AND email IN ({$placeholders})", $chunk ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	foreach ( array( 'points_ledger', 'points_balances', 'customer_tiers', 'customer_badges', 'challenge_progress', 'redemptions' ) as $table ) {
		growthpilot_demo_delete_in( GrowthPilot::table( $table ), 'customer_id', $users );
	}
	growthpilot_demo_delete_in( GrowthPilot::table( 'referrals' ), 'referrer_id', $users );
	growthpilot_demo_delete_in( GrowthPilot::table( 'referrals' ), 'referee_id', $users );
	growthpilot_demo_delete_in( GrowthPilot::table( 'challenge_progress' ), 'challenge_id', $seed['challenges'] );
	growthpilot_demo_delete_in( GrowthPilot::table( 'challenges' ), 'id', $seed['challenges'] );
	growthpilot_demo_delete_in( GrowthPilot::table( 'email_stats' ), 'id', $seed['email_stats'] );

	$like = $wpdb->esc_like( 'demo-' ) . '%';
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . GrowthPilot::table( 'analytics_events' ) . ' WHERE session_id LIKE %s', $like ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . GrowthPilot::table( 'referral_clicks' ) . ' WHERE visitor_hash LIKE %s', $like ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

	foreach ( array_merge( $seed['coupons'], array_map( 'intval', $coupon_ids ) ) as $coupon_id ) {
		wp_delete_post( (int) $coupon_id, true );
	}

	$products = array();
	foreach ( $seed['comments'] as $comment_id ) {
		$comment = get_comment( $comment_id );
		if ( $comment ) {
			$products[] = (int) $comment->comment_post_ID;
			wp_delete_comment( $comment_id, true );
		}
	}
	foreach ( array_unique( $products ) as $pid ) {
		WC_Comments::clear_transients( $pid );
	}

	foreach ( $users as $user_id ) {
		wp_delete_user( $user_id );
	}

	foreach ( $seed['products'] as $pid => $original ) {
		$product = wc_get_product( $pid );
		if ( ! $product ) {
			continue;
		}
		$product->set_manage_stock( $original['manage_stock'] );
		$product->set_stock_quantity( $original['stock'] );
		$product->set_stock_status( $original['stock_status'] );
		$product->save();

		if ( '' === $original['cogs'] ) {
			delete_post_meta( $pid, '_cogs' );
		} else {
			update_post_meta( $pid, '_cogs', $original['cogs'] );
		}
	}

	$wpdb->query( 'DELETE FROM ' . GrowthPilot::table( 'ai_predictions' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	delete_option( growthpilot_demo_option() );
	GrowthPilot_AI_Engine::flush();
	\Automattic\WooCommerce\Admin\API\Reports\Cache::invalidate();

	WP_CLI::success( sprintf( 'Removed %d demo customers and %d demo orders.', count( $users ), count( $orders ) ) );
}

/**
 * Replace demo customer and guest names already stored in the database.
 *
 * @return void
 */
function growthpilot_demo_rename() {
	global $wpdb;

	$seed = get_option( growthpilot_demo_option() );
	if ( ! $seed ) {
		WP_CLI::error( 'No demo data recorded.' );
	}

	$people = growthpilot_demo_identities();
	$users  = array_map( 'intval', $seed['users'] );
	sort( $users );

	$orders = $wpdb->prefix . 'wc_orders';
	$addr   = $wpdb->prefix . 'wc_order_addresses';
	$lookup = $wpdb->prefix . 'wc_customer_lookup';

	foreach ( $users as $user_id ) {
		$wpdb->update(
			$wpdb->users,
			array(
				'user_login'    => 'gpdemo' . $user_id,
				'user_nicename' => 'gpdemo' . $user_id,
				'user_email'    => 'gpdemo' . $user_id . '@example.com',
			),
			array( 'ID' => $user_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
		clean_user_cache( $user_id );
	}

	$apply = static function ( $where_sql, $where_args, $person, $email ) use ( $wpdb, $orders, $addr ) {
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$addr} a INNER JOIN {$orders} o ON o.id = a.order_id
				 SET a.first_name = %s, a.last_name = %s, a.city = %s, a.country = %s, a.postcode = %s,
				     a.address_1 = CONCAT( MOD(a.order_id, 80) + 1, ' ', %s ),
				     a.email = CASE WHEN a.address_type = 'billing' THEN %s ELSE a.email END,
				     a.phone = CASE WHEN a.address_type = 'billing' THEN %s ELSE a.phone END
				 WHERE {$where_sql}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge(
					array( $person['first'], $person['last'], $person['city'], $person['country'], $person['postcode'], $person['street'], $email, $person['phone'] ),
					$where_args
				)
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$orders} o SET o.billing_email = %s WHERE {$where_sql}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( array( $email ), $where_args )
			)
		);
	};

	foreach ( $users as $i => $user_id ) {
		$person = $people[ $i ];
		$email  = $person['login'] . '@example.com';
		$name   = $person['first'] . ' ' . $person['last'];

		$wpdb->update(
			$wpdb->users,
			array(
				'user_login'    => $person['login'],
				'user_nicename' => $person['login'],
				'user_email'    => $email,
				'display_name'  => $name,
			),
			array( 'ID' => $user_id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
		clean_user_cache( $user_id );

		update_user_meta( $user_id, 'first_name', $person['first'] );
		update_user_meta( $user_id, 'last_name', $person['last'] );
		update_user_meta( $user_id, 'billing_first_name', $person['first'] );
		update_user_meta( $user_id, 'billing_last_name', $person['last'] );
		update_user_meta( $user_id, 'billing_email', $email );
		update_user_meta( $user_id, 'billing_city', $person['city'] );
		update_user_meta( $user_id, 'billing_country', $person['country'] );
		update_user_meta( $user_id, 'billing_postcode', $person['postcode'] );
		update_user_meta( $user_id, 'billing_phone', $person['phone'] );
		update_user_meta( $user_id, 'billing_address_1', ( ( $user_id % 80 ) + 1 ) . ' ' . $person['street'] );
		update_user_meta( $user_id, 'shipping_first_name', $person['first'] );
		update_user_meta( $user_id, 'shipping_last_name', $person['last'] );
		update_user_meta( $user_id, 'shipping_city', $person['city'] );
		update_user_meta( $user_id, 'shipping_country', $person['country'] );

		$wpdb->update(
			$wpdb->comments,
			array(
				'comment_author'       => $name,
				'comment_author_email' => $email,
			),
			array( 'user_id' => $user_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		$apply( 'o.customer_id = %d', array( $user_id ), $person, $email );
		$wpdb->update(
			$lookup,
			array(
				'username' => $person['login'],
				'first_name' => $person['first'],
				'last_name'  => $person['last'],
				'email'      => $email,
				'city'       => $person['city'],
				'country'    => $person['country'],
				'postcode'   => $person['postcode'],
			),
			array( 'user_id' => $user_id )
		);
	}

	$guest_emails = $seed['guests'];
	foreach ( $guest_emails as $i => $old_email ) {
		$temp = 'guest-tmp-' . $i . '@example.com';
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$orders} SET billing_email = %s WHERE billing_email = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$temp,
				$old_email
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$lookup} SET email = %s WHERE (user_id IS NULL OR user_id = 0) AND email = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$temp,
				$old_email
			)
		);
		$guest_emails[ $i ] = $temp;
	}

	$guests = array();
	foreach ( $guest_emails as $i => $old_email ) {
		$person = $people[ count( $users ) + $i ];
		$email  = 'guest.' . $person['login'] . '@example.com';
		$apply( 'o.billing_email = %s', array( $old_email ), $person, $email );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$lookup} SET first_name = %s, last_name = %s, email = %s, city = %s, country = %s, postcode = %s WHERE (user_id IS NULL OR user_id = 0) AND email = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$person['first'],
				$person['last'],
				$email,
				$person['city'],
				$person['country'],
				$person['postcode'],
				$old_email
			)
		);
		$guests[] = $email;
	}

	$seed['guests'] = $guests;
	update_option( growthpilot_demo_option(), $seed, false );
	GrowthPilot_AI_Engine::refresh_all();

	WP_CLI::success( sprintf( 'Renamed %d customers and %d guests.', count( $users ), count( $guests ) ) );
}

if ( isset( $args[0] ) && 'reset' === $args[0] ) {
	growthpilot_demo_reset();
} elseif ( isset( $args[0] ) && 'rename' === $args[0] ) {
	growthpilot_demo_rename();
} else {
	growthpilot_demo_seed();
}
