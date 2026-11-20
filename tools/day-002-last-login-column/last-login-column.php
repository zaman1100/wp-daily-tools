<?php
/**
 * Plugin Name:       WPDT 002 – Last Login Column
 * Description:       Records each user's last login time and shows it as a sortable "Last Login" column on the Users screen.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 * Text Domain:       wpdt-last-login-column
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPDT002_META_KEY', 'wpdt002_last_login' );

/**
 * Save the login time (Unix timestamp) on every successful login.
 */
function wpdt002_record_login( $user_login, $user ) {
	update_user_meta( $user->ID, WPDT002_META_KEY, time() );
}
add_action( 'wp_login', 'wpdt002_record_login', 10, 2 );

/**
 * Add the column.
 */
function wpdt002_add_column( $columns ) {
	$columns['wpdt002_last_login'] = __( 'Last Login', 'wpdt-last-login-column' );
	return $columns;
}
add_filter( 'manage_users_columns', 'wpdt002_add_column' );

/**
 * Render the column.
 */
function wpdt002_render_column( $output, $column_name, $user_id ) {
	if ( 'wpdt002_last_login' !== $column_name ) {
		return $output;
	}

	$timestamp = (int) get_user_meta( $user_id, WPDT002_META_KEY, true );

	if ( ! $timestamp ) {
		return '<span aria-hidden="true">&mdash;</span><span class="screen-reader-text">' . esc_html__( 'Never logged in', 'wpdt-last-login-column' ) . '</span>';
	}

	return sprintf(
		'<span title="%s">%s</span>',
		esc_attr( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) ),
		esc_html( sprintf( __( '%s ago', 'wpdt-last-login-column' ), human_time_diff( $timestamp, time() ) ) )
	);
}
add_filter( 'manage_users_custom_column', 'wpdt002_render_column', 10, 3 );

/**
 * Make the column sortable.
 */
function wpdt002_sortable_column( $columns ) {
	$columns['wpdt002_last_login'] = 'wpdt002_last_login';
	return $columns;
}
add_filter( 'manage_users_sortable_columns', 'wpdt002_sortable_column' );

/**
 * Sort by last login. Users who never logged in are kept in the list.
 */
function wpdt002_sort_query( $query ) {
	if ( ! is_admin() || 'wpdt002_last_login' !== $query->get( 'orderby' ) ) {
		return;
	}

	$query->set(
		'meta_query',
		array(
			'relation'     => 'OR',
			'wpdt002_has'  => array(
				'key'     => WPDT002_META_KEY,
				'compare' => 'EXISTS',
				'type'    => 'NUMERIC',
			),
			'wpdt002_none' => array(
				'key'     => WPDT002_META_KEY,
				'compare' => 'NOT EXISTS',
			),
		)
	);
	$query->set( 'orderby', 'wpdt002_has' );
}
add_action( 'pre_get_users', 'wpdt002_sort_query' );
