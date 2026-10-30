<?php
/**
 * Shared helpers for the tests: users of each role and events.
 *
 * @package Osec\Addon\Contributors
 */

/**
 * Base class of this plugin's tests.
 */
abstract class Osec_Addon_Contributors_TestCase extends WP_UnitTestCase {

	/**
	 * Creates a user with the given role.
	 *
	 * @param string $role Role name.
	 *
	 * @return int User ID.
	 */
	protected function user( string $role ): int {
		return self::factory()->user->create( [ 'role' => $role ] );
	}

	/**
	 * Creates an event.
	 *
	 * @param int    $author Author user ID.
	 * @param string $status Post status.
	 *
	 * @return int Post ID.
	 */
	protected function event( int $author, string $status = 'draft' ): int {
		return self::factory()->post->create(
			[
				'post_type'   => 'osec_event',
				'post_author' => $author,
				'post_status' => $status,
			]
		);
	}

	/**
	 * Creates an image attachment record (no file needed for capability checks).
	 *
	 * @param int $author Author user ID.
	 * @param int $parent_id Parent post ID, 0 for none.
	 *
	 * @return int Attachment ID.
	 */
	protected function image( int $author, int $parent_id = 0 ): int {
		return self::factory()->attachment->create(
			[
				'post_author'    => $author,
				'post_parent'    => $parent_id,
				'post_mime_type' => 'image/jpeg',
				'file'           => 'image.jpg',
			]
		);
	}
}
