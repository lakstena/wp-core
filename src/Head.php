<?php
namespace Lakstena\WpCore;

defined( 'ABSPATH' ) || exit;

/** <title>, meta description, Open Graph, Twitter card, geo meta, robots directives. */
final class Head {

	public static function register(): void {
		add_filter( 'pre_get_document_title', array( self::class, 'document_title' ), 20 );
		add_action( 'wp_head', array( self::class, 'print_meta' ), 2 );
		add_filter( 'wp_robots', array( self::class, 'robots' ) );
	}

	/** True when another SEO plugin is active (then this module prints nothing). */
	public static function other_plugin(): bool {
		return defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'AIOSEO_VERSION' );
	}

	/** Current language slug (Polylang when present, else the WordPress locale). */
	public static function lang( int $post_id = 0 ): string {
		if ( $post_id && function_exists( 'pll_get_post_language' ) ) {
			$l = pll_get_post_language( $post_id );
			if ( $l ) {
				return $l;
			}
		}
		if ( function_exists( 'pll_current_language' ) && pll_current_language() ) {
			return pll_current_language();
		}
		return substr( get_locale(), 0, 2 );
	}

	public static function locale( string $lang ): string {
		$map = (array) Config::get( 'locales' );
		return $map[ $lang ] ?? str_replace( '-', '_', get_locale() );
	}

	public static function document_title( $title ) {
		if ( self::other_plugin() || ! is_singular() ) {
			return $title;
		}
		return Meta::title( get_queried_object_id() );
	}

	/** Share image: featured image, default option, custom logo. [ url, width, height ] or null. */
	public static function image( int $post_id ): ?array {
		if ( $post_id && has_post_thumbnail( $post_id ) ) {
			$img = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'large' );
			if ( $img ) {
				return $img;
			}
		}
		$og = Options::get( 'og_image' );
		if ( $og ) {
			return array( $og, 1200, 630 );
		}
		$logo = get_theme_mod( 'custom_logo' );
		if ( $logo ) {
			$img = wp_get_attachment_image_src( $logo, 'full' );
			if ( $img ) {
				return $img;
			}
		}
		return null;
	}

	public static function print_meta(): void {
		if ( self::other_plugin() || ! Config::get( 'print_head_meta' ) ) {
			return;
		}
		$id    = is_singular() ? get_queried_object_id() : 0;
		$title = $id ? Meta::title( $id ) : wp_get_document_title();
		$desc  = $id ? Meta::description( $id ) : get_bloginfo( 'description' );
		$url   = $id ? get_permalink( $id ) : home_url( add_query_arg( array() ) );
		$lang  = self::lang( $id );

		echo "\n<!-- " . esc_html( Config::get( 'head_comment' ) ) . " -->\n";
		if ( $desc ) {
			printf( '<meta name="description" content="%s">' . "\n", esc_attr( $desc ) );
		}
		printf( '<meta property="og:type" content="%s">' . "\n", is_front_page() ? 'website' : 'article' );
		printf( '<meta property="og:site_name" content="%s">' . "\n", esc_attr( Options::get( 'name' ) ) );
		printf( '<meta property="og:title" content="%s">' . "\n", esc_attr( $title ) );
		if ( $desc ) {
			printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( $desc ) );
		}
		printf( '<meta property="og:url" content="%s">' . "\n", esc_url( $url ) );
		printf( '<meta property="og:locale" content="%s">' . "\n", esc_attr( self::locale( $lang ) ) );
		if ( $id && function_exists( 'pll_get_post_translations' ) ) {
			foreach ( pll_get_post_translations( $id ) as $l => $tid ) {
				if ( $l !== $lang && 'publish' === get_post_status( $tid ) ) {
					printf( '<meta property="og:locale:alternate" content="%s">' . "\n", esc_attr( self::locale( $l ) ) );
				}
			}
		}
		$img = self::image( $id );
		if ( $img ) {
			printf( '<meta property="og:image" content="%s">' . "\n", esc_url( $img[0] ) );
			if ( ! empty( $img[1] ) ) {
				printf( '<meta property="og:image:width" content="%d">' . "\n<meta property=\"og:image:height\" content=\"%d\">\n", (int) $img[1], (int) $img[2] );
			}
		}
		echo '<meta name="twitter:card" content="' . ( $img && $img[1] >= 600 ? 'summary_large_image' : 'summary' ) . '">' . "\n";
		echo '<meta name="theme-color" content="' . esc_attr( Config::get( 'theme_color' ) ) . '">' . "\n";
		$geo_lat = Options::get( 'lat' );
		$geo_lng = Options::get( 'lng' );
		if ( $geo_lat && $geo_lng ) {
			printf( '<meta name="geo.region" content="%s"><meta name="geo.placename" content="%s"><meta name="geo.position" content="%s;%s"><meta name="ICBM" content="%s, %s">' . "\n",
				esc_attr( Config::get( 'geo_region' ) ), esc_attr( Options::get( 'city' ) ), esc_attr( $geo_lat ), esc_attr( $geo_lng ), esc_attr( $geo_lat ), esc_attr( $geo_lng ) );
		}
	}

	/** robots: one tag through wp_robots. */
	public static function robots( $robots ) {
		if ( self::other_plugin() ) {
			return $robots;
		}
		if ( is_singular() && Meta::noindex( get_queried_object_id() ) ) {
			return array( 'noindex' => true, 'follow' => true );
		}
		if ( get_option( 'blog_public' ) ) {
			$robots['max-snippet']       = '-1';
			$robots['max-image-preview'] = 'large';
			$robots['max-video-preview'] = '-1';
		}
		return $robots;
	}
}
