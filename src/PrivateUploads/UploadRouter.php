<?php
/**
 * Sends restricted users' uploads into the private folder.
 *
 * @package Osec\Addon\Contributors
 */

namespace Osec\Addon\Contributors\PrivateUploads;

use Osec\Addon\Contributors\Restriction;

/**
 * Uploads of restricted users go to the private folder, unless they are uploaded to an event that is
 * already published (it was approved once, decision: images there go public at once).
 *
 * Covers the media dialog (admin-ajax async-upload, `post_id`) and the block editor (REST, `post`).
 * While the folder is not safe (StorageCheck) such uploads are refused.
 */
final class UploadRouter {

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'wp_handle_upload_prefilter', [ self::class, 'route' ] );
		add_filter( 'wp_handle_sideload_prefilter', [ self::class, 'route' ] );
		add_filter( 'wp_handle_upload', [ self::class, 'done' ] );
		add_action( 'add_attachment', [ self::class, 'mark' ] );
	}

	/**
	 * Before WordPress moves an upload: decides whether it is private, refuses it while storage is unsafe.
	 *
	 * @param array $file The upload (`name`, `tmp_name`, ..., `error`).
	 *
	 * @return array
	 */
	public static function route( array $file ): array {
		if ( ! Restriction::applies() ) {
			return $file;
		}
		$parent = self::parent_id();
		if ( $parent && 'publish' === get_post_status( $parent ) ) {
			return $file;
		}
		if ( ! StorageCheck::is_safe() || ! Storage::ensure() ) {
			$file['error'] = __( 'Image uploads are paused until an administrator secures the storage for private images.', 'osec-addon-contributors' );
			return $file;
		}
		add_filter( 'upload_dir', [ self::class, 'private_dir' ] );
		return $file;
	}

	/**
	 * Points the upload target to the private folder (only while one upload is handled).
	 *
	 * @param array $uploads Result of wp_upload_dir().
	 *
	 * @return array
	 */
	public static function private_dir( array $uploads ): array {
		$uploads['path']   = Storage::root();
		$uploads['subdir'] = '';
		// Becomes the attachment's GUID only, every displayed URL comes from UrlFilter.
		$uploads['url'] = Endpoint::url( 0, '' );
		return $uploads;
	}

	/**
	 * After WordPress moved the upload: back to the normal uploads folder.
	 *
	 * @param array $upload Result of the upload (`file`, `url`, `type`).
	 *
	 * @return array
	 */
	public static function done( array $upload ): array {
		remove_filter( 'upload_dir', [ self::class, 'private_dir' ] );
		return $upload;
	}

	/**
	 * Marks a new attachment as private when its file landed in the private folder.
	 *
	 * @param int $attachment_id New attachment ID.
	 *
	 * @return void
	 */
	public static function mark( int $attachment_id ): void {
		remove_filter( 'upload_dir', [ self::class, 'private_dir' ] );
		$file = get_attached_file( $attachment_id, true );
		if ( is_string( $file ) && Storage::contains( $file ) ) {
			update_post_meta( $attachment_id, Storage::MARKER, 1 );
		}
	}

	/**
	 * The post the upload belongs to, as sent by the media dialog or the block editor.
	 *
	 * Core has already checked the request's nonce and the user's right to edit that post
	 * (async-upload.php / REST) before uploads are handled.
	 *
	 * @return int
	 */
	private static function parent_id(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified by core, see above.
		return absint( $_REQUEST['post_id'] ?? $_REQUEST['post'] ?? 0 );
	}
}
