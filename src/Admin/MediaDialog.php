<?php
/**
 * A simpler media dialog for restricted users.
 *
 * @package Osec\Addon\Contributors
 */

namespace Osec\Addon\Contributors\Admin;

use Osec\Addon\Contributors\Restriction;

/**
 * Removes "Create gallery", "Create audio playlist" and "Create video playlist" from the media dialog
 * of the event editor. Contributors add single images to an event.
 *
 * The playlists have core filters, which make core mark their entries `hidden`. In WordPress 7.1 that
 * mark has no effect: `.media-menu .media-menu-item { display: block }` comes after
 * `.media-frame .hidden { display: none }` in core's CSS with the same specificity. "Create gallery" has no
 * filter at all. So the stylesheet of the media dialog gets a rule hiding all three menu entries.
 */
final class MediaDialog {

	/**
	 * Menu entries of the media dialog hidden for restricted users.
	 */
	public const HIDDEN_ENTRIES = [ 'menu-item-gallery', 'menu-item-playlist', 'menu-item-video-playlist' ];

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'media_library_show_audio_playlist', [ self::class, 'show_playlist' ] );
		add_filter( 'media_library_show_video_playlist', [ self::class, 'show_playlist' ] );
		add_action( 'wp_enqueue_media', [ self::class, 'hide_gallery' ] );
	}

	/**
	 * No playlists for restricted users.
	 *
	 * @param bool|null $show Whether to show the playlist entry (null: when such media exist).
	 *
	 * @return bool|null
	 */
	public static function show_playlist( $show ) {
		return Restriction::applies() ? false : $show;
	}

	/**
	 * Hides gallery and playlist entries for restricted users whenever the media dialog is loaded.
	 *
	 * @return void
	 */
	public static function hide_gallery(): void {
		if ( Restriction::applies() ) {
			$selectors = array_map( static fn( string $id ): string => '.media-modal #' . $id, self::HIDDEN_ENTRIES );
			wp_add_inline_style( 'media-views', implode( ', ', $selectors ) . ' { display: none; }' );
		}
	}
}
