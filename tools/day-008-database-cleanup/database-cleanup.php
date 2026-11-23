<?php
/**
 * Plugin Name:       Database Cleanup
 * Description:       See how much junk is sitting in your database (old revisions, trash, spam, expired transients, orphaned meta) and clear each type safely with one click.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       database-cleanup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- Settings via filters ---------- */

function wpdt008_batch_size() {
	return max( 50, (int) apply_filters( 'wpdt008_batch_size', 500 ) );
}

function wpdt008_revision_days() {
	return max( 0, (int) apply_filters( 'wpdt008_revision_days', 30 ) );
}

function wpdt008_autodraft_days() {
	return max( 1, (int) apply_filters( 'wpdt008_autodraft_days', 7 ) );
}

function wpdt008_cutoff( $days ) {
	return gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
}

/* ---------- Counting and cleaning, one pair per item ---------- */

function wpdt008_count_revisions() {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision' AND post_modified_gmt < %s", wpdt008_cutoff( wpdt008_revision_days() ) ) );
}

function wpdt008_clean_revisions() {
	global $wpdb;
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision' AND post_modified_gmt < %s ORDER BY ID ASC LIMIT %d", wpdt008_cutoff( wpdt008_revision_days() ), wpdt008_batch_size() ) );
	return wpdt008_delete_posts( $ids );
}

function wpdt008_count_autodrafts() {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft' AND post_modified_gmt < %s", wpdt008_cutoff( wpdt008_autodraft_days() ) ) );
}

function wpdt008_clean_autodrafts() {
	global $wpdb;
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'auto-draft' AND post_modified_gmt < %s ORDER BY ID ASC LIMIT %d", wpdt008_cutoff( wpdt008_autodraft_days() ), wpdt008_batch_size() ) );
	return wpdt008_delete_posts( $ids );
}

function wpdt008_count_trash() {
	global $wpdb;
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'" );
}

function wpdt008_clean_trash() {
	global $wpdb;
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'trash' ORDER BY ID ASC LIMIT %d", wpdt008_batch_size() ) );
	return wpdt008_delete_posts( $ids );
}

function wpdt008_delete_posts( $ids ) {
	$done = 0;
	foreach ( (array) $ids as $id ) {
		if ( wp_delete_post( (int) $id, true ) ) {
			$done++;
		}
	}
	return $done;
}

function wpdt008_count_comments( $status ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = %s", $status ) );
}

function wpdt008_clean_comments( $status ) {
	global $wpdb;
	$ids  = $wpdb->get_col( $wpdb->prepare( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved = %s ORDER BY comment_ID ASC LIMIT %d", $status, wpdt008_batch_size() ) );
	$done = 0;
	foreach ( $ids as $id ) {
		if ( wp_delete_comment( (int) $id, true ) ) {
			$done++;
		}
	}
	return $done;
}

function wpdt008_count_transients() {
	global $wpdb;
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
			$wpdb->esc_like( '_transient_timeout_' ) . '%',
			time()
		)
	);
}

function wpdt008_clean_transients() {
	$before = wpdt008_count_transients();
	delete_expired_transients( true );
	return max( 0, $before - wpdt008_count_transients() );
}

function wpdt008_count_orphans() {
	global $wpdb;
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL" );
}

function wpdt008_clean_orphans() {
	global $wpdb;
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT pm.meta_id FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL LIMIT %d", wpdt008_batch_size() * 4 ) );
	$ids = array_map( 'absint', $ids );
	if ( ! $ids ) {
		return 0;
	}
	return (int) $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_id IN (" . implode( ',', $ids ) . ')' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IDs are integers.
}

/**
 * Each item: label, description, count callback, clean callback.
 */
function wpdt008_items() {
	$rev_days = wpdt008_revision_days();
	$ad_days  = wpdt008_autodraft_days();

	return array(
		'revisions'  => array(
			__( 'Old post revisions', 'database-cleanup' ),
			/* translators: %d: number of days */
			sprintf( _n( 'Saved revisions older than %d day. Newer ones are kept.', 'Saved revisions older than %d days. Newer ones are kept.', $rev_days, 'database-cleanup' ), $rev_days ),
			'wpdt008_count_revisions',
			'wpdt008_clean_revisions',
		),
		'autodrafts' => array(
			__( 'Auto-drafts', 'database-cleanup' ),
			/* translators: %d: number of days */
			sprintf( _n( 'Empty drafts WordPress created automatically, older than %d day.', 'Empty drafts WordPress created automatically, older than %d days.', $ad_days, 'database-cleanup' ), $ad_days ),
			'wpdt008_count_autodrafts',
			'wpdt008_clean_autodrafts',
		),
		'trash'      => array(
			__( 'Trashed posts and pages', 'database-cleanup' ),
			__( 'Everything currently in the trash. This cannot be undone.', 'database-cleanup' ),
			'wpdt008_count_trash',
			'wpdt008_clean_trash',
		),
		'spam'       => array(
			__( 'Spam comments', 'database-cleanup' ),
			__( 'Comments marked as spam.', 'database-cleanup' ),
			function () {
				return wpdt008_count_comments( 'spam' );
			},
			function () {
				return wpdt008_clean_comments( 'spam' );
			},
		),
		'trashcomm'  => array(
			__( 'Trashed comments', 'database-cleanup' ),
			__( 'Comments sitting in the comment trash.', 'database-cleanup' ),
			function () {
				return wpdt008_count_comments( 'trash' );
			},
			function () {
				return wpdt008_clean_comments( 'trash' );
			},
		),
		'transients' => array(
			__( 'Expired transients', 'database-cleanup' ),
			__( 'Temporary cached data that has already expired. WordPress rebuilds anything it still needs.', 'database-cleanup' ),
			'wpdt008_count_transients',
			'wpdt008_clean_transients',
		),
		'orphans'    => array(
			__( 'Orphaned post meta', 'database-cleanup' ),
			__( 'Custom field rows that belong to posts that no longer exist.', 'database-cleanup' ),
			'wpdt008_count_orphans',
			'wpdt008_clean_orphans',
		),
	);
}

/* ---------- Action ---------- */

function wpdt008_handle_clean() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to do this.', 'database-cleanup' ), 403 );
	}
	check_admin_referer( 'wpdt008_clean' );

	$items = wpdt008_items();
	$which = isset( $_POST['item'] ) ? sanitize_key( wp_unslash( $_POST['item'] ) ) : '';
	$run   = 'all' === $which ? array_keys( $items ) : ( isset( $items[ $which ] ) ? array( $which ) : array() );

	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,Squiz.PHP.DiscouragedFunctions.Discouraged
	}

	$removed = 0;
	$left    = 0;
	foreach ( $run as $key ) {
		$removed += (int) call_user_func( $items[ $key ][3] );
		$left    += (int) call_user_func( $items[ $key ][2] );
	}

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'            => 'database-cleanup',
				'wpdt008_removed' => $removed,
				'wpdt008_left'    => $left,
				'wpdt008_ran'     => $run ? 1 : 0,
			),
			admin_url( 'tools.php' )
		)
	);
	exit;
}
add_action( 'admin_post_wpdt008_clean', 'wpdt008_handle_clean' );

