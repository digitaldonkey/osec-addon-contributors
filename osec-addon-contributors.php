<?php
/**
 * Plugin Name:       Contributors Add-on for Open Source Event Calendar
 * Plugin URI:        https://github.com/digitaldonkey/osec-addon-contributors
 * Description:       Lets registered users draft events with images for review: own events and media only in the admin, private images until the event is published, and an Event Publisher role.
 * Version:           0.1.1
 * Requires at least: 7.1
 * Requires PHP:      8.4
 * Requires Plugins:  open-source-event-calendar
 * Author:            digitaldonkey
 * Author URI:        https://donkeymedia.eu
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       osec-addon-contributors
 * Domain Path:       /languages
 *
 * Entry point: defines the plugin constants, registers a PSR-4 class loader for `src/`
 * (namespace Osec\Addon\Contributors\) and boots the plugin. Composer is used for development
 * tools only, the plugin needs no `vendor/` folder at runtime.
 *
 * @package Osec\Addon\Contributors
 */

defined( 'ABSPATH' ) || exit;

const OSEC_ADDON_CONTRIBUTORS_VERSION = '0.1.1';
const OSEC_ADDON_CONTRIBUTORS_FILE    = __FILE__;

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'Osec\\Addon\\Contributors\\';
		if ( ! str_starts_with( $class_name, $prefix ) ) {
			return;
		}
		$file = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

register_activation_hook( __FILE__, [ Osec\Addon\Contributors\Admin\Roles::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ Osec\Addon\Contributors\Admin\Roles::class, 'deactivate' ] );

Osec\Addon\Contributors\Plugin::boot();
