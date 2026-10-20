<?php
namespace Lakstena\WpCore;

defined( 'ABSPATH' ) || exit;

/**
 * Search engine webmaster glue:
 *  – optional verification meta tags on the front page ({prefix}GSC_VERIFICATION, {prefix}BING_VERIFICATION from the environment)
 *  – IndexNow: the key file /<key>.txt is served from the site root and a ping is sent when a public post is published or updated
 *    (production only). The IndexNow key is public by design.
 */
final class Webmaster {

	public static function register(): void {
		add_action( 'wp_head', array( self::class, 'verification' ), 2 );
		add_action( 'init', array( self::class, 'key_file' ), 1 );
		add_action( 'transition_post_status', array( self::class, 'ping' ), 10, 3 );
	}

	private static function env_token( string $name ): string {
		$v = Config::env( $name );
		return preg_match( '/^[A-Za-z0-9_\-]{10,100}$/', $v ) ? $v : '';
	}

	public static function verification(): void {
		if ( ! is_front_page() ) {
			return;
		}
		$g = self::env_token( 'GSC_VERIFICATION' );
		$b = self::env_token( 'BING_VERIFICATION' );
		if ( $g ) {
			printf( '<meta name="google-site-verification" content="%s">' . "\n", esc_attr( $g ) );
		}
		if ( $b ) {
			printf( '<meta name="msvalidate.01" content="%s">' . "\n", esc_attr( $b ) );
		}
	}

	private static function key(): string {
		$k = (string) Config::get( 'indexnow_key' );
		return preg_match( '/^[A-Za-z0-9\-]{8,128}$/', $k ) ? $k : '';
	}

	public static function key_file(): void {
		$key = self::key();
		if ( '' === $key ) {
			return;
		}
		$path = wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH );
		if ( '/' . $key . '.txt' !== $path ) {
			return;
		}
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo $key; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	public static function ping( $new, $old, $post ): void {
		$key = self::key();
		if ( '' === $key || 'publish' !== $new || ! defined( 'WP_ENV' ) || 'production' !== WP_ENV ) {
			return;
		}
		if ( ! is_post_type_viewable( $post->post_type ) || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}
		$url = get_permalink( $post );
		if ( ! $url ) {
			return;
		}
		wp_remote_post( 'https://api.indexnow.org/indexnow', array(
			'timeout'  => 5,
			'blocking' => false,
			'headers'  => array( 'Content-Type' => 'application/json; charset=utf-8' ),
			'body'     => wp_json_encode( array(
				'host'        => wp_parse_url( home_url(), PHP_URL_HOST ),
				'key'         => $key,
				'keyLocation' => home_url( '/' . $key . '.txt' ),
				'urlList'     => array( $url ),
			) ),
		) );
	}
}
