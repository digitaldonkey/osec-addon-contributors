<?php
/**
 * Is the private folder really private?
 *
 * @package Osec\Addon\Contributors
 */

namespace Osec\Addon\Contributors\PrivateUploads;

/**
 * Checks that nobody can download private files directly. Fails closed: while the check fails or
 * cannot run, restricted users cannot upload, and administrators get a notice and a Site Health result.
 *
 * Outside the WordPress folder the files are safe by location. Inside it (fallback in uploads),
 * a test file is written and fetched without login: when the web server delivers it, the folder is
 * public. Apache is kept out by the .htaccess file; nginx needs the rule shown in the notice.
 * The result is cached (safe: one day, unsafe: five minutes).
 */
final class StorageCheck {

	/**
	 * Transient caching the result, "yes" or "no".
	 */
	public const TRANSIENT = 'osec_addon_contributors_storage_safe';

	/**
	 * Registers the notice and the Site Health test.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_notices', [ self::class, 'notice' ] );
		add_filter( 'site_status_tests', [ self::class, 'site_health' ] );
	}

	/**
	 * Whether private files are safe from direct download.
	 *
	 * @return bool
	 */
	public static function is_safe(): bool {
		if ( ! Storage::in_web_root() ) {
			return true;
		}
		$cached = get_transient( self::TRANSIENT );
		if ( false !== $cached ) {
			return 'yes' === $cached;
		}
		$safe = self::probe();
		set_transient( self::TRANSIENT, $safe ? 'yes' : 'no', $safe ? DAY_IN_SECONDS : 5 * MINUTE_IN_SECONDS );
		return $safe;
	}

	/**
	 * Writes a test file into the private folder and fetches it without login.
	 *
	 * @return bool True when the web server refused to deliver it.
	 */
	public static function probe(): bool {
		if ( ! Storage::ensure() ) {
			return false;
		}
		$token = wp_generate_password( 32, false );
		$name  = 'osec-check-' . wp_hash( $token ) . '.txt';
		$path  = Storage::root() . '/' . $name;
		$fs    = Storage::filesystem();
		if ( ! $fs->put_contents( $path, $token, Storage::FILE_MODE ) ) {
			return false;
		}
		$relative = substr( $path, strlen( trailingslashit( wp_normalize_path( ABSPATH ) ) ) );
		$response = wp_remote_get(
			site_url( $relative ),
			[
				'timeout'     => 10,
				'redirection' => 0,
				// Same as WordPress's own loopback requests: local sites often have self-signed certificates.
				'sslverify'   => apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.
			]
		);
		$fs->delete( $path );

		if ( is_wp_error( $response ) ) {
			return false;
		}
		return ! str_contains( (string) wp_remote_retrieve_body( $response ), $token );
	}

	/**
	 * The nginx rule that keeps the fallback folder private.
	 *
	 * @return string
	 */
	public static function nginx_rule(): string {
		$location = '/' . substr( Storage::root(), strlen( trailingslashit( wp_normalize_path( ABSPATH ) ) ) ) . '/';
		return "location ^~ {$location} {\n    deny all;\n}";
	}

	/**
	 * Admin notice while the folder is not safe.
	 *
	 * @return void
	 */
	public static function notice(): void {
		if ( ! current_user_can( 'manage_options' ) || self::is_safe() ) {
			return;
		}
		wp_admin_notice(
			sprintf(
				/* translators: 1: folder path, 2: nginx rule */
				esc_html__( 'Contributors Add-on: private images are stored in %1$s, but the web server delivers files from there to anyone. Image uploads of event contributors are paused. On nginx, add this rule to the server configuration (Apache is handled automatically), or define OSEC_ADDON_CONTRIBUTORS_PRIVATE_DIR as a folder outside the web root: %2$s', 'osec-addon-contributors' ),
				'<code>' . esc_html( Storage::root() ) . '</code>',
				'<pre>' . esc_html( self::nginx_rule() ) . '</pre>'
			),
			[
				'type'               => 'error',
				'paragraph_wrap'     => true,
				'additional_classes' => [ 'osec-addon-contributors-storage' ],
			]
		);
	}

	/**
	 * Adds the Site Health test.
	 *
	 * @param array $tests Site Health tests.
	 *
	 * @return array
	 */
	public static function site_health( array $tests ): array {
		$tests['direct']['osec_addon_contributors_storage'] = [
			'label' => __( 'Private event images', 'osec-addon-contributors' ),
			'test'  => [ self::class, 'site_health_result' ],
		];
		return $tests;
	}

	/**
	 * Site Health result (runs the check again, without the cache).
	 *
	 * @return array
	 */
	public static function site_health_result(): array {
		delete_transient( self::TRANSIENT );
		$safe = self::is_safe();
		return [
			'label'       => $safe
				? __( 'Private event images cannot be downloaded directly', 'osec-addon-contributors' )
				: __( 'Private event images can be downloaded by anyone', 'osec-addon-contributors' ),
			'status'      => $safe ? 'good' : 'critical',
			'badge'       => [
				'label' => __( 'Security', 'osec-addon-contributors' ),
				'color' => $safe ? 'blue' : 'red',
			],
			'description' => '<p>' . sprintf(
				/* translators: %s: folder path */
				esc_html__( 'Images of unpublished events are stored in %s.', 'osec-addon-contributors' ),
				'<code>' . esc_html( Storage::root() ) . '</code>'
			) . '</p>' . ( $safe ? '' : '<pre>' . esc_html( self::nginx_rule() ) . '</pre>' ),
			'test'        => 'osec_addon_contributors_storage',
		];
	}
}
