<?php
/**
 * Tests for private uploads: storage, endpoint, publishing, REST, fail-closed storage check.
 *
 * Every test uses its own private folder in the temp directory (set through the plugin's option).
 *
 * @package Osec\Addon\Contributors
 */

use Osec\Addon\Contributors\Admin\Roles;
use Osec\Addon\Contributors\PrivateUploads\Endpoint;
use Osec\Addon\Contributors\PrivateUploads\Storage;
use Osec\Addon\Contributors\PrivateUploads\StorageCheck;

require_once __DIR__ . '/class-testcase.php';

/**
 * Private images of restricted users, from upload to publication.
 */
class Test_Private_Uploads extends Osec_Addon_Contributors_TestCase {

	/**
	 * This test's private folder.
	 *
	 * @var string
	 */
	private string $root;

	/**
	 * Gives every test a fresh private folder outside the WordPress folder.
	 */
	public function set_up() {
		parent::set_up();
		$this->root = wp_normalize_path( sys_get_temp_dir() ) . '/osec-private-test-' . wp_generate_password( 8, false );
		update_site_option( Storage::OPTION, $this->root );
		delete_transient( StorageCheck::TRANSIENT );
	}

	/**
	 * Removes the test's files.
	 */
	public function tear_down() {
		$this->remove_dir( $this->root );
		$this->remove_dir( wp_upload_dir()['basedir'] . '/' . Storage::DIR_NAME );
		$this->remove_added_uploads();
		parent::tear_down();
	}

	/**
	 * A contributor's upload lands in the private folder with all sizes, and gets endpoint URLs.
	 */
	public function test_contributor_upload_is_private() {
		$me = $this->user( Roles::ASSISTANT );
		$id = $this->upload( $me, $this->event( $me ) );

		$this->assertTrue( Storage::is_private( $id ) );
		$this->assertStringStartsWith( $this->root . '/', get_attached_file( $id ) );
		$this->assertNotEmpty( wp_get_attachment_metadata( $id )['sizes'] );
		foreach ( Storage::file_names( $id ) as $name ) {
			$this->assertFileExists( $this->root . '/' . $name );
		}

		$this->assertSame( Endpoint::url( $id, 'canola.jpg' ), wp_get_attachment_url( $id ) );
		$this->assertStringContainsString( '?osec_private_file=' . $id . '/canola-150x150.jpg', wp_get_attachment_image_url( $id, 'thumbnail' ) );
		// WordPress builds no srcset for endpoint URLs; whatever it builds must not point to public uploads.
		$this->assertStringNotContainsString( wp_upload_dir()['baseurl'], (string) wp_get_attachment_image( $id, 'full' ) );
	}

	/**
	 * Uploads to a published event and uploads of editors stay public.
	 */
	public function test_public_uploads() {
		$me = $this->user( Roles::PUBLISHER );
		$this->assertFalse( Storage::is_private( $this->upload( $me, $this->event( $me, 'publish' ) ) ) );

		$editor = $this->user( 'editor' );
		$this->assertFalse( Storage::is_private( $this->upload( $editor, $this->event( $editor ) ) ) );
	}

	/**
	 * The endpoint delivers to the uploader and reviewers only, and only the attachment's own files.
	 */
	public function test_endpoint_access() {
		$me = $this->user( Roles::ASSISTANT );
		$id = $this->upload( $me );

		wp_set_current_user( $me );
		$this->assertSame( 200, Endpoint::resolve( "$id/canola.jpg" )['status'] );
		$this->assertSame( 200, Endpoint::resolve( "$id/canola-150x150.jpg" )['status'] );
		$this->assertSame( 404, Endpoint::resolve( "$id/other.jpg" )['status'] );
		$this->assertSame( 404, Endpoint::resolve( "$id/..%2F..%2Fwp-config.php" )['status'] );
		$this->assertSame( 404, Endpoint::resolve( "$id/../canola.jpg" )['status'] );
		$this->assertSame( 404, Endpoint::resolve( $this->event( $me ) . '/canola.jpg' )['status'] );

		wp_set_current_user( $this->user( 'editor' ) );
		$this->assertSame( 200, Endpoint::resolve( "$id/canola.jpg" )['status'] );

		wp_set_current_user( $this->user( Roles::ASSISTANT ) );
		$this->assertSame( 403, Endpoint::resolve( "$id/canola.jpg" )['status'] );

		wp_set_current_user( 0 );
		$this->assertSame( 403, Endpoint::resolve( "$id/canola.jpg" )['status'] );
	}

