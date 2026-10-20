<?php
namespace Lakstena\WpCore;

defined( 'ABSPATH' ) || exit;

/**
 * /llms.txt – summary for AI search (https://llmstxt.org).
 * Content comes from the settings option (name, facts_{lang}) and config `llms`; sites extend the output with filters:
 *  wp_core_llms_page_args (get_posts args), wp_core_llms_pages (WP_Post[]), wp_core_llms_after_pages (string[] lines).
 */
final class Llms {

	public static function register(): void {
		add_action( 'init', static function () {
			add_rewrite_rule( '^llms\.txt$', 'index.php?lk_llms=1', 'top' );
		} );
		add_filter( 'query_vars', static function ( $v ) {
			$v[] = 'lk_llms';
			return $v;
		} );
		// Works without rewrite rules too (e.g. before they are flushed).
		add_action( 'parse_request', static function ( $wp ) {
			$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
			if ( 'llms.txt' === $path || ! empty( $wp->query_vars['lk_llms'] ) ) {
				self::output();
			}
		}, 0 );
		// The rewrite rule is new for a site that had none: flush once per module version.
		add_action( 'init', static function () {
			if ( get_option( 'wp_core_version' ) !== WP_CORE_VERSION ) {
				flush_rewrite_rules( false );
				update_option( 'wp_core_version', WP_CORE_VERSION );
			}
		}, 99 );
	}

	/** @return string[] */
	public static function lines(): array {
		$o     = Options::all();
		$cfg   = (array) Config::get( 'llms' );
		$lines = array();

		$title = $cfg['title'] ?: ( $o['name'] ?? get_bloginfo( 'name' ) );
		$lines[] = '# ' . $title;
		$lines[] = '';

		$summary = $cfg['summary'] ?? '';
		if ( '' === $summary ) {
			$parts = array();
			foreach ( (array) ( $cfg['summary_langs'] ?? array_keys( (array) $cfg['facts'] ) ) as $l ) {
				$first = strtok( (string) ( $o[ 'facts_' . $l ] ?? '' ), "\n" );
				if ( false !== $first && '' !== $first ) {
					$parts[] = str_replace( "\n", ' ', $first );
				}
			}
			$summary = implode( ' ', $parts );
		}
		if ( '' !== $summary ) {
			$lines[] = '> ' . $summary;
			$lines[] = '';
		}

		$contact = is_callable( $cfg['contact'] ?? '' ) ? call_user_func( $cfg['contact'], $o ) : (string) ( $cfg['contact'] ?? '' );
		if ( '' !== $contact ) {
			$lines[] = $contact;
		}

		foreach ( (array) $cfg['facts'] as $l => $section ) {
			$facts = array_filter( array_map( 'trim', explode( "\n", (string) ( $o[ 'facts_' . $l ] ?? '' ) ) ) );
			if ( ! $facts ) {
				continue;
			}
			$lines[] = '';
			$lines[] = '## ' . $section;
			$lines[] = '';
			foreach ( $facts as $f ) {
				$lines[] = '- ' . $f;
			}
		}

		$pages = get_posts( apply_filters( 'wp_core_llms_page_args', array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => 50, 'lang' => '', 'suppress_filters' => true, 'orderby' => 'menu_order title', 'order' => 'ASC' ) ) );
		$pages = apply_filters( 'wp_core_llms_pages', $pages );
		if ( $pages ) {
			$lines[] = '';
			$lines[] = '## ' . $cfg['pages_title'];
			$lines[] = '';
			foreach ( $pages as $p ) {
				if ( Meta::noindex( $p->ID ) ) {
					continue;
				}
				$lang    = function_exists( 'pll_get_post_language' ) ? strtoupper( (string) pll_get_post_language( $p->ID ) ) : '';
				$lines[] = sprintf( '- [%s](%s)%s: %s', Meta::title( $p->ID ), get_permalink( $p ), $lang ? " ($lang)" : '', Meta::description( $p->ID ) );
			}
		}

		$lines = apply_filters( 'wp_core_llms_after_pages', $lines, $pages );

		$brands = (array) ( $cfg['brands'] ?? array() );
		if ( $brands ) {
			$lines[] = '';
			$lines[] = '## Brands';
			$lines[] = '';
			foreach ( $brands as $b ) {
				$lines[] = sprintf( '- [%s](%s): %s', $b[0], $b[1], $b[2] );
			}
		}
		return $lines;
	}

	public static function output(): void {
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		header( 'Cache-Control: public, max-age=3600' );
		echo implode( "\n", self::lines() ) . "\n";
		exit;
	}
}
