<?php
namespace Lakstena\WpCore;

defined( 'ABSPATH' ) || exit;

/** robots.txt: AI search crawlers allowed, sitemap announced. Only when the site is public. */
final class Robots {

	public static function register(): void {
		add_filter( 'robots_txt', array( self::class, 'filter' ), 20, 2 );
	}

	public static function filter( $out, $public ) {
		if ( ! $public ) {
			return $out;
		}
		$add = "\n# " . Config::get( 'robots_comment' ) . "\n";
		foreach ( (array) Config::get( 'ai_bots' ) as $b ) {
			$add .= "User-agent: $b\nAllow: /\n\n";
		}
		if ( false === strpos( $out, 'Sitemap:' ) ) {
			$add .= 'Sitemap: ' . home_url( '/wp-sitemap.xml' ) . "\n";
		}
		return $out . $add;
	}
}
