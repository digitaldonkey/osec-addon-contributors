<?php
/**
 * Where private images are stored, and which attachments are private.
 *
 * @package Osec\Addon\Contributors
 */

namespace Osec\Addon\Contributors\PrivateUploads;

use WP_Filesystem_Direct;

/**
 * The private folder.
 *
 * Location, in this order:
 * 1. the constant OSEC_ADDON_CONTRIBUTORS_PRIVATE_DIR (wp-config.php), an absolute path;
 * 2. the folder chosen on first use, stored in the option `osec_addon_contributors_private_root`, so it
 *    never changes under existing files;
 * 3. on first use: `osec_private_files/` one level above the topmost web folder (WordPress folder, the
 *    folder holding wp-content, the web server's document root) when that can be written, where no web
 *    server serves it; otherwise `wp-content/uploads/osec_private_files/`, protected by an .htaccess file
 *    (Apache) and a server rule the admin adds (nginx), see StorageCheck.
 *
 * On multisite every site gets a subfolder `site-<id>`.
 *
 * Private attachments carry the meta key MARKER and their files lie inside the private folder.
 */
final class Storage {

	/**
	 * Folder name of the private folder.
	 */
	public const DIR_NAME = 'osec_private_files';

	/**
	 * Option that remembers the chosen folder.
	 */
	public const OPTION = 'osec_addon_contributors_private_root';

	/**
	 * Post meta marking an attachment as private.
	 */
	public const MARKER = '_osec_addon_contributors_private';

	/**
	 * Permission of files written here (WordPress's FS_CHMOD_FILE exists only after WP_Filesystem()).
	 */
	public const FILE_MODE = 0644;

	/**
	 * Registers the hook that deletes private files with their attachment.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'delete_attachment', [ self::class, 'delete_files' ] );
	}

	/**
	 * The private folder of the current site, without trailing slash.
	 *
	 * @return string
	 */
	public static function root(): string {
		if ( defined( 'OSEC_ADDON_CONTRIBUTORS_PRIVATE_DIR' ) ) {
			$dir = (string) OSEC_ADDON_CONTRIBUTORS_PRIVATE_DIR;
		} else {
			$dir = (string) get_site_option( self::OPTION, '' );
			if ( '' === $dir ) {
				$dir = self::choose();
				update_site_option( self::OPTION, $dir );
			}
		}
		if ( is_multisite() ) {
			$dir .= '/site-' . get_current_blog_id();
		}
		return untrailingslashit( wp_normalize_path( $dir ) );
	}

