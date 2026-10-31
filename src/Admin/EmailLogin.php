<?php
/**
 * "Email" as the login field label.
 *
 * @package Osec\Addon\Contributors
 */

namespace Osec\Addon\Contributors\Admin;

/**
 * Renames core's "Username or Email Address" to "Email" on wp-login.php (login and lost-password form),
 * for everyone. Only the label changes: a username still works.
 *
 * The gettext filter is added on `login_init`, so it runs on wp-login.php only.
 */
final class EmailLogin {

	/**
	 * Core's label that is replaced.
	 */
	public const LABEL = 'Username or Email Address';

	/**
	 * Registers the hook.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'login_init', [ self::class, 'on_login_page' ] );
	}

	/**
	 * Adds the label filter on wp-login.php.
	 *
	 * @return void
	 */
	public static function on_login_page(): void {
		add_filter( 'gettext', [ self::class, 'email_label' ], 20, 3 );
	}

	/**
	 * Replaces core's login label.
	 *
	 * @param string $translation Translated text.
	 * @param string $text        Original text.
	 * @param string $domain      Text domain.
	 *
	 * @return string
	 */
	public static function email_label( $translation, $text, $domain ) {
		if ( 'default' === $domain && self::LABEL === $text ) {
			return __( 'Email', 'osec-addon-contributors' );
		}
		return $translation;
	}
}
