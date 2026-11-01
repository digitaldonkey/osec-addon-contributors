<?php
/**
 * Makes an event's private images public when the event is published.
 *
 * @package Osec\Addon\Contributors
 */

namespace Osec\Addon\Contributors\PrivateUploads;

use Osec\Addon\Contributors\Plugin;
use WP_Post;

/**
 * Whenever a published event is saved (first publication included, scheduled ones too) or gets a featured
 * image, every private image of the event's author used by it moves to the public uploads folder: the
 * featured image, images attached to the event and images placed in the event text (`wp-image-<ID>`).
 * All sizes move, the event text gets the public URLs, and the endpoint redirects old links. Unpublishing
 * does not make images private again. An image whose file is missing stays private.
 *
 * Target: uploads/<year>/<month>/ (event site's timezone); when a file name is already taken there,
 * uploads/<year>/<month>/<attachment ID>/, so no file is renamed and every size keeps its name.
 */
final class Publisher {

	/**
	 * Registers the hook.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_after_insert_post', [ self::class, 'after_save' ], 10, 2 );
		// The classic editor sets the featured image right away, before the event is saved.
		add_action( 'added_post_meta', [ self::class, 'featured_image_set' ], 10, 3 );
		add_action( 'updated_post_meta', [ self::class, 'featured_image_set' ], 10, 3 );
	}

	/**
	 * After an event was saved, with its meta and terms.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    The post.
	 *
	 * @return void
	 */
	public static function after_save( int $post_id, WP_Post $post ): void {
		unset( $post_id );
		self::publish_images( $post );
	}

	/**
	 * After a featured image was set on a post.
	 *
	 * @param int    $meta_id   Meta ID.
	 * @param int    $object_id Post ID.
	 * @param string $meta_key  Meta key.
	 *
	 * @return void
	 */
	public static function featured_image_set( $meta_id, $object_id, $meta_key ): void {
		unset( $meta_id );
		$post = '_thumbnail_id' === $meta_key ? get_post( (int) $object_id ) : null;
		if ( $post instanceof WP_Post ) {
			self::publish_images( $post );
		}
	}

	/**
	 * Makes the private images of a published event public.
	 *
	 * @param WP_Post $post The event.
	 *
	 * @return void
	 */
	public static function publish_images( WP_Post $post ): void {
		if ( 'publish' !== $post->post_status || Plugin::post_type() !== $post->post_type ) {
			return;
		}
		$replace = [];
		foreach ( self::private_images_of( $post ) as $attachment_id ) {
			$replace += self::make_public( $attachment_id );
		}
		if ( $replace ) {
			self::rewrite_content( $post->ID, $replace );
		}
	}

	/**
	 * Private images of the event's author used by the event.
	 *
	 * @param WP_Post $post The event.
	 *
	 * @return int[]
	 */
	public static function private_images_of( WP_Post $post ): array {
		$ids   = [ (int) get_post_thumbnail_id( $post ) ];
		$ids   = array_merge( $ids, array_keys( get_attached_media( '', $post ) ) );
		$found = [];
		preg_match_all( '/wp-image-(\d+)/', $post->post_content, $found );
		$ids = array_merge( $ids, array_map( 'intval', $found[1] ?? [] ) );

		return array_values(
			array_filter(
				array_unique( $ids ),
				static fn( int $id ): bool => $id > 0
					&& (int) get_post_field( 'post_author', $id ) === (int) $post->post_author
					&& Storage::is_private( $id )
			)
		);
	}

	/**
	 * Moves all files of a private attachment to the public uploads folder.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return array<string, string> Old endpoint URL => new public URL, per file; empty on failure.
	 */
	public static function make_public( int $attachment_id ): array {
		$main_file = get_attached_file( $attachment_id, true );
		if ( ! is_string( $main_file ) || ! file_exists( $main_file ) ) {
			self::log_failure( $attachment_id, 'its file is missing' );
			return [];
		}
		$source = dirname( $main_file );
		$names  = array_filter( Storage::file_names( $attachment_id ), static fn( $name ) => file_exists( $source . '/' . $name ) );
		$target = self::target_dir( $attachment_id, $names );
		if ( null === $target ) {
			self::log_failure( $attachment_id, 'no free public folder' );
			return [];
		}

		$fs    = Storage::filesystem();
		$moved = [];
		foreach ( $names as $name ) {
			if ( ! $fs->move( $source . '/' . $name, $target['path'] . '/' . $name ) ) {
				foreach ( $moved as $done ) {
					$fs->move( $target['path'] . '/' . $done, $source . '/' . $done );
				}
				self::log_failure( $attachment_id, 'could not move ' . $name );
				return [];
			}
			$moved[] = $name;
		}
		self::set_mode( $target['path'], $moved, self::upload_mode( $target['path'] ), $attachment_id );

		$main = wp_basename( $main_file );
		$meta = wp_get_attachment_metadata( $attachment_id );
		update_attached_file( $attachment_id, $target['path'] . '/' . $main );
		if ( is_array( $meta ) ) {
			$meta['file'] = ltrim( $target['subdir'] . '/' . $main, '/' );
			wp_update_attachment_metadata( $attachment_id, $meta );
		}
		delete_post_meta( $attachment_id, Storage::MARKER );

		$urls = [];
		foreach ( $names as $name ) {
			$urls[ Endpoint::url( $attachment_id, $name ) ] = $target['url'] . '/' . $name;
		}
		return $urls;
	}

