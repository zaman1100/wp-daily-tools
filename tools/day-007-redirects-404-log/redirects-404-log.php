<?php
/**
 * Plugin Name:       Redirects & 404 Log
 * Description:       See which broken URLs visitors actually hit, then fix them with a redirect in one click. Lightweight, no tracking, no IP addresses stored.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       redirects-404-log
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPDT008_DB_VERSION', '1' );

function wpdt008_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'wpdt008_' . $name;
}

/* ---------- Database ---------- */

function wpdt008_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();
	$log     = wpdt008_table( 'log' );
	$redir   = wpdt008_table( 'redirects' );

	dbDelta( "CREATE TABLE {$log} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  path varchar(191) NOT NULL,
  hits bigint(20) unsigned NOT NULL DEFAULT 1,
  referrer varchar(255) NOT NULL DEFAULT '',
  first_seen datetime NOT NULL,
  last_seen datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY path (path),
  KEY last_seen (last_seen)
) {$charset};" );

	dbDelta( "CREATE TABLE {$redir} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  source varchar(191) NOT NULL,
  target text NOT NULL,
  code smallint(3) unsigned NOT NULL DEFAULT 301,
  is_wild tinyint(1) unsigned NOT NULL DEFAULT 0,
  hits bigint(20) unsigned NOT NULL DEFAULT 0,
  created datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY source (source),
  KEY is_wild (is_wild)
) {$charset};" );

	update_option( 'wpdt008_db_version', WPDT008_DB_VERSION );
}
register_activation_hook( __FILE__, 'wpdt008_install' );

function wpdt008_maybe_install() {
	if ( get_option( 'wpdt008_db_version' ) !== WPDT008_DB_VERSION ) {
		wpdt008_install();
	}
}
add_action( 'admin_init', 'wpdt008_maybe_install' );

/* ---------- Path helpers ---------- */

/**
 * Clean a path or same-site URL: strip host, query and fragment, decode,
 * make sure it starts with "/" and has no trailing slash (except the root).
 * Case is kept; use wpdt008_key() for matching.
 */
function wpdt008_clean( $input ) {
	$input = trim( (string) $input );
	$path  = wp_parse_url( $input, PHP_URL_PATH );
	$path  = is_string( $path ) ? rawurldecode( $path ) : '';
	$path  = preg_replace( '#/+#', '/', '/' . ltrim( $path, '/' ) );

	$home = wp_parse_url( home_url(), PHP_URL_PATH );
	$home = $home ? untrailingslashit( $home ) : '';
	if ( $home && 0 === stripos( $path, $home . '/' ) && preg_match( '#^https?://#i', $input ) ) {
		$path = substr( $path, strlen( $home ) );
	}

	return '/' === $path ? '/' : rtrim( $path, '/' );
}

function wpdt008_key( $path ) {
	return strtolower( $path );
}

function wpdt008_request_path() {
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parsed and cleaned below.
	$path = wp_parse_url( $uri, PHP_URL_PATH );
	$path = is_string( $path ) ? rawurldecode( $path ) : '/';
	$home = wp_parse_url( home_url(), PHP_URL_PATH );
	$home = $home ? untrailingslashit( $home ) : '';
	if ( $home && 0 === stripos( $path, $home . '/' ) ) {
		$path = substr( $path, strlen( $home ) );
	}
	$path = preg_replace( '#/+#', '/', '/' . ltrim( $path, '/' ) );
	return '/' === $path ? '/' : rtrim( $path, '/' );
}

/* ---------- Front end: redirect + log ---------- */

function wpdt008_is_front_request() {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return false;
	}
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
	return in_array( $method, array( 'GET', 'HEAD' ), true );
}

function wpdt008_find_redirect( $raw ) {
	global $wpdb;
	$table = wpdt008_table( 'redirects' );
	$key   = wpdt008_key( $raw );

	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE source = %s LIMIT 1", $key ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( $row ) {
		return array( $row, '' );
	}

	$wild = $wpdb->get_results( "SELECT * FROM {$table} WHERE is_wild = 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	foreach ( (array) $wild as $row ) {
		$prefix = rtrim( $row->source, '*' );
		if ( 0 === strpos( $key . '/', $prefix ) || 0 === strpos( $key, $prefix ) ) {
			$rest = strlen( $key ) >= strlen( $prefix ) ? substr( $raw, strlen( $prefix ) ) : '';
			return array( $row, (string) $rest );
		}
	}
	return null;
}

