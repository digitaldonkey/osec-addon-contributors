<?php
/**
 * A shorter profile page for restricted users.
 *
 * @package Osec\Addon\Contributors
 */

namespace Osec\Addon\Contributors\Admin;

use Osec\Addon\Contributors\Restriction;

/**
 * Hides the editing options of the "Personal Options" section (visual editor, syntax highlighting,
 * colour scheme, keyboard shortcuts, toolbar, media library scrolling) on the own profile page
 * (profile.php). The language choice stays. WordPress prints the rows without a filter, so a
 * stylesheet hides them. The hidden fields are still sent with the form, stored values do not change.
 * Administrators editing a contributor (user-edit.php) still see every row.
 */
final class ProfilePage {

	/**
	 * Style handle (inline CSS only).
	 */
	public const HANDLE = 'osec-addon-contributors-profile';

	/**
	 * Rows of "Personal Options" that are hidden; WordPress prints some of them only for some users.
	 */
	public const HIDDEN_ROWS = [
		'user-rich-editing-wrap',
		'user-syntax-highlighting-wrap',
		'user-admin-color-wrap',
		'user-comment-shortcuts-wrap',
		'user-admin-bar-front-wrap',
		'user-infinite-scrolling-wrap',
	];

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
	 * The CSS hiding the rows.
	 *
	 * @return string
	 */
	public static function css(): string {
		$selectors = array_map( static fn( string $row ): string => '#your-profile .' . $row, self::HIDDEN_ROWS );
		return implode( ', ', $selectors ) . ' { display: none; }';
	}
}
