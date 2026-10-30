<?php
/**
 * A reduced admin menu and admin bar for restricted users.
 *
 * @package Osec\Addon\Contributors
 */

namespace Osec\Addon\Contributors\Admin;

use Osec\Addon\Contributors\Plugin;
use Osec\Addon\Contributors\Restriction;
use WP_Admin_Bar;

/**
 * Hides the Dashboard and Media menus and the WordPress logo / "New → Media" admin bar entries,
 * points the admin bar's "+ New" to a new event, and renames OSEC's "All Events" to "My events"
 * (which also drops OSEC's count of everybody's pending events from the label).
 *
 * Media stays usable from the event editor's media dialog.
 */
final class AdminMenu {

	/**
	 * Admin bar nodes removed for restricted users.
	 */
	private const ADMIN_BAR_NODES = [ 'wp-logo', 'wp-logo-external', 'command-palette', 'new-media' ];

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'reduce_menu' ], 999 );
		add_action( 'admin_bar_menu', [ self::class, 'reduce_admin_bar' ], 999 );
	}

	/**
	 * Removes Dashboard and Media, renames "All Events".
	 *
	 * @return void
	 */
	public static function reduce_menu(): void {
		global $submenu;
		if ( ! Restriction::applies() ) {
			return;
		}
		remove_menu_page( 'index.php' );
		remove_menu_page( 'upload.php' );

		$list = 'edit.php?post_type=' . Plugin::post_type();
		foreach ( $submenu[ $list ] ?? [] as $position => $item ) {
			if ( $list === $item[2] ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- renaming a menu entry is done in $submenu.
				$submenu[ $list ][ $position ][0] = esc_html__( 'My events', 'osec-addon-contributors' );
			}
		}
	}

	/**
	 * Removes the WordPress logo menu, the command palette and "New → Media", and lets "+ New" create
	 * an event. WordPress links "+ New" to its first entry, for contributors that is "Media".
	 *
	 * @param WP_Admin_Bar $admin_bar The admin bar.
	 *
	 * @return void
	 */
	public static function reduce_admin_bar( WP_Admin_Bar $admin_bar ): void {
		if ( ! Restriction::applies() ) {
			return;
		}
		foreach ( self::ADMIN_BAR_NODES as $node ) {
			$admin_bar->remove_node( $node );
		}
		if ( $admin_bar->get_node( 'new-content' ) ) {
			$admin_bar->add_node(
				[
					'id'   => 'new-content',
					'href' => admin_url( 'post-new.php?post_type=' . Plugin::post_type() ),
				]
			);
		}
	}
}
