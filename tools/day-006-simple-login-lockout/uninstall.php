<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 * Lockout counters are transients and expire on their own.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wpdt006_settings' );
