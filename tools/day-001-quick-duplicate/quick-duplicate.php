<?php
/**
 * Plugin Name:       WPDT 001 – Quick Duplicate
 * Description:       Adds a "Duplicate" row action to posts, pages and custom post types. Copies content, meta, featured image and taxonomies into a new draft.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 * Text Domain:       wpdt-quick-duplicate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post types that should NOT get a Duplicate link.
 * WooCommerce products are excluded because WooCommerce has its own
 * duplicator that also handles variations.
 */
function wpdt001_excluded_post_types() {
	return apply_filters( 'wpdt001_excluded_post_types', array( 'product', 'attachment', 'shop_order', 'shop_coupon' ) );
}

/**
 * Add the "Duplicate" row action.
 */
function wpdt001_row_action( $actions, $post ) {
	if ( in_array( $post->post_type, wpdt001_excluded_post_types(), true ) ) {
		return $actions;
	}

	$type_object = get_post_type_object( $post->post_type );
	if ( ! $type_object || ! current_user_can( $type_object->cap->edit_posts ) || ! current_user_can( 'read_post', $post->ID ) ) {
		return $actions;
	}

	$url = wp_nonce_url(
		admin_url( 'admin.php?action=wpdt001_duplicate&post=' . $post->ID ),
		'wpdt001_duplicate_' . $post->ID
	);

	$actions['wpdt001_duplicate'] = sprintf(
		'<a href="%s" aria-label="%s">%s</a>',
		esc_url( $url ),
		esc_attr( sprintf( __( 'Duplicate &#8220;%s&#8221;', 'wpdt-quick-duplicate' ), get_the_title( $post ) ) ),
		esc_html__( 'Duplicate', 'wpdt-quick-duplicate' )
	);

	return $actions;
}
add_filter( 'post_row_actions', 'wpdt001_row_action', 10, 2 );
add_filter( 'page_row_actions', 'wpdt001_row_action', 10, 2 );

/**
 * Handle the duplication request.
 */
function wpdt001_handle_duplicate() {
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

	if ( ! $post_id ) {
		wp_die( esc_html__( 'No post to duplicate.', 'wpdt-quick-duplicate' ) );
	}

	check_admin_referer( 'wpdt001_duplicate_' . $post_id );

	$post = get_post( $post_id );
	if ( ! $post || in_array( $post->post_type, wpdt001_excluded_post_types(), true ) ) {
		wp_die( esc_html__( 'This item cannot be duplicated.', 'wpdt-quick-duplicate' ) );
	}

	$type_object = get_post_type_object( $post->post_type );
	if ( ! $type_object || ! current_user_can( $type_object->cap->edit_posts ) || ! current_user_can( 'read_post', $post_id ) ) {
		wp_die( esc_html__( 'You are not allowed to duplicate this item.', 'wpdt-quick-duplicate' ), '', array( 'response' => 403 ) );
	}

	$new_id = wp_insert_post(
		wp_slash(
			array(
				'post_type'      => $post->post_type,
				'post_status'    => 'draft',
				'post_title'     => $post->post_title . ' ' . __( '(Copy)', 'wpdt-quick-duplicate' ),
				'post_content'   => $post->post_content,
				'post_excerpt'   => $post->post_excerpt,
				'post_author'    => get_current_user_id(),
				'post_parent'    => $post->post_parent,
				'menu_order'     => $post->menu_order,
				'comment_status' => $post->comment_status,
				'ping_status'    => $post->ping_status,
				'post_password'  => $post->post_password,
			)
		),
		true
	);

	if ( is_wp_error( $new_id ) ) {
		wp_die( esc_html( $new_id->get_error_message() ) );
	}

	// Taxonomies.
	foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
		$term_ids = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $term_ids ) ) {
			wp_set_object_terms( $new_id, $term_ids, $taxonomy );
		}
	}

	// Meta (includes featured image via _thumbnail_id).
	$skip_meta = apply_filters(
		'wpdt001_skip_meta_keys',
		array( '_edit_lock', '_edit_last', '_wp_old_slug', '_wp_trash_meta_status', '_wp_trash_meta_time' )
	);

	foreach ( get_post_meta( $post_id ) as $key => $values ) {
		if ( in_array( $key, $skip_meta, true ) ) {
			continue;
		}
		foreach ( $values as $value ) {
			add_post_meta( $new_id, $key, wp_slash( maybe_unserialize( $value ) ) );
		}
	}

	do_action( 'wpdt001_post_duplicated', $new_id, $post_id );

	wp_safe_redirect(
		add_query_arg(
			array(
				'post_type'          => $post->post_type,
				'wpdt001_duplicated' => $new_id,
			),
			admin_url( 'edit.php' )
		)
	);
	exit;
}
add_action( 'admin_action_wpdt001_duplicate', 'wpdt001_handle_duplicate' );

/**
 * Success notice with a link to the new draft.
 */
function wpdt001_admin_notice() {
	if ( empty( $_GET['wpdt001_duplicated'] ) ) {
		return;
	}

	$new_id = absint( $_GET['wpdt001_duplicated'] );
	if ( ! $new_id || ! current_user_can( 'edit_post', $new_id ) ) {
		return;
	}

	printf(
		'<div class="notice notice-success is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
		esc_html__( 'Draft created.', 'wpdt-quick-duplicate' ),
		esc_url( get_edit_post_link( $new_id, 'raw' ) ),
		esc_html__( 'Edit the copy', 'wpdt-quick-duplicate' )
	);
}
add_action( 'admin_notices', 'wpdt001_admin_notice' );
