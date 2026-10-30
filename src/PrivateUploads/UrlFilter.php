<?php
/**
 * Private images get endpoint URLs and stay out of public listings.
 *
 * @package Osec\Addon\Contributors
 */

namespace Osec\Addon\Contributors\PrivateUploads;

use Osec\Addon\Contributors\Restriction;
use WP_Error;
use WP_Post;
use WP_REST_Response;

/**
 * Rewrites the URLs WordPress builds for private attachments (main file, sizes, srcset) to the
 * endpoint, and hides private attachments from the REST media API for everyone who may not see
 * them (logged out visitors included: WordPress lists unattached uploads there).
 */
final class UrlFilter {

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'wp_get_attachment_url', [ self::class, 'attachment_url' ], 10, 2 );
		add_filter( 'wp_calculate_image_srcset', [ self::class, 'srcset' ], 10, 5 );
		add_filter( 'rest_attachment_query', [ self::class, 'rest_query' ], 20 );
		add_filter( 'rest_prepare_attachment', [ self::class, 'rest_item' ], 10, 2 );
	}

	/**
	 * Main file URL of a private attachment. Size URLs follow, WordPress swaps the file name.
	 *
	 * @param string $url           URL WordPress built.
	 * @param int    $attachment_id Attachment ID.
	 *
	 * @return string
	 */
	public static function attachment_url( string $url, int $attachment_id ): string {
		if ( ! Storage::is_private( $attachment_id ) ) {
			return $url;
		}
		return Endpoint::url( $attachment_id, wp_basename( get_attached_file( $attachment_id, true ) ) );
	}

	/**
	 * Srcset URLs of a private attachment (WordPress builds them from the uploads folder URL).
	 *
	 * @param array  $sources       Sources keyed by width, each with a `url`.
	 * @param array  $size_array    Requested size.
	 * @param string $image_src     The image's src.
	 * @param array  $image_meta    Attachment metadata.
	 * @param int    $attachment_id Attachment ID.
	 *
	 * @return array
	 */
	public static function srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
		unset( $size_array, $image_src, $image_meta );
		if ( ! is_array( $sources ) || ! Storage::is_private( (int) $attachment_id ) ) {
			return $sources;
		}
		foreach ( $sources as $width => $source ) {
			$sources[ $width ]['url'] = Endpoint::url( (int) $attachment_id, wp_basename( $source['url'] ) );
		}
		return $sources;
	}

	/**
	 * REST media collection: no private attachments for users who may not see them.
	 *
	 * Restricted users already see only their own uploads (OwnMedia), reviewers see all.
	 *
	 * @param array<string, mixed> $args WP_Query arguments.
	 *
	 * @return array<string, mixed>
	 */
	public static function rest_query( array $args ): array {
		if ( Restriction::applies() || current_user_can( 'edit_others_osec_events' ) ) {
			return $args;
		}
		$args['meta_query']   = (array) ( $args['meta_query'] ?? [] ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$args['meta_query'][] = [
			'key'     => Storage::MARKER,
			'compare' => 'NOT EXISTS',
		];
		return $args;
	}

	/**
	 * REST single attachment: 404 for a private one the user may not see.
	 *
	 * @param WP_REST_Response $response The response.
	 * @param WP_Post          $post     The attachment.
	 *
	 * @return WP_REST_Response
	 */
	public static function rest_item( WP_REST_Response $response, WP_Post $post ): WP_REST_Response {
		if ( ! Storage::is_private( $post->ID ) || Endpoint::can_view( $post ) ) {
			return $response;
		}
		return rest_convert_error_to_response(
			new WP_Error( 'rest_post_invalid_id', __( 'Invalid post ID.', 'osec-addon-contributors' ), [ 'status' => 404 ] )
		);
	}
}
