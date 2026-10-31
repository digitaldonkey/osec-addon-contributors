<?php
/**
 * Registration with an email address only.
 *
 * @package Osec\Addon\Contributors
 */

namespace Osec\Addon\Contributors\Admin;

/**
 * Hides the username field of core's registration form (wp-login.php?action=register). Every new
 * account gets a random login name of 8 lowercase letters and digits; whatever the form sends as
 * username is ignored. People log in with their email address.
 *
 * The login name ends up in the author URL (/author/<name>/) and is the first display name, so it is
 * random rather than derived from the email address. Users can change the display name in their profile.
 *
 * Core reads `$_POST['user_login']` after the `login_form_register` action, so the name is set there
 * and core's own checks run on it unchanged. Multisite registers through wp-signup.php, not covered.
 */
final class EmailRegistration {

	/**
	 * Style handle (inline CSS only).
	 */
	public const HANDLE = 'osec-addon-contributors-registration';

	/**
	 * Length of the random part of a login name.
	 */
	public const NAME_LENGTH = 8;

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'login_form_register', [ self::class, 'set_login_name' ] );
		add_action( 'login_enqueue_scripts', [ self::class, 'hide_username_field' ] );
	}

	/**
	 * Replaces the sent username by a random, unused login name.
	 *
	 * @return void
	 */
	public static function set_login_name(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- core's registration form has no nonce; only the name is replaced.
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			$_POST['user_login'] = self::login_name();
		}
	}

	/**
	 * A random login name that no user has yet; on a clash -2, -3 ... is added.
	 *
	 * @return string
	 */
	public static function login_name(): string {
		$base = strtolower( wp_generate_password( self::NAME_LENGTH, false ) );
		$name = $base;
		$n    = 1;
		while ( username_exists( $name ) ) {
			++$n;
			$name = $base . '-' . $n;
		}
		return $name;
	}

	/**
	 * Hides the username field on the registration form.
	 *
	 * @return void
	 */
	public static function hide_username_field(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only selects the form shown.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		if ( 'register' !== $action ) {
			return;
		}
		wp_register_style( self::HANDLE, false, [], OSEC_ADDON_CONTRIBUTORS_VERSION );
		wp_enqueue_style( self::HANDLE );
		wp_add_inline_style( self::HANDLE, self::css() );
	}

	/**
	 * The CSS hiding the username row.
	 *
	 * @return string
	 */
	public static function css(): string {
		return '#registerform p:has(> #user_login) { display: none; }';
	}
}
