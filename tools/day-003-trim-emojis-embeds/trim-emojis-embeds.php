<?php
/**
 * Plugin Name:       WPDT 003 – Trim Emojis & Embeds
 * Description:       Removes the WordPress emoji scripts/styles and the wp-embed script from the front end. Fewer requests, cleaner <head>.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 * Text Domain:       wpdt-trim-emojis-embeds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remove emoji detection script, styles and related filters.
 * Disable with: add_filter( 'wpdt003_disable_emojis', '__return_false' );
 */
function wpdt003_disable_emojis() {
	if ( ! apply_filters( 'wpdt003_disable_emojis', true ) ) {
		return;
	}

	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

	add_filter(
		'tiny_mce_plugins',
		function ( $plugins ) {
			return is_array( $plugins ) ? array_diff( $plugins, array( 'wpemoji' ) ) : array();
		}
	);

	add_filter(
		'wp_resource_hints',
		function ( $urls, $relation_type ) {
			if ( 'dns-prefetch' !== $relation_type ) {
				return $urls;
			}
			return array_filter(
				$urls,
				function ( $url ) {
					$href = is_array( $url ) && isset( $url['href'] ) ? $url['href'] : $url;
					return ! is_string( $href ) || false === strpos( $href, 's.w.org' );
				}
			);
		},
		10,
		2
	);
}
add_action( 'init', 'wpdt003_disable_emojis' );

/**
 * Remove oEmbed discovery links and the wp-embed script.
 * Embedding YouTube, Vimeo etc. in your content keeps working.
 * Disable with: add_filter( 'wpdt003_disable_embeds', '__return_false' );
 */
function wpdt003_disable_embeds() {
	if ( ! apply_filters( 'wpdt003_disable_embeds', true ) ) {
		return;
	}

	remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
	remove_action( 'wp_head', 'wp_oembed_add_host_js' );
	add_filter( 'embed_oembed_discover', '__return_false' );
}
add_action( 'init', 'wpdt003_disable_embeds', 9999 );

/**
 * Deregister the wp-embed script on the front end.
 */
function wpdt003_deregister_embed_script() {
	if ( ! apply_filters( 'wpdt003_disable_embeds', true ) || is_admin() ) {
		return;
	}
	wp_deregister_script( 'wp-embed' );
}
add_action( 'wp_enqueue_scripts', 'wpdt003_deregister_embed_script', 100 );