	/**
	 * The public folder for an attachment's files: the month folder, or the first subfolder
	 * <attachment ID>, <attachment ID>-2, ... in which no file name is taken.
	 *
	 * @param int      $attachment_id Attachment ID.
	 * @param string[] $names         File names to place.
	 *
	 * @return array{path:string, url:string, subdir:string}|null Null when no free folder is found.
	 */
	private static function target_dir( int $attachment_id, array $names ): ?array {
		$uploads = wp_upload_dir( wp_date( 'Y/m' ) );
		if ( ! empty( $uploads['error'] ) ) {
			return null;
		}
		$candidates = [ '' ];
		for ( $n = 1; $n <= 10; $n++ ) {
			$candidates[] = '/' . $attachment_id . ( $n > 1 ? '-' . $n : '' );
		}
		foreach ( $candidates as $sub ) {
			$path  = $uploads['path'] . $sub;
			$taken = array_filter( $names, static fn( $name ) => file_exists( $path . '/' . $name ) );
			if ( ! $taken ) {
				return wp_mkdir_p( $path ) ? [
					'path'   => $path,
					'url'    => $uploads['url'] . $sub,
					'subdir' => $uploads['subdir'] . $sub,
				] : null;
			}
		}
		return null;
	}

	/**
	 * Replaces the endpoint URLs in the event text with the public URLs.
	 *
	 * Written directly, so no save hooks or content filters run a second time during publishing.
	 *
	 * @param int                   $post_id Event ID.
	 * @param array<string, string> $replace Old URL => new URL.
	 *
	 * @return void
	 */
	private static function rewrite_content( int $post_id, array $replace ): void {
		global $wpdb;
		$content = (string) get_post_field( 'post_content', $post_id, 'raw' );
		$updated = strtr( $content, $replace + array_combine( array_map( 'esc_url', array_keys( $replace ) ), $replace ) );
		if ( $updated !== $content ) {
			$wpdb->update( $wpdb->posts, [ 'post_content' => $updated ], [ 'ID' => $post_id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			clean_post_cache( $post_id );
		}
	}

	/**
	 * The mode WordPress gives an upload in a folder (_wp_handle_upload()): the folder's mode & 0666.
	 *
	 * @param string $dir Folder.
	 *
	 * @return int 0 when the folder's mode cannot be read.
	 */
	public static function upload_mode( string $dir ): int {
		$mode = @fileperms( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- false is handled.
		return false === $mode ? 0 : $mode & 0666;
	}

	/**
	 * Gives moved files the mode of a normal upload. Otherwise they keep the private folder's mode:
	 * 0600 when it sits below a 0710 host folder (Plesk), which the web server cannot read.
	 *
	 * @param string   $dir           Folder of the files.
	 * @param string[] $names         File names.
	 * @param int      $mode          Mode to set (upload_mode()).
	 * @param int      $attachment_id Attachment ID, for the log.
	 *
	 * @return bool False when a file kept its mode (logged).
	 */
	public static function set_mode( string $dir, array $names, int $mode, int $attachment_id ): bool {
		// Core's chmod() takes 0 as "no mode" and falls back to FS_CHMOD_FILE, undefined outside WP_Filesystem().
		if ( 0 === $mode ) {
			self::log_unreadable( $attachment_id, 'the mode of ' . $dir . ' cannot be read' );
			return false;
		}
		$fs = Storage::filesystem();
		$ok = true;
		foreach ( $names as $name ) {
			$file = $dir . '/' . $name;
			if ( ! $fs->is_file( $file ) || ! $fs->chmod( $file, $mode ) ) {
				self::log_unreadable( $attachment_id, 'could not set the mode of ' . $name );
				$ok = false;
			}
		}
		return $ok;
	}

	/**
	 * Logs a public image whose files may be unreadable for the web server (only with WP_DEBUG_LOG).
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $reason        What failed.
	 *
	 * @return void
	 */
	private static function log_unreadable( int $attachment_id, string $reason ): void {
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- only with WP_DEBUG_LOG.
			error_log( sprintf( 'Contributors Add-on: image %d is public, but its files keep the private permissions: %s.', $attachment_id, $reason ) );
		}
	}

	/**
	 * Logs an image that could not be made public; it stays private (fail closed).
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $reason        What failed.
	 *
	 * @return void
	 */
	private static function log_failure( int $attachment_id, string $reason ): void {
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- only with WP_DEBUG_LOG.
			error_log( sprintf( 'Contributors Add-on: image %d stays private, %s.', $attachment_id, $reason ) );
		}
	}
}
