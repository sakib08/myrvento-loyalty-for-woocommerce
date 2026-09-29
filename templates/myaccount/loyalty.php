<?php
/**
 * My Account — Loyalty.
 *
 * @package Myrvento
 *
 * @var int $user_id
 */

defined( 'ABSPATH' ) || exit;

// Included from Myrvento_Frontend::load_template(), so these assignments stay in that method.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$user_id   = get_current_user_id();
$balance   = Myrvento_Points_Ledger::get_balance( $user_id );
$history   = Myrvento_Points_Ledger::get_history( $user_id, array( 'per_page' => 15 ) );
$tier      = $balance->tier_id ? Myrvento_VIP_Tiers::get( (int) $balance->tier_id ) : Myrvento_VIP_Tiers::get_default();
$rewards   = Myrvento_Rewards::all( true );
$badges    = Myrvento_Gamification::customer_badges( $user_id );
$challenges = Myrvento_Gamification::customer_challenges( $user_id );
$points_name = Myrvento_Settings::get_value( 'points_name', __( 'Points', 'myrvento-loyalty-for-woocommerce' ) );
$birthday    = get_user_meta( $user_id, 'myrvento_birthday', true );
$redemptions = Myrvento_Rewards::redemptions( array( 'customer_id' => $user_id, 'per_page' => 10 ) );
?>
<div class="myrvento-account myrvento-loyalty">
	<div class="myrvento-account__hero">
		<div>
			<p class="myrvento-account__kicker"><?php echo esc_html( $points_name ); ?></p>
			<p class="myrvento-account__balance"><?php echo esc_html( number_format_i18n( (int) $balance->available ) ); ?></p>
			<p class="myrvento-account__muted">
				<?php
				printf(
					/* translators: 1: lifetime earned, 2: points name */
					esc_html__( '%1$s lifetime %2$s', 'myrvento-loyalty-for-woocommerce' ),
					esc_html( number_format_i18n( (int) $balance->lifetime_earned ) ),
					esc_html( strtolower( $points_name ) )
				);
				?>
			</p>
		</div>
		<?php if ( $tier ) : ?>
			<div class="myrvento-account__tier" style="--myrvento-tier: <?php echo esc_attr( $tier->color ); ?>">
				<span><?php echo esc_html( $tier->name ); ?></span>
				<?php
				$benefits = Myrvento::decode( $tier->benefits );
				if ( $benefits ) :
					?>
					<ul>
						<?php foreach ( $benefits as $benefit ) : ?>
							<li><?php echo esc_html( $benefit ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>

	<form class="myrvento-account__birthday" data-myrvento-birthday>
		<label>
			<?php esc_html_e( 'Birthday', 'myrvento-loyalty-for-woocommerce' ); ?>
			<input type="date" name="birthday" value="<?php echo $birthday ? esc_attr( wp_date( 'Y' ) . '-' . $birthday ) : ''; ?>">
		</label>
		<button type="submit"><?php esc_html_e( 'Save', 'myrvento-loyalty-for-woocommerce' ); ?></button>
	</form>

	<h3><?php esc_html_e( 'Rewards', 'myrvento-loyalty-for-woocommerce' ); ?></h3>
	<div class="myrvento-account__grid">
		<?php foreach ( $rewards as $reward ) : ?>
			<div class="myrvento-account__card">
				<strong><?php echo esc_html( $reward->name ); ?></strong>
				<p><?php echo esc_html( number_format_i18n( (int) $reward->points_cost ) . ' ' . $points_name ); ?></p>
				<button
					type="button"
					class="button"
					data-myrvento-redeem="<?php echo esc_attr( (string) $reward->id ); ?>"
					<?php disabled( (int) $balance->available < (int) $reward->points_cost ); ?>
				>
					<?php esc_html_e( 'Redeem', 'myrvento-loyalty-for-woocommerce' ); ?>
				</button>
			</div>
		<?php endforeach; ?>
	</div>

	<?php if ( $challenges ) : ?>
		<h3><?php esc_html_e( 'Challenges', 'myrvento-loyalty-for-woocommerce' ); ?></h3>
		<?php foreach ( $challenges as $challenge ) : ?>
			<?php
			$pct = $challenge['target_value'] > 0
				? min( 100, round( ( $challenge['progress'] / $challenge['target_value'] ) * 100 ) )
				: 0;
			?>
			<div class="myrvento-account__challenge">
				<div class="myrvento-account__challenge-head">
					<strong><?php echo esc_html( $challenge['name'] ); ?></strong>
					<span><?php echo esc_html( $challenge['progress'] . ' / ' . $challenge['target_value'] ); ?></span>
				</div>
				<div class="myrvento-progress"><span style="width: <?php echo esc_attr( (string) $pct ); ?>%"></span></div>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>

	<?php if ( $badges ) : ?>
		<h3><?php esc_html_e( 'Badges', 'myrvento-loyalty-for-woocommerce' ); ?></h3>
		<ul class="myrvento-account__badges">
			<?php foreach ( $badges as $badge ) : ?>
				<li><?php echo esc_html( $badge->name ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<h3><?php esc_html_e( 'History', 'myrvento-loyalty-for-woocommerce' ); ?></h3>
	<table class="shop_table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Date', 'myrvento-loyalty-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Description', 'myrvento-loyalty-for-woocommerce' ); ?></th>
				<th><?php echo esc_html( $points_name ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $history['items'] as $row ) : ?>
				<tr>
					<td><?php echo esc_html( $row->created_at ); ?></td>
					<td><?php echo esc_html( $row->description ); ?></td>
					<td><?php echo esc_html( ( $row->amount > 0 ? '+' : '' ) . $row->amount ); ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if ( empty( $history['items'] ) ) : ?>
				<tr><td colspan="3"><?php esc_html_e( 'No points activity yet.', 'myrvento-loyalty-for-woocommerce' ); ?></td></tr>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( ! empty( $redemptions['items'] ) ) : ?>
		<h3><?php esc_html_e( 'Redeemed rewards', 'myrvento-loyalty-for-woocommerce' ); ?></h3>
		<table class="shop_table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'myrvento-loyalty-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Coupon', 'myrvento-loyalty-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Points', 'myrvento-loyalty-for-woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $redemptions['items'] as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row->created_at ); ?></td>
						<td><code><?php echo esc_html( $row->coupon_code ); ?></code></td>
						<td><?php echo esc_html( (string) $row->points_spent ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
