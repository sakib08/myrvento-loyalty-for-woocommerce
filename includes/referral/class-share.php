<?php
/**
 * Share channel helpers for referral links.
 *
 * @package Ciwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Share & invite URLs.
 */
class Ciwp_Share {

	/**
	 * Share payload for a customer.
	 *
	 * @param int $user_id User ID.
	 * @return array<string, mixed>
	 */
	public static function payload( $user_id ) {
		$url  = Ciwp_Referral_Program::share_url( $user_id );
		$code = Ciwp_Referral_Program::get_or_create_code( $user_id );
		$text = rawurlencode(
			sprintf(
				/* translators: %s site name */
				__( 'Join me at %s and get a welcome bonus.', 'myrvento-loyalty-for-woocommerce' ),
				wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
			)
		);

		return array(
			'code'     => $code,
			'url'      => $url,
			'email'    => 'mailto:?subject=' . rawurlencode( get_bloginfo( 'name' ) ) . '&body=' . $text . '%20' . rawurlencode( $url ),
			'whatsapp' => 'https://wa.me/?text=' . $text . '%20' . rawurlencode( $url ),
			'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $url ),
			'twitter'  => 'https://twitter.com/intent/tweet?text=' . $text . '&url=' . rawurlencode( $url ),
		);
	}
}
