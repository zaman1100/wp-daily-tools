<?php
/**
 * Plugin Name:       Simple Hardening
 * Description:       Three small security tweaks with on/off switches: hide the WordPress version, disable XML-RPC, and stop user name enumeration.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       simple-hardening
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPDT009_OPTION', 'wpdt009_settings' );

/**
 * Defaults are the low-risk options. XML-RPC is off by default because
 * Jetpack, the WordPress mobile app and some integrations need it.
 */
function wpdt009_settings() {
	$saved = get_option( WPDT009_OPTION, array() );
	return wp_parse_args(
		is_array( $saved ) ? $saved : array(),
		array(
			'hide_version'  => 1,
			'block_enum'    => 1,
			'disable_xmlrpc' => 0,
		)
	);
}

/* ---------- 1. Hide the WordPress version ---------- */

function wpdt009_hide_version() {
	$s = wpdt009_settings();
	if ( empty( $s['hide_version'] ) ) {
		return;
	}
	remove_action( 'wp_head', 'wp_generator' );
	add_filter( 'the_generator', '__return_empty_string' );
}
add_action( 'init', 'wpdt009_hide_version' );

/* ---------- 2. Disable XML-RPC ---------- */

function wpdt009_disable_xmlrpc() {
	$s = wpdt009_settings();
	if ( empty( $s['disable_xmlrpc'] ) ) {
		return;
	}
	add_filter( 'xmlrpc_enabled', '__return_false' );
	add_filter(
		'wp_headers',
		function ( $headers ) {
			unset( $headers['X-Pingback'] );
			return $headers;
		}
	);
}
add_action( 'init', 'wpdt009_disable_xmlrpc' );

/* ---------- 3. Stop user enumeration ---------- */

function wpdt009_block_enumeration() {
	$s = wpdt009_settings();
	if ( empty( $s['block_enum'] ) ) {
		return;
	}

	// Public REST user list for logged-out visitors.
	add_filter(
		'rest_endpoints',
		function ( $endpoints ) {
			if ( ! is_user_logged_in() ) {
				unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
			}
			return $endpoints;
		}
	);

	// ?author=1 redirects to /author/username/ and reveals the login name.
	add_action(
		'template_redirect',
		function () {
			if ( is_admin() || is_user_logged_in() ) {
				return;
			}
			if ( isset( $_GET['author'] ) && is_numeric( wp_unslash( $_GET['author'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				wp_safe_redirect( home_url( '/' ), 301 );
				exit;
			}
		},
		0
	);
}
add_action( 'init', 'wpdt009_block_enumeration' );

/* ---------- Settings page ---------- */

function wpdt009_menu() {
	add_options_page(
		__( 'Hardening', 'simple-hardening' ),
		__( 'Hardening', 'simple-hardening' ),
		'manage_options',
		'simple-hardening',
		'wpdt009_render_page'
	);
}
add_action( 'admin_menu', 'wpdt009_menu' );

function wpdt009_sanitize( $input ) {
	$input = is_array( $input ) ? $input : array();
	return array(
		'hide_version'   => empty( $input['hide_version'] ) ? 0 : 1,
		'block_enum'     => empty( $input['block_enum'] ) ? 0 : 1,
		'disable_xmlrpc' => empty( $input['disable_xmlrpc'] ) ? 0 : 1,
	);
}

function wpdt009_register_settings() {
	register_setting(
		'wpdt009_group',
		WPDT009_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'wpdt009_sanitize',
			'default'           => array(),
		)
	);
}
add_action( 'admin_init', 'wpdt009_register_settings' );

function wpdt009_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$s      = wpdt009_settings();
	$fields = array(
		'hide_version'   => array(
			__( 'Hide WordPress version', 'simple-hardening' ),
			__( 'Removes the version number from the page source, so bots can not easily target known bugs in your version.', 'simple-hardening' ),
		),
		'block_enum'     => array(
			__( 'Block user name enumeration', 'simple-hardening' ),
			__( 'Hides the public REST user list and stops ?author=1 from revealing login names to logged-out visitors.', 'simple-hardening' ),
		),
		'disable_xmlrpc' => array(
			__( 'Disable XML-RPC', 'simple-hardening' ),
			__( 'Turns off the legacy xmlrpc.php API often used in attacks. Leave off if you use Jetpack, the WordPress mobile app, or remote publishing tools.', 'simple-hardening' ),
		),
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Hardening', 'simple-hardening' ); ?></h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'wpdt009_group' ); ?>
			<table class="form-table" role="presentation">
				<?php foreach ( $fields as $key => $field ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $field[0] ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( WPDT009_OPTION . '[' . $key . ']' ); ?>" value="1" <?php checked( ! empty( $s[ $key ] ) ); ?>>
								<?php esc_html_e( 'Enabled', 'simple-hardening' ); ?>
							</label>
							<p class="description"><?php echo esc_html( $field[1] ); ?></p>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

function wpdt009_action_links( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=simple-hardening' ) ) . '">' . esc_html__( 'Settings', 'simple-hardening' ) . '</a>' );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wpdt009_action_links' );
