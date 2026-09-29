<?php
/**
 * My Account — Referrals.
 *
 * @package Ciwp
 */

defined( 'ABSPATH' ) || exit;

// Included from Ciwp_Frontend::load_template(), so these assignments stay in that method.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$user_id  = get_current_user_id();
$share    = Ciwp_Share::payload( $user_id );
$stats    = Ciwp_Referral_Program::stats_for( $user_id );
$campaign = Ciwp_Referral_Program::active_campaign();
$history  = Ciwp_Referral_Program::list( array( 'referrer_id' => $user_id, 'per_page' => 20 ) );
?>
<div class="ciwp-account ciwp-referrals">
	<div class="ciwp-account__hero">
		<div>
			<p class="ciwp-account__kicker"><?php esc_html_e( 'Your referral code', 'myrvento-loyalty-for-woocommerce' ); ?></p>
			<p class="ciwp-account__balance"><?php echo esc_html( $share['code'] ); ?></p>
			<?php if ( $campaign ) : ?>
				<p class="ciwp-account__muted">
					<?php
					printf(
						/* translators: %d points */
						esc_html__( 'Earn %d points when a friend places their first order.', 'myrvento-loyalty-for-woocommerce' ),
						(int) $campaign->first_order_points
					);
					?>
				</p>
			<?php endif; ?>
		</div>
		<div class="ciwp-account__stats">
			<div><strong><?php echo esc_html( (string) $stats['clicks'] ); ?></strong><span><?php esc_html_e( 'Clicks', 'myrvento-loyalty-for-woocommerce' ); ?></span></div>
			<div><strong><?php echo esc_html( (string) $stats['signups'] ); ?></strong><span><?php esc_html_e( 'Signups', 'myrvento-loyalty-for-woocommerce' ); ?></span></div>
			<div><strong><?php echo esc_html( (string) $stats['converted'] ); ?></strong><span><?php esc_html_e( 'Orders', 'myrvento-loyalty-for-woocommerce' ); ?></span></div>
		</div>
	</div>

	<div class="ciwp-share" data-ciwp-share>
		<label>
			<?php esc_html_e( 'Referral link', 'myrvento-loyalty-for-woocommerce' ); ?>
			<input type="text" readonly value="<?php echo esc_attr( $share['url'] ); ?>" data-ciwp-copy-target>
		</label>
		<button type="button" class="button" data-ciwp-copy><?php esc_html_e( 'Copy link', 'myrvento-loyalty-for-woocommerce' ); ?></button>
		<div class="ciwp-share__channels">
			<a class="button" href="<?php echo esc_url( $share['email'] ); ?>" data-ciwp-share-channel="email"><?php esc_html_e( 'Email', 'myrvento-loyalty-for-woocommerce' ); ?></a>
			<a class="button" href="<?php echo esc_url( $share['whatsapp'] ); ?>" target="_blank" rel="noopener noreferrer" data-ciwp-share-channel="whatsapp"><?php esc_html_e( 'WhatsApp', 'myrvento-loyalty-for-woocommerce' ); ?></a>
			<a class="button" href="<?php echo esc_url( $share['facebook'] ); ?>" target="_blank" rel="noopener noreferrer" data-ciwp-share-channel="facebook"><?php esc_html_e( 'Facebook', 'myrvento-loyalty-for-woocommerce' ); ?></a>
			<a class="button" href="<?php echo esc_url( $share['twitter'] ); ?>" target="_blank" rel="noopener noreferrer" data-ciwp-share-channel="twitter"><?php esc_html_e( 'X', 'myrvento-loyalty-for-woocommerce' ); ?></a>
		</div>
		<canvas data-ciwp-qr="<?php echo esc_attr( $share['url'] ); ?>" width="160" height="160" aria-label="<?php esc_attr_e( 'Referral QR code', 'myrvento-loyalty-for-woocommerce' ); ?>"></canvas>
	</div>

	<h3><?php esc_html_e( 'Referral history', 'myrvento-loyalty-for-woocommerce' ); ?></h3>
	<table class="shop_table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Date', 'myrvento-loyalty-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Status', 'myrvento-loyalty-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Order', 'myrvento-loyalty-for-woocommerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $history['items'] as $row ) : ?>
				<tr>
					<td><?php echo esc_html( $row->created_at ); ?></td>
					<td><?php echo esc_html( $row->status ); ?></td>
					<td><?php echo $row->attributed_order_id ? esc_html( '#' . $row->attributed_order_id ) : '—'; ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if ( empty( $history['items'] ) ) : ?>
				<tr><td colspan="3"><?php esc_html_e( 'No referrals yet. Share your link to get started.', 'myrvento-loyalty-for-woocommerce' ); ?></td></tr>
			<?php endif; ?>
		</tbody>
	</table>
</div>