function wpdt008_maybe_redirect() {
	if ( ! wpdt008_is_front_request() ) {
		return;
	}
	$raw   = wpdt008_request_path();
	$match = wpdt008_find_redirect( $raw );
	if ( ! $match ) {
		return;
	}
	list( $row, $rest ) = $match;

	$target = $row->target;
	if ( false !== strpos( $target, '*' ) ) {
		$target = str_replace( '*', str_replace( '%2F', '/', rawurlencode( $rest ) ), $target );
	}
	if ( 0 === strpos( $target, '/' ) ) {
		$target = home_url( $target );
	}
	$target = apply_filters( 'wpdt008_redirect_target', $target, $row );
	if ( ! $target ) {
		return;
	}

	global $wpdb;
	$wpdb->query( $wpdb->prepare( 'UPDATE ' . wpdt008_table( 'redirects' ) . ' SET hits = hits + 1 WHERE id = %d', $row->id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

	// The target was entered by an administrator, so any host is allowed.
	wp_redirect( $target, (int) $row->code, 'Redirects & 404 Log' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
	exit;
}
add_action( 'template_redirect', 'wpdt008_maybe_redirect', 0 );

function wpdt008_log_404() {
	if ( ! is_404() || ! wpdt008_is_front_request() ) {
		return;
	}
	$path = wpdt008_key( wpdt008_request_path() );
	if ( strlen( $path ) > 191 || ! apply_filters( 'wpdt008_should_log', true, $path ) ) {
		return;
	}

	global $wpdb;
	$ref = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
	$ref = substr( $ref, 0, 255 );
	$now = current_time( 'mysql', true );
	$tbl = wpdt008_table( 'log' );

	$wpdb->query( $wpdb->prepare( "INSERT INTO {$tbl} (path, hits, referrer, first_seen, last_seen) VALUES (%s, 1, %s, %s, %s) ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = VALUES(last_seen), referrer = IF(VALUES(referrer) <> '', VALUES(referrer), referrer)", $path, $ref, $now, $now ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	// Keep the table small: occasionally drop the oldest entries.
	if ( 1 === wp_rand( 1, 50 ) ) {
		$max = max( 100, (int) apply_filters( 'wpdt008_max_log', 1000 ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$tbl} WHERE id NOT IN (SELECT id FROM (SELECT id FROM {$tbl} ORDER BY last_seen DESC LIMIT %d) keep)", $max ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
add_action( 'template_redirect', 'wpdt008_log_404', 20 );

/* ---------- Admin: actions ---------- */

function wpdt008_page_url( $tab = 'log', $extra = array() ) {
	return add_query_arg( array_merge( array( 'page' => 'redirects-404-log', 'tab' => $tab ), $extra ), admin_url( 'tools.php' ) );
}

function wpdt008_guard() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to do this.', 'redirects-404-log' ), 403 );
	}
	check_admin_referer( 'wpdt008_action' );
}

function wpdt008_back( $tab, $msg ) {
	wp_safe_redirect( wpdt008_page_url( $tab, array( 'wpdt008_msg' => $msg ) ) );
	exit;
}

function wpdt008_handle_add() {
	wpdt008_guard();
	global $wpdb;

	$tab    = isset( $_POST['tab'] ) && 'redirects' === $_POST['tab'] ? 'redirects' : 'log';
	$source = isset( $_POST['source'] ) ? wp_unslash( $_POST['source'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cleaned by wpdt008_clean().
	$target = isset( $_POST['target'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['target'] ) ) ) : '';
	$code   = isset( $_POST['code'] ) ? absint( $_POST['code'] ) : 301;
	$code   = in_array( $code, array( 301, 302, 307, 308 ), true ) ? $code : 301;

	$source = wpdt008_clean( $source );
	$key    = wpdt008_key( $source );

	if ( '/' === $key || strlen( $key ) > 191 || '' === $target ) {
		wpdt008_back( $tab, 'invalid' );
	}

	if ( 0 === strpos( $target, '/' ) ) {
		$target = '/' . ltrim( preg_replace( '#\s+#', '', $target ), '/' );
		if ( wpdt008_key( wpdt008_clean( $target ) ) === $key ) {
			wpdt008_back( $tab, 'loop' );
		}
	} else {
		$target = esc_url_raw( $target, array( 'http', 'https' ) );
		if ( '' === $target ) {
			wpdt008_back( $tab, 'invalid' );
		}
		if ( wp_parse_url( $target, PHP_URL_HOST ) === wp_parse_url( home_url(), PHP_URL_HOST ) && wpdt008_key( wpdt008_clean( $target ) ) === $key ) {
			wpdt008_back( $tab, 'loop' );
		}
	}

	$is_wild = '*' === substr( $key, -1 ) ? 1 : 0;
	$table   = wpdt008_table( 'redirects' );
	$wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (source, target, code, is_wild, hits, created) VALUES (%s, %s, %d, %d, 0, %s) ON DUPLICATE KEY UPDATE target = VALUES(target), code = VALUES(code), is_wild = VALUES(is_wild)", $key, $target, $code, $is_wild, current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->delete( wpdt008_table( 'log' ), array( 'path' => $key ), array( '%s' ) );

	wpdt008_back( $tab, 'saved' );
}
add_action( 'admin_post_wpdt008_add', 'wpdt008_handle_add' );

function wpdt008_handle_delete() {
	wpdt008_guard();
	global $wpdb;
	$what = isset( $_POST['what'] ) && 'redirect' === $_POST['what'] ? 'redirect' : 'log';
	$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	$wpdb->delete( wpdt008_table( 'redirect' === $what ? 'redirects' : 'log' ), array( 'id' => $id ), array( '%d' ) );
	wpdt008_back( 'redirect' === $what ? 'redirects' : 'log', 'deleted' );
}
add_action( 'admin_post_wpdt008_delete', 'wpdt008_handle_delete' );

function wpdt008_handle_clear() {
	wpdt008_guard();
	global $wpdb;
	$wpdb->query( 'TRUNCATE TABLE ' . wpdt008_table( 'log' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	wpdt008_back( 'log', 'cleared' );
}
add_action( 'admin_post_wpdt008_clear', 'wpdt008_handle_clear' );

function wpdt008_csv_cell( $value ) {
	$value = (string) $value;
	// Stop spreadsheet apps from running a value as a formula.
	return preg_match( '/^[=+\-@\t\r]/', $value ) ? "'" . $value : $value;
}

function wpdt008_handle_export() {
	wpdt008_guard();
	global $wpdb;
	$rows = $wpdb->get_results( 'SELECT path, hits, first_seen, last_seen, referrer FROM ' . wpdt008_table( 'log' ) . ' ORDER BY hits DESC', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="404-log-' . gmdate( 'Y-m-d' ) . '.csv"' );
	$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	fputcsv( $out, array( 'path', 'hits', 'first_seen_utc', 'last_seen_utc', 'last_referrer' ) );
	foreach ( (array) $rows as $r ) {
		fputcsv( $out, array_map( 'wpdt008_csv_cell', $r ) );
	}
	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	exit;
}
add_action( 'admin_post_wpdt008_export', 'wpdt008_handle_export' );

/* ---------- Admin: page ---------- */

function wpdt008_menu() {
	add_management_page(
		__( 'Redirects & 404 Log', 'redirects-404-log' ),
		__( 'Redirects & 404s', 'redirects-404-log' ),
		'manage_options',
		'redirects-404-log',
		'wpdt008_render_page'
	);
}
add_action( 'admin_menu', 'wpdt008_menu' );

function wpdt008_post_form_open( $action, $extra = array() ) {
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
	echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
	wp_nonce_field( 'wpdt008_action' );
	foreach ( $extra as $name => $value ) {
		echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
	}
}

function wpdt008_code_select() {
	echo '<select name="code" aria-label="' . esc_attr__( 'Redirect type', 'redirects-404-log' ) . '">';
	echo '<option value="301">' . esc_html__( '301 Permanent', 'redirects-404-log' ) . '</option>';
	echo '<option value="302">' . esc_html__( '302 Temporary', 'redirects-404-log' ) . '</option>';
	echo '<option value="307">307</option><option value="308">308</option></select>';
}

function wpdt008_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	global $wpdb;

	$tab = isset( $_GET['tab'] ) && 'redirects' === $_GET['tab'] ? 'redirects' : 'log'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$msg = isset( $_GET['wpdt008_msg'] ) ? sanitize_key( wp_unslash( $_GET['wpdt008_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$notices = array(
		'saved'   => array( 'success', __( 'Redirect saved.', 'redirects-404-log' ) ),
		'deleted' => array( 'success', __( 'Deleted.', 'redirects-404-log' ) ),
		'cleared' => array( 'success', __( '404 log cleared.', 'redirects-404-log' ) ),
		'invalid' => array( 'error', __( 'Please enter a valid source path and a target (a path starting with / or a full http(s) URL).', 'redirects-404-log' ) ),
		'loop'    => array( 'error', __( 'The source and target are the same, which would cause a redirect loop.', 'redirects-404-log' ) ),
	);

	echo '<div class="wrap"><h1>' . esc_html__( 'Redirects & 404 Log', 'redirects-404-log' ) . '</h1>';
	if ( isset( $notices[ $msg ] ) ) {
		printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $notices[ $msg ][0] ), esc_html( $notices[ $msg ][1] ) );
	}

	echo '<nav class="nav-tab-wrapper">';
	printf( '<a href="%s" class="nav-tab %s">%s</a>', esc_url( wpdt008_page_url( 'log' ) ), 'log' === $tab ? 'nav-tab-active' : '', esc_html__( '404 Log', 'redirects-404-log' ) );
	printf( '<a href="%s" class="nav-tab %s">%s</a>', esc_url( wpdt008_page_url( 'redirects' ) ), 'redirects' === $tab ? 'nav-tab-active' : '', esc_html__( 'Redirects', 'redirects-404-log' ) );
	echo '</nav>';

	if ( 'redirects' === $tab ) {
		wpdt008_render_redirects();
	} else {
		wpdt008_render_log();
	}
	echo '</div>';
}

function wpdt008_render_log() {
	global $wpdb;
	$rows = $wpdb->get_results( 'SELECT * FROM ' . wpdt008_table( 'log' ) . ' ORDER BY hits DESC, last_seen DESC LIMIT 300' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

	echo '<p>' . esc_html__( 'Pages visitors tried to open but could not find, most requested first. Enter where each one should go and press Redirect.', 'redirects-404-log' ) . '</p>';

	if ( ! $rows ) {
		echo '<p><em>' . esc_html__( 'No 404s recorded yet. That is good news.', 'redirects-404-log' ) . '</em></p>';
		return;
	}

	echo '<p>';
	wpdt008_post_form_open( 'wpdt008_clear' );
	submit_button( __( 'Clear log', 'redirects-404-log' ), 'secondary small', 'submit', false, array( 'onclick' => "return confirm('" . esc_js( __( 'Clear the whole 404 log?', 'redirects-404-log' ) ) . "');" ) );
	echo '</form> ';
	echo '<a class="button button-small" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wpdt008_export' ), 'wpdt008_action' ) ) . '">' . esc_html__( 'Export CSV', 'redirects-404-log' ) . '</a></p>';

	echo '<table class="widefat striped"><thead><tr>';
	foreach ( array( 'Path', 'Hits', 'Last seen', 'Last referrer', 'Redirect to', '' ) as $h ) {
		echo '<th>' . esc_html( $h ) . '</th>';
	}
	echo '</tr></thead><tbody>';

	foreach ( $rows as $r ) {
		echo '<tr>';
		echo '<td><code>' . esc_html( $r->path ) . '</code></td>';
		echo '<td>' . esc_html( number_format_i18n( $r->hits ) ) . '</td>';
		/* translators: %s: time difference, e.g. "2 hours" */
		echo '<td>' . esc_html( sprintf( __( '%s ago', 'redirects-404-log' ), human_time_diff( strtotime( $r->last_seen . ' UTC' ), time() ) ) ) . '</td>';
		echo '<td>' . ( $r->referrer ? '<span title="' . esc_attr( $r->referrer ) . '">' . esc_html( wp_html_excerpt( $r->referrer, 40, '…' ) ) . '</span>' : '&mdash;' ) . '</td>';
		echo '<td>';
		wpdt008_post_form_open( 'wpdt008_add', array( 'source' => $r->path, 'tab' => 'log', 'code' => 301 ) );
		echo '<input type="text" name="target" class="regular-text" placeholder="/new-page or https://…" required> ';
		submit_button( __( 'Redirect', 'redirects-404-log' ), 'primary small', 'submit', false );
		echo '</form></td><td>';
		wpdt008_post_form_open( 'wpdt008_delete', array( 'what' => 'log', 'id' => $r->id ) );
		submit_button( __( 'Ignore', 'redirects-404-log' ), 'link-delete small', 'submit', false );
		echo '</form></td></tr>';
	}
	echo '</tbody></table>';
}

function wpdt008_render_redirects() {
	global $wpdb;
	$rows = $wpdb->get_results( 'SELECT * FROM ' . wpdt008_table( 'redirects' ) . ' ORDER BY id DESC LIMIT 500' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

	echo '<h2>' . esc_html__( 'Add a redirect', 'redirects-404-log' ) . '</h2>';
	wpdt008_post_form_open( 'wpdt008_add', array( 'tab' => 'redirects' ) );
	echo '<table class="form-table" role="presentation"><tr><th scope="row"><label for="wpdt008_src">' . esc_html__( 'From', 'redirects-404-log' ) . '</label></th><td>';
	echo '<input type="text" id="wpdt008_src" name="source" class="regular-text" placeholder="/old-page" required>';
	echo '<p class="description">' . esc_html__( 'A path on this site. End with * to match everything below it, for example /old-blog/*', 'redirects-404-log' ) . '</p></td></tr>';
	echo '<tr><th scope="row"><label for="wpdt008_tgt">' . esc_html__( 'To', 'redirects-404-log' ) . '</label></th><td>';
	echo '<input type="text" id="wpdt008_tgt" name="target" class="regular-text" placeholder="/new-page or https://example.com/page" required> ';
	wpdt008_code_select();
	echo '<p class="description">' . esc_html__( 'With a wildcard source, put * in the target where the rest of the path should go, for example /blog/*', 'redirects-404-log' ) . '</p></td></tr></table>';
	submit_button( __( 'Save redirect', 'redirects-404-log' ), 'primary', 'submit', false );
	echo '</form>';

	echo '<h2>' . esc_html__( 'Active redirects', 'redirects-404-log' ) . '</h2>';
	if ( ! $rows ) {
		echo '<p><em>' . esc_html__( 'No redirects yet.', 'redirects-404-log' ) . '</em></p>';
		return;
	}
	echo '<table class="widefat striped"><thead><tr><th>From</th><th>To</th><th>Type</th><th>Hits</th><th></th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		echo '<tr><td><code>' . esc_html( $r->source ) . '</code></td>';
		echo '<td><code>' . esc_html( $r->target ) . '</code></td>';
		echo '<td>' . esc_html( $r->code ) . '</td>';
		echo '<td>' . esc_html( number_format_i18n( $r->hits ) ) . '</td><td>';
		wpdt008_post_form_open( 'wpdt008_delete', array( 'what' => 'redirect', 'id' => $r->id ) );
		submit_button( __( 'Delete', 'redirects-404-log' ), 'link-delete small', 'submit', false );
		echo '</form></td></tr>';
	}
	echo '</tbody></table>';
}

function wpdt008_action_links( $links ) {
	array_unshift( $links, '<a href="' . esc_url( wpdt008_page_url() ) . '">' . esc_html__( '404 Log', 'redirects-404-log' ) . '</a>' );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wpdt008_action_links' );
