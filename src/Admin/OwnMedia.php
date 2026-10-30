<?php
/**
 * Own media only, and managing own images.
 *
 * @package Osec\Addon\Contributors
 */

namespace Osec\Addon\Contributors\Admin;

use Osec\Addon\Contributors\Restriction;
use WP_Post;
use WP_Query;
use WP_REST_Request;

/**
 * Restricted users see only their own uploads, in every place WordPress lists media:
 * the media dialog (admin-ajax `query-attachments`), the block editor (REST `/wp/v2/media`) and
 * the media list wp-admin/upload.php (hidden from their menu, reachable by URL).
 *
 * They may also edit their own images (title, alt text, caption), which WordPress refuses without
 * the general `edit_posts` right, and delete them as long as no published post uses them.
 */
final class OwnMedia {

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'ajax_query_attachments_args', [ self::class, 'limit_ajax' ] );
		add_filter( 'rest_attachment_query', [ self::class, 'limit_rest' ], 10, 2 );
		add_action( 'pre_get_posts', [ self::class, 'limit_list' ] );
		add_filter( 'map_meta_cap', [ self::class, 'own_media_caps' ], 10, 4 );
	}

	/**
	 * Media dialog query: own uploads only.
	 *
	 * @param array<string, mixed> $query Query arguments for WP_Query.
	 *
	 * @return array<string, mixed>
	 */
	public static function limit_ajax( array $query ): array {
		if ( Restriction::applies() ) {
			$query['author'] = get_current_user_id();
		}
		return $query;
	}

	/**
	 * REST media collection: own uploads only.
	 *
	 * @param array<string, mixed> $args    Query arguments for WP_Query.
	 * @param WP_REST_Request      $request The REST request.
	 *
	 * @return array<string, mixed>
	 */
	public static function limit_rest( array $args, WP_REST_Request $request ): array {
		unset( $request );
		if ( Restriction::applies() ) {
			$args['author'] = get_current_user_id();
		}
		return $args;
	}

	/**
	 * Media list (upload.php, list mode): own uploads only.
	 *
	 * @param WP_Query $query The query being prepared.
	 *
	 * @return void
	 */
	public static function limit_list( WP_Query $query ): void {
		if ( is_admin() && $query->is_main_query() && 'attachment' === $query->get( 'post_type' ) && Restriction::applies() ) {
			$query->set( 'author', get_current_user_id() );
		}
	}

	/**
	 * Lets restricted users edit their own images, and delete them while no published post uses them.
	 *
	 * @param string[] $caps    Primitive capabilities WordPress requires.
	 * @param string   $cap     Capability being checked.
	 * @param int      $user_id User ID.
	 * @param array    $args    Extra arguments, the post ID first.
	 *
	 * @return string[]
	 */
	public static function own_media_caps( array $caps, string $cap, int $user_id, array $args ): array {
		if ( ! in_array( $cap, [ 'edit_post', 'delete_post' ], true ) || empty( $args[0] ) ) {
			return $caps;
		}
		$post = get_post( (int) $args[0] );
		if ( ! $post instanceof WP_Post || 'attachment' !== $post->post_type || (int) $post->post_author !== $user_id ) {
			return $caps;
		}
		if ( ! Restriction::applies( $user_id ) ) {
			return $caps;
		}
		if ( 'delete_post' === $cap && self::is_used_by_published( $post ) ) {
			return [ 'do_not_allow' ];
		}
		return [ 'upload_files' ];
	}

	/**
	 * Whether a published post uses the attachment: as its parent, or as the featured image of one.
	 *
	 * @param WP_Post $attachment The attachment.
	 *
	 * @return bool
	 */
	public static function is_used_by_published( WP_Post $attachment ): bool {
		if ( $attachment->post_parent && 'publish' === get_post_status( $attachment->post_parent ) ) {
			return true;
		}
		$featured_on = get_posts(
			[
				'post_type'      => 'any',
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery -- only on a delete check, not on page views.
				'meta_query'     => [
					[
						'key'   => '_thumbnail_id',
						'value' => (string) $attachment->ID,
					],
				],
			]
		);
		return [] !== $featured_on;
	}
}
