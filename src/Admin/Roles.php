<?php
/**
 * Event roles: the Event Publisher role and the upload right of both contributor roles.
 *
 * @package Osec\Addon\Contributors
 */

namespace Osec\Addon\Contributors\Admin;

/**
 * Creates `osec_event_publisher` and grants `upload_files` to it and to OSEC's `osec_event_assistant`.
 *
 * Lifecycle: activation creates the role and grants the upload right, deactivation takes the upload
 * right away again and keeps the role, uninstall moves publishers back to Event Contributor and removes
 * the role. Role changes are WordPress's own (Users → Role, `wp user set-role`), done by administrators.
 */
final class Roles {

	/**
	 * OSEC's contributor role: drafts events, publishing needs a review.
	 */
	public const ASSISTANT = 'osec_event_assistant';

	/**
	 * This plugin's role: like the assistant, plus publishing without review.
	 */
	public const PUBLISHER = 'osec_event_publisher';

	/**
	 * Capabilities of the publisher role: OSEC's assistant capabilities plus publish and upload.
	 */
	public const PUBLISHER_CAPS = [
		'read',
		'read_osec_event',
		'edit_osec_events',
		'delete_osec_events',
		'edit_published_osec_events',
		'delete_published_osec_events',
		'publish_osec_events',
		'upload_files',
	];

	/**
	 * Registers the hook that restores the upload right after OSEC rebuilt its role.
	 *
	 * @return void
	 */
	public static function register(): void {
		// After OSEC (init -100), which deletes and recreates its role when the role looks wrong.
		add_action( 'init', [ self::class, 'ensure_upload_right' ], 20 );
	}

	/**
	 * Activation: creates the publisher role (or completes its capabilities) and grants uploads.
	 *
	 * @return void
	 */
	public static function activate(): void {
		$publisher = get_role( self::PUBLISHER );
		if ( ! $publisher ) {
			// Stored untranslated like WordPress's own role names.
			$publisher = add_role( self::PUBLISHER, 'Event Publisher', array_fill_keys( self::PUBLISHER_CAPS, true ) );
		}
		foreach ( self::PUBLISHER_CAPS as $cap ) {
			if ( $publisher && ! $publisher->has_cap( $cap ) ) {
				$publisher->add_cap( $cap );
			}
		}
		self::ensure_upload_right();
	}

	/**
	 * Grants `upload_files` to both roles where it is missing. Writes only when something is missing.
	 *
	 * @return void
	 */
	public static function ensure_upload_right(): void {
		foreach ( [ self::ASSISTANT, self::PUBLISHER ] as $name ) {
			$role = get_role( $name );
			if ( $role && ! $role->has_cap( 'upload_files' ) ) {
				$role->add_cap( 'upload_files' );
			}
		}
	}

	/**
	 * Deactivation: takes the upload right away, keeps the publisher role and its users.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		foreach ( [ self::ASSISTANT, self::PUBLISHER ] as $name ) {
			get_role( $name )?->remove_cap( 'upload_files' );
		}
	}

	/**
	 * Uninstall: publishers become Event Contributors, then the publisher role is removed.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		foreach ( get_users( [ 'role' => self::PUBLISHER ] ) as $user ) {
			$user->remove_role( self::PUBLISHER );
			$user->add_role( self::ASSISTANT );
		}
		remove_role( self::PUBLISHER );
		get_role( self::ASSISTANT )?->remove_cap( 'upload_files' );
	}
}