/* ---------- Page ---------- */

function wpdt008_menu() {
	add_management_page(
		__( 'Database Cleanup', 'database-cleanup' ),
		__( 'Database Cleanup', 'database-cleanup' ),
		'manage_options',
		'database-cleanup',
		'wpdt008_render_page'
	);
}
add_action( 'admin_menu', 'wpdt008_menu' );

function wpdt008_db_size() {
	global $wpdb;
	$size = $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = %s', DB_NAME ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return $size ? size_format( (float) $size, 1 ) : '';
}

function wpdt008_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	echo '<div class="wrap"><h1>' . esc_html__( 'Database Cleanup', 'database-cleanup' ) . '</h1>';

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
	if ( isset( $_GET['wpdt008_ran'] ) && '1' === $_GET['wpdt008_ran'] ) {
		$removed = isset( $_GET['wpdt008_removed'] ) ? absint( $_GET['wpdt008_removed'] ) : 0;
		$left    = isset( $_GET['wpdt008_left'] ) ? absint( $_GET['wpdt008_left'] ) : 0;
		/* translators: %s: number of rows removed */
		$text = sprintf( _n( 'Removed %s item.', 'Removed %s items.', $removed, 'database-cleanup' ), number_format_i18n( $removed ) );
		if ( $left > 0 ) {
			/* translators: %s: number of items still waiting */
			$text .= ' ' . sprintf( __( '%s still to go. Press the button again to continue (large sites are cleaned in batches).', 'database-cleanup' ), number_format_i18n( $left ) );
		}
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $text ) . '</p></div>';
	}
	// phpcs:enable

	$size = wpdt008_db_size();
	if ( $size ) {
		/* translators: %s: database size, e.g. 24.5 MB */
		echo '<p>' . esc_html( sprintf( __( 'Current database size: %s', 'database-cleanup' ), $size ) ) . '</p>';
	}
	echo '<div class="notice notice-warning inline"><p><strong>' . esc_html__( 'Back up your database first.', 'database-cleanup' ) . '</strong> ' . esc_html__( 'Removed items cannot be restored.', 'database-cleanup' ) . '</p></div>';

	echo '<table class="widefat striped" style="margin-top:12px;max-width:900px"><thead><tr><th>' . esc_html__( 'What', 'database-cleanup' ) . '</th><th>' . esc_html__( 'Found', 'database-cleanup' ) . '</th><th></th></tr></thead><tbody>';
	$total = 0;
	foreach ( wpdt008_items() as $key => $item ) {
		$count  = (int) call_user_func( $item[2] );
		$total += $count;
		echo '<tr><td><strong>' . esc_html( $item[0] ) . '</strong><br><span class="description">' . esc_html( $item[1] ) . '</span></td>';
		echo '<td>' . esc_html( number_format_i18n( $count ) ) . '</td><td>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="wpdt008_clean"><input type="hidden" name="item" value="' . esc_attr( $key ) . '">';
		wp_nonce_field( 'wpdt008_clean' );
		submit_button( __( 'Clean', 'database-cleanup' ), 'secondary small', 'submit', false, $count ? array() : array( 'disabled' => 'disabled' ) );
		echo '</form></td></tr>';
	}
	echo '</tbody></table>';

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:16px">';
	echo '<input type="hidden" name="action" value="wpdt008_clean"><input type="hidden" name="item" value="all">';
	wp_nonce_field( 'wpdt008_clean' );
	submit_button(
		__( 'Clean everything above', 'database-cleanup' ),
		'primary',
		'submit',
		false,
		array_merge( array( 'onclick' => "return confirm('" . esc_js( __( 'Permanently remove everything listed? Make sure you have a backup.', 'database-cleanup' ) ) . "');" ), $total ? array() : array( 'disabled' => 'disabled' ) )
	);
	echo '</form></div>';
}

function wpdt008_action_links( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'tools.php?page=database-cleanup' ) ) . '">' . esc_html__( 'Clean up', 'database-cleanup' ) . '</a>' );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wpdt008_action_links' );
