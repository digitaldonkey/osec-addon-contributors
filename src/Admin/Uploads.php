<?php
/**
 * Image-only uploads for restricted users.
 *
 * @package Osec\Addon\Contributors
 */

namespace Osec\Addon\Contributors\Admin;

use Osec\Addon\Contributors\Restriction;
use WP_User;

/**
 * Restricted users may upload images only. WordPress checks the real file content against the
 * allowed types (wp_check_filetype_and_ext()), so renaming a file does not get around it.
 */
final class Uploads {

	/**
	 * Allowed types: extension pattern => MIME type.
	 */
	public const IMAGE_TYPES = [
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png'          => 'image/png',
		'gif'          => 'image/gif',
		'webp'         => 'image/webp',
		'avif'         => 'image/avif',
	];

	/**
	 * Registers the hook.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'upload_mimes', [ self::class, 'images_only' ], 10, 2 );
	}

	/**
	 * Reduces the allowed upload types to images for restricted users.
	 *
	 * @param array<string, string> $mimes Allowed types.
	 * @param WP_User|int|null      $user  The uploading user, null for the current user.
	 *
	 * @return array<string, string>
	 */
	public static function images_only( array $mimes, WP_User|int|null $user = null ): array {
		return Restriction::applies( $user ) ? self::IMAGE_TYPES : $mimes;
	}
}
