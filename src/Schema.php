<?php
namespace Lakstena\WpCore;

defined( 'ABSPATH' ) || exit;

/** JSON-LD helpers. Sites build their own @graph and print it with Schema::print_graph(). */
final class Schema {

	/** Home URL of a language (Polylang) or the plain home URL. */
	public static function home_url( string $lang = '' ): string {
		if ( function_exists( 'pll_home_url' ) && $lang ) {
			return pll_home_url( $lang );
		}
		return home_url( '/' );
	}

	public static function default_lang(): string {
		return function_exists( 'pll_default_language' ) ? (string) pll_default_language() : '';
	}

	/** Organization @id: the same for every language. */
	public static function org_id(): string {
		return trailingslashit( self::home_url( self::default_lang() ) ) . '#organization';
	}

	public static function website_id(): string {
		return trailingslashit( self::home_url( self::default_lang() ) ) . '#website';
	}

	/** Walks blocks recursively. */
	public static function walk_blocks( array $blocks, callable $cb ): void {
		foreach ( $blocks as $b ) {
			$cb( $b );
			if ( ! empty( $b['innerBlocks'] ) ) {
				self::walk_blocks( $b['innerBlocks'], $cb );
			}
		}
	}

	public static function clean( string $html ): string {
		return trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $html ) ) );
	}

	/** First H1 in the post content, else the title. */
	public static function h1( \WP_Post $post ): string {
		return preg_match( '#<h1[^>]*>(.*?)</h1>#s', $post->post_content, $m ) ? self::clean( $m[1] ) : wp_strip_all_tags( get_the_title( $post ) );
	}

	/** Organization node from the settings option. Sites extend or replace it through the `wp_core_organization` filter. */
	public static function organization(): array {
		$o   = Options::all();
		$tel = preg_replace( '/[^0-9+]/', '', (string) ( $o['phone'] ?? '' ) );
		$org = array(
			'@type'         => array( 'Organization', 'LocalBusiness' ),
			'@id'           => self::org_id(),
			'name'          => $o['name'] ?? '',
			'legalName'     => $o['legal_name'] ?? '',
			'alternateName' => $o['brand'] ?? '',
			'url'           => self::home_url( self::default_lang() ),
			'email'         => $o['email'] ?? '',
			'telephone'     => $tel,
			'vatID'         => $o['vat'] ?? '',
			'taxID'         => $o['code'] ?? '',
			'address'       => array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => $o['street'] ?? '',
				'postalCode'      => $o['postal'] ?? '',
				'addressLocality' => $o['city'] ?? '',
				'addressRegion'   => $o['region'] ?? '',
				'addressCountry'  => $o['country'] ?? '',
			),
		);
		return apply_filters( 'wp_core_organization', $org, $o );
	}

	/** Logo, geo, opening hours, map and sameAs from the settings (shared by all sites). */
	public static function decorate_organization( array $org ): array {
		$o    = Options::all();
		$logo = get_theme_mod( 'custom_logo' );
		if ( $logo && ( $src = wp_get_attachment_image_src( $logo, 'full' ) ) ) {
			$org['logo']  = array( '@type' => 'ImageObject', 'url' => $src[0], 'width' => $src[1], 'height' => $src[2] );
			$org['image'] = $src[0];
		}
		if ( ! empty( $o['lat'] ) && ! empty( $o['lng'] ) ) {
			$org['geo'] = array( '@type' => 'GeoCoordinates', 'latitude' => (float) $o['lat'], 'longitude' => (float) $o['lng'] );
		}
		if ( ! empty( $o['hours'] ) ) {
			$org['openingHours'] = array_values( array_filter( array_map( 'trim', explode( "\n", $o['hours'] ) ) ) );
		}
		if ( ! empty( $o['gbp_url'] ) ) {
			$org['hasMap'] = $o['gbp_url'];
		}
		$same = array_values( array_filter( array_map( 'trim', explode( "\n", (string) ( $o['same_as'] ?? '' ) ) ) ) );
		if ( ! empty( $o['gbp_url'] ) ) {
			$same[] = $o['gbp_url'];
		}
		if ( $same ) {
			$org['sameAs'] = $same;
		}
		return $org;
	}

	/** WebSite node. */
	public static function website( array $languages ): array {
		return array(
			'@type'      => 'WebSite',
			'@id'        => self::website_id(),
			'url'        => self::home_url( self::default_lang() ),
			'name'       => Options::get( 'name' ),
			'inLanguage' => $languages,
			'publisher'  => array( '@id' => self::org_id() ),
		);
	}

	/** BreadcrumbList node: items are [ name, url ] pairs. */
	public static function breadcrumb( string $page_url, array $items ): array {
		$list = array();
		foreach ( array_values( $items ) as $i => $item ) {
			$list[] = array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => $item[0], 'item' => $item[1] );
		}
		return array( '@type' => 'BreadcrumbList', '@id' => $page_url . '#breadcrumb', 'itemListElement' => $list );
	}

	/** Echoes the <script type="application/ld+json"> block for a graph. */
	public static function print_now( array $graph ): void {
		$data = array( '@context' => 'https://schema.org', '@graph' => $graph );
		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
	}

	/**
	 * Prints the graph in wp_head at priority 5. The builder is called at render time. Call from a hook after mu-plugins are loaded,
	 * or register `add_action( 'wp_head', fn() => Schema::print_now( … ), 5 )` yourself.
	 */
	public static function print_graph( callable $graph_builder ): void {
		add_action( 'wp_head', static function () use ( $graph_builder ) {
			self::print_now( (array) $graph_builder() );
		}, 5 );
	}
}
