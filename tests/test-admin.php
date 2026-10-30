<?php
/**
 * Tests for the reduced admin: menu, redirect, upload types, editor layout.
 *
 * @package Osec\Addon\Contributors
 */

use Osec\Addon\Contributors\Admin\AdminMenu;
use Osec\Addon\Contributors\Admin\EditorLayout;
use Osec\Addon\Contributors\Admin\MediaDialog;
use Osec\Addon\Contributors\Admin\PageRedirect;
use Osec\Addon\Contributors\Admin\ProfilePage;
use Osec\Addon\Contributors\Admin\Roles;
use Osec\Addon\Contributors\Admin\Uploads;

require_once __DIR__ . '/class-testcase.php';

/**
 * Admin changes for restricted users, and none for everyone else.
 */
class Test_Admin extends Osec_Addon_Contributors_TestCase {

	/**
	 * Dashboard and Media leave the menu, "All Events" becomes "My events".
	 */
	public function test_menu() {
		global $menu, $submenu;
		wp_set_current_user( $this->user( Roles::ASSISTANT ) );
		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$menu    = [
			[ 'Dashboard', 'read', 'index.php' ],
			[ 'Media', 'upload_files', 'upload.php' ],
			[ 'Events', 'edit_osec_events', 'edit.php?post_type=osec_event' ],
		];
		$submenu = [ 'edit.php?post_type=osec_event' => [ [ 'All Events', 'edit_osec_events', 'edit.php?post_type=osec_event' ] ] ];
		// phpcs:enable

		AdminMenu::reduce_menu();

		$this->assertSame( [ 'edit.php?post_type=osec_event' ], array_values( array_column( $menu, 2 ) ) );
		$this->assertSame( 'My events', $submenu['edit.php?post_type=osec_event'][0][0] );
	}

