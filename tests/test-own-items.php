<?php
/**
 * Tests for "own events and own media only".
 *
 * @package Osec\Addon\Contributors
 */

use Osec\Addon\Contributors\Admin\OwnEvents;
use Osec\Addon\Contributors\Admin\OwnMedia;
use Osec\Addon\Contributors\Admin\Roles;

require_once __DIR__ . '/class-testcase.php';

/**
 * Event list, status counts, media queries and own-media capabilities.
 */
class Test_Own_Items extends Osec_Addon_Contributors_TestCase {

	/**
	 * The event list in wp-admin shows a contributor their own events only.
	 */
	public function test_event_list_shows_own_events_only() {
		$me    = $this->user( Roles::ASSISTANT );
		$other = $this->user( Roles::ASSISTANT );
		$mine  = $this->event( $me );
		$this->event( $other );

		$this->assertSame( [ $mine ], $this->admin_list( $me, 'edit-osec_event', [ 'post_type' => 'osec_event' ] ) );
	}

	/**
	 * An editor still sees everybody's events.
	 */
	public function test_event_list_unchanged_for_editor() {
		$editor = $this->user( 'editor' );
		$this->event( $this->user( Roles::ASSISTANT ) );
		$this->event( $this->user( Roles::PUBLISHER ) );

		$this->assertCount( 2, $this->admin_list( $editor, 'edit-osec_event', [ 'post_type' => 'osec_event' ] ) );
	}

	/**
	 * Status links count own events, "Mine" and statuses without own events disappear.
	 */
	public function test_status_counts_are_own() {
		$me    = $this->user( Roles::ASSISTANT );
		$other = $this->user( Roles::ASSISTANT );
		$this->event( $me, 'draft' );
		$this->event( $me, 'publish' );
		$this->event( $other, 'pending' );
		wp_set_current_user( $me );

		$views = OwnEvents::own_counts(
			[
				'all'     => '<a href="#">All <span class="count">(3)</span></a>',
				'mine'    => '<a href="#">Mine <span class="count">(2)</span></a>',
				'publish' => '<a href="#">Published <span class="count">(1)</span></a>',
				'draft'   => '<a href="#">Draft <span class="count">(1)</span></a>',
				'pending' => '<a href="#">Pending <span class="count">(1)</span></a>',
			]
		);

		$this->assertSame( [ 'all', 'publish', 'draft' ], array_keys( $views ) );
		$this->assertStringContainsString( '(2)', $views['all'] );
	}

	/**
	 * Media dialog, REST and the media list return own uploads only.
	 */
	public function test_media_queries_return_own_uploads_only() {
		$me    = $this->user( Roles::PUBLISHER );
		$mine  = $this->image( $me );
		$other = $this->image( $this->user( Roles::ASSISTANT ) );
		wp_set_current_user( $me );

		$this->assertSame( $me, OwnMedia::limit_ajax( [] )['author'] );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/media' );
		$response = rest_get_server()->dispatch( $request );
		$ids      = wp_list_pluck( $response->get_data(), 'id' );
		$this->assertContains( $mine, $ids );
		$this->assertNotContains( $other, $ids );

		$this->assertSame( [ $mine ], $this->admin_list( $me, 'upload', [ 'post_type' => 'attachment' ] ) );
	}

	/**
	 * Own images can be edited; deleted only while no published event uses them.
	 */
	public function test_own_image_edit_and_delete() {
		$me    = $this->user( Roles::ASSISTANT );
		$draft = $this->event( $me );
		$live  = $this->event( $me, 'publish' );

		$on_draft = $this->image( $me, $draft );
		$on_live  = $this->image( $me, $live );
		$featured = $this->image( $me );
		set_post_thumbnail( $live, $featured );
		$others = $this->image( $this->user( Roles::ASSISTANT ) );

		$this->assertTrue( user_can( $me, 'edit_post', $on_draft ) );
		$this->assertTrue( user_can( $me, 'delete_post', $on_draft ) );
		$this->assertTrue( user_can( $me, 'edit_post', $on_live ) );
		$this->assertFalse( user_can( $me, 'delete_post', $on_live ), 'attached to a published event' );
		$this->assertFalse( user_can( $me, 'delete_post', $featured ), 'featured image of a published event' );
		$this->assertFalse( user_can( $me, 'edit_post', $others ) );
		$this->assertFalse( user_can( $me, 'delete_post', $others ) );
	}

	/**
	 * Runs the main admin list query of a screen as a user and returns the post IDs.
	 *
	 * @param int                  $user_id User ID.
	 * @param string               $screen  Screen ID.
	 * @param array<string, mixed> $vars    Query variables.
	 *
	 * @return int[]
	 */
	private function admin_list( int $user_id, string $screen, array $vars ): array {
		wp_set_current_user( $user_id );
		set_current_screen( $screen );
		// The admin list is the main query: WP_Query::is_main_query() compares with $wp_the_query.
		$query                   = new WP_Query();
		$GLOBALS['wp_the_query'] = $query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$ids                     = $query->query(
			$vars + [
				'post_status' => 'any',
				'fields'      => 'ids',
			]
		);
		set_current_screen( 'front' );
		return array_map( 'intval', $ids );
	}
}