	/**
	 * Folders a web server may serve: the WordPress folder, the folder holding wp-content (they differ
	 * when WordPress lives in its own subfolder) and the document root of the current request.
	 *
	 * @return string[] Normalized paths with trailing slash, as given and resolved.
	 */
	public static function web_roots(): array {
		$roots = [ ABSPATH, dirname( WP_CONTENT_DIR ) ];
		if ( ! empty( $_SERVER['DOCUMENT_ROOT'] ) ) {
			$roots[] = sanitize_text_field( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) );
		}
		$all = [];
		foreach ( $roots as $root ) {
			$all[] = trailingslashit( wp_normalize_path( $root ) );
			$real  = realpath( $root );
			if ( false !== $real ) {
				$all[] = trailingslashit( wp_normalize_path( $real ) );
			}
		}
		return array_values( array_unique( $all ) );
	}

	/**
	 * Whether a path lies inside a folder a web server may serve.
	 *
	 * @param string|null $path Absolute path, default the private folder.
	 *
	 * @return bool
	 */
	public static function in_web_root( ?string $path = null ): bool {
		$path  = trailingslashit( wp_normalize_path( $path ?? self::root() ) );
		$real  = realpath( $path );
		$paths = false === $real ? [ $path ] : [ $path, trailingslashit( wp_normalize_path( $real ) ) ];
		foreach ( self::web_roots() as $root ) {
			foreach ( $paths as $candidate ) {
				if ( str_starts_with( $candidate, $root ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Whether the private folder exists (or was created) and can be written.
	 *
	 * @return bool
	 */
	public static function is_usable(): bool {
		return self::ensure() && wp_is_writable( self::root() );
	}

	/**
	 * Creates the private folder, and in the web root the files that keep Apache out.
	 *
	 * @return bool False when the folder cannot be created.
	 */
	public static function ensure(): bool {
		$root = self::root();
		if ( ! is_dir( $root ) && ( file_exists( $root ) || ! wp_mkdir_p( $root ) ) ) {
			return false;
		}
		if ( self::in_web_root() ) {
			$fs = self::filesystem();
			if ( ! $fs->exists( $root . '/.htaccess' ) ) {
				$fs->put_contents( $root . '/.htaccess', "# Private images, served only by WordPress.\nRequire all denied\n", self::FILE_MODE );
			}
			if ( ! $fs->exists( $root . '/index.php' ) ) {
				$fs->put_contents( $root . '/index.php', "<?php\n// Silence is golden.\n", self::FILE_MODE );
			}
		}
		return true;
	}

	/**
	 * Whether an attachment is private: marked, and its file inside the private folder.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return bool
	 */
	public static function is_private( int $attachment_id ): bool {
		if ( ! get_post_meta( $attachment_id, self::MARKER, true ) ) {
			return false;
		}
		$file = get_attached_file( $attachment_id, true );
		return is_string( $file ) && self::contains( $file );
	}

	/**
	 * Whether a path lies inside the private folder (resolving `..` and symbolic links).
	 *
	 * @param string $path Absolute path.
	 *
	 * @return bool
	 */
	public static function contains( string $path ): bool {
		$root = realpath( self::root() );
		$real = realpath( $path );
		if ( false === $root ) {
			$root = self::root();
		}
		if ( false === $real ) {
			$real = wp_normalize_path( $path );
			// Not resolvable (does not exist yet): ".." could lead anywhere.
			if ( preg_match( '#(^|/)\.\.(/|$)#', $real ) ) {
				return false;
			}
		}
		return str_starts_with( wp_normalize_path( $real ), trailingslashit( wp_normalize_path( $root ) ) );
	}

	/**
	 * File names of an attachment: the main file, every size, the original before scaling and the
	 * backup copies WordPress keeps of an edited image.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return string[]
	 */
	public static function file_names( int $attachment_id ): array {
		$file  = get_attached_file( $attachment_id, true );
		$meta  = wp_get_attachment_metadata( $attachment_id );
		$names = $file ? [ wp_basename( $file ) ] : [];
		if ( is_array( $meta ) ) {
			foreach ( $meta['sizes'] ?? [] as $size ) {
				$names[] = wp_basename( $size['file'] );
			}
			if ( ! empty( $meta['original_image'] ) ) {
				$names[] = wp_basename( $meta['original_image'] );
			}
		}
		$backups = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );
		foreach ( is_array( $backups ) ? $backups : [] as $backup ) {
			if ( ! empty( $backup['file'] ) ) {
				$names[] = wp_basename( $backup['file'] );
			}
		}
		return array_values( array_unique( $names ) );
	}

	/**
	 * Deletes the files of a private attachment. WordPress deletes only files inside the uploads folder.
	 *
	 * @param int $attachment_id Attachment ID being deleted.
	 *
	 * @return void
	 */
	public static function delete_files( int $attachment_id ): void {
		if ( ! self::is_private( $attachment_id ) ) {
			return;
		}
		$dir = dirname( get_attached_file( $attachment_id, true ) );
		foreach ( self::file_names( $attachment_id ) as $name ) {
			$path = $dir . '/' . $name;
			if ( self::contains( $path ) && file_exists( $path ) ) {
				wp_delete_file( $path );
			}
		}
	}

	/**
	 * Direct file access for the private folder (our own folder, no FTP credentials involved).
	 *
	 * @return WP_Filesystem_Direct
	 */
	public static function filesystem(): WP_Filesystem_Direct {
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		return new WP_Filesystem_Direct( null );
	}

	/**
	 * Chooses the folder on first use: beside the topmost web folder when writable and not served,
	 * else in uploads.
	 *
	 * @return string
	 */
	private static function choose(): string {
		$roots = self::web_roots();
		usort( $roots, static fn( string $a, string $b ): int => strlen( $a ) <=> strlen( $b ) );
		$outside = dirname( untrailingslashit( $roots[0] ) ) . '/' . self::DIR_NAME;
		$usable  = is_dir( $outside ) ? wp_is_writable( $outside ) : wp_is_writable( dirname( $outside ) );
		if ( $usable && ! self::in_web_root( $outside ) ) {
			return $outside;
		}
		return wp_normalize_path( wp_upload_dir( null, false )['basedir'] ) . '/' . self::DIR_NAME;
	}
}
