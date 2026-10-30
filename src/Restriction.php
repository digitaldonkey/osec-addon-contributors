<?php
/**
 * Who the plugin restricts.
 *
 * @package Osec\Addon\Contributors
 */

namespace Osec\Addon\Contributors;

use WP_User;

/**
 * The one check every part uses: a restricted user can edit events, but not other people's events.
 *
 * That matches osec_event_assistant, osec_event_publisher and any role built the same way.
 * Administrators, Editors and Authors (OSEC grants them edit_others_osec_events) are not restricted,
 * users without event rights (Subscriber, WordPress Contributor) are left alone.
 */
final class Restriction {

	/**
	 * Whether the given user (default: the current user) is restricted.
	 *
	 * @param WP_User|int|null $user User object, user ID or null for the current user.
	 *
	 * @return bool
	 */
	public static function applies( WP_User|int|null $user = null ): bool {
		$user = match ( true ) {
			$user instanceof WP_User => $user,
			is_int( $user )          => get_userdata( $user ),
			default                  => wp_get_current_user(),
		};
		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return false;
		}

		$applies = user_can( $user, 'edit_osec_events' ) && ! user_can( $user, 'edit_others_osec_events' );

		/**
		 * Filters whether a user gets the restricted contributor admin and private uploads.
		 *
		 * @param bool    $applies True for users who can edit events but not others' events.
		 * @param WP_User $user    The user checked.
		 */
		return (bool) apply_filters( 'osec_addon_contributors_applies_to_user', $applies, $user );
	}
}
