<?php
/**
 * Plugin Name:       WPDT 005 – GitHub Repos Shortcode
 * Description:       Display a GitHub user's public repositories anywhere with [wpdt_github_repos user="zaman1100"]. Cached, no API key needed.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 * Text Domain:       wpdt-github-repos-shortcode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetch repos for a user. Fresh copy is cached for $hours; a long-lived
 * backup copy is served if GitHub is unreachable or rate limits us.
 *
 * @return array|WP_Error
 */
function wpdt005_get_repos( $user, $hours ) {
	$key        = 'wpdt005_' . md5( strtolower( $user ) );
	$cached     = get_transient( $key );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$args = apply_filters(
		'wpdt005_request_args',
		array(
			'timeout' => 10,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'wp-daily-tools/' . home_url(),
			),
		)
	);

	$response = wp_remote_get(
		'https://api.github.com/users/' . rawurlencode( $user ) . '/repos?per_page=100&sort=pushed',
		$args
	);

	$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
	$data = 200 === $code ? json_decode( wp_remote_retrieve_body( $response ), true ) : null;

	if ( ! is_array( $data ) ) {
		$backup = get_transient( $key . '_backup' );
		if ( is_array( $backup ) ) {
			return $backup;
		}
		return new WP_Error( 'wpdt005_failed', __( 'Could not load repositories from GitHub.', 'wpdt-github-repos-shortcode' ) );
	}

	set_transient( $key, $data, max( 1, (int) $hours ) * HOUR_IN_SECONDS );
	set_transient( $key . '_backup', $data, WEEK_IN_SECONDS );

	return $data;
}

/**
 * [wpdt_github_repos user="" limit="12" columns="3" show_forks="no" hours="6"]
 */
function wpdt005_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'user'       => '',
			'limit'      => 12,
			'columns'    => 3,
			'show_forks' => 'no',
			'hours'      => 6,
		),
		$atts,
		'wpdt_github_repos'
	);

	$user = sanitize_text_field( $atts['user'] );
	if ( '' === $user || ! preg_match( '/^[A-Za-z0-9-]{1,39}$/', $user ) ) {
		return current_user_can( 'edit_posts' ) ? '<p>' . esc_html__( 'GitHub repos: add a valid user="username" attribute.', 'wpdt-github-repos-shortcode' ) . '</p>' : '';
	}

	$repos = wpdt005_get_repos( $user, absint( $atts['hours'] ) );
	if ( is_wp_error( $repos ) ) {
		return current_user_can( 'edit_posts' ) ? '<p>' . esc_html( $repos->get_error_message() ) . '</p>' : '';
	}

	$show_forks = in_array( strtolower( $atts['show_forks'] ), array( 'yes', 'true', '1' ), true );
	$repos      = array_filter(
		$repos,
		function ( $repo ) use ( $show_forks ) {
			return $show_forks || empty( $repo['fork'] );
		}
	);
	$repos      = array_slice( $repos, 0, max( 1, absint( $atts['limit'] ) ) );

	if ( ! $repos ) {
		return '';
	}

	$columns = min( 4, max( 1, absint( $atts['columns'] ) ) );

	wp_register_style( 'wpdt005', false, array(), '1.0.0' );
	wp_enqueue_style( 'wpdt005' );
	wp_add_inline_style(
		'wpdt005',
		'.wpdt005-grid{display:grid;gap:16px;grid-template-columns:repeat(var(--wpdt005-cols,3),1fr)}'
		. '@media(max-width:782px){.wpdt005-grid{grid-template-columns:1fr}}'
		. '.wpdt005-card{border:1px solid rgba(128,128,128,.35);border-radius:8px;padding:16px;display:flex;flex-direction:column;gap:8px}'
		. '.wpdt005-card h3{margin:0;font-size:1.05em;word-break:break-word}'
		. '.wpdt005-card p{margin:0;flex:1;opacity:.85}'
		. '.wpdt005-meta{font-size:.85em;opacity:.75;display:flex;gap:12px;flex-wrap:wrap}'
	);

	$html = '<div class="wpdt005-grid" style="--wpdt005-cols:' . (int) $columns . '">';
	foreach ( $repos as $repo ) {
		$html .= '<div class="wpdt005-card">';
		$html .= '<h3><a href="' . esc_url( $repo['html_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $repo['name'] ) . '</a></h3>';
		$html .= '<p>' . esc_html( ! empty( $repo['description'] ) ? $repo['description'] : __( 'No description.', 'wpdt-github-repos-shortcode' ) ) . '</p>';
		$html .= '<div class="wpdt005-meta">';
		if ( ! empty( $repo['language'] ) ) {
			$html .= '<span>' . esc_html( $repo['language'] ) . '</span>';
		}
		$html .= '<span>&#9733; ' . (int) $repo['stargazers_count'] . '</span>';
		if ( ! empty( $repo['pushed_at'] ) ) {
			$html .= '<span>' . esc_html( sprintf( __( 'Updated %s ago', 'wpdt-github-repos-shortcode' ), human_time_diff( strtotime( $repo['pushed_at'] ), time() ) ) ) . '</span>';
		}
		$html .= '</div></div>';
	}
	$html .= '</div>';

	return $html;
}
add_shortcode( 'wpdt_github_repos', 'wpdt005_shortcode' );
