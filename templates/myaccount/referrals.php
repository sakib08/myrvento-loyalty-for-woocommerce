<?php
/**
 * My Account — Referrals.
 *
 * @package GrowthPilot
 */

defined( 'ABSPATH' ) || exit;

// Included from GrowthPilot_Frontend::load_template(), so these assignments stay in that method.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$user_id  = get_current_user_id();
$share    = GrowthPilot_Share::payload( $user_id );
$stats    = GrowthPilot_Referral_Program::stats_for( $user_id );
$campaign = GrowthPilot_Referral_Program::active_campaign();
$history  = GrowthPilot_Referral_Program::list( array( 'referrer_id' => $user_id, 'per_page' => 20 ) );
?>
<div class="gp-ppros-account gp-ppros-referrals">
	<div class="gp-ppros-account__hero">
		<div>
			<p class="gp-ppros-account__kicker"><?php esc_html_e( 'Your referral code', 'gp_ppros' ); ?></p>
			<p class="gp-ppros-account__balance"><?php echo esc_html( $share['code'] ); ?></p>
			<?php if ( $campaign ) : ?>
				<p class="gp-ppros-account__muted">
					<?php
					printf(
						/* translators: %d points */
						esc_html__( 'Earn %d points when a friend places their first order.', 'gp_ppros' ),
						(int) $campaign->first_order_points
					);
					?>
				</p>
			<?php endif; ?>
		</div>
		<div class="gp-ppros-account__stats">
			<div><strong><?php echo esc_html( (string) $stats['clicks'] ); ?></strong><span><?php esc_html_e( 'Clicks', 'gp_ppros' ); ?></span></div>
			<div><strong><?php echo esc_html( (string) $stats['signups'] ); ?></strong><span><?php esc_html_e( 'Signups', 'gp_ppros' ); ?></span></div>
			<div><strong><?php echo esc_html( (string) $stats['converted'] ); ?></strong><span><?php esc_html_e( 'Orders', 'gp_ppros' ); ?></span></div>
		</div>
	</div>

	<div class="gp-ppros-share" data-gp-ppros-share>
		<label>
			<?php esc_html_e( 'Referral link', 'gp_ppros' ); ?>
			<input type="text" readonly value="<?php echo esc_attr( $share['url'] ); ?>" data-gp-ppros-copy-target>
		</label>
		<button type="button" class="button" data-gp-ppros-copy><?php esc_html_e( 'Copy link', 'gp_ppros' ); ?></button>
		<div class="gp-ppros-share__channels">
			<a class="button" href="<?php echo esc_url( $share['email'] ); ?>" data-gp-ppros-share-channel="email"><?php esc_html_e( 'Email', 'gp_ppros' ); ?></a>
			<a class="button" href="<?php echo esc_url( $share['whatsapp'] ); ?>" target="_blank" rel="noopener noreferrer" data-gp-ppros-share-channel="whatsapp"><?php esc_html_e( 'WhatsApp', 'gp_ppros' ); ?></a>
			<a class="button" href="<?php echo esc_url( $share['facebook'] ); ?>" target="_blank" rel="noopener noreferrer" data-gp-ppros-share-channel="facebook"><?php esc_html_e( 'Facebook', 'gp_ppros' ); ?></a>
			<a class="button" href="<?php echo esc_url( $share['twitter'] ); ?>" target="_blank" rel="noopener noreferrer" data-gp-ppros-share-channel="twitter"><?php esc_html_e( 'X', 'gp_ppros' ); ?></a>
		</div>
		<canvas data-gp-ppros-qr="<?php echo esc_attr( $share['url'] ); ?>" width="160" height="160" aria-label="<?php esc_attr_e( 'Referral QR code', 'gp_ppros' ); ?>"></canvas>
	</div>

	<h3><?php esc_html_e( 'Referral history', 'gp_ppros' ); ?></h3>
	<table class="shop_table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Date', 'gp_ppros' ); ?></th>
				<th><?php esc_html_e( 'Status', 'gp_ppros' ); ?></th>
				<th><?php esc_html_e( 'Order', 'gp_ppros' ); ?></th>
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
				<tr><td colspan="3"><?php esc_html_e( 'No referrals yet. Share your link to get started.', 'gp_ppros' ); ?></td></tr>
			<?php endif; ?>
		</tbody>
	</table>
</div>
