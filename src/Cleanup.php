<?php
namespace Lakstena\WpCore;

defined( 'ABSPATH' ) || exit;

/**
 * Technical SEO and a cleaner <head>:
 *  – author archives and attachment pages redirect (thin pages)
 *  – sitemap without users, taxonomies and noindex posts
 *  – no emoji script, generator, wlwmanifest, RSD, shortlink, feed and oEmbed discovery, REST link
 */
final class Cleanup {

	public static function register(): void {
		add_action( 'template_redirect', array( self::class, 'redirect_thin_pages' ), 1 );
		add_filter( 'wp_sitemaps_add_provider', static function ( $provider, $name ) {
			return 'users' === $name ? false : $provider;
		}, 10, 2 );
		add_filter( 'wp_sitemaps_taxonomies', static function ( $t ) {
			unset( $t['category'], $t['post_tag'], $t['post_format'] );
			return $t;
		} );
		add_filter( 'wp_sitemaps_posts_query_args', array( self::class, 'sitemap_query' ), 10, 2 );

		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		remove_action( 'wp_head', 'feed_links_extra', 3 );
		add_filter( 'the_generator', '__return_empty_string' );
		remove_action( 'wp_head', 'feed_links', 2 );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'rest_output_link_wp_head' );
	}

	public static function redirect_thin_pages(): void {
		if ( is_author() ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
		if ( is_attachment() ) {
			$parent = wp_get_post_parent_id( get_queried_object_id() );
			wp_safe_redirect( $parent ? get_permalink( $parent ) : home_url( '/' ), 301 );
			exit;
		}
	}

	public static function sitemap_query( $args, $post_type ) {
		$key                = Meta::key( 'noindex' );
		$args['meta_query'] = array(
			'relation' => 'OR',
			array( 'key' => $key, 'compare' => 'NOT EXISTS' ),
			array( 'key' => $key, 'value' => '1', 'compare' => '!=' ),
		);
		return $args;
	}
}
