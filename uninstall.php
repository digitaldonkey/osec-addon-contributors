<?php
/**
 * Runs when the plugin is deleted in wp-admin (not on deactivation).
 *
 * Event Publishers become Event Contributors and the Event Publisher role is removed.
 * Images and other content stay untouched.
 *
 * @package Osec\Addon\Contributors
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/src/Admin/Roles.php';

Osec\Addon\Contributors\Admin\Roles::uninstall();
