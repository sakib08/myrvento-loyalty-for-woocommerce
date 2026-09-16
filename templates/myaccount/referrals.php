<?php
/**
 * My Account — Referrals.
 *
 * @package GrowthPilot
 */

defined( 'ABSPATH' ) || exit;

$user_id  = get_current_user_id();
$share    = GrowthPilot_Share::payload( $user_id );
$stats    = GrowthPilot_Referral_Program::stats_for( $user_id );
$campaign = GrowthPilot_Referral_Program::active_campaign();
$history  = GrowthPilot_Referral_Program::list( array( 'referrer_id' => $user_id, 'per_page' => 20 ) );
?>
<div class="gp-account gp-referrals">
	<div class="gp-account__hero">
		<div>
			<p class="gp-account__kicker"><?php esc_html_e( 'Your referral code', 'growthpilot' ); ?></p>
			<p class="gp-account__balance"><?php echo esc_html( $share['code'] ); ?></p>
			<?php if ( $campaign ) : ?>
				<p class="gp-account__muted">
					<?php
					printf(
						/* translators: %d points */
						esc_html__( 'Earn %d points when a friend places their first order.', 'growthpilot' ),
						(int) $campaign->first_order_points
					);
					?>
				</p>
			<?php endif; ?>
		</div>
		<div class="gp-account__stats">
			<div><strong><?php echo esc_html( (string) $stats['clicks'] ); ?></strong><span><?php esc_html_e( 'Clicks', 'growthpilot' ); ?></span></div>
			<div><strong><?php echo esc_html( (string) $stats['signups'] ); ?></strong><span><?php esc_html_e( 'Signups', 'growthpilot' ); ?></span></div>
			<div><strong><?php echo esc_html( (string) $stats['converted'] ); ?></strong><span><?php esc_html_e( 'Orders', 'growthpilot' ); ?></span></div>
		</div>
	</div>

	<div class="gp-share" data-gp-share>
		<label>
			<?php esc_html_e( 'Referral link', 'growthpilot' ); ?>
			<input type="text" readonly value="<?php echo esc_attr( $share['url'] ); ?>" data-gp-copy-target>
		</label>
		<button type="button" class="button" data-gp-copy><?php esc_html_e( 'Copy link', 'growthpilot' ); ?></button>
		<div class="gp-share__channels">
			<a class="button" href="<?php echo esc_url( $share['email'] ); ?>" data-gp-share-channel="email"><?php esc_html_e( 'Email', 'growthpilot' ); ?></a>
			<a class="button" href="<?php echo esc_url( $share['whatsapp'] ); ?>" target="_blank" rel="noopener noreferrer" data-gp-share-channel="whatsapp"><?php esc_html_e( 'WhatsApp', 'growthpilot' ); ?></a>
			<a class="button" href="<?php echo esc_url( $share['facebook'] ); ?>" target="_blank" rel="noopener noreferrer" data-gp-share-channel="facebook"><?php esc_html_e( 'Facebook', 'growthpilot' ); ?></a>
			<a class="button" href="<?php echo esc_url( $share['twitter'] ); ?>" target="_blank" rel="noopener noreferrer" data-gp-share-channel="twitter"><?php esc_html_e( 'X', 'growthpilot' ); ?></a>
		</div>
		<canvas data-gp-qr="<?php echo esc_attr( $share['url'] ); ?>" width="160" height="160" aria-label="<?php esc_attr_e( 'Referral QR code', 'growthpilot' ); ?>"></canvas>
	</div>

	<h3><?php esc_html_e( 'Referral history', 'growthpilot' ); ?></h3>
	<table class="shop_table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Date', 'growthpilot' ); ?></th>
				<th><?php esc_html_e( 'Status', 'growthpilot' ); ?></th>
				<th><?php esc_html_e( 'Order', 'growthpilot' ); ?></th>
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
				<tr><td colspan="3"><?php esc_html_e( 'No referrals yet. Share your link to get started.', 'growthpilot' ); ?></td></tr>
			<?php endif; ?>
		</tbody>
	</table>
</div>
