<?php
/**
 * Plugin Name:       WPDT 004 – Maintenance Toggle
 * Description:       One-click maintenance mode from the admin bar. Visitors see a 503 "back soon" page; administrators browse the site normally.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 * Text Domain:       wpdt-maintenance-toggle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPDT004_OPTION', 'wpdt004_maintenance_on' );

function wpdt004_is_on() {
	return (bool) get_option( WPDT004_OPTION, false );
}

/**
 * Block front-end visitors who cannot manage options.
 * wp-login.php, wp-admin, admin-ajax, REST and cron do not use template_redirect,
 * so they keep working.
 */
function wpdt004_block_visitors() {
	if ( ! wpdt004_is_on() || current_user_can( 'manage_options' ) ) {
		return;
	}

	nocache_headers();
	header( 'Retry-After: ' . (int) apply_filters( 'wpdt004_retry_after', HOUR_IN_SECONDS ) );

	wp_die(
		wp_kses_post( apply_filters( 'wpdt004_message', __( 'We are doing a bit of maintenance. Please check back shortly.', 'wpdt-maintenance-toggle' ) ) ),
		esc_html__( 'Back soon', 'wpdt-maintenance-toggle' ),
		array( 'response' => 503 )
	);
}
add_action( 'template_redirect', 'wpdt004_block_visitors', 0 );

/**
 * Admin bar toggle.
 */
function wpdt004_admin_bar( $wp_admin_bar ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$on  = wpdt004_is_on();
	$url = wp_nonce_url( admin_url( 'admin-post.php?action=wpdt004_toggle' ), 'wpdt004_toggle' );

	$wp_admin_bar->add_node(
		array(
			'id'    => 'wpdt004_maintenance',
			'title' => $on ? esc_html__( 'Maintenance: ON', 'wpdt-maintenance-toggle' ) : esc_html__( 'Maintenance: OFF', 'wpdt-maintenance-toggle' ),
			'href'  => $url,
			'meta'  => array(
				'class' => $on ? 'wpdt004-on' : 'wpdt004-off',
				'title' => $on ? __( 'Click to turn maintenance mode off', 'wpdt-maintenance-toggle' ) : __( 'Click to turn maintenance mode on', 'wpdt-maintenance-toggle' ),
			),
		)
	);
}
add_action( 'admin_bar_menu', 'wpdt004_admin_bar', 100 );

/**
 * Colour the node red while maintenance mode is on.
 */
function wpdt004_admin_bar_css() {
	if ( ! is_admin_bar_showing() || ! wpdt004_is_on() ) {
		return;
	}
	wp_add_inline_style( 'admin-bar', '#wpadminbar .wpdt004-on > .ab-item{background:#b32d2e;color:#fff;}#wpadminbar .wpdt004-on:hover > .ab-item{background:#8a2424;color:#fff;}' );
}
add_action( 'wp_enqueue_scripts', 'wpdt004_admin_bar_css' );
add_action( 'admin_enqueue_scripts', 'wpdt004_admin_bar_css' );

/**
 * Handle the toggle click.
 */
function wpdt004_handle_toggle() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'wpdt-maintenance-toggle' ), '', array( 'response' => 403 ) );
	}

	check_admin_referer( 'wpdt004_toggle' );

	update_option( WPDT004_OPTION, wpdt004_is_on() ? 0 : 1, false );

	$back = wp_get_referer();
	wp_safe_redirect( $back ? $back : admin_url() );
	exit;
}
add_action( 'admin_post_wpdt004_toggle', 'wpdt004_handle_toggle' );
