<?php
namespace Lakstena\WpCore;

defined( 'ABSPATH' ) || exit;

/**
 * Per-post SEO fields (registered for REST/editor use):
 *  {prefix}title – search title, {prefix}desc – meta description, {prefix}schema – page type for JSON-LD, {prefix}noindex.
 */
final class Meta {

	public static function register(): void {
		add_action( 'init', array( self::class, 'register_meta' ) );
	}

	public static function key( string $name ): string {
		return Config::get( 'meta_prefix' ) . $name;
	}

	public static function register_meta(): void {
		$auth = static function () {
			return current_user_can( 'edit_posts' );
		};
		foreach ( (array) Config::get( 'post_types' ) as $type ) {
			foreach ( array( 'title', 'desc', 'schema' ) as $name ) {
				register_post_meta( $type, self::key( $name ), array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'default' => '', 'auth_callback' => $auth, 'sanitize_callback' => 'sanitize_text_field' ) );
			}
			register_post_meta( $type, self::key( 'noindex' ), array( 'type' => 'boolean', 'single' => true, 'show_in_rest' => true, 'default' => false, 'auth_callback' => $auth ) );
		}
	}

	/** Search title: the field or "Page title | Brand". */
	public static function title( int $post_id ): string {
		$t = trim( (string) get_post_meta( $post_id, self::key( 'title' ), true ) );
		return $t ? $t : wp_strip_all_tags( get_the_title( $post_id ) ) . ' | ' . Options::get( 'brand' );
	}

	/** Meta description: the field, the excerpt, or the first long paragraph. */
	public static function description( int $post_id ): string {
		$d = trim( (string) get_post_meta( $post_id, self::key( 'desc' ), true ) );
		if ( $d ) {
			return $d;
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}
		if ( has_excerpt( $post ) ) {
			return wp_strip_all_tags( $post->post_excerpt );
		}
		if ( preg_match_all( '#<p[^>]*>(.*?)</p>#s', $post->post_content, $m ) ) {
			foreach ( $m[1] as $txt ) {
				$txt = trim( wp_strip_all_tags( $txt ) );
				if ( mb_strlen( $txt ) > 60 ) {
					return mb_strlen( $txt ) > 160 ? rtrim( mb_substr( $txt, 0, 155 ), " ,.;" ) . '…' : $txt;
				}
			}
		}
		return '';
	}

	public static function noindex( int $post_id ): bool {
		return (bool) get_post_meta( $post_id, self::key( 'noindex' ), true );
	}
}
