<?php
/**
 * Plugin bootstrap: registers the hooks of all parts.
 *
 * @package Osec\Addon\Contributors
 */

namespace Osec\Addon\Contributors;

use Osec\Addon\Contributors\Admin\AdminMenu;
use Osec\Addon\Contributors\Admin\EditorLayout;
use Osec\Addon\Contributors\Admin\EmailLogin;
use Osec\Addon\Contributors\Admin\EmailRegistration;
use Osec\Addon\Contributors\Admin\MediaDialog;
use Osec\Addon\Contributors\Admin\OwnEvents;
use Osec\Addon\Contributors\Admin\OwnMedia;
use Osec\Addon\Contributors\Admin\PageRedirect;
use Osec\Addon\Contributors\Admin\ProfilePage;
use Osec\Addon\Contributors\Admin\Roles;
use Osec\Addon\Contributors\Admin\Uploads;
use Osec\Addon\Contributors\PrivateUploads\Endpoint;
use Osec\Addon\Contributors\PrivateUploads\Publisher;
use Osec\Addon\Contributors\PrivateUploads\Storage;
use Osec\Addon\Contributors\PrivateUploads\StorageCheck;
use Osec\Addon\Contributors\PrivateUploads\UploadRouter;
use Osec\Addon\Contributors\PrivateUploads\UrlFilter;

/**
 * Wires every part of the plugin into WordPress.
 */
final class Plugin {

	/**
	 * Text domain, also the plugin slug.
	 */
	public const TEXT_DOMAIN = 'osec-addon-contributors';

	/**
	 * Registers all hooks. Called once from the plugin's main file.
	 *
	 * @return void
	 */
	public static function boot(): void {
		add_action( 'init', [ self::class, 'load_textdomain' ] );

		Roles::register();
		OwnEvents::register();
		OwnMedia::register();
		AdminMenu::register();
		PageRedirect::register();
		Uploads::register();
		EditorLayout::register();
		ProfilePage::register();
		MediaDialog::register();
		EmailLogin::register();
		EmailRegistration::register();

		Storage::register();
		StorageCheck::register();
		UploadRouter::register();
		Endpoint::register();
		UrlFilter::register();
		Publisher::register();
	}

	/**
	 * Loads the translations bundled in `languages/`.
	 *
	 * A translation from translate.wordpress.org (wp-content/languages/plugins/) takes priority
	 * once one exists, WordPress loads that file first.
	 *
	 * @return void
	 */
	public static function load_textdomain(): void {
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- bundled de_DE.
		load_plugin_textdomain( self::TEXT_DOMAIN, false, dirname( plugin_basename( OSEC_ADDON_CONTRIBUTORS_FILE ) ) . '/languages' );
	}

	/**
	 * The event post type of Open Source Event Calendar.
	 *
	 * @return string
	 */
	public static function post_type(): string {
		return defined( 'OSEC_POST_TYPE' ) ? OSEC_POST_TYPE : 'osec_event';
	}
}
