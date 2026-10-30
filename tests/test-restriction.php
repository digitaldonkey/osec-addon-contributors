<?php
/**
 * Tests for the restriction check and the roles.
 *
 * @package Osec\Addon\Contributors
 */

use Osec\Addon\Contributors\Admin\Roles;
use Osec\Addon\Contributors\Restriction;

require_once __DIR__ . '/class-testcase.php';

/**
 * Who is restricted, and what the roles can do.
 */
class Test_Restriction extends Osec_Addon_Contributors_TestCase {

	/**
	 * Both contributor roles are restricted, reviewers and users without event rights are not.
	 */
	public function test_applies_to_contributor_roles_only() {
		$this->assertTrue( Restriction::applies( $this->user( Roles::ASSISTANT ) ) );
		$this->assertTrue( Restriction::applies( $this->user( Roles::PUBLISHER ) ) );
		foreach ( [ 'administrator', 'editor', 'author', 'contributor', 'subscriber' ] as $role ) {
			$this->assertFalse( Restriction::applies( $this->user( $role ) ), $role );
		}
		$this->assertFalse( Restriction::applies(), 'logged out' );
	}

	/**
	 * The filter can include or exclude a user.
	 */
	public function test_filter_decides() {
		$assistant = $this->user( Roles::ASSISTANT );
		add_filter( 'osec_addon_contributors_applies_to_user', '__return_false' );
		$this->assertFalse( Restriction::applies( $assistant ) );
	}

	/**
	 * The publisher is the assistant plus publishing, both can upload.
	 */
	public function test_publisher_role() {
		$publisher = get_role( Roles::PUBLISHER );
		$assistant = get_role( Roles::ASSISTANT );
		$this->assertNotNull( $publisher );
		$this->assertTrue( $publisher->has_cap( 'publish_osec_events' ) );
		$this->assertFalse( $assistant->has_cap( 'publish_osec_events' ) );
		$this->assertFalse( $publisher->has_cap( 'edit_others_osec_events' ) );
		foreach ( [ $publisher, $assistant ] as $role ) {
			$this->assertTrue( $role->has_cap( 'upload_files' ), $role->name );
		}
	}

	/**
	 * Saving a draft with "Publish": the assistant submits it for review, the publisher publishes.
	 */
	public function test_publish_needs_review_for_assistant_only() {
		$this->assertSame( 'pending', $this->save_with_publish( $this->user( Roles::ASSISTANT ) ) );
		$this->assertSame( 'publish', $this->save_with_publish( $this->user( Roles::PUBLISHER ) ) );
	}

	/**
	 * Deactivation removes the upload right and keeps the role, uninstall moves publishers back.
	 */
	public function test_deactivate_and_uninstall() {
		$publisher = $this->user( Roles::PUBLISHER );

		Roles::deactivate();
		$this->assertFalse( get_role( Roles::ASSISTANT )->has_cap( 'upload_files' ) );
		$this->assertNotNull( get_role( Roles::PUBLISHER ) );

		Roles::uninstall();
		$this->assertNull( get_role( Roles::PUBLISHER ) );
		$this->assertSame( [ Roles::ASSISTANT ], array_values( get_userdata( $publisher )->roles ) );

		Roles::activate();
		$this->assertTrue( get_role( Roles::ASSISTANT )->has_cap( 'upload_files' ) );
	}

	/**
	 * Saves an own draft the way the event editor does, asking to publish it.
	 *
	 * @param int $user_id The saving user.
	 *
	 * @return string Resulting post status.
	 */
	private function save_with_publish( int $user_id ): string {
		require_once ABSPATH . 'wp-admin/includes/post.php';
		wp_set_current_user( $user_id );
		$event = $this->event( $user_id );
		$_POST = [
			'post_ID'              => $event,
			'post_type'            => 'osec_event',
			'post_title'           => 'Event',
			'original_post_status' => 'draft',
			'post_status'          => 'publish',
			'publish'              => 'Publish',
		];
		edit_post();
		$_POST = [];
		return get_post_status( $event );
	}
}
