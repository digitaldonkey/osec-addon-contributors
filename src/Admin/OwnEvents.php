<?php
/**
 * Own events only in the event list.
 *
 * @package Osec\Addon\Contributors
 */

namespace Osec\Addon\Contributors\Admin;

use Osec\Addon\Contributors\Plugin;
use Osec\Addon\Contributors\Restriction;
use WP_Query;

/**
 * Restricted users see only their own events in wp-admin/edit.php?post_type=osec_event, and the
 * status links above the list ("All (3) | Drafts (1)") count only their own events.
 *
 * WordPress itself lists other people's drafts and pending events there (titles only, no edit link).
 */
final class OwnEvents {

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'pre_get_posts', [ self::class, 'limit_list' ] );
		add_filter( 'views_edit-' . Plugin::post_type(), [ self::class, 'own_counts' ] );
	}

	/**
	 * Limits the admin event list query to the current user's events.
	 *
	 * @param WP_Query $query The query being prepared.
	 *
	 * @return void
	 */
	public static function limit_list( WP_Query $query ): void {
		if ( is_admin() && $query->is_main_query() && Plugin::post_type() === $query->get( 'post_type' ) && Restriction::applies() ) {
			$query->set( 'author', get_current_user_id() );
		}
	}

	/**
	 * Replaces the counts of the status links with the user's own counts, drops links without own events.
	 *
	 * @param array<string, string> $views Status links keyed by status ("all", "draft", "mine", ...).
	 *
	 * @return array<string, string>
	 */
	public static function own_counts( array $views ): array {
		if ( ! Restriction::applies() ) {
			return $views;
		}
		$counts = self::count_own( get_current_user_id() );
		$all    = 0;
		foreach ( get_post_stati( [ 'show_in_admin_all_list' => true ] ) as $status ) {
			$all += $counts[ $status ] ?? 0;
		}

		// "Mine" now shows the same as "All".
		unset( $views['mine'] );
		foreach ( $views as $status => $link ) {
			$count = 'all' === $status ? $all : ( $counts[ $status ] ?? 0 );
			if ( 0 === $count && 'all' !== $status ) {
				unset( $views[ $status ] );
				continue;
			}
			$views[ $status ] = preg_replace(
				'#<span class="count">\([^)]*\)</span>#',
				'<span class="count">(' . number_format_i18n( $count ) . ')</span>',
				$link
			);
		}
		return $views;
	}

	/**
	 * Counts a user's events per post status.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return array<string, int> Status => number of events.
	 */
	public static function count_own( int $user_id ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one grouped count instead of one query per status; changes with every save.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_status, COUNT(*) AS num FROM {$wpdb->posts} WHERE post_type = %s AND post_author = %d GROUP BY post_status",
				Plugin::post_type(),
				$user_id
			)
		);
		return array_map( 'intval', array_column( $rows, 'num', 'post_status' ) );
	}
}
