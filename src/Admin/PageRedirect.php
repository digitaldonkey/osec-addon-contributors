<?php
/**
 * Dashboard and media pages → event list for restricted users.
 *
 * @package Osec\Addon\Contributors
 */

namespace Osec\Addon\Contributors\Admin;

use Osec\Addon\Contributors\Plugin;
use Osec\Addon\Contributors\Restriction;
use WP_Error;
use WP_User;

/**
 * Restricted users land on their event list: after login (WordPress would send them to their profile,
 * they lack the general `edit_posts` right), and when they open the Dashboard, the media library
 * (upload.php) or the upload page (media-new.php). Contributors use media only inside an event,
 * through the editor's media dialog. A login link that names a page keeps it.
 *
 * Every other admin page (profile, other plugins' pages) stays reachable.
 */
final class PageRedirect {

	/**
	 * Admin pages that redirect restricted users. `load-<page>` fires only when that page is requested.
	 */
	public const PAGES = [ 'index.php', 'upload.php', 'media-new.php' ];

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		foreach ( self::PAGES as $page ) {
			add_action( 'load-' . $page, [ self::class, 'redirect' ] );
		}
		add_filter( 'login_redirect', [ self::class, 'after_login' ], 10, 3 );
	}

	/**
	 * After login: the event list, unless the login link asked for a page.
	 *
	 * @param string           $redirect_to           Where WordPress will send the user.
	 * @param string           $requested_redirect_to The `redirect_to` of the login link, may be empty.
	 * @param WP_User|WP_Error $user                  The user, or the login error.
	 *
	 * @return string
	 */
	public static function after_login( $redirect_to, $requested_redirect_to, $user ) {
		if ( ! $user instanceof WP_User || ! Restriction::applies( $user ) ) {
			return $redirect_to;
		}
		if ( '' !== (string) $requested_redirect_to && admin_url() !== $requested_redirect_to ) {
			return $redirect_to;
		}
		return self::target();
	}

	/**
	 * Redirects restricted users to their event list.
	 *
	 * @return void
	 */
	public static function redirect(): void {
		if ( Restriction::applies() && wp_safe_redirect( self::target() ) ) {
			exit;
		}
	}

	/**
	 * The event list URL.
	 *
	 * @return string
	 */
	public static function target(): string {
		return admin_url( 'edit.php?post_type=' . Plugin::post_type() );
	}
}
