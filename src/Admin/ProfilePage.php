<?php
/**
 * A shorter profile page for restricted users.
 *
 * @package Osec\Addon\Contributors
 */

namespace Osec\Addon\Contributors\Admin;

use Osec\Addon\Contributors\Restriction;

/**
 * Hides the "Personal Options" section (toolbar, colour scheme, shortcuts, language, ...) of the own
 * profile page (profile.php). WordPress prints it without a filter, so a stylesheet hides its heading
 * and table. The hidden fields are still sent with the form, stored values do not change.
 * Administrators editing a contributor (user-edit.php) still see the section.
 */
final class ProfilePage {

	/**
	 * Style handle (inline CSS only).
	 */
	public const HANDLE = 'osec-addon-contributors-profile';

	/**
	 * Rows of the "Personal Options" table; WordPress prints some of them only for some users.
	 */
	private const ROWS = '.user-rich-editing-wrap, .user-syntax-highlighting-wrap, .user-admin-color-wrap, .user-comment-shortcuts-wrap, .user-admin-bar-front-wrap, .user-language-wrap';

	/**
	 * Registers the hook.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_enqueue_scripts', [ self::class, 'hide_personal_options' ] );
	}

	/**
	 * Adds the CSS on the own profile page of restricted users.
	 *
	 * @param string $hook_suffix Current admin page.
	 *
	 * @return void
	 */
	public static function hide_personal_options( string $hook_suffix ): void {
		if ( 'profile.php' !== $hook_suffix || ! Restriction::applies() ) {
			return;
		}
		wp_register_style( self::HANDLE, false, [], OSEC_ADDON_CONTRIBUTORS_VERSION );
		wp_enqueue_style( self::HANDLE );
		wp_add_inline_style( self::HANDLE, self::css() );
	}

	/**
	 * The CSS: the table holding the personal option rows, and the heading right before it.
	 *
	 * @return string
	 */
	public static function css(): string {
		$table = '#your-profile .form-table:has(' . self::ROWS . ')';
		return '#your-profile h2:has(+ .form-table :is(' . self::ROWS . ")), {$table} { display: none; }";
	}
}
