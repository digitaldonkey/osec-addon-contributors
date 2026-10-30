<?php
/**
 * Tests for the findings of the review of 0.1.0 (harsh, security, WordPress practice).
 *
 * Each test failed before its fix. Numbers refer to the review list in the project plan.
 *
 * @package Osec\Addon\Contributors
 */

use Osec\Addon\Contributors\Admin\OwnMedia;
use Osec\Addon\Contributors\Admin\Roles;
use Osec\Addon\Contributors\PrivateUploads\Endpoint;
use Osec\Addon\Contributors\PrivateUploads\Storage;
use Osec\Addon\Contributors\PrivateUploads\StorageCheck;
use Osec\Addon\Contributors\PrivateUploads\UploadRouter;

require_once __DIR__ . '/class-testcase.php';

/**
 * Review findings 1-11.
 */
class Test_Review_Fixes extends Osec_Addon_Contributors_TestCase {

	/**
	 * This test's private folder.
	 *
	 * @var string
	 */
	private string $root;

	/**
	 * Document root before the test.
	 *
	 * @var string|null
	 */
	private ?string $document_root;

	/**
	 * A fresh private folder outside the WordPress folder.
	 */
	public function set_up() {
		parent::set_up();
		$this->document_root = isset( $_SERVER['DOCUMENT_ROOT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) ) : null;
		$this->root          = wp_normalize_path( sys_get_temp_dir() ) . '/osec-review-test-' . wp_generate_password( 8, false );
		update_site_option( Storage::OPTION, $this->root );
		delete_transient( StorageCheck::TRANSIENT );
	}

	/**
	 * Restores the document root and removes the test's files.
	 */
	public function tear_down() {
		if ( null === $this->document_root ) {
			unset( $_SERVER['DOCUMENT_ROOT'] );
		} else {
			$_SERVER['DOCUMENT_ROOT'] = $this->document_root;
		}
		foreach ( [ $this->root, wp_upload_dir()['basedir'] . '/' . Storage::DIR_NAME ] as $dir ) {
			if ( is_dir( $dir ) ) {
				Storage::filesystem()->rmdir( $dir, true );
			}
		}
		$this->remove_added_uploads();
		parent::tear_down();
	}

	/**
	 * 1: a folder inside the web root but outside the WordPress folder is not safe by location.
	 */
	public function test_1_folder_in_document_root_counts_as_web_root() {
		$_SERVER['DOCUMENT_ROOT'] = sys_get_temp_dir();
		$this->assertTrue( Storage::in_web_root() );
	}

	/**
	 * 1: the default folder is never chosen inside the web root without being treated as such.
	 */
	public function test_1_default_folder_not_unprotected_in_document_root() {
		$document_root            = dirname( untrailingslashit( ABSPATH ) );
		$_SERVER['DOCUMENT_ROOT'] = $document_root;
		delete_site_option( Storage::OPTION );

		$root = Storage::root();

		$inside_document_root = str_starts_with( $root . '/', trailingslashit( wp_normalize_path( $document_root ) ) );
		$this->assertTrue( ! $inside_document_root || Storage::in_web_root(), "unprotected: $root" );
	}

	/**
	 * 1: the test download uses the uploads URL for the fallback folder, and none for unknown places.
	 */
	public function test_1_probe_url() {
		$uploads = wp_upload_dir();
		$this->assertSame( $uploads['baseurl'] . '/' . Storage::DIR_NAME . '/x.txt', StorageCheck::url_for( $uploads['basedir'] . '/' . Storage::DIR_NAME . '/x.txt' ) );
		$this->assertNull( StorageCheck::url_for( '/nowhere/x.txt' ) );
	}

	/**
	 * 2: a private image set as featured image of a published event becomes public at once.
	 */
	public function test_2_featured_image_of_published_event_goes_public() {
		$me    = $this->user( Roles::ASSISTANT );
		$event = $this->event( $me, 'publish' );
		$image = $this->upload( $me );
		$this->assertTrue( Storage::is_private( $image ) );

		set_post_thumbnail( $event, $image );

		$this->assertFalse( Storage::is_private( $image ) );
	}

	/**
	 * 2: a private image placed in the text of a published event becomes public on save.
	 */
	public function test_2_image_in_text_of_published_event_goes_public() {
		$me    = $this->user( Roles::PUBLISHER );
		$event = $this->event( $me, 'publish' );
		$image = $this->upload( $me );

		wp_update_post(
			[
				'ID'           => $event,
				'post_content' => '<img src="' . esc_url( wp_get_attachment_url( $image ) ) . '" class="wp-image-' . $image . '" />',
			]
		);

		$this->assertFalse( Storage::is_private( $image ) );
		$this->assertStringNotContainsString( 'osec_private_file', get_post_field( 'post_content', $event ) );
	}

	/**
	 * 3: an image whose files are gone stays private instead of turning into a broken public one.
	 */
	public function test_3_missing_files_stay_private() {
		$me    = $this->user( Roles::ASSISTANT );
		$event = $this->event( $me, 'pending' );
		$image = $this->upload( $me, $event );
		foreach ( Storage::file_names( $image ) as $name ) {
			wp_delete_file( $this->root . '/' . $name );
		}

		wp_publish_post( $event );

		$this->assertTrue( Storage::is_private( $image ) );
	}

	/**
	 * 4: a path with ".." that does not exist is not inside the private folder.
	 */
	public function test_4_contains_rejects_dot_dot() {
		wp_mkdir_p( $this->root );
		$this->assertFalse( Storage::contains( $this->root . '/../osec-not-there/x.jpg' ) );
		$this->assertTrue( Storage::contains( $this->root . '/not-there-yet.jpg' ) );
	}

	/**
	 * 5: an own image in the text of a published event cannot be deleted.
	 */
	public function test_5_image_in_published_text_cannot_be_deleted() {
		$me    = $this->user( Roles::ASSISTANT );
		$image = $this->image( $me );
		self::factory()->post->create(
			[
				'post_type'    => 'osec_event',
				'post_status'  => 'publish',
				'post_author'  => $me,
				'post_content' => '<img class="wp-image-' . $image . '" />',
			]
		);
		$other = $this->image( $me );
		self::factory()->post->create(
			[
				'post_type'    => 'osec_event',
				'post_status'  => 'publish',
				'post_author'  => $me,
				'post_content' => '<img class="wp-image-' . $other . '0" />',
			]
		);

		$this->assertTrue( OwnMedia::is_used_by_published( get_post( $image ) ) );
		$this->assertFalse( OwnMedia::is_used_by_published( get_post( $other ) ), 'wp-image-<ID>0 is another image' );
	}

	/**
	 * 6: a folder that cannot be written is reported, uploads are refused.
	 */
	public function test_6_unwritable_folder_is_reported() {
		$blocker = wp_normalize_path( wp_tempnam( 'osec-blocker' ) );
		update_site_option( Storage::OPTION, $blocker . '/private' );

		$this->assertFalse( Storage::is_usable() );
		$this->assertSame( 'not_writable', StorageCheck::problem() );
		$this->assertStringContainsString( 'paused', (string) $this->upload( $this->user( Roles::ASSISTANT ) ) );
		wp_delete_file( $blocker );
	}

	/**
	 * 7: the endpoint serves the plugin's image types only, whatever the viewer may upload.
	 */
	public function test_7_endpoint_serves_image_types_only() {
		$me = $this->user( Roles::ASSISTANT );
		$id = $this->upload( $me );
		Storage::filesystem()->put_contents( $this->root . '/drawing.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>', Storage::FILE_MODE );
		update_attached_file( $id, $this->root . '/drawing.svg' );
		add_filter(
			'upload_mimes',
			static function ( $mimes ) {
				$mimes['svg'] = 'image/svg+xml';
				return $mimes;
			},
			20
		);

		wp_set_current_user( $this->user( 'administrator' ) );
		$this->assertSame( 404, Endpoint::resolve( "$id/drawing.svg" )['status'] );
	}

	/**
	 * 8: an array as query value is a 404, not a PHP warning.
	 */
	public function test_8_array_query_value() {
		$this->assertSame( 404, Endpoint::resolve_query_value( [ '1/x.jpg' ] )['status'] );
	}

	/**
	 * 10: backup copies of an edited image are files of the attachment.
	 */
	public function test_10_backup_copies_belong_to_the_image() {
		$me = $this->user( Roles::ASSISTANT );
		$id = $this->upload( $me );
		update_post_meta( $id, '_wp_attachment_backup_sizes', [ 'full-orig' => [ 'file' => 'canola-orig.jpg' ] ] );

		$this->assertContains( 'canola-orig.jpg', Storage::file_names( $id ) );
	}

	/**
	 * 11: a failed upload does not leave the private folder as upload target.
	 */
	public function test_11_failed_upload_resets_target() {
		$this->assertIsString( $this->upload( $this->user( Roles::ASSISTANT ), 0, 'notes.txt' ) );
		$this->assertFalse( has_filter( 'upload_dir', [ UploadRouter::class, 'private_dir' ] ) );
	}

	/**
	 * Uploads a test file as a user the way WordPress does it, with sizes.
	 *
	 * @param int    $user_id   Uploading user.
	 * @param int    $parent_id Event the upload belongs to, 0 for none.
	 * @param string $name      File name; canola.jpg holds a real image, other names get text.
	 *
	 * @return int|string Attachment ID, or the upload error message.
	 */
	private function upload( int $user_id, int $parent_id = 0, string $name = 'canola.jpg' ) {
		wp_set_current_user( $user_id );
		$_REQUEST['post_id'] = $parent_id;
		$tmp                 = wp_tempnam( $name );
		if ( 'canola.jpg' === $name ) {
			copy( DIR_TESTDATA . '/images/canola.jpg', $tmp );
		} else {
			Storage::filesystem()->put_contents( $tmp, 'text', Storage::FILE_MODE );
		}
		$file   = [
			'name'     => $name,
			'type'     => 'canola.jpg' === $name ? 'image/jpeg' : 'text/plain',
			'tmp_name' => $tmp,
			'error'    => 0,
			'size'     => filesize( $tmp ),
		];
		$result = wp_handle_sideload( $file, [ 'test_form' => false ] );
		unset( $_REQUEST['post_id'] );
		if ( isset( $result['error'] ) ) {
			return $result['error'];
		}
		$id = wp_insert_attachment(
			[
				'post_mime_type' => $result['type'],
				'post_title'     => 'canola',
				'post_author'    => $user_id,
			],
			$result['file'],
			$parent_id
		);
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $result['file'] ) );
		return $id;
	}
}