	/**
	 * Publishing moves featured, attached and in-text images of the author, rewrites the text, old links redirect.
	 */
	public function test_publish_makes_images_public() {
		$me       = $this->user( Roles::ASSISTANT );
		$event    = $this->event( $me, 'pending' );
		$featured = $this->upload( $me );
		$attached = $this->upload( $me, $event );
		$in_text  = $this->upload( $me );
		$others   = $this->upload( $this->user( Roles::ASSISTANT ) );
		set_post_thumbnail( $event, $featured );
		$text_url = wp_get_attachment_image_url( $in_text, 'thumbnail' );
		wp_update_post(
			[
				'ID'           => $event,
				'post_content' => '<img src="' . esc_url( $text_url ) . '" class="wp-image-' . $in_text . '" /><img class="wp-image-' . $others . '" />',
			]
		);

		wp_set_current_user( $this->user( 'editor' ) );
		wp_publish_post( $event );

		$uploads = wp_upload_dir();
		foreach ( [ $featured, $attached, $in_text ] as $id ) {
			$this->assertFalse( Storage::is_private( $id ) );
			$this->assertStringStartsWith( $uploads['basedir'] . '/', get_attached_file( $id ) );
			$this->assertFileExists( get_attached_file( $id ) );
			$this->assertStringStartsWith( $uploads['baseurl'] . '/', wp_get_attachment_image_url( $id, 'thumbnail' ) );
		}
		$this->assertTrue( Storage::is_private( $others ), 'another user\'s image stays private' );

		$content = get_post_field( 'post_content', $event );
		$this->assertStringNotContainsString( 'osec_private_file', explode( '<img class', $content )[0] );
		$this->assertStringContainsString( wp_get_attachment_image_url( $in_text, 'thumbnail' ), $content );

		wp_set_current_user( 0 );
		$redirect = Endpoint::resolve( "$in_text/" . wp_basename( $text_url ) );
		$this->assertSame( 301, $redirect['status'] );
		$this->assertSame( wp_get_attachment_image_url( $in_text, 'thumbnail' ), $redirect['location'] );
	}

	/**
	 * Logged out visitors find no private images in the REST media API, reviewers do.
	 */
	public function test_rest_hides_private_images() {
		$id = $this->upload( $this->user( Roles::ASSISTANT ) );

		wp_set_current_user( 0 );
		$list = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/wp/v2/media' ) );
		$this->assertNotContains( $id, wp_list_pluck( $list->get_data(), 'id' ) );
		$this->assertSame( 404, rest_get_server()->dispatch( new WP_REST_Request( 'GET', "/wp/v2/media/$id" ) )->get_status() );

		wp_set_current_user( $this->user( 'editor' ) );
		$this->assertSame( 200, rest_get_server()->dispatch( new WP_REST_Request( 'GET', "/wp/v2/media/$id" ) )->get_status() );
	}

	/**
	 * In the uploads fallback, uploads are refused while the folder is public, and allowed once it is safe.
	 */
	public function test_fallback_folder_fails_closed() {
		update_site_option( Storage::OPTION, wp_upload_dir()['basedir'] . '/' . Storage::DIR_NAME );
		$me = $this->user( Roles::ASSISTANT );
		$this->assertTrue( Storage::in_web_root() );

		set_transient( StorageCheck::TRANSIENT, 'no' );
		$this->assertStringContainsString( 'paused', (string) $this->upload( $me ) );

		set_transient( StorageCheck::TRANSIENT, 'yes' );
		$this->assertTrue( Storage::is_private( $this->upload( $me ) ) );
		$this->assertFileExists( Storage::root() . '/.htaccess' );
	}

	/**
	 * Deleting a private image deletes its files (WordPress deletes only inside uploads).
	 */
	public function test_delete_removes_private_files() {
		$id    = $this->upload( $this->user( Roles::ASSISTANT ) );
		$names = Storage::file_names( $id );

		wp_delete_attachment( $id, true );

		foreach ( $names as $name ) {
			$this->assertFileDoesNotExist( $this->root . '/' . $name );
		}
	}

	/**
	 * Uploads canola.jpg as a user the way WordPress does it, with sizes.
	 *
	 * @param int $user_id   Uploading user.
	 * @param int $parent_id Event the upload belongs to, 0 for none.
	 *
	 * @return int|string Attachment ID, or the upload error message.
	 */
	private function upload( int $user_id, int $parent_id = 0 ) {
		wp_set_current_user( $user_id );
		$_REQUEST['post_id'] = $parent_id;
		$tmp                 = wp_tempnam( 'canola.jpg' );
		copy( DIR_TESTDATA . '/images/canola.jpg', $tmp );
		$file   = [
			'name'     => 'canola.jpg',
			'type'     => 'image/jpeg',
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

	/**
	 * Deletes a folder with its files.
	 *
	 * @param string $dir Folder.
	 *
	 * @return void
	 */
	private function remove_dir( string $dir ): void {
		if ( is_dir( $dir ) ) {
			Storage::filesystem()->rmdir( $dir, true );
		}
	}
}
