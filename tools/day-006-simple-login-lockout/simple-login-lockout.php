<?php
/**
 * Plugin Name:       Simple Login Lockout
 * Description:       Blocks brute-force password guessing by temporarily locking out an IP address after too many failed logins. No account, no bloat.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       simple-login-lockout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPDT006_OPTION', 'wpdt006_settings' );

function wpdt006_settings() {
	$saved = get_option( WPDT006_OPTION, array() );
	return wp_parse_args(
		is_array( $saved ) ? $saved : array(),
		array(
			'max_attempts'    => 5,
			'lockout_minutes' => 15,
		)
	);
}

/**
 * Visitor IP. Uses REMOTE_ADDR only, because proxy headers can be spoofed.
 * Behind a trusted proxy/CDN, return the right address with this filter:
 * add_filter( 'wpdt006_client_ip', function () { return $_SERVER['HTTP_CF_CONNECTING_IP']; } );
 */
function wpdt006_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$ip = apply_filters( 'wpdt006_client_ip', $ip );
	return $ip ? $ip : 'unknown';
}

function wpdt006_key( $type ) {
	return 'wpdt006_' . $type . '_' . md5( wpdt006_ip() );
}

function wpdt006_locked_until() {
	return (int) get_transient( wpdt006_key( 'lock' ) );
}

/**
 * Run after the normal checks so a correct password is still refused while locked.
 */
function wpdt006_block_if_locked( $user ) {
	$until = wpdt006_locked_until();
	if ( $until > time() ) {
		return new WP_Error(
			'wpdt006_locked',
			sprintf(
				/* translators: %s: time remaining, e.g. "12 mins" */
				__( '<strong>Too many failed attempts.</strong> Please try again in %s.', 'simple-login-lockout' ),
				human_time_diff( time(), $until )
			)
		);
	}
	return $user;
}
add_filter( 'authenticate', 'wpdt006_block_if_locked', 100 );

function wpdt006_record_failure() {
	if ( wpdt006_locked_until() > time() ) {
		return;
	}

	$settings = wpdt006_settings();
	$window   = max( 1, (int) $settings['lockout_minutes'] ) * MINUTE_IN_SECONDS;
	$key      = wpdt006_key( 'fails' );
	$count    = (int) get_transient( $key ) + 1;

	if ( $count >= max( 1, (int) $settings['max_attempts'] ) ) {
		set_transient( wpdt006_key( 'lock' ), time() + $window, $window );
		delete_transient( $key );
		do_action( 'wpdt006_locked_out', wpdt006_ip() );
		return;
	}

	set_transient( $key, $count, $window );
}
add_action( 'wp_login_failed', 'wpdt006_record_failure' );

function wpdt006_clear_failures() {
	delete_transient( wpdt006_key( 'fails' ) );
}
add_action( 'wp_login', 'wpdt006_clear_failures' );

/* ---------- Settings page ---------- */

function wpdt006_menu() {
	add_options_page(
		__( 'Login Lockout', 'simple-login-lockout' ),
		__( 'Login Lockout', 'simple-login-lockout' ),
		'manage_options',
		'simple-login-lockout',
		'wpdt006_render_page'
	);
}
add_action( 'admin_menu', 'wpdt006_menu' );

function wpdt006_register_settings() {
	register_setting(
		'wpdt006_group',
		WPDT006_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'wpdt006_sanitize',
			'default'           => array(),
		)
	);
}
add_action( 'admin_init', 'wpdt006_register_settings' );

function wpdt006_sanitize( $input ) {
	$input = is_array( $input ) ? $input : array();
	return array(
		'max_attempts'    => min( 50, max( 1, absint( isset( $input['max_attempts'] ) ? $input['max_attempts'] : 5 ) ) ),
		'lockout_minutes' => min( 1440, max( 1, absint( isset( $input['lockout_minutes'] ) ? $input['lockout_minutes'] : 15 ) ) ),
	);
}

function wpdt006_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s = wpdt006_settings();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Login Lockout', 'simple-login-lockout' ); ?></h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'wpdt006_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="wpdt006_max"><?php esc_html_e( 'Failed attempts allowed', 'simple-login-lockout' ); ?></label></th>
					<td><input type="number" min="1" max="50" id="wpdt006_max" name="<?php echo esc_attr( WPDT006_OPTION ); ?>[max_attempts]" value="<?php echo esc_attr( $s['max_attempts'] ); ?>" class="small-text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpdt006_min"><?php esc_html_e( 'Lockout length (minutes)', 'simple-login-lockout' ); ?></label></th>
					<td>
						<input type="number" min="1" max="1440" id="wpdt006_min" name="<?php echo esc_attr( WPDT006_OPTION ); ?>[lockout_minutes]" value="<?php echo esc_attr( $s['lockout_minutes'] ); ?>" class="small-text">
						<p class="description"><?php esc_html_e( 'Also the window in which failed attempts are counted.', 'simple-login-lockout' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<p class="description">
			<?php
			/* translators: %s: the visitor's current IP address */
			echo esc_html( sprintf( __( 'Your current IP as seen by WordPress: %s. If this looks wrong (for example the same for every visitor), your site is behind a proxy and you should set the IP with the wpdt006_client_ip filter.', 'simple-login-lockout' ), wpdt006_ip() ) );
			?>
		</p>
	</div>
	<?php
}

function wpdt006_action_links( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=simple-login-lockout' ) ) . '">' . esc_html__( 'Settings', 'simple-login-lockout' ) . '</a>' );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wpdt006_action_links' );
