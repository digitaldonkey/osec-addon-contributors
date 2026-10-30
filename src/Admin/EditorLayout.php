<?php
/**
 * Default layout of the event editor for restricted users.
 *
 * @package Osec\Addon\Contributors
 */

namespace Osec\Addon\Contributors\Admin;

use Osec\Addon\Contributors\Plugin;
use Osec\Addon\Contributors\Restriction;
use WP_Screen;

/**
 * Gives restricted users a tidy event editor: an order of the boxes and a few hidden ones.
 *
 * Both are defaults only. WordPress uses them while the user has not arranged the boxes or changed
 * "Screen Options" themselves, a saved choice always wins. Nothing is written to the database.
 */
final class EditorLayout {

	/**
	 * Default box order per column, box IDs of WordPress and OSEC.
	 */
	public const ORDER = [
		'side'     => 'submitdiv,osec_events_categoriesdiv,tagsdiv-osec_events_tags',
		'normal'   => 'osec_event_meta_box_location,osec_event_meta_box_tickets,osec_eventmeta_box_organizer',
		'advanced' => 'osec_event_meta_box_date,postimagediv',
	];

	/**
	 * Boxes hidden by default: custom fields, tags (restricted users cannot create tags), slug.
	 */
	public const HIDDEN = [ 'postcustom', 'tagsdiv-osec_events_tags', 'slugdiv' ];

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'get_user_option_meta-box-order_' . Plugin::post_type(), [ self::class, 'default_order' ], 10, 3 );
		add_filter( 'default_hidden_meta_boxes', [ self::class, 'default_hidden' ], 10, 2 );
	}

	/**
	 * Returns the default order when the user has none saved.
	 *
	 * @param mixed  $value  Saved order, false when there is none.
	 * @param string $option Option name.
	 * @param mixed  $user   The user (WP_User).
	 *
	 * @return mixed
	 */
	public static function default_order( mixed $value, string $option, mixed $user ): mixed {
		unset( $option );
		if ( false !== $value || ! Restriction::applies( $user instanceof \WP_User ? $user : null ) ) {
			return $value;
		}
		return self::ORDER;
	}

	/**
	 * Adds the default hidden boxes (used by WordPress only while the user saved no choice).
	 *
	 * @param string[]  $hidden Box IDs hidden by default.
	 * @param WP_Screen $screen Current screen.
	 *
	 * @return string[]
	 */
	public static function default_hidden( array $hidden, WP_Screen $screen ): array {
		if ( 'post' !== $screen->base || Plugin::post_type() !== $screen->post_type || ! Restriction::applies() ) {
			return $hidden;
		}
		return array_values( array_unique( array_merge( $hidden, self::HIDDEN ) ) );
	}
}