	/**
	 * Dashboard and media pages redirect contributors to their event list, others stay.
	 */
	public function test_page_redirect() {
		$redirects = [];
		add_filter(
			'wp_redirect',
			function ( $location ) use ( &$redirects ) {
				$redirects[] = $location;
				return false; // Stops the redirect, so the test does not exit.
			}
		);

		wp_set_current_user( $this->user( 'editor' ) );
		foreach ( PageRedirect::PAGES as $page ) {
			do_action( 'load-' . $page ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound, WordPress.NamingConventions.ValidHookName.UseUnderscores -- core hook.
		}
		$this->assertSame( [], $redirects );

		wp_set_current_user( $this->user( Roles::PUBLISHER ) );
		foreach ( PageRedirect::PAGES as $page ) {
			do_action( 'load-' . $page ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound, WordPress.NamingConventions.ValidHookName.UseUnderscores -- core hook.
		}
		$this->assertSame( array_fill( 0, 3, admin_url( 'edit.php?post_type=osec_event' ) ), $redirects );
	}

	/**
	 * After login contributors land on their event list, unless the login link named a page.
	 */
	public function test_login_redirect() {
		$assistant = get_userdata( $this->user( Roles::ASSISTANT ) );
		$editor    = get_userdata( $this->user( 'editor' ) );
		$events    = admin_url( 'edit.php?post_type=osec_event' );

		$this->assertSame( $events, apply_filters( 'login_redirect', admin_url(), '', $assistant ) );
		$this->assertSame( $events, apply_filters( 'login_redirect', admin_url(), admin_url(), $assistant ) );
		$this->assertSame( admin_url( 'post.php?post=5&action=edit' ), apply_filters( 'login_redirect', admin_url( 'post.php?post=5&action=edit' ), admin_url( 'post.php?post=5&action=edit' ), $assistant ) );
		$this->assertSame( admin_url(), apply_filters( 'login_redirect', admin_url(), '', $editor ) );
		$this->assertSame( admin_url(), apply_filters( 'login_redirect', admin_url(), '', new WP_Error() ) );
	}

	/**
	 * The admin bar's "+ New" creates an event, "New → Media" is gone.
	 */
	public function test_admin_bar_new_creates_event() {
		require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';
		wp_set_current_user( $this->user( Roles::ASSISTANT ) );
		$bar = new WP_Admin_Bar();
		$bar->initialize();
		wp_admin_bar_new_content_menu( $bar );
		$this->assertStringContainsString( 'media-new.php', $bar->get_node( 'new-content' )->href, 'core links "+ New" to Media' );

		AdminMenu::reduce_admin_bar( $bar );

		$this->assertSame( admin_url( 'post-new.php?post_type=osec_event' ), $bar->get_node( 'new-content' )->href );
		$this->assertNull( $bar->get_node( 'new-media' ) );
	}

	/**
	 * Contributors may upload images only, editors keep all types.
	 */
	public function test_upload_types() {
		$assistant = $this->user( Roles::ASSISTANT );
		$editor    = $this->user( 'editor' );

		$this->assertSame( Uploads::IMAGE_TYPES, get_allowed_mime_types( $assistant ) );
		$this->assertArrayHasKey( 'pdf', get_allowed_mime_types( $editor ) );
	}

	/**
	 * The default box order applies while none is saved, a saved order wins.
	 */
	public function test_editor_order_is_a_default_only() {
		$assistant = $this->user( Roles::ASSISTANT );
		$this->assertSame( EditorLayout::ORDER, get_user_option( 'meta-box-order_osec_event', $assistant ) );

		$saved = [ 'side' => 'submitdiv' ];
		update_user_option( $assistant, 'meta-box-order_osec_event', $saved, true );
		$this->assertSame( $saved, get_user_option( 'meta-box-order_osec_event', $assistant ) );

		$this->assertFalse( get_user_option( 'meta-box-order_osec_event', $this->user( 'editor' ) ) );
	}

	/**
	 * Tags, custom fields and slug are hidden by default in the event editor.
	 */
	public function test_hidden_boxes() {
		wp_set_current_user( $this->user( Roles::ASSISTANT ) );
		$screen = WP_Screen::get( 'osec_event' );
		$hidden = EditorLayout::default_hidden( [ 'slugdiv' ], $screen );

		$this->assertEqualsCanonicalizing( [ 'slugdiv', 'postcustom', 'tagsdiv-osec_events_tags' ], $hidden );
	}

	/**
	 * The "Personal Options" CSS is added on the own profile page of contributors only, and keeps the language.
	 */
	public function test_profile_hides_personal_options() {
		wp_set_current_user( $this->user( 'editor' ) );
		ProfilePage::hide_personal_options( 'profile.php' );
		$this->assertFalse( wp_style_is( ProfilePage::HANDLE, 'enqueued' ) );

		wp_set_current_user( $this->user( Roles::ASSISTANT ) );
		ProfilePage::hide_personal_options( 'user-edit.php' );
		$this->assertFalse( wp_style_is( ProfilePage::HANDLE, 'enqueued' ) );

		ProfilePage::hide_personal_options( 'profile.php' );
		$this->assertTrue( wp_style_is( ProfilePage::HANDLE, 'enqueued' ) );
		$css = implode( '', wp_styles()->get_data( ProfilePage::HANDLE, 'after' ) );
		$this->assertStringContainsString( '.user-admin-color-wrap', $css );
		$this->assertStringNotContainsString( 'user-language-wrap', $css );
	}

	/**
	 * The media dialog offers contributors no gallery and no playlists; editors keep them.
	 */
	public function test_media_dialog_without_gallery_and_playlists() {
		wp_set_current_user( $this->user( 'editor' ) );
		$this->assertTrue( apply_filters( 'media_library_show_audio_playlist', true ) );
		MediaDialog::hide_gallery();
		$this->assertStringNotContainsString( 'menu-item-gallery', implode( '', (array) wp_styles()->get_data( 'media-views', 'after' ) ) );

		wp_set_current_user( $this->user( Roles::ASSISTANT ) );
		$this->assertFalse( apply_filters( 'media_library_show_audio_playlist', true ) );
		$this->assertFalse( apply_filters( 'media_library_show_video_playlist', true ) );
		MediaDialog::hide_gallery();
		$css = implode( '', (array) wp_styles()->get_data( 'media-views', 'after' ) );
		foreach ( MediaDialog::HIDDEN_ENTRIES as $entry ) {
			$this->assertStringContainsString( '#' . $entry, $css );
		}
	}

	/**
	 * Contributors get no application passwords; for others the site's setting stays.
	 */
	public function test_no_application_passwords_for_contributors() {
		$this->assertFalse( apply_filters( 'wp_is_application_passwords_available_for_user', true, get_userdata( $this->user( Roles::PUBLISHER ) ) ) );
		$this->assertTrue( apply_filters( 'wp_is_application_passwords_available_for_user', true, get_userdata( $this->user( 'editor' ) ) ) );
	}
}
