<?php
namespace Lakstena\WpCore;

defined( 'ABSPATH' ) || exit;

/**
 * Uncached health endpoint for the external monitor.
 *  – GET /_health with header "X-Health-Token: <token>"; only the SHA-256 of the token is in config, the token itself is in the password manager
 *  – the token goes in a header, never in the query string (it must not reach the access log)
 *  – wrong or missing token, or no hash configured → plain 404 (the endpoint does not exist for anyone else)
 *  – checks the database with a real query and, only when a persistent object cache is used, the object cache
 *  – answers with Cache-Control: no-store
 * Sites add informational checks with the `wp_core_health_checks` filter (string 'fail' values flip the status to 503).
 */
final class Health {

	public static function register(): void {
		add_action( 'init', array( self::class, 'serve' ), 1 );
	}

	public static function serve(): void {
		$path = wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH );
		if ( '/_health' !== untrailingslashit( (string) $path ) ) {
			return;
		}

		nocache_headers();
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
		header( 'X-Robots-Tag: noindex, nofollow' );

		$expected = (string) Config::get( 'health_token_sha256' );
		$given    = isset( $_SERVER['HTTP_X_HEALTH_TOKEN'] ) ? (string) wp_unslash( $_SERVER['HTTP_X_HEALTH_TOKEN'] ) : '';
		if ( '' === $expected || '' === $given || ! hash_equals( $expected, hash( 'sha256', $given ) ) ) {
			status_header( 404 );
			exit;
		}

		global $wpdb;
		$checks       = array();
		$checks['db'] = ( 1 === (int) $wpdb->get_var( 'SELECT 1' ) ) ? 'ok' : 'fail';

		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
			$key = 'wpc_health_' . wp_rand();
			wp_cache_set( $key, 'x', 'wpc_health', 30 );
			$checks['redis'] = ( 'x' === wp_cache_get( $key, 'wpc_health' ) ) ? 'ok' : 'fail';
			wp_cache_delete( $key, 'wpc_health' );
		}

		$checks = apply_filters( 'wp_core_health_checks', $checks );

		$ok = ! in_array( 'fail', array_filter( $checks, 'is_string' ), true );
		status_header( $ok ? 200 : 503 );
		header( 'Content-Type: application/json; charset=utf-8' );
		echo wp_json_encode( array( 'status' => $ok ? 'ok' : 'fail' ) + $checks );
		exit;
	}
}
