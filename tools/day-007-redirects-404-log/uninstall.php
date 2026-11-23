<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 * Removes the two tables and the stored DB version.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wpdt008_log" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wpdt008_redirects" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
delete_option( 'wpdt008_db_version' );
