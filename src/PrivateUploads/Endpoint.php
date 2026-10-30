<?php
/**
 * Delivers private images to the people allowed to see them.
 *
 * @package Osec\Addon\Contributors
 */

namespace Osec\Addon\Contributors\PrivateUploads;

use Osec\Addon\Contributors\Admin\Uploads;
use WP;
use WP_Post;

/**
 * URL: <home>/?osec_private_file=<attachment ID>/<file name>, works with any permalink setting.
 * The file name stays the last path part, so WordPress can build the URLs of image sizes from it.
 *
 * Who sees a private image: its uploader, and users who can edit others' events (Editors, Authors,
 * Administrators: the reviewers). Everyone else gets 403, unknown files 404. Once the image is public,
 * the URL redirects (301) to the public file, so links stored earlier keep working.
 */
final class Endpoint {

	/**
	 * Query variable of the URL.
	 */
	public const QUERY_VAR = 'osec_private_file';

	/**
	 * Registers the query variable and the request handler.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'query_vars', [ self::class, 'query_vars' ] );
		add_action( 'parse_request', [ self::class, 'serve' ] );
	}

	/**
	 * Adds the query variable.
	 *
	 * @param string[] $vars Public query variables.
	 *
	 * @return string[]
	 */
	public static function query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * The URL of one file of a private attachment.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $name          File name (main file or a size).
	 *
	 * @return string
	 */
	public static function url( int $attachment_id, string $name ): string {
		return home_url( '/' ) . '?' . self::QUERY_VAR . '=' . $attachment_id . '/' . rawurlencode( $name );
	}

	/**
	 * Handles a request for a private file and ends it.
	 *
	 * @param WP $wp The request.
	 *
	 * @return void
	 */
	public static function serve( WP $wp ): void {
		if ( ! isset( $wp->query_vars[ self::QUERY_VAR ] ) ) {
			return;
		}
		$result = self::resolve_query_value( $wp->query_vars[ self::QUERY_VAR ] );

		nocache_headers();
		header( 'X-Content-Type-Options: nosniff' );
		if ( 301 === $result['status'] ) {
			// The address is the attachment's own URL from WordPress, possibly on a media host (offload plugins).
			wp_redirect( esc_url_raw( $result['location'] ), 301 ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
			exit;
		}
		if ( 200 !== $result['status'] ) {
			status_header( $result['status'] );
			exit;
		}
		status_header( 200 );
		header( 'Content-Type: ' . $result['type'] );
		header( 'Content-Length: ' . filesize( $result['path'] ) );
		header( 'Cache-Control: private, no-store' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streams the image without loading it into memory.
		readfile( $result['path'] );
		exit;
	}

	/**
	 * Decides what a request gets, for any query value (WordPress passes arrays through as well).
	 *
	 * @param mixed $value Query value.
	 *
	 * @return array{status:int, path?:string, type?:string, location?:string}
	 */
	public static function resolve_query_value( mixed $value ): array {
		return is_string( $value ) ? self::resolve( $value ) : [ 'status' => 404 ];
	}

	/**
	 * Decides what a request gets.
	 *
	 * @param string $value Query value "<attachment ID>/<file name>".
	 *
	 * @return array{status:int, path?:string, type?:string, location?:string}
	 */
	public static function resolve( string $value ): array {
		if ( ! preg_match( '#^(\d+)/([^/\\\\]+)$#', $value, $match ) ) {
			return [ 'status' => 404 ];
		}
		$id   = (int) $match[1];
		$name = wp_basename( rawurldecode( $match[2] ) );
		$post = get_post( $id );
		if ( ! $post instanceof WP_Post || 'attachment' !== $post->post_type || ! in_array( $name, Storage::file_names( $id ), true ) ) {
			return [ 'status' => 404 ];
		}

		if ( ! Storage::is_private( $id ) ) {
			$main = wp_get_attachment_url( $id );
			return [
				'status'   => 301,
				'location' => str_replace( wp_basename( $main ), $name, $main ),
			];
		}

		if ( ! self::can_view( $post ) ) {
			return [ 'status' => 403 ];
		}

		$path = dirname( get_attached_file( $id, true ) ) . '/' . $name;
		// The plugin's own image list, not the viewer's allowed upload types (which may include SVG).
		$type = wp_check_filetype( $name, Uploads::IMAGE_TYPES )['type'];
		if ( ! Storage::contains( $path ) || ! is_file( $path ) || ! is_string( $type ) || ! str_starts_with( $type, 'image/' ) ) {
			return [ 'status' => 404 ];
		}
		return [
			'status' => 200,
			'path'   => $path,
			'type'   => $type,
		];
	}

	/**
	 * Whether the current user may see a private attachment: its uploader or a reviewer.
	 *
	 * @param WP_Post $attachment The attachment.
	 *
	 * @return bool
	 */
	public static function can_view( WP_Post $attachment ): bool {
		$user_id = get_current_user_id();
		return $user_id && ( (int) $attachment->post_author === $user_id || current_user_can( 'edit_others_osec_events' ) );
	}
}
