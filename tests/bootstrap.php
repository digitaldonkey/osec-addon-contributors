<?php
/**
 * PHPUnit bootstrap file, based on the one `wp scaffold plugin-tests` generates.
 *
 * Loads the WordPress test library (installed by bin/install-wp-tests.sh), then Open Source Event
 * Calendar (this add-on needs it) and this plugin, and runs the plugin's activation once.
 *
 * Environment variables:
 * - WP_TESTS_DIR:     the test library, default <temp dir>/wordpress-tests-lib.
 * - OSEC_PLUGIN_DIR:  the Open Source Event Calendar folder, default the sibling folder
 *                     ../open-source-event-calendar (in CI: the unzipped WordPress.org release).
 *
 * @package Osec\Addon\Contributors
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
$_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $_phpunit_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

$_osec_dir = getenv( 'OSEC_PLUGIN_DIR' ) ? getenv( 'OSEC_PLUGIN_DIR' ) : dirname( __DIR__, 2 ) . '/open-source-event-calendar';
if ( ! file_exists( "{$_osec_dir}/open-source-event-calendar.php" ) ) {
	echo "Could not find Open Source Event Calendar in {$_osec_dir}, set OSEC_PLUGIN_DIR." . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}
// PHPUnit includes this file inside a method, a constant reaches the function below.
define( 'OSEC_ADDON_CONTRIBUTORS_TESTS_OSEC_DIR', $_osec_dir );

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Manually load Open Source Event Calendar and the plugin being tested.
 */
function _manually_load_plugin() {
	require OSEC_ADDON_CONTRIBUTORS_TESTS_OSEC_DIR . '/open-source-event-calendar.php';
	require dirname( __DIR__ ) . '/osec-addon-contributors.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Plugins loaded this way skip activation: create the roles as activation would, after OSEC's init.
tests_add_filter( 'init', [ Osec\Addon\Contributors\Admin\Roles::class, 'activate' ], 30 );

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";
